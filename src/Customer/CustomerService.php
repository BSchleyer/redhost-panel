<?php

declare(strict_types=1);

namespace Nebrix\Customer;

use Nebrix\Audit\AuditLogger;
use Nebrix\Auth\Role;
use Nebrix\Auth\TokenService;
use Nebrix\Auth\TwoFactorService;
use Nebrix\Auth\User;
use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Mail\Mailer;
use Nebrix\Repository\UserRepository;
use Nebrix\Security\PasswordHasher;
use Nebrix\Session\SessionStore;
use RuntimeException;

/**
 * Administrative account operations for customers and staff.
 */
class CustomerService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenService $tokens,
        private readonly Mailer $mailer,
        private readonly SessionStore $sessions,
        private readonly TwoFactorService $twoFactor,
        private readonly AuditLogger $audit,
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly Config $config,
        private readonly \Nebrix\Api\ApiTokenService $apiTokens,
    ) {
    }

    /**
     * Creates an account without a usable password and e-mails a link to set
     * one. Administrators never see or choose user passwords.
     *
     * @param array<string, mixed> $profile validated profile fields
     */
    public function createAccount(string $email, int $roleId, array $profile, int $actorId, ?string $ip): int
    {
        $now = $this->clock->nowString();
        $id = $this->users->create($profile + [
            'role_id' => $roleId,
            'email' => $email,
            'password_hash' => $this->hasher->hash(bin2hex(random_bytes(32))),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => $now,
            'password_changed_at' => null,
        ]);

        $user = $this->users->find($id);
        if ($user === null) {
            throw new RuntimeException('Account could not be created.');
        }

        $this->sendPasswordSetupLink($user);
        $this->audit->log($actorId, $user->isStaff() ? 'staff.created' : 'customer.created', 'user', $id, ['role' => $user->roleSlug], $ip);

        return $id;
    }

    /** E-mails a password reset link (valid 24 hours) to the account owner. */
    public function sendPasswordSetupLink(User $user): void
    {
        $token = $this->tokens->create($user->id, TokenService::PASSWORD_RESET, 1440);
        $this->mailer->queue($user->email, $user->displayName(), 'password_reset', [
            'name' => $user->displayName(),
            'reset_url' => rtrim($this->config->string('app.url'), '/') . '/password/reset/' . $token,
            'expires_minutes' => 1440,
            'ip' => 'administrator',
        ]);
    }

    public function setStatus(User $user, string $status, ?string $reason, int $actorId, ?string $ip): void
    {
        if (!in_array($status, [User::STATUS_ACTIVE, User::STATUS_SUSPENDED], true)) {
            throw new RuntimeException('Unsupported status.');
        }
        if ($user->status === User::STATUS_CLOSED) {
            throw new RuntimeException('Closed accounts cannot be reactivated.');
        }

        $this->users->update($user->id, ['status' => $status]);
        if ($status === User::STATUS_SUSPENDED) {
            $this->sessions->destroyForUser($user->id);
        }

        $this->audit->log($actorId, $status === User::STATUS_SUSPENDED ? 'account.suspended' : 'account.activated', 'user', $user->id, ['reason' => $reason], $ip);
    }

    public function resetTwoFactor(User $user, int $actorId, ?string $ip): void
    {
        $this->twoFactor->disable($user);
        $this->sessions->destroyForUser($user->id);
        $this->apiTokens->revokeAllForUser($user->id, $actorId, $ip);
        $this->audit->log($actorId, 'account.two_factor_reset', 'user', $user->id, [], $ip);
    }

    public function revokeSessions(User $user, int $actorId, ?string $ip): int
    {
        $count = $this->sessions->destroyForUser($user->id);
        $tokens = $this->apiTokens->revokeAllForUser($user->id, $actorId, $ip);
        $this->audit->log($actorId, 'account.sessions_revoked', 'user', $user->id, ['count' => $count, 'api_tokens' => $tokens], $ip);

        return $count;
    }

    /**
     * Closes an account and removes personal data (GDPR erasure).
     *
     * Orders, invoices and transactions are kept because of statutory
     * retention obligations; invoices keep their billing address snapshot.
     * Accounts with services that are still running must be terminated first.
     */
    public function closeAndAnonymize(User $user, int $actorId, ?string $ip): void
    {
        if ($user->roleSlug === Role::SUPER_ADMIN) {
            throw new RuntimeException('Super administrator accounts cannot be closed here.');
        }

        $running = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM `nx_services` WHERE `user_id` = ? AND `status` IN ('pending', 'provisioning', 'active', 'suspended')",
            [$user->id],
        );
        if ($running > 0) {
            throw new RuntimeException(sprintf('The account still has %d service(s). Terminate or cancel them first.', $running));
        }

        $this->db->transaction(function () use ($user): void {
            $this->users->update($user->id, [
                'status' => User::STATUS_CLOSED,
                'email' => sprintf('closed-%d-%s@invalid.invalid', $user->id, bin2hex(random_bytes(4))),
                'username' => null,
                'password_hash' => $this->hasher->hash(bin2hex(random_bytes(32))),
                'first_name' => null,
                'last_name' => null,
                'company' => null,
                'vat_id' => null,
                'address_line1' => null,
                'address_line2' => null,
                'postcode' => null,
                'city' => null,
                'phone' => null,
                'notes' => null,
                'two_factor_secret' => null,
                'two_factor_enabled_at' => null,
                'two_factor_recovery_codes' => null,
            ]);
            $this->db->run('DELETE FROM `nx_user_tokens` WHERE `user_id` = ?', [$user->id]);
            $this->db->run('UPDATE `nx_api_tokens` SET `revoked_at` = COALESCE(`revoked_at`, ?) WHERE `user_id` = ?', [$this->clock->nowString(), $user->id]);
            $this->db->run("UPDATE `nx_api_tokens` SET `name` = 'closed account', `allowed_ips` = NULL, `created_ip` = NULL, `last_used_ip` = NULL WHERE `user_id` = ?", [$user->id]);
            $this->db->run('UPDATE `nx_login_history` SET `identifier` = NULL, `ip` = NULL, `user_agent` = NULL WHERE `user_id` = ?', [$user->id]);
            $this->sessions->destroyForUser($user->id);
        });

        $this->audit->log($actorId, 'account.closed', 'user', $user->id, [], $ip);
    }
}
