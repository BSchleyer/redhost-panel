<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration;

use DateTimeImmutable;
use Nebrix\Database\Migrator;
use Nebrix\Security\RateLimiter;
use Nebrix\Session\SessionStore;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Security\ValidationException;
use Nebrix\Tests\DatabaseTestCase;

final class CoreDatabaseTest extends DatabaseTestCase
{
    public function testMigrationsAreRecordedAndIdempotent(): void
    {
        $migrator = $this->app->container()->get(Migrator::class);

        self::assertSame([], $migrator->pending());
        self::assertCount(count($migrator->available()), $migrator->applied());
    }

    public function testConnectionRejectsUnsafeIdentifiersAndEmptyWhere(): void
    {
        try {
            $this->db->insert('nx_settings; DROP TABLE nx_users', ['a' => 1]);
            self::fail('Expected exception');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->db->update('nx_settings', ['value' => 'x'], []);
    }

    public function testRateLimiterCountsWithinWindowAndResets(): void
    {
        $limiter = $this->app->container()->get(RateLimiter::class);
        $this->clock()->freeze(new DateTimeImmutable('2026-01-01 10:00:00'));

        self::assertSame(1, $limiter->hit('login|1.2.3.4', 60));
        self::assertSame(2, $limiter->hit('login|1.2.3.4', 60));
        self::assertSame(3, $limiter->hit('login|1.2.3.4', 60));
        self::assertTrue($limiter->tooManyAttempts('login|1.2.3.4', 3));
        self::assertFalse($limiter->tooManyAttempts('login|5.6.7.8', 3));
        self::assertSame(60, $limiter->availableIn('login|1.2.3.4'));

        $this->clock()->freeze(new DateTimeImmutable('2026-01-01 10:01:01'));
        self::assertFalse($limiter->tooManyAttempts('login|1.2.3.4', 3));
        self::assertSame(1, $limiter->hit('login|1.2.3.4', 60));

        $limiter->clear('login|1.2.3.4');
        self::assertSame(0, $limiter->attempts('login|1.2.3.4'));
    }

    public function testSessionStoreHashesIdsAndExpiresIdleSessions(): void
    {
        $store = $this->app->container()->get(SessionStore::class);
        $this->clock()->freeze(new DateTimeImmutable('2026-01-01 10:00:00'));

        $session = $store->create();
        $session->put('foo', 'bar');
        $store->save($session, '203.0.113.1', 'phpunit');

        $raw = $this->db->fetchOne('SELECT * FROM nx_sessions WHERE id = ?', [hash('sha256', $session->id())]);
        self::assertNotNull($raw, 'Session rows are keyed by the SHA-256 hash of the ID.');
        self::assertNull($this->db->fetchOne('SELECT * FROM nx_sessions WHERE id = ?', [$session->id()]));

        $loaded = $store->load($session->id());
        self::assertNotNull($loaded);
        self::assertSame('bar', $loaded->get('foo'));

        $this->clock()->freeze(new DateTimeImmutable('2026-01-01 11:01:00')); // idle timeout is 60 minutes
        self::assertNull($store->load($session->id()));
    }

    public function testRegeneratedSessionDeletesThePreviousId(): void
    {
        $store = $this->app->container()->get(SessionStore::class);
        $session = $store->create();
        $session->put('x', 1);
        $store->save($session, '127.0.0.1', 'phpunit');
        $oldId = $session->id();

        $session->regenerate();
        $store->save($session, '127.0.0.1', 'phpunit');

        self::assertNull($store->load($oldId), 'Session fixation: the old ID must be invalid after regeneration.');
        self::assertNotNull($store->load($session->id()));
    }

    public function testRevokedSessionIsNotRecreatedByAnInFlightRequest(): void
    {
        $store = $this->app->container()->get(SessionStore::class);
        $user = $this->createUser();
        $session = $store->create();
        $session->setUserId($user->id);
        $session->put('x', 1);
        $store->save($session, '127.0.0.1', 'phpunit');

        // Two requests of an attacker load the session ...
        $inFlight = $store->load($session->id());
        $regenerating = $store->load($session->id());
        self::assertNotNull($inFlight);
        self::assertNotNull($regenerating);

        // ... the owner signs out everywhere meanwhile ...
        self::assertSame(1, $store->destroyForUser($user->id));

        // ... and the in-flight requests finish with changed session data.
        $inFlight->flash('notice', 'changed');
        $store->save($inFlight, '198.51.100.7', 'attacker');
        $regenerating->regenerate();
        $store->save($regenerating, '198.51.100.7', 'attacker');

        self::assertNull($store->load($session->id()));
        self::assertNull($store->load($regenerating->id()));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_sessions WHERE user_id = ?', [$user->id]));
    }

    public function testSettingsAreTypedValidatedAndSecretsEncrypted(): void
    {
        $settings = $this->app->container()->get(SettingsRepository::class);

        self::assertSame('Nebrix', $settings->get('general.company_name'));
        self::assertTrue($settings->get('auth.registration_enabled'));

        $settings->saveGroup('mail', [
            'mail__enabled' => '1',
            'mail__host' => 'smtp.example.com',
            'mail__port' => '465',
            'mail__encryption' => 'ssl',
            'mail__password' => 'smtp-secret-value',
            'mail__from_address' => 'noreply@example.com',
        ], null);

        self::assertTrue($settings->get('mail.enabled'));
        self::assertSame(465, $settings->get('mail.port'));
        self::assertSame('smtp-secret-value', $settings->get('mail.password'));

        $stored = (string) $this->db->fetchValue("SELECT value FROM nx_settings WHERE `key` = 'mail.password'");
        self::assertStringStartsWith('nx1:', $stored);
        self::assertStringNotContainsString('smtp-secret-value', $stored);

        // Empty secret input keeps the stored value.
        $settings->saveGroup('mail', ['mail__port' => '587', 'mail__encryption' => 'tls', 'mail__password' => ''], null);
        self::assertSame('smtp-secret-value', $settings->get('mail.password'));

        // Explicit clear removes it.
        $settings->saveGroup('mail', ['mail__port' => '587', 'mail__encryption' => 'tls', 'mail__password__clear' => '1'], null);
        self::assertNull($settings->get('mail.password'));

        $this->expectException(ValidationException::class);
        $settings->saveGroup('branding', ['branding__primary_color' => 'red; background:url(x)'], null);
    }
}
