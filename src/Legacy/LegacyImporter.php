<?php

declare(strict_types=1);

namespace Nebrix\Legacy;

use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Billing\CreditService;
use Nebrix\Billing\Money;
use Nebrix\Database\Connection;
use Nebrix\Repository\RoleRepository;
use Nebrix\Repository\UserRepository;
use Throwable;

/**
 * Imports customer accounts from a RED-Host database.
 *
 * - Reads the legacy `users` table only (never writes to legacy tables).
 * - Idempotent: users are matched by legacy_id; existing imports are skipped.
 * - Legacy bcrypt hashes keep working and are upgraded to Argon2id at the
 *   next successful login.
 * - Legacy administrators become "admin" (never super administrator) and
 *   legacy staff accounts are imported suspended: the legacy database
 *   credentials were exposed (AUDIT C-01), so a super administrator must
 *   review and reactivate each staff account.
 * - The prepaid balance is booked through the credit ledger.
 * Services are not imported automatically because they must be mapped to
 * the new integrations; see UPGRADE_GUIDE.md.
 */
class LegacyImporter
{
    private const COUNTRIES = [
        'deutschland' => 'DE', 'germany' => 'DE', 'österreich' => 'AT', 'oesterreich' => 'AT', 'austria' => 'AT',
        'schweiz' => 'CH', 'switzerland' => 'CH', 'niederlande' => 'NL', 'netherlands' => 'NL', 'belgien' => 'BE',
        'frankreich' => 'FR', 'france' => 'FR', 'polen' => 'PL', 'poland' => 'PL', 'luxemburg' => 'LU',
    ];

    public function __construct(
        private readonly Connection $target,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly CreditService $credit,
    ) {
    }

    /**
     * @return array{imported: int, skipped: int, conflicts: list<string>, errors: list<string>}
     */
    public function importUsers(Connection $source, bool $dryRun = false): array
    {
        $report = ['imported' => 0, 'skipped' => 0, 'conflicts' => [], 'errors' => []];
        $roleIds = [
            'customer' => $this->roles->idForSlug(Role::CUSTOMER),
            'support' => $this->roles->idForSlug(Role::SUPPORT),
            'admin' => $this->roles->idForSlug(Role::ADMIN),
        ];

        foreach ($source->fetchAll('SELECT * FROM users ORDER BY id') as $row) {
            $legacyId = (int) $row['id'];
            if ((int) $this->target->fetchValue('SELECT COUNT(*) FROM `nx_users` WHERE `legacy_id` = ?', [$legacyId]) > 0) {
                $report['skipped']++;
                continue;
            }

            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $report['conflicts'][] = sprintf('legacy #%d: invalid e-mail address', $legacyId);
                continue;
            }
            if ($this->users->emailExists($email)) {
                $report['conflicts'][] = sprintf('legacy #%d: e-mail already used by an existing account', $legacyId);
                continue;
            }

            $hash = (string) ($row['password'] ?? '');
            if (!str_starts_with($hash, '$2y$') && !str_starts_with($hash, '$argon2')) {
                $report['conflicts'][] = sprintf('legacy #%d: unsupported password hash format', $legacyId);
                continue;
            }

            $username = trim((string) ($row['username'] ?? ''));
            if ($username === '' || preg_match('/^[A-Za-z0-9._-]{1,64}$/', $username) !== 1 || $this->users->usernameExists($username)) {
                $username = null;
            }

            $created = (string) ($row['created_at'] ?? '') ?: null;
            $status = match ((string) ($row['state'] ?? 'active')) {
                'pending' => User::STATUS_PENDING,
                'banned' => User::STATUS_SUSPENDED,
                default => User::STATUS_ACTIVE,
            };
            $legacyRole = (string) ($row['role'] ?? 'customer');
            if ($legacyRole !== 'customer' && isset($roleIds[$legacyRole])) {
                $status = User::STATUS_SUSPENDED;
                $report['conflicts'][] = sprintf('legacy #%d: staff account (%s) imported suspended – review and reactivate it in Admin → Staff', $legacyId, $legacyRole);
            }
            $street = trim(trim((string) ($row['street'] ?? '')) . ' ' . trim((string) ($row['number'] ?? '')));
            $balance = $this->balance($row['amount'] ?? '0');

            if ($dryRun) {
                $report['imported']++;
                continue;
            }

            try {
                $this->target->transaction(function () use ($row, $roleIds, $email, $username, $hash, $status, $created, $street, $legacyId, $balance): void {
                    $id = $this->users->create([
                        'role_id' => $roleIds[(string) ($row['role'] ?? 'customer')] ?? $roleIds['customer'],
                        'email' => $email,
                        'username' => $username,
                        'password_hash' => $hash,
                        'status' => $status,
                        'email_verified_at' => $status !== User::STATUS_PENDING ? ($created ?? gmdate('Y-m-d H:i:s')) : null,
                        'first_name' => $this->text($row['firstname'] ?? null, 100),
                        'last_name' => $this->text($row['lastname'] ?? null, 100),
                        'address_line1' => $this->text($street, 255),
                        'postcode' => $this->text($row['postcode'] ?? null, 20),
                        'city' => $this->text($row['city'] ?? null, 100),
                        'country' => $this->country((string) ($row['country'] ?? '')),
                        'legal_accepted_at' => (int) ($row['legal_accepted'] ?? 0) === 1 ? ($created ?? gmdate('Y-m-d H:i:s')) : null,
                        'legacy_id' => $legacyId,
                    ]);

                    if ($balance > 0) {
                        $this->credit->adjust($id, $balance, 'Balance imported from RED-Host', 'legacy_user', (string) $legacyId);
                    }
                });
                $report['imported']++;
            } catch (Throwable $exception) {
                $report['errors'][] = sprintf('legacy #%d: %s', $legacyId, $exception->getMessage());
            }
        }

        return $report;
    }

    private function balance(mixed $amount): int
    {
        try {
            return max(0, Money::parse(number_format((float) $amount, 2, '.', '')));
        } catch (\InvalidArgumentException) {
            return 0;
        }
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function country(string $value): ?string
    {
        $value = mb_strtolower(trim($value));
        if (preg_match('/^[a-z]{2}$/', $value) === 1) {
            return strtoupper($value);
        }

        return self::COUNTRIES[$value] ?? null;
    }
}
