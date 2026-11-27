<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use Nebrix\Authorization\Permission;
use Nebrix\Core\Config;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Mail\Mailer;
use Nebrix\Repository\UserRepository;
use Nebrix\Support\TicketRepository;

final class TicketController extends Controller
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
        $status = $request->string('status', 'active');
        $filters = [
            'status' => in_array($status, [...TicketRepository::STATUSES, 'active', ''], true) ? $status : 'active',
            'search' => mb_substr($request->string('search'), 0, 100),
        ];
        if ($request->string('mine') === '1') {
            $filters['assigned'] = $this->user($request)->id;
        }
        $page = $this->page($request);

        return $this->render($request, 'admin/tickets/index.twig', ['result' => $this->tickets->paginate($filters, $page), 'filters' => $filters, 'page' => $page, 'mine' => $request->string('mine') === '1']);
    }

    public function show(Request $request, int $id): Response
    {
        $ticket = $this->tickets->find($id) ?? throw HttpException::notFound();
        $staff = [];
        foreach ($this->users->paginate(['staff' => true, 'status' => 'active'], 1, 200)['items'] as $member) {
            $staff[(int) $member['id']] = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?: (string) $member['email'];
        }

        return $this->render($request, 'admin/tickets/show.twig', ['ticket' => $ticket, 'messages' => $this->tickets->messages($id), 'staff' => $staff]);
    }

    public function reply(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::TICKETS_REPLY);
        $ticket = $this->tickets->find($id) ?? throw HttpException::notFound();
        $data = $this->validate($request, ['message' => 'required|string|min:2|max:20000']);

        $this->tickets->addMessage($id, $this->user($request)->id, true, (string) $data['message']);
        $this->audit($request, 'ticket.replied', 'ticket', $id);

        $customer = $this->users->find((int) $ticket['user_id']);
        if ($customer !== null) {
            $this->mailer->queue($customer->email, $customer->displayName(), 'ticket_reply', [
                'name' => $customer->displayName(),
                'ticket_subject' => (string) $ticket['subject'],
                'ticket_url' => rtrim($this->config->string('app.url'), '/') . '/tickets/' . $id,
            ]);
        }
        $this->flash($request, 'success', 'Reply sent.');

        return $this->redirect('/admin/tickets/' . $id);
    }

    public function update(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::TICKETS_MANAGE);
        $this->tickets->find($id) ?? throw HttpException::notFound();
        $data = $this->validate($request, [
            'status' => 'required|in:' . implode(',', TicketRepository::STATUSES),
            'priority' => 'required|in:' . implode(',', TicketRepository::PRIORITIES),
            'assigned_to' => 'nullable|int|min:1',
        ]);

        if ($data['assigned_to'] !== null) {
            $assignee = $this->users->find((int) $data['assigned_to']);
            if ($assignee === null || !$assignee->isStaff()) {
                throw HttpException::badRequest();
            }
        }

        $this->tickets->setStatus($id, (string) $data['status']);
        $this->tickets->setPriority($id, (string) $data['priority']);
        $this->tickets->assign($id, $data['assigned_to'] !== null ? (int) $data['assigned_to'] : null);
        $this->audit($request, 'ticket.updated', 'ticket', $id, ['status' => $data['status'], 'priority' => $data['priority'], 'assigned_to' => $data['assigned_to']]);
        $this->flash($request, 'success', 'Ticket updated.');

        return $this->redirect('/admin/tickets/' . $id);
    }
}
