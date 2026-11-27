<?php

declare(strict_types=1);

namespace Nebrix\Mail;

use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Settings\SettingsRepository;

/**
 * Admin-editable e-mail templates with safe placeholder substitution.
 *
 * Templates are not executed as code (no Twig/PHP), so a staff member with
 * template permissions cannot achieve server-side template injection. All
 * placeholder values are HTML-escaped in the HTML part.
 *
 * Templates exist per language: English defaults (config/mail_templates.php),
 * translations (config/mail_templates_<locale>.php) and administrator
 * overrides per key and locale. Unknown locales fall back to English.
 */
class MailTemplates
{
    /**
     * @param array<string, array{name: string, variables: list<string>, subject: string, html: string}> $defaults
     * @param array<string, array<string, array{subject: string, html: string}>> $translations locale => key => template
     */
    public function __construct(
        private readonly Connection $db,
        private readonly SettingsRepository $settings,
        private readonly Clock $clock,
        private readonly Config $config,
        private readonly array $defaults,
        private readonly array $translations = [],
    ) {
    }

    public const DEFAULT_LOCALE = 'en';

    /**
     * Languages templates can be edited in: English plus every translation.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        return array_values(array_unique([self::DEFAULT_LOCALE, ...array_keys($this->translations)]));
    }

    public function normalizeLocale(?string $locale): string
    {
        return $locale !== null && in_array($locale, $this->locales(), true) ? $locale : self::DEFAULT_LOCALE;
    }

    /**
     * @return array<string, array{name: string, variables: list<string>, subject: string, html: string}>
     */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /**
     * @return array{key: string, locale: string, name: string, variables: list<string>, subject: string, html: string, customized: bool}
     */
    public function get(string $key, ?string $locale = null): array
    {
        if (!isset($this->defaults[$key])) {
            throw new \InvalidArgumentException(sprintf('Unknown mail template "%s".', $key));
        }
        $locale = $this->normalizeLocale($locale);

        $default = $this->defaults[$key];
        $translated = $this->translations[$locale][$key] ?? null;
        $override = $this->db->fetchOne('SELECT `subject`, `body_html` FROM `nx_mail_templates` WHERE `key` = ? AND `locale` = ?', [$key, $locale]);

        return [
            'key' => $key,
            'locale' => $locale,
            'name' => $default['name'],
            'variables' => $default['variables'],
            'subject' => $override !== null ? (string) $override['subject'] : ($translated['subject'] ?? $default['subject']),
            'html' => $override !== null ? (string) $override['body_html'] : ($translated['html'] ?? $default['html']),
            'customized' => $override !== null,
        ];
    }

    public function save(string $key, string $subject, string $html, ?int $actorId, ?string $locale = null): void
    {
        $locale = $this->get($key, $locale)['locale'];
        $now = $this->clock->nowString();
        $this->db->run(
            'INSERT INTO `nx_mail_templates` (`key`, `locale`, `subject`, `body_html`, `body_text`, `updated_by`, `updated_at`) VALUES (?, ?, ?, ?, NULL, ?, ?)
             ON DUPLICATE KEY UPDATE `subject` = ?, `body_html` = ?, `updated_by` = ?, `updated_at` = ?',
            [$key, $locale, $subject, $html, $actorId, $now, $subject, $html, $actorId, $now],
        );
    }

    public function reset(string $key, ?string $locale = null): void
    {
        $this->db->delete('nx_mail_templates', ['key' => $key, 'locale' => $this->normalizeLocale($locale)]);
    }

    /**
     * @param array<string, scalar|null> $variables
     * @return array{subject: string, html: string, text: string}
     */
    public function render(string $key, array $variables, ?string $locale = null): array
    {
        $template = $this->get($key, $locale);
        $variables += [
            'company_name' => $this->settings->string('general.company_name'),
            'support_email' => $this->settings->string('general.support_email'),
            'app_url' => $this->config->string('app.url'),
        ];

        $subject = $this->replace($template['subject'], $variables, false);
        $body = $this->replace($template['html'], $variables, true);

        return [
            'subject' => preg_replace('/[\r\n]+/', ' ', $subject) ?? $subject,
            'html' => $this->layout($body, $subject),
            'text' => $this->toText($body),
        ];
    }

    /**
     * @param array<string, scalar|null> $variables
     */
    private function replace(string $template, array $variables, bool $escapeHtml): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_][a-z0-9_]*)\s*\}\}/i',
            static function (array $match) use ($variables, $escapeHtml): string {
                $value = (string) ($variables[$match[1]] ?? '');

                return $escapeHtml ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
            },
            $template,
        );
    }

    private function layout(string $body, string $subject): string
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $color = $this->settings->string('branding.email_header_color');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            $color = '#6d28d9';
        }
        $company = $escape($this->settings->string('general.company_name'));
        $footer = nl2br($escape($this->settings->string('branding.email_footer') ?: $this->settings->string('general.company_address')));

        return <<<HTML
            <!doctype html>
            <html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>{$escape($subject)}</title>
            <style>
              body{margin:0;background:#f4f5fa;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#172033}
              .wrap{max-width:600px;margin:0 auto;padding:24px}
              .head{background:{$color};color:#fff;padding:18px 24px;border-radius:10px 10px 0 0;font-size:20px;font-weight:700}
              .body{background:#fff;padding:24px;border-radius:0 0 10px 10px;line-height:1.55}
              .button{display:inline-block;background:{$color};color:#fff !important;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:600}
              .foot{color:#6b7280;font-size:12px;padding:16px 4px;text-align:center}
            </style></head>
            <body><div class="wrap"><div class="head">{$company}</div><div class="body">{$body}</div><div class="foot">{$footer}</div></div></body></html>
            HTML;
    }

    private function toText(string $html): string
    {
        $text = preg_replace('/<a\s[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/i', '$2: $1', $html) ?? $html;
        $text = preg_replace('/<\/(p|div|h[1-6]|li)>|<br\s*\/?>/i', "\n", $text) ?? $text;

        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
