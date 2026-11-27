<?php

declare(strict_types=1);

namespace Nebrix\Auth;

use Nebrix\Audit\AuditLogger;
use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Http\Request;
use Nebrix\Mail\Mailer;
use Nebrix\Repository\UserRepository;
use Nebrix\Security\Csrf;
use Nebrix\Security\PasswordHasher;
use Nebrix\Security\PasswordPolicy;
use Nebrix\Security\RateLimiter;
use Nebrix\Security\ValidationException;
use Nebrix\Session\Session;
use Nebrix\Session\SessionStore;
use Nebrix\Settings\SettingsRepository;

/**
 * Authentication workflows.
 *
 * Security properties (see DOCUMENTATION/SECURITY.md):
 * - Generic error messages; unknown accounts cost the same time as known ones.
 * - Per-account and per-IP throttling with lockout (legacy H-06).
 * - Session ID regeneration and CSRF token rotation on login (fixation).
 * - Second factor checked before the session is authenticated.
 * - Password reset/verification tokens are hashed, expiring, single-use.
 * - Password changes and resets terminate all other sessions.
 */
class AuthService
{
    public const SESSION_2FA_USER = 'auth.2fa_user';
    public const SESSION_2FA_STARTED = 'auth.2fa_started';
    public const SESSION_AUTHENTICATED_AT = 'auth.authenticated_at';

