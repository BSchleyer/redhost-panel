<?php

declare(strict_types=1);

namespace Nebrix\Controller\Api;

use Nebrix\Api\ApiException;
use Nebrix\Api\Resource;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Security\ValidationException;
use Nebrix\Service\ServiceRepository;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Support\TicketRepository;

/**
 * The customer's support tickets (same limits as the web interface).
 */
final class TicketController extends ApiController
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly ServiceRepository $services,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->ensureEnabled();
        [$page, $perPage] = $this->pagination($request);
        $result = $this->tickets->paginate(['user_id' => $this->user($request)->id, 'status' => $this->stringQuery($request, 'status', 20)], $page, $perPage);

        return $this->paginated(array_map(static fn (array $ticket): array => Resource::ticket($ticket), $result['items']), $result['total'], $page, $perPage);
    }

    public function show(Request $request, int $id): Response
    {
        $this->ensureEnabled();
        $ticket = $this->find($request, $id);

        return $this->data(Resource::ticket($ticket, $this->tickets->messages($id)));
    }

    public function store(Request $request): Response
    {
        $this->ensureEnabled();
        $user = $this->user($request);
        $data = $this->validateInput($request, [
            'subject' => 'required|string|min:3|max:200',
            'message' => 'required|string|min:10|max:20000',
            'service_id' => 'nullable|int|min:1',
            'priority' => 'nullable|in:low,normal,high',
        ]);
        if ($data['service_id'] !== null && $this->services->findForUser((int) $data['service_id'], $user->id) === null) {
            throw ValidationException::withMessages(['service_id' => 'Please choose one of your services.']);
        }
        $this->throttle('ticket-create:' . $user->id, 5, 3600, 'You have opened several tickets in a short time. Please wait before opening another one.');

        $id = $this->tickets->create($user->id, (string) $data['subject'], (string) $data['message'], $data['service_id'] !== null ? (int) $data['service_id'] : null, (string) ($data['priority'] ?? 'normal'));
        $ticket = $this->tickets->findForUser($id, $user->id) ?? throw new \RuntimeException('Ticket not found after creation.');

        return $this->data(Resource::ticket($ticket, $this->tickets->messages($id)), 201);
    }

    public function reply(Request $request, int $id): Response
    {
        $this->ensureEnabled();
        $user = $this->user($request);
        $ticket = $this->find($request, $id);
        $data = $this->validateInput($request, ['message' => 'required|string|min:2|max:20000']);
        $this->throttle('ticket-reply:' . $user->id, 30, 3600);

        $this->tickets->addMessage((int) $ticket['id'], $user->id, false, (string) $data['message']);

        return $this->data(Resource::ticket($this->find($request, $id), $this->tickets->messages($id)), 201);
    }

    public function close(Request $request, int $id): Response
    {
        $this->ensureEnabled();
        $this->find($request, $id);
        $this->tickets->setStatus($id, 'closed');

        return $this->data(Resource::ticket($this->find($request, $id)));
    }

    /**
     * @return array<string, mixed>
     */
    private function find(Request $request, int $id): array
    {
        return $this->tickets->findForUser($id, $this->user($request)->id) ?? throw ApiException::notFound();
    }

    private function ensureEnabled(): void
    {
        if (!$this->settings->bool('system.tickets_enabled')) {
            throw new ApiException(404, 'tickets_disabled', 'Support tickets are not enabled.');
        }
    }
}
