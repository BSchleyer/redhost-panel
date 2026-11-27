<?php

declare(strict_types=1);

namespace Nebrix\Security;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Nebrix\Http\Request;
use Nebrix\Settings\SettingsRepository;
use Psr\Log\LoggerInterface;

/**
 * Optional CAPTCHA (hCaptcha or Cloudflare Turnstile) for public forms
 * (registration, password reset). Tokens are verified server-side; if the
 * provider cannot be reached the form is rejected (fail closed).
 */
class Captcha
{
    private const PROVIDERS = [
        'hcaptcha' => [
            'script' => 'https://js.hcaptcha.com/1/api.js',
            'verify' => 'https://api.hcaptcha.com/siteverify',
            'field' => 'h-captcha-response',
            'class' => 'h-captcha',
            // Sources required by hCaptcha (script, frame, style, connect).
            'csp' => ['script-src' => ['https://hcaptcha.com', 'https://*.hcaptcha.com'], 'frame-src' => ['https://hcaptcha.com', 'https://*.hcaptcha.com'], 'style-src' => ['https://hcaptcha.com', 'https://*.hcaptcha.com'], 'connect-src' => ['https://hcaptcha.com', 'https://*.hcaptcha.com']],
        ],
        'turnstile' => [
            'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'field' => 'cf-turnstile-response',
            'class' => 'cf-turnstile',
            'csp' => ['script-src' => ['https://challenges.cloudflare.com'], 'frame-src' => ['https://challenges.cloudflare.com']],
        ],
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ClientInterface $http,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enabled(): bool
    {
        return isset(self::PROVIDERS[$this->provider()])
            && $this->settings->string('auth.captcha_site_key') !== ''
            && (string) ($this->settings->get('auth.captcha_secret') ?? '') !== '';
    }

    /**
     * Widget data for templates, or null when disabled.
     *
     * @return array{class: string, site_key: string, script: string}|null
     */
    public function widget(): ?array
    {
        if (!$this->enabled()) {
            return null;
        }
        $provider = self::PROVIDERS[$this->provider()];

        return ['class' => $provider['class'], 'site_key' => $this->settings->string('auth.captcha_site_key'), 'script' => $provider['script']];
    }

    /**
     * Extra CSP sources for pages that show the widget.
     *
     * @return array<string, list<string>>
     */
    public function cspSources(): array
    {
        return $this->enabled() ? self::PROVIDERS[$this->provider()]['csp'] : [];
    }

    /**
     * @throws ValidationException when the check fails
     */
    public function verify(Request $request): void
    {
        if (!$this->enabled()) {
            return;
        }
        $provider = self::PROVIDERS[$this->provider()];
        $token = (string) ($request->input($provider['field']) ?? '');
        if ($token === '' || strlen($token) > 4096) {
            throw ValidationException::withMessages(['captcha' => 'Please confirm that you are not a robot.']);
        }

        try {
            $response = $this->http->request('POST', $provider['verify'], [
                'form_params' => array_filter([
                    'secret' => (string) $this->settings->get('auth.captcha_secret'),
                    'response' => $token,
                    'remoteip' => $request->clientIp(),
                    'sitekey' => $this->provider() === 'hcaptcha' ? $this->settings->string('auth.captcha_site_key') : null,
                ]),
                'timeout' => 10,
                'connect_timeout' => 5,
                'http_errors' => false,
                'allow_redirects' => false,
            ]);
            $result = json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $exception) {
            $this->logger->warning('CAPTCHA verification failed', ['message' => $exception->getMessage()]);
            throw ValidationException::withMessages(['captcha' => 'The verification is temporarily unavailable. Please try again.']);
        }

        if (!is_array($result) || ($result['success'] ?? false) !== true) {
            throw ValidationException::withMessages(['captcha' => 'The verification failed. Please try again.']);
        }
    }

    private function provider(): string
    {
        return $this->settings->string('auth.captcha_provider') ?: 'none';
    }
}