    private const IP_MAX_ATTEMPTS = 30;
    private const IP_DECAY_SECONDS = 900;
    private const TWO_FACTOR_MAX_ATTEMPTS = 5;
    private const TWO_FACTOR_WINDOW_SECONDS = 300;
    private const RESET_TOKEN_MINUTES = 60;
    private const VERIFY_TOKEN_MINUTES = 2880;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly RateLimiter $limiter,
        private readonly SettingsRepository $settings,
        private readonly LoginHistory $history,
        private readonly TokenService $tokens,
        private readonly TwoFactorService $twoFactor,
        private readonly SessionStore $sessions,
        private readonly Mailer $mailer,
        private readonly AuditLogger $audit,
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly Config $config,
        private readonly \Nebrix\Api\ApiTokenService $apiTokens,
        private readonly \Nebrix\View\Translator $translator,
    ) {
    }

    /**
     * @return 'authenticated'|'two_factor_required'
     *
     * @throws AuthenticationException with a user-safe message
     */
    public function attempt(Request $request, string $login, #[\SensitiveParameter] string $password): string
    {
        $ip = $request->clientIp();
        $login = mb_strtolower(trim($login));
        $accountKey = 'login:account:' . $login;
        $ipKey = 'login:ip:' . $ip;
        $maxAttempts = max(3, $this->settings->int('auth.max_login_attempts'));
        $lockoutSeconds = max(60, $this->settings->int('auth.lockout_minutes') * 60);

        // Count the attempt before the password is checked, so that parallel
        // requests cannot all pass the limit (each one is counted atomically).
        $withinAccountLimit = $this->limiter->attempt($accountKey, $maxAttempts, $lockoutSeconds);
        $withinIpLimit = $this->limiter->attempt($ipKey, self::IP_MAX_ATTEMPTS, self::IP_DECAY_SECONDS);
        if (!$withinAccountLimit || !$withinIpLimit) {
            $this->history->record(null, $login, $ip, $request->userAgent(), false, 'throttled');
            $minutes = (int) ceil(max($this->limiter->availableIn($accountKey), $this->limiter->availableIn($ipKey)) / 60);

            throw new AuthenticationException(sprintf('Too many failed sign-in attempts. Please try again in %d minute(s).', max(1, $minutes)));
        }

        $user = $login !== '' ? $this->users->findByLogin($login) : null;
        $valid = $this->hasher->verify($password, $user?->passwordHash);

        if ($user === null || !$valid) {
            $this->history->record($user?->id, $login, $ip, $request->userAgent(), false, 'invalid_credentials');

            throw new AuthenticationException('These credentials do not match our records.');
        }

        if ($user->status === User::STATUS_SUSPENDED || $user->status === User::STATUS_CLOSED) {
            $this->history->record($user->id, $login, $ip, $request->userAgent(), false, 'account_' . $user->status);

            throw new AuthenticationException('This account is disabled. Please contact support.');
        }

        if ($user->status === User::STATUS_PENDING) {
            $this->history->record($user->id, $login, $ip, $request->userAgent(), false, 'email_unverified');

            throw new AuthenticationException('Please confirm your e-mail address first. You can request a new confirmation link below.', AuthenticationException::EMAIL_UNVERIFIED);
        }

        if (!$user->isStaff() && !$this->settings->bool('auth.login_enabled')) {
            throw new AuthenticationException('Sign-in is temporarily disabled. Please try again later.');
        }

        if ($this->hasher->needsRehash($user->passwordHash)) {
            $this->users->update($user->id, ['password_hash' => $this->hasher->hash($password)]);
        }

        // Correct password: forget the account's failures and take back this
        // attempt from the IP counter (other failures from the IP still count).
        $this->limiter->clear($accountKey);
        $this->limiter->release($ipKey);
        $session = $request->session();

        if ($user->hasTwoFactor()) {
            // Not authenticated yet: remember the pending user, regenerate to bind the challenge to a fresh ID.
            $session->regenerate();
            $session->put(self::SESSION_2FA_USER, $user->id);
            $session->put(self::SESSION_2FA_STARTED, $this->clock->now()->getTimestamp());

            return 'two_factor_required';
        }

        $this->completeLogin($request, $user);

        return 'authenticated';
    }

    public function pendingTwoFactorUser(Session $session): ?User
    {
        $userId = $session->get(self::SESSION_2FA_USER);
        $started = (int) $session->get(self::SESSION_2FA_STARTED, 0);
        if (!is_int($userId) || $this->clock->now()->getTimestamp() - $started > 600) {
            return null;
        }

        $user = $this->users->find($userId);

        return $user !== null && $user->isActive() ? $user : null;
    }

    /**
     * @throws AuthenticationException
     */
    public function completeTwoFactor(Request $request, string $code): void
    {
        $session = $request->session();
        $user = $this->pendingTwoFactorUser($session);
        if ($user === null) {
            $this->clearTwoFactorChallenge($session);

            throw new AuthenticationException('Your sign-in has expired. Please sign in again.');
        }

        $key = 'two_factor:' . $user->id;
        if (!$this->limiter->attempt($key, self::TWO_FACTOR_MAX_ATTEMPTS, self::TWO_FACTOR_WINDOW_SECONDS)) {
            $this->clearTwoFactorChallenge($session);
            $this->history->record($user->id, $user->email, $request->clientIp(), $request->userAgent(), false, 'two_factor_throttled');

            throw new AuthenticationException('Too many invalid codes. Please sign in again in a few minutes.');
        }

        if (!$this->twoFactor->verify($user, $code)) {
            $this->history->record($user->id, $user->email, $request->clientIp(), $request->userAgent(), false, 'two_factor_invalid');

            throw new AuthenticationException('The authentication code is invalid.');
        }

        $this->limiter->clear($key);
        $this->clearTwoFactorChallenge($session);
        $this->completeLogin($request, $user);
    }

    private function clearTwoFactorChallenge(Session $session): void
    {
        $session->forget(self::SESSION_2FA_USER);
        $session->forget(self::SESSION_2FA_STARTED);
    }

    private function completeLogin(Request $request, User $user): void
    {
        $session = $request->session();
        $session->regenerate();
        $session->setUserId($user->id);
        $session->put(self::SESSION_AUTHENTICATED_AT, $this->clock->now()->getTimestamp());
        Csrf::rotate($session);

        $this->users->update($user->id, [
            'last_login_at' => $this->clock->nowString(),
            'last_login_ip' => $request->clientIp(),
        ]);
        $this->history->record($user->id, $user->email, $request->clientIp(), $request->userAgent(), true);

        if ($user->isStaff()) {
            $this->audit->log($user->id, 'staff.login', 'user', $user->id, [], $request->clientIp());
        }
    }

    public function logout(Request $request): void
    {
        $session = $request->session();
        $userId = $session->userId();
        $session->invalidate();
        if ($userId !== null) {
            $this->audit->log($userId, 'auth.logout', 'user', $userId, [], $request->clientIp());
        }
    }

    /**
     * @param array{email: string, password: string, first_name?: ?string, last_name?: ?string, company?: ?string, country?: ?string} $data
     *
     * @throws ValidationException
     * @throws AuthenticationException
     */
    public function register(Request $request, array $data): User
    {
        if (!$this->settings->bool('auth.registration_enabled')) {
            throw new AuthenticationException('Registration is currently disabled.');
        }

        // Every attempt counts (including rejected addresses), which bounds
        // probing for registered e-mail addresses.
        if (!$this->limiter->attempt('register:attempt:ip:' . $request->clientIp(), 20, 3600)) {
            throw new AuthenticationException('Too many registration attempts from your network. Please try again later.');
        }

        $errors = PasswordPolicy::validate($data['password'], [$data['email'], (string) ($data['first_name'] ?? ''), (string) ($data['last_name'] ?? '')]);
        if ($errors !== []) {
            throw new ValidationException(['password' => $errors], ['email' => $data['email']]);
        }

        if ($this->users->emailExists($data['email'])) {
            // Do not reveal more than necessary; the form needs a clear hint, the address owner is not notified.
            throw ValidationException::withMessages(['email' => 'This e-mail address cannot be used. If it is yours, please sign in or reset your password.']);
        }

        if (!$this->limiter->attempt('register:ip:' . $request->clientIp(), 5, 3600)) {
            throw new AuthenticationException('Too many registrations from your network. Please try again later.');
        }
        $requireVerification = $this->settings->bool('auth.require_email_verification');
        $roleId = (int) $this->db->fetchValue('SELECT `id` FROM `nx_roles` WHERE `slug` = ?', [Role::CUSTOMER]);
        $now = $this->clock->nowString();

        $userId = $this->users->create([
            'role_id' => $roleId,
            'email' => $data['email'],
            'password_hash' => $this->hasher->hash($data['password']),
            'status' => $requireVerification ? User::STATUS_PENDING : User::STATUS_ACTIVE,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'company' => $data['company'] ?? null,
            'country' => $data['country'] ?? null,
            // The language the visitor registered in (browser language or default).
            'language' => $this->translator->locale(),
            'password_changed_at' => $now,
            'legal_accepted_at' => $now,
        ]);

        $user = $this->users->find($userId);
        if ($user === null) {
            throw new \RuntimeException('User could not be created.');
        }

        $this->audit->log($userId, 'customer.registered', 'user', $userId, [], $request->clientIp());
        $this->mailer->queue($user->email, $user->displayName(), 'account_registration', [
            'name' => $user->displayName(),
            'email' => $user->email,
            'login_url' => $this->url('/login'),
        ]);

        if ($requireVerification) {
            $this->sendVerification($user);
        }

        return $user;
    }

    public function sendVerification(User $user): void
    {
        $token = $this->tokens->create($user->id, TokenService::EMAIL_VERIFICATION, self::VERIFY_TOKEN_MINUTES);
        $this->mailer->queue($user->email, $user->displayName(), 'email_verification', [
            'name' => $user->displayName(),
            'verification_url' => $this->url('/email/verify/' . $token),
            'expires_hours' => (int) (self::VERIFY_TOKEN_MINUTES / 60),
        ]);
    }

    public function resendVerification(Request $request, User $user): bool
    {
        $key = 'verify-resend:' . $user->id;
        if ($user->isEmailVerified() || !$this->limiter->attempt($key, 3, 3600)) {
            return false;
        }
        $this->sendVerification($user);

        return true;
    }

    /**
     * Guest variant used from the login page; responds identically whether
     * or not a pending account exists for the address (no enumeration).
     */
    public function resendVerificationByEmail(Request $request, string $email): void
    {
        $ipKey = 'verify-resend:ip:' . $request->clientIp();
        if (!$this->limiter->attempt($ipKey, 10, 3600)) {
            return;
        }

        $user = $this->users->findByEmail($email);
        if ($user !== null && $user->status === User::STATUS_PENDING) {
            $this->resendVerification($request, $user);
        }
    }

    public function verifyEmail(string $token): ?User
    {
        $userId = $this->tokens->consume($token, TokenService::EMAIL_VERIFICATION);
        if ($userId === null) {
            return null;
        }

        $user = $this->users->find($userId);
        if ($user === null) {
            return null;
        }

        $update = ['email_verified_at' => $this->clock->nowString()];
        if ($user->status === User::STATUS_PENDING) {
            $update['status'] = User::STATUS_ACTIVE;
        }
        $this->users->update($user->id, $update);
        $this->audit->log($user->id, 'customer.email_verified', 'user', $user->id);

        return $this->users->find($user->id);
    }

    /**
     * Always behaves the same whether or not the account exists (no enumeration).
     */
    public function requestPasswordReset(Request $request, string $email): void
    {
        $email = mb_strtolower(trim($email));
        $ipKey = 'reset:ip:' . $request->clientIp();
        $emailKey = 'reset:email:' . $email;

        $withinIpLimit = $this->limiter->attempt($ipKey, 10, 3600);
        $withinEmailLimit = $this->limiter->attempt($emailKey, 3, 3600);
        if (!$withinIpLimit || !$withinEmailLimit) {
            return;
        }

        $user = $this->users->findByEmail($email);
        if ($user === null || in_array($user->status, [User::STATUS_SUSPENDED, User::STATUS_CLOSED], true)) {
            return;
        }

        $token = $this->tokens->create($user->id, TokenService::PASSWORD_RESET, self::RESET_TOKEN_MINUTES);
        $this->mailer->queue($user->email, $user->displayName(), 'password_reset', [
            'name' => $user->displayName(),
            'reset_url' => $this->url('/password/reset/' . $token),
            'expires_minutes' => self::RESET_TOKEN_MINUTES,
            'ip' => $request->clientIp(),
        ]);
        $this->audit->log($user->id, 'auth.password_reset_requested', 'user', $user->id, [], $request->clientIp());
    }

    public function isResetTokenValid(string $token): bool
    {
        return $this->tokens->isValid($token, TokenService::PASSWORD_RESET);
    }

    /**
     * @throws ValidationException
     * @throws AuthenticationException
     */
    public function resetPassword(Request $request, string $token, #[\SensitiveParameter] string $password): void
    {
        if (!$this->tokens->isValid($token, TokenService::PASSWORD_RESET)) {
            throw new AuthenticationException('This password reset link is invalid or has expired.');
        }

        $userId = (int) $this->db->fetchValue('SELECT `user_id` FROM `nx_user_tokens` WHERE `token_hash` = ?', [hash('sha256', $token)]);
        $user = $this->users->find($userId);
        if ($user === null) {
            throw new AuthenticationException('This password reset link is invalid or has expired.');
        }

        $errors = PasswordPolicy::validate($password, [$user->email, (string) $user->firstName, (string) $user->lastName]);
        if ($errors !== []) {
            throw new ValidationException(['password' => $errors]);
        }

        if ($this->tokens->consume($token, TokenService::PASSWORD_RESET) !== $user->id) {
            throw new AuthenticationException('This password reset link is invalid or has expired.');
        }

        $this->setPassword($request, $user, $password, null);
        // A successful reset proves control over the mailbox.
        if ($user->status === User::STATUS_PENDING) {
            $this->users->update($user->id, ['status' => User::STATUS_ACTIVE, 'email_verified_at' => $this->clock->nowString()]);
        }
        $this->limiter->clear('login:account:' . $user->email);
        $this->audit->log($user->id, 'auth.password_reset', 'user', $user->id, [], $request->clientIp());
    }

    /**
     * @throws ValidationException
     */
    public function changePassword(Request $request, User $user, #[\SensitiveParameter] string $current, #[\SensitiveParameter] string $new): void
    {
        $key = 'password-change:' . $user->id;
        if (!$this->limiter->attempt($key, 5, 900)) {
            throw ValidationException::withMessages(['current_password' => 'Too many attempts. Please try again later.']);
        }

        if (!$this->hasher->verify($current, $user->passwordHash)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }

        $this->limiter->clear($key);

        $errors = PasswordPolicy::validate($new, [$user->email, (string) $user->firstName, (string) $user->lastName]);
        if ($errors !== []) {
            throw new ValidationException(['password' => $errors]);
        }

        $this->setPassword($request, $user, $new, $request->session()->id());
        $request->session()->regenerate();
        $this->audit->log($user->id, 'auth.password_changed', 'user', $user->id, [], $request->clientIp());
    }

    private function setPassword(Request $request, User $user, #[\SensitiveParameter] string $password, ?string $keepSessionId): void
    {
        $this->users->update($user->id, [
            'password_hash' => $this->hasher->hash($password),
            'password_changed_at' => $this->clock->nowString(),
        ]);
        $this->tokens->invalidate($user->id, TokenService::PASSWORD_RESET);
        $this->sessions->destroyForUser($user->id, $keepSessionId);
        // A password change or reset is how users recover a compromised account: tokens go too.
        $this->apiTokens->revokeAllForUser($user->id, $user->id, $request->clientIp());

        $this->mailer->queue($user->email, $user->displayName(), 'password_changed', [
            'name' => $user->displayName(),
            'ip' => $request->clientIp(),
            'time' => $this->clock->now()->format('Y-m-d H:i') . ' UTC',
        ]);
    }

    /** Confirms the current password before sensitive account changes. */
    public function confirmPassword(User $user, #[\SensitiveParameter] string $password): bool
    {
        $key = 'password-confirm:' . $user->id;
        if (!$this->limiter->attempt($key, 5, 900)) {
            return false;
        }
        if ($this->hasher->verify($password, $user->passwordHash)) {
            $this->limiter->clear($key);

            return true;
        }

        return false;
    }

    private function url(string $path): string
    {
        return rtrim($this->config->string('app.url'), '/') . $path;
    }
}
