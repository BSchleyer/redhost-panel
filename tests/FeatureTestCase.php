<?php

declare(strict_types=1);

namespace Nebrix\Tests;

use Nebrix\Auth\User;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Mail\Mailer;
use Nebrix\Security\Csrf;
use Nebrix\Security\Encrypter;
use Nebrix\Session\SessionStore;

/**
 * Runs full HTTP requests through the kernel in-process, keeping cookies
 * between requests like a browser.
 */
abstract class FeatureTestCase extends DatabaseTestCase
{
    protected const SESSION_COOKIE = 'nebrix_session';

    /** @var array<string, string> */
    protected array $cookies = [];

    protected string $clientIp = '198.51.100.20';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookies = [];
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function get(string $uri, array $query = []): Response
    {
        return $this->send(Request::create('GET', $uri, $query, $this->cookies, $this->server()));
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function post(string $uri, array $data = [], bool $withCsrf = true): Response
    {
        if ($withCsrf && !array_key_exists(Csrf::FIELD, $data)) {
            $data[Csrf::FIELD] = $this->csrfToken();
        }

        return $this->send(Request::create('POST', $uri, $data, $this->cookies, $this->server()));
    }

    /**
     * @param array<string, string> $headers
     */
    protected function postRaw(string $uri, string $body, array $headers = []): Response
    {
        $server = $this->server();
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[$key === 'CONTENT_TYPE' ? $key : 'HTTP_' . $key] = $value;
        }

        return $this->send(Request::create('POST', $uri, [], $this->cookies, $server, $body));
    }

    /**
     * @return array<string, string>
     */
    /** @var array<string, string> additional $_SERVER values, e.g. HTTP_ACCEPT_LANGUAGE */
    protected array $serverOverrides = [];

    private function server(): array
    {
        return $this->serverOverrides + ['REMOTE_ADDR' => $this->clientIp, 'HTTP_HOST' => 'localhost', 'HTTP_USER_AGENT' => 'NebrixTest/1.0'];
    }

    protected function send(Request $request): Response
    {
        $response = $this->app->handle($request);

        foreach ($response->cookies() as $cookie) {
            $expires = (int) ($cookie['options']['expires'] ?? 0);
            if ($cookie['value'] === '' || ($expires !== 0 && $expires < time())) {
                unset($this->cookies[$cookie['name']]);
            } else {
                $this->cookies[$cookie['name']] = $cookie['value'];
            }
        }

        return $response;
    }

    protected function csrfToken(): string
    {
        $store = $this->app->container()->get(SessionStore::class);
        $id = $this->cookies[self::SESSION_COOKIE] ?? null;
        $session = ($id !== null ? $store->load($id) : null) ?? $store->create();
        $token = Csrf::token($session);
        $store->save($session, $this->clientIp, 'NebrixTest/1.0');
        $this->cookies[self::SESSION_COOKIE] = $session->id();

        return $token;
    }

    protected function actingAs(User $user): void
    {
        $store = $this->app->container()->get(SessionStore::class);
        $session = $store->create();
        $session->setUserId($user->id);
        Csrf::token($session);
        $store->save($session, $this->clientIp, 'NebrixTest/1.0');
        $this->cookies[self::SESSION_COOKIE] = $session->id();
    }

    protected function assertRedirect(Response $response, string $location): void
    {
        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect, got %d.', $response->status()));
        self::assertSame($location, $response->header('Location'));
    }

    protected function followRedirect(Response $response): Response
    {
        self::assertTrue($response->isRedirect(), sprintf('Expected a redirect, got %d.', $response->status()));

        return $this->get((string) $response->header('Location'));
    }

    /**
     * Decrypts the most recently queued e-mail.
     *
     * @return array{to: string, subject: string, html: string, text: string, template: string}|null
     */
    protected function lastMail(?string $template = null): ?array
    {
        $sql = 'SELECT payload FROM nx_jobs WHERE type = ?';
        $params = [Mailer::JOB_TYPE];
        if ($template !== null) {
            $sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.template')) = ?";
            $params[] = $template;
        }
        $payload = $this->db->fetchValue($sql . ' ORDER BY id DESC LIMIT 1', $params);
        if (!is_string($payload)) {
            return null;
        }

        $decoded = json_decode($payload, true);
        /** @var array{to: string, subject: string, html: string, text: string, template: string} */
        return $this->app->container()->get(Encrypter::class)->decryptArray((string) $decoded['message'], 'mail-job');
    }

    /** Whether an e-mail with the template was queued (also after delivery, when the content is scrubbed). */
    protected function mailQueued(string $template): bool
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM nx_jobs WHERE type = ? AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.template')) = ?",
            [Mailer::JOB_TYPE, $template],
        ) > 0;
    }

    protected function extractLink(string $text, string $pathPrefix): string
    {
        self::assertSame(1, preg_match('#https?://[^/\s"]+(' . preg_quote($pathPrefix, '#') . '[A-Za-z0-9/_-]+)#', $text, $match), 'Link not found in e-mail.');

        return $match[1];
    }
}
