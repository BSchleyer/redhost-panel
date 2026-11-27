<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Authorization\Permission;
use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Database\Migrator;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Mail\Mailer;
use Nebrix\Queue\JobQueue;
use Nebrix\Security\ValidationException;
use Nebrix\Settings\BrandingAssets;
use Nebrix\Settings\SettingsRepository;
use RuntimeException;

/**
 * Central configuration pages. Each page groups several setting groups and
 * is protected by its own permission.
 */
final class SettingsController extends Controller
{
    /** page => [permission, title, groups] */
    private const PAGES = [
        'general' => [Permission::SETTINGS_GENERAL, 'General settings', ['general' => 'Company & localization', 'auth' => 'Registration & sign-in', 'billing' => 'Billing', 'tax' => 'Taxes', 'invoice' => 'Invoice details']],
        'branding' => [Permission::SETTINGS_BRANDING, 'Branding & design', ['branding' => 'Colors & texts']],
        'email' => [Permission::SETTINGS_EMAIL, 'E-mail delivery', ['mail' => 'SMTP server']],
        'payments' => [Permission::PAYMENTS_SETTINGS, 'Payment providers', ['payments_paypal' => 'PayPal', 'payments_stripe' => 'Stripe', 'payments_credit' => 'Account credit']],
        'system' => [Permission::SETTINGS_SYSTEM, 'System', ['system' => 'Operation', 'api' => 'REST API']],
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly BrandingAssets $assets,
        private readonly Mailer $mailer,
        private readonly Config $config,
        private readonly Connection $db,
        private readonly Migrator $migrator,
        private readonly JobQueue $jobs,
        private readonly Clock $clock,
    ) {
    }

    public function show(Request $request, string $page): Response
    {
        [$permission, $title, $groups] = $this->pageDefinition($page);
        $this->authorize($request, $permission);

        $sections = [];
        foreach ($groups as $group => $label) {
            $definitions = $this->settings->definitions($group);
            $values = [];
            $secretsSet = [];
            foreach ($definitions as $key => $definition) {
                if (($definition['secret'] ?? false) === true) {
                    $values[$key] = null;
                    $secretsSet[$key] = $this->settings->isSet($key);
                } else {
                    $values[$key] = $this->settings->get($key);
                }
            }
            $sections[] = ['group' => $group, 'label' => $label, 'definitions' => $definitions, 'values' => $values, 'secrets_set' => $secretsSet];
        }

        return $this->render($request, 'admin/settings.twig', [
            'page' => $page,
            'title' => $title,
            'sections' => $sections,
            'logo' => $this->settings->string('branding.logo_path'),
            'favicon' => $this->settings->string('branding.favicon_path'),
            'webhook_urls' => [
                'stripe' => $this->config->string('app.url') . '/webhooks/stripe',
                'paypal' => $this->config->string('app.url') . '/webhooks/paypal',
            ],
            'system' => $page === 'system' ? $this->systemInfo() : null,
        ]);
    }

    public function save(Request $request, string $page): Response
    {
        [$permission, , $groups] = $this->pageDefinition($page);
        $this->authorize($request, $permission);

        $group = $request->string('group');
        if (!array_key_exists($group, $groups)) {
            throw HttpException::badRequest();
        }

        // Invoices and correction documents share the unique number column.
        if ($group === 'billing') {
            $credit = mb_strtoupper(trim($request->string('billing__credit_note_prefix')));
            if ($credit === '' || $credit === mb_strtoupper(trim($request->string('billing__invoice_prefix')))) {
                throw \Nebrix\Security\ValidationException::withMessages(['billing__credit_note_prefix' => 'Use a prefix for correction documents that differs from the invoice prefix.']);
            }
        }

        $changed = $this->settings->saveGroup($group, $request->all(), $this->user($request)->id);
        if ($changed !== []) {
            // Secret values are never written to the audit log, only which keys changed.
            $this->audit($request, 'settings.updated', 'settings', $group, ['keys' => $changed]);
        }
        $this->flash($request, 'success', $changed === [] ? 'No changes.' : 'Settings saved.');

        return $this->redirect('/admin/settings/' . $page);
    }

