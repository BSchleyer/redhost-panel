<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use DateTimeImmutable;
use Nebrix\Auth\Role;
use Nebrix\Auth\TwoFactorService;
use Nebrix\Auth\User;
use Nebrix\Repository\UserRepository;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Tests\FeatureTestCase;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

final class AuthenticationTest extends FeatureTestCase
{
    private function login(string $email, string $password = 'Valid-Password-123'): \Nebrix\Http\Response
    {
        return $this->post('/login', ['email' => $email, 'password' => $password]);
    }

    public function testGuestsAreRedirectedToLogin(): void
    {
        $this->assertRedirect($this->get('/dashboard'), '/login');
        $this->assertRedirect($this->get('/account/security'), '/login');
    }

    public function testLoginPageHasCsrfFieldAndSecurityHeaders(): void
    {
        $response = $this->get('/login');

        self::assertSame(200, $response->status());
        self::assertMatchesRegularExpression('/name="_token" value="[a-f0-9]{64}"/', $response->body());
        self::assertStringContainsString("frame-ancestors 'none'", (string) $response->header('Content-Security-Policy'));
        self::assertSame('DENY', $response->header('X-Frame-Options'));
        self::assertSame('nosniff', $response->header('X-Content-Type-Options'));
        self::assertSame('no-store, private', $response->header('Cache-Control'));
    }

