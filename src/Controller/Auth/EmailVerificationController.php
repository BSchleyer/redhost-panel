<?php

declare(strict_types=1);

namespace Nebrix\Controller\Auth;

use Nebrix\Auth\AuthService;
use Nebrix\Auth\User;
use Nebrix\Http\Controller;
use Nebrix\Http\Request;
use Nebrix\Http\Response;

final class EmailVerificationController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function notice(Request $request): Response
    {
        $user = $this->user($request);
        if ($user->isEmailVerified()) {
            return $this->redirect('/dashboard');
        }

        return $this->render($request, 'auth/verify_notice.twig');
    }

    public function resend(Request $request): Response
    {
        $sent = $this->auth->resendVerification($request, $this->user($request));
        $this->flash($request, $sent ? 'success' : 'info', $sent
            ? 'A new confirmation link has been sent.'
            : 'Please wait a while before requesting another confirmation e-mail.');

        return $this->redirect('/email/verify');
    }

    public function resendGuest(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email|max:254']);
        $this->auth->resendVerificationByEmail($request, (string) $data['email']);
        $this->flash($request, 'success', 'If a pending account exists for this address, a new confirmation link has been sent.');

        return $this->redirect('/login');
    }

    public function verify(Request $request, string $token): Response
    {
        $user = $this->auth->verifyEmail($token);
        if ($user === null) {
            $this->flash($request, 'error', 'This confirmation link is invalid or has expired.');

            return $this->redirect($request->attribute('user') instanceof User ? '/email/verify' : '/login');
        }

        $this->flash($request, 'success', 'Your e-mail address has been confirmed.');

        return $this->redirect($request->attribute('user') instanceof User ? '/dashboard' : '/login');
    }
}
