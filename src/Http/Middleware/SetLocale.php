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
 * the enabled languages, otherwise the default language from the settings.
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
            }
            $this->translator->setLocale($locale);
        } catch (Throwable) {
            $this->translator->setLocale('en');
        }

        return $next($request);
    }
}
