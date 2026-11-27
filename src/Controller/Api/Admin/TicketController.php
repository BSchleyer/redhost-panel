<?php

declare(strict_types=1);

namespace Nebrix\Controller\Api\Admin;

use Nebrix\Api\ApiException;
use Nebrix\Api\Resource;
use Nebrix\Controller\Api\ApiController;
use Nebrix\Core\Config;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Mail\Mailer;
use Nebrix\Repository\UserRepository;
use Nebrix\Support\TicketRepository;

/**
 * Admin access to support tickets: list, read and reply (the customer is
 * notified by e-mail like for replies in the admin interface).
 */
final class TicketController extends ApiController
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly UserRepository $users,
        private readonly Mailer $mailer,
        private readonly Config $config,
    ) {
    }

    public function index(Request $request): Response
    {
        [$page, $perPage] = $this->pagination($request);
        $filters = ['status' => $this->stringQuery($request, 'status', 20), 'search' => $this->stringQuery($request, 'search')];
        foreach (['user_id' => 'user_id', 'assigned_to' => 'assigned'] as $query => $filter) {
            $value = $this->intQuery($request, $query);
            if ($value !== null) {
                $filters[$filter] = $value;
            }
        }
        $result = $this->tickets->paginate($filters, $page, $perPage);

        return $this->paginated(array_map(static fn (array $ticket): array => Resource::ticket($ticket, null, true), $result['items']), $result['total'], $page, $perPage);
    }

    public function show(Request $request, int $id): Response
    {
        $ticket = $this->tickets->find($id) ?? throw ApiException::notFound();

        return $this->data(Resource::ticket($ticket, $this->tickets->messages($id), true));
    }

    public function reply(Request $request, int $id): Response
    {
        $ticket = $this->tickets->find($id) ?? throw ApiException::notFound();
        $data = $this->validateInput($request, ['message' => 'required|string|min:2|max:20000']);

        $this->tickets->addMessage($id, $this->user($request)->id, true, (string) $data['message']);
        $this->apiAudit($request, 'ticket.replied', 'ticket', $id);

        $customer = $this->users->find((int) $ticket['user_id']);
        if ($customer !== null) {
            $this->mailer->queue($customer->email, $customer->displayName(), 'ticket_reply', [
                'name' => $customer->displayName(),
                'ticket_subject' => (string) $ticket['subject'],
                'ticket_url' => rtrim($this->config->string('app.url'), '/') . '/tickets/' . $id,
            ]);
        }

        $updated = $this->tickets->find($id) ?? $ticket;

        return $this->data(Resource::ticket($updated, $this->tickets->messages($id), true), 201);
    }
}
