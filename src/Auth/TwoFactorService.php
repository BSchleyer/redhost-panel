<?php

declare(strict_types=1);

namespace Nebrix\Auth;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Repository\UserRepository;
use Nebrix\Security\Encrypter;
use Nebrix\Settings\SettingsRepository;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

/**
 * TOTP (RFC 6238) two-factor authentication with recovery codes.
 *
 * - The shared secret is encrypted at rest.
 * - A code is accepted at most once (the last used time slice is stored).
 * - Recovery codes are random, single use and stored as SHA-256 hashes.
 * - QR codes are rendered locally as SVG; the secret never leaves the server.
 */
class TwoFactorService
{
    private const RECOVERY_CODE_COUNT = 10;

    public function __construct(
        private readonly UserRepository $users,
        private readonly Encrypter $encrypter,
        private readonly SettingsRepository $settings,
        private readonly Clock $clock,
        private readonly Connection $db,
    ) {
    }

    private function engine(): TwoFactorAuth
    {
        $issuer = $this->settings->string('general.company_name') ?: 'Nebrix';

        return new TwoFactorAuth(new BaconQrCodeProvider(4, '#ffffff', '#000000', 'svg'), $issuer, 6, 30);
    }

    public function generateSecret(): string
    {
        return $this->engine()->createSecret(160);
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $uri = $this->engine()->getQRText($user->email, $secret);

        return (new BaconQrCodeProvider(4, '#ffffff', '#000000', 'svg'))->getQRCodeImage($uri, 220);
    }

    public function otpauthUri(User $user, string $secret): string
    {
        return $this->engine()->getQRText($user->email, $secret);
    }

    public function verifySecret(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        return preg_match('/^\d{6}$/', $code) === 1
            && $this->engine()->verifyCode($secret, $code, 1, $this->clock->now()->getTimestamp());
    }

    /**
     * Enables 2FA after the user proved possession of the secret.
     *
     * @return list<string> plaintext recovery codes (shown exactly once)
     */
    public function enable(User $user, string $secret, string $code): ?array
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        $timeslice = 0;
        if (preg_match('/^\d{6}$/', $code) !== 1
            || !$this->engine()->verifyCode($secret, $code, 1, $this->clock->now()->getTimestamp(), $timeslice)) {
            return null;
        }

        $codes = $this->generateRecoveryCodes();
        $this->users->update($user->id, [
            'two_factor_secret' => $this->encrypter->encrypt($secret, 'user:2fa:' . $user->id),
            'two_factor_enabled_at' => $this->clock->nowString(),
            'two_factor_recovery_codes' => json_encode(array_map(self::hashCode(...), $codes), JSON_THROW_ON_ERROR),
            // The confirmation code counts as used and cannot be replayed at the next sign-in.
            'two_factor_last_slice' => $timeslice,
        ]);

        return $codes;
    }

    public function disable(User $user): void
    {
        $this->users->update($user->id, [
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_slice' => null,
        ]);
    }

    /**
     * Verifies a TOTP code or a recovery code for a user with 2FA enabled.
     */
    public function verify(User $user, string $input): bool
    {
        $input = trim($input);
        if (!$user->hasTwoFactor()) {
            return false;
        }

        $code = preg_replace('/\s+/', '', $input) ?? '';
        if (preg_match('/^\d{6}$/', $code) === 1) {
            return $this->verifyTotp($user, $code);
        }

        return $this->useRecoveryCode($user, $input);
    }

    private function verifyTotp(User $user, string $code): bool
    {
        $encrypted = $user->attribute('two_factor_secret');
        if (!is_string($encrypted) || $encrypted === '') {
            return false;
        }

        $secret = $this->encrypter->decrypt($encrypted, 'user:2fa:' . $user->id);
        $timeslice = 0;
        if (!$this->engine()->verifyCode($secret, $code, 1, $this->clock->now()->getTimestamp(), $timeslice)) {
            return false;
        }

        // Accept the time slice only if it is newer than the last accepted
        // one, decided by a single conditional UPDATE: two parallel requests
        // with the same code cannot both succeed (replay protection).
        $accepted = $this->db->run(
            'UPDATE `nx_users` SET `two_factor_last_slice` = ? WHERE `id` = ? AND (`two_factor_last_slice` IS NULL OR `two_factor_last_slice` < ?)',
            [$timeslice, $user->id, $timeslice],
        )->rowCount();

        return $accepted === 1;
    }

    private function useRecoveryCode(User $user, string $input): bool
    {
        $hash = self::hashCode($input);

        // Re-read under a row lock so that a code can be consumed only once,
        // even by parallel requests.
        return $this->db->transaction(function () use ($user, $hash): bool {
            $raw = $this->db->fetchValue('SELECT `two_factor_recovery_codes` FROM `nx_users` WHERE `id` = ? FOR UPDATE', [$user->id]);
            $stored = json_decode(is_string($raw) ? $raw : '[]', true);
            if (!is_array($stored) || $stored === []) {
                return false;
            }

            foreach ($stored as $index => $candidate) {
                if (is_string($candidate) && hash_equals($candidate, $hash)) {
                    unset($stored[$index]);
                    $this->users->update($user->id, ['two_factor_recovery_codes' => json_encode(array_values($stored), JSON_THROW_ON_ERROR)]);

                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();
        $this->users->update($user->id, [
            'two_factor_recovery_codes' => json_encode(array_map(self::hashCode(...), $codes), JSON_THROW_ON_ERROR),
        ]);

        return $codes;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        $stored = json_decode((string) ($user->attribute('two_factor_recovery_codes') ?? '[]'), true);

        return is_array($stored) ? count($stored) : 0;
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(5)));
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
        }

        return $codes;
    }

    private static function hashCode(string $code): string
    {
        return hash('sha256', strtoupper(str_replace(' ', '', trim($code))));
    }
}
