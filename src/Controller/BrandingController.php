<?php

declare(strict_types=1);

namespace Nebrix\Controller;

use Nebrix\Http\Controller;
use Nebrix\Http\Response;
use Nebrix\Settings\SettingsRepository;

/**
 * Serves the brand colors as a stylesheet so that no inline styles are
 * needed (strict CSP). Values are validated hex colors from the settings.
 */
final class BrandingController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function stylesheet(): Response
    {
        $color = function (string $key, string $fallback): string {
            $value = $this->settings->string($key);

            return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : $fallback;
        };

        $css = sprintf(
            ":root{--brand-primary:%s;--brand-secondary:%s;--brand-accent:%s;}\n",
            $color('branding.primary_color', '#6d28d9'),
            $color('branding.secondary_color', '#0f172a'),
            $color('branding.accent_color', '#22d3ee'),
        );

        $response = Response::text($css, 200, 'text/css; charset=UTF-8');
        $response->setHeader('Cache-Control', 'public, max-age=300');

        return $response;
    }
}
