<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

use Nebrix\Auth\User;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Settings\SettingsRepository;
use Nebrix\View\Translator;
use Throwable;

/**
 * Chooses the interface language: the user's preference when it is one of
 * the enabled languages; for visitors who are not signed in the browser's
 * preferred language (Accept-Language) when it is enabled; otherwise the
 * default language from the settings.
 */
final class SetLocale implements Middleware
{
    public function __construct(
        private readonly Translator $translator,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        try {
            $available = $this->settings->list('general.available_languages') ?: ['en'];
            $locale = $this->settings->string('general.default_language') ?: 'en';
            $user = $request->attribute('user');
            if ($user instanceof User) {
                $preferred = (string) ($user->attribute('language') ?? '');
                if (in_array($preferred, $available, true)) {
                    $locale = $preferred;
                }
            } else {
                $locale = self::fromAcceptLanguage((string) $request->header('Accept-Language'), $available) ?? $locale;
            }
            $this->translator->setLocale($locale);
        } catch (Throwable) {
            $this->translator->setLocale('en');
        }

        return $next($request);
    }

    /**
     * The first enabled language in the Accept-Language header, by quality.
     *
     * @param list<string> $available
     */
    public static function fromAcceptLanguage(string $header, array $available): ?string
    {
        $ranked = [];
        foreach (array_slice(explode(',', $header), 0, 20) as $index => $part) {
            if (preg_match('/^\s*([A-Za-z]{1,8})(?:-[A-Za-z0-9]{1,8})*\s*(?:;\s*q\s*=\s*([01](?:\.\d{0,3})?))?\s*$/', $part, $match) !== 1) {
                continue;
            }
            $quality = isset($match[2]) ? (float) $match[2] : 1.0;
            if ($quality > 0) {
                $ranked[] = [strtolower($match[1]), $quality, $index];
            }
        }
        usort($ranked, static fn (array $a, array $b): int => [$b[1], $a[2]] <=> [$a[1], $b[2]]);
        foreach ($ranked as [$language]) {
            if (in_array($language, $available, true)) {
                return $language;
            }
        }

        return null;
    }
}
