<?php

declare(strict_types=1);

namespace Nebrix\Controller\Customer;

use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Security\RateLimiter;
use Nebrix\Security\ValidationException;
use Nebrix\Service\ServiceRepository;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Support\TicketRepository;

final class TicketController extends Controller
{
    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly ServiceRepository $services,
        private readonly RateLimiter $limiter,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->ensureEnabled();

        return $this->render($request, 'customer/tickets/index.twig', ['tickets' => $this->tickets->forUser($this->user($request)->id)]);
    }

    public function create(Request $request): Response
    {
        $this->ensureEnabled();
        $services = [];
        foreach ($this->services->forUser($this->user($request)->id) as $service) {
            $services[$service->id] = $service->name;
        }
        $preselect = $request->query('service');

        return $this->render($request, 'customer/tickets/create.twig', [
            'services' => $services,
            'preselect' => is_string($preselect) && ctype_digit($preselect) ? (int) $preselect : null,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->ensureEnabled();
        $user = $this->user($request);
        $data = $this->validate($request, [
            'subject' => 'required|string|min:3|max:200',
            'message' => 'required|string|min:10|max:20000',
            'service_id' => 'nullable|int|min:1',
            'priority' => 'required|in:low,normal,high',
        ]);

        // A customer can only reference their own services.
        if ($data['service_id'] !== null && $this->services->findForUser((int) $data['service_id'], $user->id) === null) {
            throw ValidationException::withMessages(['service_id' => 'Please choose one of your services.']);
        }

        $key = 'ticket-create:' . $user->id;
        if (!$this->limiter->attempt($key, 5, 3600)) {
            throw HttpException::tooManyRequests('You have opened several tickets in a short time. Please wait before opening another one.');
        }

        $id = $this->tickets->create($user->id, (string) $data['subject'], (string) $data['message'], $data['service_id'] !== null ? (int) $data['service_id'] : null, (string) $data['priority']);
        $this->flash($request, 'success', 'Your request has been submitted. We will get back to you as soon as possible.');

        return $this->redirect('/tickets/' . $id);
    }

    public function show(Request $request, int $id): Response
    {
        $this->ensureEnabled();
        $ticket = $this->find($request, $id);

        return $this->render($request, 'customer/tickets/show.twig', ['ticket' => $ticket, 'messages' => $this->tickets->messages($id)]);
    }

    public function reply(Request $request, int $id): Response
    {
        $this->ensureEnabled();
        $user = $this->user($request);
        $ticket = $this->find($request, $id);
        $data = $this->validate($request, ['message' => 'required|string|min:2|max:20000']);

        $key = 'ticket-reply:' . $user->id;
        if (!$this->limiter->attempt($key, 30, 3600)) {
            throw HttpException::tooManyRequests();
        }

        $this->tickets->addMessage((int) $ticket['id'], $user->id, false, (string) $data['message']);
        $this->flash($request, 'success', 'Your reply has been sent.');

        return $this->redirect('/tickets/' . $id);
    }

    public function close(Request $request, int $id): Response
    {
        $this->ensureEnabled();
        $this->find($request, $id);
        $this->tickets->setStatus($id, 'closed');
        $this->flash($request, 'success', 'The ticket has been closed.');

        return $this->redirect('/tickets/' . $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function find(Request $request, int $id): array
    {
        return $this->tickets->findForUser($id, $this->user($request)->id) ?? throw HttpException::notFound();
    }

    private function ensureEnabled(): void
    {
        if (!$this->settings->bool('system.tickets_enabled')) {
            throw HttpException::notFound();
        }
    }
}
