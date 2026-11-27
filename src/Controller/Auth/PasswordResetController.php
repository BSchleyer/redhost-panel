<?php

declare(strict_types=1);

namespace Nebrix\Controller\Auth;

use Nebrix\Auth\AuthenticationException;
use Nebrix\Auth\AuthService;
use Nebrix\Http\Controller;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Http\SecurityHeaders;
use Nebrix\Security\Captcha;

final class PasswordResetController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Captcha $captcha,
    ) {
    }

    public function showRequest(Request $request): Response
    {
        $response = $this->render($request, 'auth/forgot_password.twig', ['captcha' => $this->captcha->widget()]);
        if ($this->captcha->enabled()) {
            $response->setHeader('Content-Security-Policy', SecurityHeaders::contentSecurityPolicy($this->captcha->cspSources()));
        }

        return $response;
    }

    public function sendLink(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email|max:254']);
        $this->captcha->verify($request);
        $this->auth->requestPasswordReset($request, (string) $data['email']);

        // Identical response whether or not the account exists.
        $this->flash($request, 'success', 'If an account exists for this address, we have sent a link to reset the password.');

        return $this->redirect('/password/forgot');
    }

    public function showReset(Request $request, string $token): Response
    {
        if (!$this->auth->isResetTokenValid($token)) {
            $this->flash($request, 'error', 'This password reset link is invalid or has expired.');

            return $this->redirect('/password/forgot');
        }

        $response = $this->render($request, 'auth/reset_password.twig', ['token' => $token]);
        // Keep the token out of Referer headers sent to third parties.
        $response->setHeader('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function reset(Request $request): Response
    {
        $data = $this->validate($request, [
            'token' => 'required|regex:/^[a-f0-9]{64}$/',
            'password' => 'required|string|min:10|max:256|confirmed',
        ]);

        try {
            $this->auth->resetPassword($request, (string) $data['token'], (string) $data['password']);
        } catch (AuthenticationException $exception) {
            $this->flash($request, 'error', $exception->getMessage());

            return $this->redirect('/password/forgot');
        }

        $this->flash($request, 'success', 'Your password has been changed. Please sign in with your new password.');

        return $this->redirect('/login');
    }
}
