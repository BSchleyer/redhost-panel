<?php

declare(strict_types=1);

namespace Nebrix\View;

use DateTimeImmutable;
use DateTimeZone;
use Nebrix\Auth\User;
use Nebrix\Billing\Money;
use Nebrix\Security\Csrf;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TwigExtension extends AbstractExtension
{
    public function __construct(private readonly View $view)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('csrf_field', $this->csrfField(...), ['is_safe' => ['html']]),
            new TwigFunction('csrf_token', fn (): string => $this->view->csrfToken()),
            new TwigFunction('can', $this->can(...)),
            new TwigFunction('old', $this->old(...)),
            new TwigFunction('error', $this->error(...)),
            new TwigFunction('asset', $this->asset(...)),
            new TwigFunction('setting', $this->setting(...)),
            new TwigFunction('t', $this->translate(...)),
            new TwigFunction('active', $this->active(...)),
            new TwigFunction('query', $this->query(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('t', $this->translate(...)),
            new TwigFilter('money', $this->money(...)),
            new TwigFilter('percent', $this->percent(...)),
            new TwigFilter('dt', fn (?string $value): string => $this->formatDate($value, true)),
            new TwigFilter('d', fn (?string $value): string => $this->formatDate($value, false)),
            new TwigFilter('status_class', $this->statusClass(...)),
            new TwigFilter('humanize', static fn (?string $value): string => ucfirst(str_replace(['_', '.'], ' ', (string) $value))),
            new TwigFilter('bytes', $this->bytes(...)),
        ];
    }

    private function csrfField(): Markup
    {
        $token = htmlspecialchars($this->view->csrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return new Markup(sprintf('<input type="hidden" name="%s" value="%s">', Csrf::FIELD, $token), 'UTF-8');
    }

    private function can(string $permission): bool
    {
        $user = $this->view->currentRequest()?->attribute('user');

        return $user instanceof User && $this->view->gate()->allows($user, $permission);
    }

    private function old(string $field, mixed $default = ''): mixed
    {
        $request = $this->view->currentRequest();
        if ($request === null || !$request->hasSession()) {
            return $default;
        }

        $old = $request->session()->getFlash('_old', []);

        return is_array($old) && array_key_exists($field, $old) ? $old[$field] : $default;
    }

    private function error(string $field): ?string
    {
        $request = $this->view->currentRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        $errors = $request->session()->getFlash('_errors', []);
        $messages = is_array($errors) ? ($errors[$field] ?? null) : null;

        return is_array($messages) && isset($messages[0]) ? (string) $messages[0] : null;
    }

    private function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = $this->view->assetPath() . '/' . $path;
        $version = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';

        return '/assets/' . $path . '?v=' . $version;
    }

    private function setting(string $key): mixed
    {
        $definition = $this->view->settings()->definition($key);
        if (($definition['secret'] ?? false) === true) {
            return null; // secrets are never exposed to templates
        }

        return $this->view->settings()->get($key);
    }

    /**
     * @param array<string, string|int|float> $replace
     */
    private function translate(string $message, array $replace = []): string
    {
        return $this->view->translator()->translate($message, $replace);
    }

    private function active(string $prefix, bool $exact = false): string
    {
        $path = $this->view->currentRequest()?->path() ?? '/';
        $matches = $exact ? $path === $prefix : ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/'));

        return $matches ? 'is-active' : '';
    }

    /**
     * Builds a query string from the current query merged with overrides.
     *
     * @param array<string, scalar|null> $overrides
     */
    private function query(array $overrides = []): string
    {
        $current = $this->view->currentRequest()?->allQuery() ?? [];
        $merged = array_filter(array_merge($current, $overrides), static fn ($value): bool => $value !== null && $value !== '');

        return $merged === [] ? '' : '?' . http_build_query($merged);
    }

    private function money(int|string|null $minor, ?string $currency = null): string
    {
        $currency ??= $this->safeSetting('general.default_currency', 'EUR');

        return Money::format((int) $minor, $currency, $this->view->translator()->locale());
    }

    /** Tax rate or discount in basis points, formatted for the current language (19 %, 7,5 %). */
    private function percent(int|string|null $basisPoints): string
    {
        $bp = (int) $basisPoints;
        $german = $this->view->translator()->locale() === 'de';
        $value = number_format($bp / 100, $bp % 100 === 0 ? 0 : ($bp % 10 === 0 ? 1 : 2), $german ? ',' : '.', $german ? '.' : ',');

        return $value . ' %';
    }

    private function formatDate(?string $value, bool $withTime): string
    {
        if ($value === null || $value === '') {
            return '–';
        }

        try {
            $timezone = new DateTimeZone($this->safeSetting('general.timezone', 'UTC'));
            $date = (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone($timezone);
        } catch (\Exception) {
            return $value;
        }

        $format = $this->safeSetting('general.date_format', 'd.m.Y');
        if ($withTime) {
            $format .= ' ' . $this->safeSetting('general.time_format', 'H:i');
        }

        return $date->format($format);
    }

    private function safeSetting(string $key, string $default): string
    {
        try {
            $value = $this->view->settings()->string($key);

            return $value !== '' ? $value : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function statusClass(?string $status): string
    {
        return match ($status) {
            'active', 'paid', 'completed', 'healthy', 'processed', 'answered', 'success', 'running' => 'badge-success',
            'pending', 'unpaid', 'provisioning', 'open', 'customer_reply', 'received', 'degraded', 'reserved' => 'badge-warning',
            'suspended', 'failed', 'down', 'cancelled', 'refunded', 'terminated', 'closed', 'error', 'stopped' => 'badge-danger',
            default => 'badge-neutral',
        };
    }

    private function bytes(int|float|string|null $bytes, int $decimals = 1): string
    {
        $bytes = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : $decimals) . ' ' . $units[$power];
    }
}
