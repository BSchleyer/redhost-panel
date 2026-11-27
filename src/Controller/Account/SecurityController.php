<?php

declare(strict_types=1);

namespace Nebrix\Controller\Account;

use Nebrix\Auth\AuthService;
use Nebrix\Auth\LoginHistory;
use Nebrix\Auth\TwoFactorService;
use Nebrix\Http\Controller;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Security\Encrypter;
use Nebrix\Security\ValidationException;
use Nebrix\Session\SessionStore;

/**
 * Account security: password, two-factor authentication, sessions.
 */
final class SecurityController extends Controller
{
    private const SETUP_SECRET = 'two_factor.setup_secret';
    private const NEW_RECOVERY_CODES = 'two_factor.new_codes';

    public function __construct(
        private readonly AuthService $auth,
        private readonly TwoFactorService $twoFactor,
        private readonly SessionStore $sessions,
        private readonly LoginHistory $history,
        private readonly Encrypter $encrypter,
    ) {
    }

    public function show(Request $request): Response
    {
        $user = $this->user($request);
        $currentHash = SessionStore::hashId($request->session()->id());

        $sessions = array_map(static function (array $row) use ($currentHash): array {
            $row['is_current'] = $row['id'] === $currentHash;
            unset($row['id']);

            return $row;
        }, $this->sessions->listForUser($user->id));

        $codes = $request->session()->pull(self::NEW_RECOVERY_CODES);

        return $this->render($request, 'account/security.twig', [
            'sessions' => $sessions,
            'logins' => $this->history->forUser($user->id, 10),
            'recovery_remaining' => $this->twoFactor->remainingRecoveryCodes($user),
            'new_recovery_codes' => is_array($codes) ? $codes : [],
        ]);
    }

    public function changePassword(Request $request): Response
    {
        $data = $this->validate($request, [
            'current_password' => 'required|string|max:1024',
            'password' => 'required|string|min:10|max:256|confirmed',
        ]);

        $this->auth->changePassword($request, $this->user($request), (string) $data['current_password'], (string) $data['password']);
        $this->flash($request, 'success', 'Your password has been changed. All other sessions were signed out.');

        return $this->redirect('/account/security');
    }

    public function setupTwoFactor(Request $request): Response
    {
        $user = $this->user($request);
        if ($user->hasTwoFactor()) {
            return $this->redirect('/account/security');
        }

        $session = $request->session();
        $stored = $session->get(self::SETUP_SECRET);
        $secret = is_string($stored) ? $this->encrypter->decrypt($stored, 'session:2fa-setup:' . $user->id) : null;
        if ($secret === null) {
            $secret = $this->twoFactor->generateSecret();
            $session->put(self::SETUP_SECRET, $this->encrypter->encrypt($secret, 'session:2fa-setup:' . $user->id));
        }

        return $this->render($request, 'account/two_factor_setup.twig', [
            // Rendered as an <img> data URI so no generated markup is injected into the page.
            'qr_data_uri' => 'data:image/svg+xml;base64,' . base64_encode($this->twoFactor->qrCodeSvg($user, $secret)),
            'secret' => chunk_split($secret, 4, ' '),
        ]);
    }

    public function enableTwoFactor(Request $request): Response
    {
        $user = $this->user($request);
        $data = $this->validate($request, ['code' => 'required|string|max:16', 'password' => 'required|string|max:1024']);
        $stored = $request->session()->get(self::SETUP_SECRET);
        if (!is_string($stored) || $user->hasTwoFactor()) {
            return $this->redirect('/account/security');
        }
        // A hijacked session must not be able to lock the owner out by
        // binding the account to the attacker's authenticator.
        if (!$this->auth->confirmPassword($user, (string) $data['password'])) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        $secret = $this->encrypter->decrypt($stored, 'session:2fa-setup:' . $user->id);
        $codes = $this->twoFactor->enable($user, $secret, (string) $data['code']);
        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'The code is invalid. Check the time on your device and try again.']);
        }

        $request->session()->forget(self::SETUP_SECRET);
        $request->session()->put(self::NEW_RECOVERY_CODES, $codes);
        $request->session()->regenerate();
        $this->audit($request, 'auth.two_factor_enabled', 'user', $user->id);
        $this->flash($request, 'success', 'Two-factor authentication is now enabled. Store your recovery codes in a safe place.');

        return $this->redirect('/account/security');
    }

    public function disableTwoFactor(Request $request): Response
    {
        $user = $this->user($request);
        $data = $this->validate($request, ['password' => 'required|string|max:1024', 'code' => 'required|string|max:32']);

        if (!$this->auth->confirmPassword($user, (string) $data['password'])) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }
        if (!$this->twoFactor->verify($user, (string) $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The authentication code is invalid.']);
        }

        $this->twoFactor->disable($user);
        $this->audit($request, 'auth.two_factor_disabled', 'user', $user->id);
        $this->flash($request, 'success', 'Two-factor authentication has been disabled.');

        return $this->redirect('/account/security');
    }

    public function regenerateRecoveryCodes(Request $request): Response
    {
        $user = $this->user($request);
        $data = $this->validate($request, ['password' => 'required|string|max:1024']);
        if (!$user->hasTwoFactor()) {
            return $this->redirect('/account/security');
        }
        if (!$this->auth->confirmPassword($user, (string) $data['password'])) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        $request->session()->put(self::NEW_RECOVERY_CODES, $this->twoFactor->regenerateRecoveryCodes($user));
        $this->audit($request, 'auth.recovery_codes_regenerated', 'user', $user->id);
        $this->flash($request, 'success', 'New recovery codes have been generated. The old codes are no longer valid.');

        return $this->redirect('/account/security');
    }

    public function logoutOtherSessions(Request $request): Response
    {
        $user = $this->user($request);
        $data = $this->validate($request, ['password' => 'required|string|max:1024']);
        if (!$this->auth->confirmPassword($user, (string) $data['password'])) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        $count = $this->sessions->destroyForUser($user->id, $request->session()->id());
        $this->audit($request, 'auth.other_sessions_terminated', 'user', $user->id, ['count' => $count]);
        $this->flash($request, 'success', 'All other sessions have been signed out.');

        return $this->redirect('/account/security');
    }
}
