<?php

declare(strict_types=1);

namespace Nebrix\Http;

use Nebrix\Audit\AuditLogger;
use Nebrix\Auth\User;
use Nebrix\Authorization\Gate;
use Nebrix\Core\Container;
use Nebrix\Security\RedirectGuard;
use Nebrix\Security\Validator;
use Nebrix\View\View;

/**
 * Base controller with response, validation and authorization helpers.
 */
abstract class Controller
{
    protected Container $container;

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function render(Request $request, string $template, array $context = [], int $status = 200): Response
    {
        return $this->container->get(View::class)->response($template, $context, $status, $request);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(RedirectGuard::sanitize($path));
    }

    protected function flash(Request $request, string $type, string $message): void
    {
        $request->session()->flash($type, $message);
    }

    /**
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $labels
     * @return array<string, mixed>
     */
    protected function validate(Request $request, array $rules, array $labels = []): array
    {
        return Validator::validate($request->all(), $rules, $labels);
    }

    protected function user(Request $request): User
    {
        $user = $request->attribute('user');
        if (!$user instanceof User) {
            throw new HttpException(401, 'Please sign in to continue.');
        }

        return $user;
    }

    protected function authorize(Request $request, string $permission): void
    {
        $this->container->get(Gate::class)->authorize($request->attribute('user'), $permission);
    }

    protected function can(Request $request, string $permission): bool
    {
        $user = $request->attribute('user');

        return $user instanceof User && $this->container->get(Gate::class)->allows($user, $permission);
    }

    /**
     * @param array<string, mixed> $details
     */
    protected function audit(Request $request, string $action, ?string $targetType = null, int|string|null $targetId = null, array $details = []): void
    {
        $user = $request->attribute('user');
        $this->container->get(AuditLogger::class)->log(
            $user instanceof User ? $user->id : null,
            $action,
            $targetType,
            $targetId,
            $details,
            $request->clientIp(),
        );
    }

    protected function page(Request $request): int
    {
        $page = $request->query('page', '1');

        return is_string($page) && ctype_digit($page) && (int) $page > 0 ? min((int) $page, 100000) : 1;
    }
}
