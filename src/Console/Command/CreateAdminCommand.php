<?php

declare(strict_types=1);

namespace Nebrix\Console\Command;

use Nebrix\Audit\AuditLogger;
use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Console\Command;
use Nebrix\Console\Input;
use Nebrix\Console\Output;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Repository\UserRepository;
use Nebrix\Security\PasswordHasher;
use Nebrix\Security\PasswordPolicy;

/**
 * Creates a staff account from the command line (initial installation or
 * recovery). The password is read interactively, or from the environment
 * variable NEBRIX_ADMIN_PASSWORD for automated installs, never from argv
 * (which would leak into shell history and process listings).
 */
final class CreateAdminCommand implements Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly AuditLogger $audit,
    ) {
    }

    public static function description(): string
    {
        return 'Create a staff account: --email=… [--role=super_admin|admin|support|finance] [--first-name=…]';
    }

    public function run(Input $input, Output $output): int
    {
        $email = mb_strtolower(trim($input->option('email') ?? $input->ask('E-mail address:')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $output->error('Please provide a valid e-mail address.');

            return 1;
        }
        if ($this->users->emailExists($email)) {
            $output->error('A user with this e-mail address already exists.');

            return 1;
        }

        $roleSlug = $input->option('role', Role::SUPER_ADMIN) ?? Role::SUPER_ADMIN;
        $role = $this->db->fetchOne('SELECT `id`, `is_staff` FROM `nx_roles` WHERE `slug` = ?', [$roleSlug]);
        if ($role === null || (int) $role['is_staff'] !== 1) {
            $output->error('Unknown staff role. Use super_admin, admin, support or finance.');

            return 1;
        }

        $password = getenv('NEBRIX_ADMIN_PASSWORD');
        if (!is_string($password) || $password === '') {
            $password = $input->ask('Password:', true);
            $confirmation = $input->ask('Repeat password:', true);
            if (!hash_equals($password, $confirmation)) {
                $output->error('The passwords do not match.');

                return 1;
            }
        }

        $errors = PasswordPolicy::validate($password, [$email]);
        if ($errors !== []) {
            foreach ($errors as $error) {
                $output->error($error);
            }

            return 1;
        }

        $now = $this->clock->nowString();
        $id = $this->users->create([
            'role_id' => (int) $role['id'],
            'email' => $email,
            'password_hash' => $this->hasher->hash($password),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => $now,
            'first_name' => $input->option('first-name'),
            'last_name' => $input->option('last-name'),
            'password_changed_at' => $now,
        ]);

        $this->audit->log(null, 'staff.created_via_cli', 'user', $id, ['role' => $roleSlug, 'email' => $email]);
        $output->success(sprintf('Created %s account #%d for %s.', $roleSlug, $id, $email));

        return 0;
    }
}
