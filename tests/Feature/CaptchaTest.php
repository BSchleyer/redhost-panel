<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Tests\FeatureTestCase;
use Nebrix\Tests\Support\FakeHttp;

final class CaptchaTest extends FeatureTestCase
{
    private FakeHttp $http;

    private bool $accept = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = (new FakeHttp())->on('POST', '/turnstile/v0/siteverify', function ($request): Psr7Response {
            $form = FakeHttp::form($request);
            $ok = $this->accept && $form['secret'] === 'turnstile-secret' && $form['response'] === 'token-ok';

            return new Psr7Response(200, [], json_encode(['success' => $ok], JSON_THROW_ON_ERROR));
        });
        $this->app->container()->set(ClientInterface::class, fn (): ClientInterface => $this->http->client());
        $settings = $this->app->container()->get(SettingsRepository::class);
        $settings->set('auth.captcha_provider', 'turnstile');
        $settings->set('auth.captcha_site_key', '0x4AAAAAAA-site');
        $settings->set('auth.captcha_secret', 'turnstile-secret');
    }

    /**
     * @return array<string, string>
     */
    private function registration(array $extra = []): array
    {
        return $extra + [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test',
            'password' => 'Analytical-Engine-1843', 'password_confirmation' => 'Analytical-Engine-1843', 'accept_terms' => '1',
        ];
    }

    public function testRegistrationRequiresAVerifiedCaptcha(): void
    {
        $page = $this->get('/register');
        self::assertStringContainsString('class="cf-turnstile" data-sitekey="0x4AAAAAAA-site"', $page->body());
        $csp = (string) $page->header('Content-Security-Policy');
        self::assertStringContainsString("script-src 'self' https://challenges.cloudflare.com", $csp);
        self::assertStringContainsString('frame-src https://challenges.cloudflare.com', $csp);
        self::assertStringNotContainsString('unsafe', $csp);

        $this->post('/register', $this->registration());
        $this->post('/register', $this->registration(['cf-turnstile-response' => 'forged']));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_users WHERE email = 'ada@example.test'"));

        $this->post('/register', $this->registration(['cf-turnstile-response' => 'token-ok']));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_users WHERE email = 'ada@example.test'"));
        self::assertSame($this->clientIp, FakeHttp::form($this->http->last())['remoteip']);
    }

    public function testPasswordResetRequestRequiresTheCaptchaAndLoginDoesNot(): void
    {
        $this->createUser();
        self::assertStringContainsString('cf-turnstile', $this->get('/password/forgot')->body());
        $this->post('/password/forgot', ['email' => 'someone@example.test']);
        self::assertCount(0, $this->http->requests('POST', '/turnstile/v0/siteverify'), 'Missing tokens are rejected without calling the provider.');

        self::assertStringNotContainsString('cf-turnstile', $this->get('/login')->body());
        self::assertStringNotContainsString('challenges.cloudflare.com', (string) $this->get('/login')->header('Content-Security-Policy'));
    }
}