    public function testLoginRequiresCsrfToken(): void
    {
        $user = $this->createUser();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'Valid-Password-123'], false);

        self::assertSame(419, $response->status());
    }

    public function testSuccessfulLoginRegeneratesSessionAndRecordsHistory(): void
    {
        $user = $this->createUser();
        $this->get('/login');
        $this->csrfToken();
        $sessionBefore = $this->cookies[self::SESSION_COOKIE];

        $response = $this->login($user->email);

        $this->assertRedirect($response, '/dashboard');
        self::assertNotSame($sessionBefore, $this->cookies[self::SESSION_COOKIE], 'Session ID must change on login (fixation).');
        self::assertSame(200, $this->get('/dashboard')->status());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_login_history WHERE user_id = ? AND success = 1', [$user->id]));
    }

    public function testLegacyUsernameLoginWorks(): void
    {
        $user = $this->createUser(Role::CUSTOMER, ['username' => 'legacyuser']);

        $this->assertRedirect($this->login('legacyuser'), '/dashboard');
        self::assertSame($user->id, (int) $this->db->fetchValue('SELECT user_id FROM nx_login_history WHERE success = 1'));
    }

    public function testInvalidCredentialsUseAGenericMessage(): void
    {
        $user = $this->createUser();

        $wrongPassword = $this->followRedirect($this->login($user->email, 'wrong-password-1'));
        $unknownUser = $this->followRedirect($this->login('nobody@example.test', 'wrong-password-1'));

        self::assertStringContainsString('These credentials do not match our records.', $wrongPassword->body());
        self::assertStringContainsString('These credentials do not match our records.', $unknownUser->body());
        $this->assertRedirect($this->get('/dashboard'), '/login');
    }

    public function testAccountIsLockedAfterRepeatedFailures(): void
    {
        $user = $this->createUser();
        $max = $this->app->container()->get(SettingsRepository::class)->int('auth.max_login_attempts');

        for ($i = 0; $i < $max; $i++) {
            $this->login($user->email, 'wrong-password-' . $i);
        }

        $response = $this->followRedirect($this->login($user->email));

        self::assertStringContainsString('Too many failed sign-in attempts', $response->body());
        $this->assertRedirect($this->get('/dashboard'), '/login');
    }

    public function testLimitIsEnforcedByTheAtomicCounterNotByAPriorRead(): void
    {
        $limiter = $this->app->container()->get(\Nebrix\Security\RateLimiter::class);
        $results = [];
        for ($i = 0; $i < 7; $i++) {
            $results[] = $limiter->attempt('test:parallel', 5, 60);
        }
        // Exactly five attempts pass, even when no request ever "checked" first.
        self::assertSame([true, true, true, true, true, false, false], $results);

        $limiter->release('test:parallel');
        self::assertSame(6, $limiter->attempts('test:parallel'));
    }

    public function testOwnSuccessfulLoginDoesNotResetTheIpCounter(): void
    {
        $victimA = $this->createUser();
        $victimB = $this->createUser();
        $attacker = $this->createUser();
        $this->login($victimA->email, 'guess-1');
        $this->login($victimB->email, 'guess-2');
        $this->login($victimB->email, 'guess-3');

        $this->assertRedirect($this->login($attacker->email), '/dashboard');

        $limiter = $this->app->container()->get(\Nebrix\Security\RateLimiter::class);
        self::assertSame(3, $limiter->attempts('login:ip:' . $this->clientIp), 'Failed guesses from the IP keep counting after the attacker signs in to their own account.');
        self::assertSame(2, $limiter->attempts('login:account:' . $victimB->email));
        self::assertSame(0, $limiter->attempts('login:account:' . $attacker->email));
    }

    public function testRegistrationProbingIsRateLimited(): void
    {
        $existing = $this->createUser();
        for ($i = 0; $i < 20; $i++) {
            $this->post('/register', [
                'first_name' => 'P', 'last_name' => 'R', 'email' => $existing->email,
                'password' => 'Compiler-Pioneer-1906', 'password_confirmation' => 'Compiler-Pioneer-1906', 'accept_terms' => '1',
            ]);
        }
        $response = $this->followRedirect($this->post('/register', [
            'first_name' => 'P', 'last_name' => 'R', 'email' => $existing->email,
            'password' => 'Compiler-Pioneer-1906', 'password_confirmation' => 'Compiler-Pioneer-1906', 'accept_terms' => '1',
        ]));

        self::assertStringContainsString('Too many registration attempts', $response->body());
        self::assertStringNotContainsString('cannot be used', $response->body(), 'Once throttled, the form no longer reveals whether the address exists.');
    }

    public function testSuspendedAccountsCannotSignIn(): void
    {
        $user = $this->createUser(Role::CUSTOMER, ['status' => User::STATUS_SUSPENDED]);

        $response = $this->followRedirect($this->login($user->email));

        self::assertStringContainsString('This account is disabled', $response->body());
    }

    public function testActiveSessionEndsWhenAccountIsSuspended(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        self::assertSame(200, $this->get('/dashboard')->status());

        $this->app->container()->get(UserRepository::class)->update($user->id, ['status' => User::STATUS_SUSPENDED]);

        $this->assertRedirect($this->get('/dashboard'), '/login');
    }

    public function testStaffIsRedirectedToAdminArea(): void
    {
        $admin = $this->createUser(Role::ADMIN);

        $this->assertRedirect($this->login($admin->email), '/admin');
    }

    public function testCustomerLoginCanBeDisabledButStaffStillSignsIn(): void
    {
        $this->app->container()->get(SettingsRepository::class)->set('auth.login_enabled', false);
        $customer = $this->createUser();
        $support = $this->createUser(Role::SUPPORT);

        self::assertStringContainsString('Sign-in is temporarily disabled', $this->followRedirect($this->login($customer->email))->body());
        $this->assertRedirect($this->login($support->email), '/admin');
    }

    public function testLogoutInvalidatesTheSession(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $oldSession = $this->cookies[self::SESSION_COOKIE];

        $this->assertRedirect($this->post('/logout'), '/login');
        $this->cookies[self::SESSION_COOKIE] = $oldSession; // replay the old cookie

        $this->assertRedirect($this->get('/dashboard'), '/login');
    }

    public function testTwoFactorChallengeAndReplayProtection(): void
    {
        $user = $this->createUser();
        $twoFactor = $this->app->container()->get(TwoFactorService::class);
        $secret = $twoFactor->generateSecret();
        $this->clock()->freeze(new DateTimeImmutable('2026-03-01 12:00:00'));
        $totp = new TwoFactorAuth(new BaconQrCodeProvider());
        $code = $totp->getCode($secret, $this->clock()->now()->getTimestamp());
        $recoveryCodes = $twoFactor->enable($user, $secret, $code);
        self::assertIsArray($recoveryCodes);
        self::assertCount(10, $recoveryCodes);

        // Password alone is not enough.
        $this->assertRedirect($this->login($user->email), '/two-factor');
        $this->assertRedirect($this->get('/dashboard'), '/login');

        $this->post('/two-factor', ['code' => '000000']);
        $this->assertRedirect($this->get('/dashboard'), '/login');

        // Same time slice as during setup: the code was already used once and must be rejected (replay).
        $this->post('/two-factor', ['code' => $code]);
        $this->assertRedirect($this->get('/dashboard'), '/login');

        $this->clock()->freeze(new DateTimeImmutable('2026-03-01 12:00:31'));
        $this->assertRedirect($this->post('/two-factor', ['code' => $totp->getCode($secret, $this->clock()->now()->getTimestamp())]), '/dashboard');
        self::assertSame(200, $this->get('/dashboard')->status());

        // Recovery codes work exactly once.
        $this->post('/logout');
        $this->login($user->email);
        $this->assertRedirect($this->post('/two-factor', ['code' => $recoveryCodes[0]]), '/dashboard');
        $this->post('/logout');
        $this->login($user->email);
        $this->post('/two-factor', ['code' => $recoveryCodes[0]]);
        $this->assertRedirect($this->get('/dashboard'), '/login');
    }

    public function testParallelRequestsCannotReuseOneTotpOrRecoveryCode(): void
    {
        $user = $this->createUser();
        $twoFactor = $this->app->container()->get(TwoFactorService::class);
        $secret = $twoFactor->generateSecret();
        $this->clock()->freeze(new DateTimeImmutable('2026-03-01 12:00:00'));
        $totp = new TwoFactorAuth(new BaconQrCodeProvider());
        $recoveryCodes = $twoFactor->enable($user, $secret, $totp->getCode($secret, $this->clock()->now()->getTimestamp()));
        self::assertIsArray($recoveryCodes);

        // Both requests loaded the user before either accepted the code.
        $users = $this->app->container()->get(UserRepository::class);
        $first = $users->find($user->id);
        $second = $users->find($user->id);
        self::assertNotNull($first);
        self::assertNotNull($second);

        $this->clock()->freeze(new DateTimeImmutable('2026-03-01 12:00:31'));
        $code = $totp->getCode($secret, $this->clock()->now()->getTimestamp());
        self::assertTrue($twoFactor->verify($first, $code));
        self::assertFalse($twoFactor->verify($second, $code), 'The same TOTP code is accepted only once.');

        self::assertTrue($twoFactor->verify($first, $recoveryCodes[3]));
        self::assertFalse($twoFactor->verify($second, $recoveryCodes[3]), 'A recovery code is consumed only once.');
    }

    public function testEnablingTwoFactorRequiresTheCurrentPassword(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $page = $this->get('/account/two-factor/setup')->body();
        self::assertSame(1, preg_match('#<code>([A-Z2-7 ]+)</code>#', $page, $match));
        $secret = str_replace(' ', '', $match[1]);
        $code = (new TwoFactorAuth(new BaconQrCodeProvider()))->getCode($secret, $this->clock()->now()->getTimestamp());

        $this->post('/account/two-factor/enable', ['code' => $code]);
        $this->post('/account/two-factor/enable', ['code' => $code, 'password' => 'Wrong-Password-123']);
        self::assertNull($this->db->fetchValue('SELECT two_factor_enabled_at FROM nx_users WHERE id = ?', [$user->id]));

        $this->post('/account/two-factor/enable', ['code' => $code, 'password' => 'Valid-Password-123']);
        self::assertNotNull($this->db->fetchValue('SELECT two_factor_enabled_at FROM nx_users WHERE id = ?', [$user->id]));
    }

    public function testTwoFactorSecretIsEncryptedAtRest(): void
    {
        $user = $this->createUser();
        $twoFactor = $this->app->container()->get(TwoFactorService::class);
        $secret = $twoFactor->generateSecret();
        $totp = new TwoFactorAuth(new BaconQrCodeProvider());
        $twoFactor->enable($user, $secret, $totp->getCode($secret, $this->clock()->now()->getTimestamp()));

        $stored = (string) $this->db->fetchValue('SELECT two_factor_secret FROM nx_users WHERE id = ?', [$user->id]);
        self::assertStringStartsWith('nx1:', $stored);
        self::assertStringNotContainsString($secret, $stored);
        self::assertStringNotContainsString($secret, (string) $this->db->fetchValue('SELECT two_factor_recovery_codes FROM nx_users WHERE id = ?', [$user->id]));
    }

    public function testRegistrationRequiresVerificationAndTokensAreSingleUse(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'Grace@Example.test',
            'password' => 'Compiler-Pioneer-1906',
            'password_confirmation' => 'Compiler-Pioneer-1906',
            'accept_terms' => '1',
        ]);
        $this->assertRedirect($response, '/login');

        $row = $this->db->fetchOne("SELECT * FROM nx_users WHERE email = 'grace@example.test'");
        self::assertNotNull($row);
        self::assertSame('pending', $row['status']);
        self::assertStringStartsWith('$argon2id$', $row['password_hash']);
        self::assertSame(Role::CUSTOMER, $this->db->fetchValue('SELECT slug FROM nx_roles WHERE id = ?', [$row['role_id']]));

        $mail = $this->lastMail('email_verification');
        self::assertNotNull($mail);
        self::assertSame('grace@example.test', $mail['to']);
        $link = $this->extractLink($mail['text'], '/email/verify/');

        // Pending accounts cannot sign in until the address is confirmed.
        $page = $this->followRedirect($this->login('grace@example.test', 'Compiler-Pioneer-1906'));
        self::assertStringContainsString('Please confirm your e-mail address first', $page->body());
        self::assertStringContainsString('action="/email/resend"', $page->body());
        $this->assertRedirect($this->get('/dashboard'), '/login');

        $this->assertRedirect($this->get($link), '/login');
        self::assertSame('active', $this->db->fetchValue("SELECT status FROM nx_users WHERE email = 'grace@example.test'"));
        $this->assertRedirect($this->login('grace@example.test', 'Compiler-Pioneer-1906'), '/dashboard');
        self::assertSame(200, $this->get('/dashboard')->status());

        // The link is single use.
        $this->assertRedirect($this->get($link), '/email/verify');
    }

    public function testActiveButUnverifiedAccountsSeeTheVerificationNotice(): void
    {
        $user = $this->createUser(Role::CUSTOMER, ['email_verified_at' => null]);
        $this->actingAs($user);

        $this->assertRedirect($this->get('/dashboard'), '/email/verify');
        self::assertSame(200, $this->get('/email/verify')->status());
    }

    public function testRegistrationRejectsWeakPasswordsAndMissingTerms(): void
    {
        $this->post('/register', ['first_name' => 'A', 'last_name' => 'B', 'email' => 'weak@example.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'accept_terms' => '1']);
        $this->post('/register', ['first_name' => 'A', 'last_name' => 'B', 'email' => 'terms@example.test', 'password' => 'A-strong-passphrase-9', 'password_confirmation' => 'A-strong-passphrase-9']);

        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM nx_users WHERE email IN ('weak@example.test', 'terms@example.test')"));
    }

    public function testRegistrationCannotAssignRoleOrStatus(): void
    {
        $this->post('/register', [
            'first_name' => 'Mallory', 'last_name' => 'X', 'email' => 'mallory@example.test',
            'password' => 'A-strong-passphrase-9', 'password_confirmation' => 'A-strong-passphrase-9', 'accept_terms' => '1',
            'role_id' => '1', 'status' => 'active', 'credit_balance' => '100000',
        ]);

        $row = $this->db->fetchOne("SELECT u.status, u.credit_balance, r.slug FROM nx_users u JOIN nx_roles r ON r.id = u.role_id WHERE email = 'mallory@example.test'");
        self::assertNotNull($row);
        self::assertSame(Role::CUSTOMER, $row['slug']);
        self::assertSame('pending', $row['status']);
        self::assertSame(0, (int) $row['credit_balance']);
    }

    public function testPasswordResetFlow(): void
    {
        $user = $this->createUser();

        // Another device is signed in.
        $this->actingAs($user);
        $otherDeviceCookie = $this->cookies[self::SESSION_COOKIE];
        $this->cookies = [];

        $unknown = $this->followRedirect($this->post('/password/forgot', ['email' => 'unknown@example.test']));
        $known = $this->followRedirect($this->post('/password/forgot', ['email' => $user->email]));
        self::assertStringContainsString('If an account exists for this address', $unknown->body());
        self::assertStringContainsString('If an account exists for this address', $known->body());

        $mail = $this->lastMail('password_reset');
        self::assertNotNull($mail);
        self::assertSame($user->email, $mail['to']);
        $link = $this->extractLink($mail['text'], '/password/reset/');
        $token = substr($link, strlen('/password/reset/'));

        self::assertSame(200, $this->get($link)->status());
        $this->assertRedirect($this->post('/password/reset', ['token' => $token, 'password' => 'Brand-New-Passphrase-1', 'password_confirmation' => 'Brand-New-Passphrase-1']), '/login');

        // Token is single use.
        $this->assertRedirect($this->get($link), '/password/forgot');
        // Old password no longer works, new one does.
        $this->assertRedirect($this->login($user->email), '/login');
        $this->assertRedirect($this->login($user->email, 'Brand-New-Passphrase-1'), '/dashboard');
        // The other device was signed out.
        $this->cookies = [self::SESSION_COOKIE => $otherDeviceCookie];
        $this->assertRedirect($this->get('/dashboard'), '/login');
        self::assertNotNull($this->lastMail('password_changed'));
    }

    public function testExpiredResetTokensAreRejected(): void
    {
        $user = $this->createUser();
        $this->clock()->freeze(new DateTimeImmutable('2026-05-01 08:00:00'));
        $this->post('/password/forgot', ['email' => $user->email]);
        $link = $this->extractLink((string) $this->lastMail('password_reset')['text'], '/password/reset/');

        $this->clock()->freeze(new DateTimeImmutable('2026-05-01 09:01:00'));

        $this->assertRedirect($this->get($link), '/password/forgot');
    }

    public function testChangePasswordRequiresCurrentPasswordAndKeepsCurrentSession(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->post('/account/password', ['current_password' => 'wrong', 'password' => 'Another-Passphrase-77', 'password_confirmation' => 'Another-Passphrase-77']);
        $hash = (string) $this->db->fetchValue('SELECT password_hash FROM nx_users WHERE id = ?', [$user->id]);
        self::assertSame($user->passwordHash, $hash);

        $this->assertRedirect($this->post('/account/password', ['current_password' => 'Valid-Password-123', 'password' => 'Another-Passphrase-77', 'password_confirmation' => 'Another-Passphrase-77']), '/account/security');
        self::assertNotSame($user->passwordHash, (string) $this->db->fetchValue('SELECT password_hash FROM nx_users WHERE id = ?', [$user->id]));
        self::assertSame(200, $this->get('/account/security')->status());
    }

    public function testProfileUpdateIgnoresProtectedFields(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->post('/account/profile', [
            'first_name' => 'New', 'last_name' => 'Name', 'country' => 'de', 'language' => 'de',
            'email' => 'attacker@example.test', 'role_id' => '1', 'credit_balance' => '999999',
        ]);

        $row = $this->db->fetchOne('SELECT * FROM nx_users WHERE id = ?', [$user->id]);
        self::assertNotNull($row);
        self::assertSame('New', $row['first_name']);
        self::assertSame('DE', $row['country']);
        self::assertSame($user->email, $row['email']);
        self::assertSame($user->roleId, (int) $row['role_id']);
        self::assertSame(0, (int) $row['credit_balance']);
    }

    public function testHtmlInProfileIsEscaped(): void
    {
        $user = $this->createUser(Role::CUSTOMER, ['first_name' => '<script>alert(1)</script>']);
        $this->actingAs($user);

        $body = $this->get('/account/profile')->body();

        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }
}
