<?php

declare(strict_types=1);

namespace Nebrix\Controller\Admin;

use DateTimeImmutable;
use DateTimeZone;
use Nebrix\Authorization\Permission;
use Nebrix\Contract\CancellationService;
use Nebrix\Http\Controller;
use Nebrix\Http\HttpException;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Repository\UserRepository;
use Nebrix\Service\ServiceRepository;
use RuntimeException;

/**
 * Admin → Cancellations: declarations from guests, extraordinary
 * cancellations and withdrawals are reviewed here (assign the contract and
 * the end date, reject with a note, or mark as handled).
 */
final class CancellationController extends Controller
{
    public function __construct(
        private readonly CancellationService $cancellations,
        private readonly ServiceRepository $services,
        private readonly UserRepository $users,
    ) {
    }

    public function index(Request $request): Response
    {
        $status = in_array($request->string('status'), ['received', 'scheduled', 'completed', 'rejected', 'withdrawn'], true) ? $request->string('status') : '';
        $page = $this->page($request);

        return $this->render($request, 'admin/cancellations/index.twig', [
            'result' => $this->cancellations->paginate(['status' => $status], $page),
            'status' => $status,
            'page' => $page,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $declaration = $this->cancellations->find($id) ?? throw HttpException::notFound();
        $owner = $this->owner($declaration);
        $services = [];
        if ($owner !== null) {
            foreach ($this->services->forUser($owner->id) as $service) {
                $services[$service->id] = sprintf('#%d %s (%s, paid until %s)', $service->id, $service->name, $service->status, substr((string) $service->nextDueAt, 0, 10) ?: '–');
            }
        }

        return $this->render($request, 'admin/cancellations/show.twig', [
            'declaration' => $declaration,
            'owner' => $owner,
            'services' => $services,
            'can_manage' => $this->can($request, Permission::SERVICES_MANAGE),
        ]);
    }

    /**
     * Candidate account: the signed-in author, else an account with the given
     * e-mail address or the account e-mail entered as customer reference.
     *
     * @param array<string, mixed> $declaration
     */
    private function owner(array $declaration): ?\Nebrix\Auth\User
    {
        if ($declaration['user_id'] !== null) {
            return $this->users->find((int) $declaration['user_id']);
        }
        $owner = $this->users->findByEmail((string) $declaration['email']);
        if ($owner === null && is_string($declaration['customer_reference']) && filter_var($declaration['customer_reference'], FILTER_VALIDATE_EMAIL) !== false) {
            $owner = $this->users->findByEmail(mb_strtolower($declaration['customer_reference']));
        }

        return $owner;
    }

    public function process(Request $request, int $id): Response
    {
        $this->authorize($request, Permission::SERVICES_MANAGE);
        $declaration = $this->cancellations->find($id) ?? throw HttpException::notFound();
        $data = $this->validate($request, [
            'action' => 'required|in:schedule,reject,complete',
            'service_id' => 'nullable|int|min:1',
            'effective_date' => 'nullable|date',
            'note' => 'nullable|string|max:5000',
        ]);
        $actor = $this->user($request)->id;

        try {
            switch ($data['action']) {
                case 'schedule':
                    $service = $data['service_id'] !== null ? $this->services->find((int) $data['service_id']) : null;
                    if ($service === null) {
                        throw new RuntimeException('Choose the contract (service).');
                    }
                    $owner = $this->owner($declaration)?->id;
                    if ($owner !== null && $service->userId !== $owner) {
                        throw new RuntimeException('The service belongs to another customer.');
                    }
                    if ($data['effective_date'] !== null && (string) $data['effective_date'] < $this->container->get(\Nebrix\Core\Clock::class)->now()->format('Y-m-d')) {
                        throw new RuntimeException('The end date cannot be in the past.');
                    }
                    $effective = $data['effective_date'] !== null
                        ? new DateTimeImmutable($data['effective_date'] . ' 00:00:00', new DateTimeZone('UTC'))
                        : $this->cancellations->effectiveDate($service, $declaration['requested_date'] !== null ? (string) $declaration['requested_date'] : null);
                    $this->cancellations->schedule($id, $service, $effective, $actor);
                    $this->flash($request, 'success', sprintf('The contract ends on %s; the customer has been informed.', $effective->format('Y-m-d')));
                    break;
                case 'reject':
                    if (trim((string) $data['note']) === '') {
                        throw new RuntimeException('Enter a note for the customer.');
                    }
                    $this->cancellations->reject($id, (string) $data['note'], $actor);
                    $this->flash($request, 'success', 'The declaration has been rejected; the customer has been informed.');
                    break;
                default:
                    $this->cancellations->complete($id, (string) ($data['note'] ?? ''), $actor);
                    $this->flash($request, 'success', 'The declaration has been marked as handled.');
            }
        } catch (RuntimeException $exception) {
            $this->flash($request, 'error', $exception->getMessage());
        }

        return $this->redirect('/admin/cancellations/' . $id);
    }
}
