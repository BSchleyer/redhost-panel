<?php

declare(strict_types=1);

namespace Nebrix\View;

use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Security\Csrf;
use Nebrix\Settings\SettingsRepository;
use Throwable;
use Twig\Environment;
use Twig\Extension\EscaperExtension;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;

/**
 * Twig renderer with HTML auto-escaping enabled for every template
 * (legacy findings H-02/H-03). Templates must never use the |raw filter on
 * user-controlled data.
 */
class View
{
    private ?Environment $twig = null;

    private ?Request $request = null;

    public function __construct(
        private readonly string $templatePath,
        private readonly ?string $cachePath,
        private readonly bool $debug,
        private readonly SettingsRepository $settings,
        private readonly Gate $gate,
        private readonly Translator $translator,
        private readonly string $assetPath,
    ) {
    }

    public function twig(): Environment
    {
        if ($this->twig === null) {
            $twig = new Environment(new FilesystemLoader($this->templatePath), [
                'autoescape' => 'html',
                'strict_variables' => $this->debug,
                'cache' => $this->cachePath ?? false,
                'auto_reload' => true,
            ]);
            $twig->addExtension(new TwigExtension($this));
            $this->twig = $twig;
        }

        return $this->twig;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = [], ?Request $request = null): string
    {
        $previous = $this->request;
        $this->request = $request;
        try {
            return $this->twig()->render($template, $context + $this->globals($request));
        } finally {
            $this->request = $previous;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function response(string $template, array $context = [], int $status = 200, ?Request $request = null): Response
    {
        return Response::html($this->render($template, $context, $request), $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function globals(?Request $request): array
    {
        $user = $request?->attribute('user');
        $session = $request !== null && $request->hasSession() ? $request->session() : null;

        $flashes = [];
        if ($session !== null) {
            foreach (['success', 'error', 'warning', 'info'] as $type) {
                $message = $session->getFlash($type);
                if (is_string($message) && $message !== '') {
                    $flashes[] = ['type' => $type, 'message' => $message];
                }
            }
        }

        $brand = $this->safeBrand();
        $themeCookie = $request?->cookie('nx_theme');
        $theme = in_array($themeCookie, ['light', 'dark'], true) ? $themeCookie : (string) ($brand['theme'] ?? 'system');

        return [
            'theme' => in_array($theme, ['light', 'dark', 'system'], true) ? $theme : 'system',
            'auth_user' => $user instanceof User ? $user : null,
            'current_path' => $request?->path() ?? '/',
            'flashes' => $flashes,
            'errors' => $session?->getFlash('_errors', []) ?? [],
            'brand' => $brand,
            'locale' => $this->translator->locale(),
        ];
    }

    /**
     * Branding values are read defensively so that error pages still render
     * when the database is unavailable.
     *
     * @return array<string, mixed>
     */
    private function safeBrand(): array
    {
        try {
            return [
                'name' => $this->settings->string('general.company_name'),
                'logo' => $this->settings->string('branding.logo_path'),
                'favicon' => $this->settings->string('branding.favicon_path'),
                'theme' => $this->settings->string('branding.default_theme'),
                'login_headline' => $this->settings->string('branding.login_headline'),
                'login_subtitle' => $this->settings->string('branding.login_subtitle'),
                'terms_url' => $this->settings->string('general.terms_url'),
                'privacy_url' => $this->settings->string('general.privacy_url'),
                'imprint_url' => $this->settings->string('general.imprint_url'),
                'support_email' => $this->settings->string('general.support_email'),
            ];
        } catch (Throwable) {
            return ['name' => 'Nebrix', 'logo' => '', 'favicon' => '', 'theme' => 'system', 'login_headline' => '', 'login_subtitle' => '',
                'terms_url' => '', 'privacy_url' => '', 'imprint_url' => '', 'support_email' => ''];
        }
    }

    public function currentRequest(): ?Request
    {
        return $this->request;
    }

    public function settings(): SettingsRepository
    {
        return $this->settings;
    }

    public function gate(): Gate
    {
        return $this->gate;
    }

    public function translator(): Translator
    {
        return $this->translator;
    }

    public function assetPath(): string
    {
        return $this->assetPath;
    }

    public function csrfToken(): string
    {
        $request = $this->request;
        if ($request === null || !$request->hasSession()) {
            return '';
        }

        return Csrf::token($request->session());
    }
}
