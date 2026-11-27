<?php

declare(strict_types=1);

namespace Nebrix\Controller\Auth;

use Nebrix\Auth\AuthenticationException;
use Nebrix\Auth\AuthService;
use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Authorization\Permission;
use Nebrix\Http\Controller;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Security\RedirectGuard;

final class LoginController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Gate $gate,
    ) {
    }

    public function home(Request $request): Response
    {
        return $this->redirect($request->attribute('user') instanceof User ? '/dashboard' : '/login');
    }

    public function show(Request $request): Response
    {
        return $this->render($request, 'auth/login.twig', [
            'show_resend' => $request->session()->getFlash('show_resend') === true,
        ]);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|string|max:254', 'password' => 'required|string|max:1024']);

        try {
            $result = $this->auth->attempt($request, (string) $data['email'], (string) $data['password']);
        } catch (AuthenticationException $exception) {
            $request->session()->flash('_old', ['email' => mb_substr((string) $data['email'], 0, 254)]);
            $this->flash($request, 'error', $exception->getMessage());
            if ($exception->reason === AuthenticationException::EMAIL_UNVERIFIED) {
                $request->session()->flash('show_resend', true);
            }

            return $this->redirect('/login');
        }

        if ($result === 'two_factor_required') {
            return $this->redirect('/two-factor');
        }

        return $this->redirectAfterLogin($request);
    }

    public function showTwoFactor(Request $request): Response
    {
        if ($this->auth->pendingTwoFactorUser($request->session()) === null) {
            return $this->redirect('/login');
        }

        return $this->render($request, 'auth/two_factor.twig');
    }

    public function twoFactor(Request $request): Response
    {
        $data = $this->validate($request, ['code' => 'required|string|max:32']);

        try {
            $this->auth->completeTwoFactor($request, (string) $data['code']);
        } catch (AuthenticationException $exception) {
            $this->flash($request, 'error', $exception->getMessage());

            return $this->redirect($this->auth->pendingTwoFactorUser($request->session()) !== null ? '/two-factor' : '/login');
        }

        return $this->redirectAfterLogin($request);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($request);
        $this->flash($request, 'success', 'You have been signed out.');

        return $this->redirect('/login');
    }

    private function redirectAfterLogin(Request $request): Response
    {
        $session = $request->session();
        $user = $session->userId() !== null ? $this->container->get(\Nebrix\Repository\UserRepository::class)->find($session->userId()) : null;
        $default = $user !== null && $this->gate->allows($user, Permission::ADMIN_ACCESS) ? '/admin' : '/dashboard';
        $intended = RedirectGuard::sanitize($session->pull('url.intended'), $default);

        return Response::redirect($intended);
    }
}