    public function uploadBranding(Request $request): Response
    {
        $this->authorize($request, Permission::SETTINGS_BRANDING);
        $kind = $request->string('kind');
        if (!in_array($kind, ['logo', 'favicon'], true)) {
            throw HttpException::badRequest();
        }

        try {
            $path = $this->assets->store($request->file('file') ?? [], $kind);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $key = 'branding.' . $kind . '_path';
        $previous = $this->settings->string($key);
        $this->settings->set($key, $path, $this->user($request)->id);
        if ($previous !== '') {
            $this->assets->delete($previous);
        }

        $this->audit($request, 'settings.branding_' . $kind . '_uploaded', 'settings', 'branding');
        $this->flash($request, 'success', ucfirst($kind) . ' uploaded.');

        return $this->redirect('/admin/settings/branding');
    }

    public function removeBranding(Request $request): Response
    {
        $this->authorize($request, Permission::SETTINGS_BRANDING);
        $kind = $request->string('kind');
        if (!in_array($kind, ['logo', 'favicon'], true)) {
            throw HttpException::badRequest();
        }

        $key = 'branding.' . $kind . '_path';
        $this->assets->delete($this->settings->string($key));
        $this->settings->set($key, '', $this->user($request)->id);
        $this->audit($request, 'settings.branding_' . $kind . '_removed', 'settings', 'branding');
        $this->flash($request, 'success', ucfirst($kind) . ' removed.');

        return $this->redirect('/admin/settings/branding');
    }

    public function testEmail(Request $request): Response
    {
        $this->authorize($request, Permission::SETTINGS_EMAIL);
        $data = $this->validate($request, ['recipient' => 'nullable|email']);

        try {
            $this->mailer->testConnection();
            if ($data['recipient'] !== null) {
                $this->mailer->sendNow((string) $data['recipient'], null, 'test');
                $this->flash($request, 'success', 'SMTP connection successful and test e-mail sent.');
            } else {
                $this->flash($request, 'success', 'SMTP connection and authentication successful.');
            }
            $this->audit($request, 'settings.smtp_tested', 'settings', 'mail', ['result' => 'ok']);
        } catch (RuntimeException $exception) {
            $this->audit($request, 'settings.smtp_tested', 'settings', 'mail', ['result' => 'failed']);
            $this->flash($request, 'error', $exception->getMessage());
        }

        return $this->redirect('/admin/settings/email');
    }

    public function clearCache(Request $request): Response
    {
        $this->authorize($request, Permission::SETTINGS_SYSTEM);
        $removed = 0;
        $twigCache = $this->config->string('paths.cache') . '/twig';
        if (is_dir($twigCache)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($twigCache, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                $removed++;
            }
        }

        $this->audit($request, 'system.cache_cleared', 'system', null, ['entries' => $removed]);
        $this->flash($request, 'success', 'Template cache cleared.');

        return $this->redirect('/admin/settings/system');
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, string>}
     */
    private function pageDefinition(string $page): array
    {
        if (!isset(self::PAGES[$page])) {
            throw HttpException::notFound();
        }

        return self::PAGES[$page];
    }

    /**
     * Read-only environment information. No phpinfo(), no paths to secrets.
     *
     * @return array<string, mixed>
     */
    private function systemInfo(): array
    {
        $required = ['pdo_mysql', 'sodium', 'curl', 'mbstring', 'openssl', 'json', 'fileinfo'];
        $extensions = [];
        foreach ([...$required, 'opcache', 'intl'] as $extension) {
            $extensions[$extension] = extension_loaded($extension) || ($extension === 'opcache' && extension_loaded('Zend OPcache'));
        }

        try {
            $dbVersion = (string) $this->db->fetchValue('SELECT VERSION()');
            $dbStatus = 'connected';
        } catch (\Throwable) {
            $dbVersion = '–';
            $dbStatus = 'unavailable';
        }

        $storage = $this->config->string('paths.base') . '/storage';

        return [
            'app_version' => $this->config->string('app.version'),
            'environment' => $this->config->string('app.env'),
            'debug' => $this->config->bool('app.debug'),
            'php_version' => PHP_VERSION,
            'extensions' => $extensions,
            'required_extensions' => $required,
            'db_version' => $dbVersion,
            'db_status' => $dbStatus,
            'pending_migrations' => $dbStatus === 'connected' ? $this->migrator->pending() : [],
            'storage_writable' => is_writable($storage . '/logs') && is_writable($storage . '/cache'),
            'uploads_writable' => is_writable($this->config->string('paths.uploads')),
            'log_level' => $this->config->string('log.level'),
            'session_idle' => $this->config->int('session.idle_minutes'),
            'session_lifetime' => $this->config->int('session.lifetime_hours'),
            'secure_cookies' => $this->config->bool('session.secure_cookie'),
            'jobs' => $this->jobs->counts(),
            'scheduler' => $this->db->fetchAll('SELECT * FROM `nx_scheduler_runs` ORDER BY `task`'),
            'server_time' => $this->clock->nowString(),
        ];
    }
}
