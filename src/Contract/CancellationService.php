<?php

declare(strict_types=1);

namespace Nebrix\Contract;

use DateTimeImmutable;
use DateTimeZone;
use Nebrix\Audit\AuditLogger;
use Nebrix\Auth\User;
use Nebrix\Billing\InvoiceRepository;
use Nebrix\Billing\InvoiceService;
use Nebrix\Core\Clock;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\Mail\Mailer;
use Nebrix\Provisioning\ServiceManager;
use Nebrix\Repository\UserRepository;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Contract cancellations (§ 312k BGB) and withdrawals (EU withdrawal
 * function).
 *
 * Every declaration is stored with its receipt time and confirmed by e-mail
 * immediately. An ordinary cancellation that a signed-in customer submits
 * for one of their active or suspended services takes effect automatically
 * at the end of the paid term (or a later requested date): no further
 * renewal invoices are created, open ones for later periods are cancelled
 * and the service is terminated when the date is reached. Declarations from
 * guests, extraordinary cancellations and withdrawals are reviewed by staff
 * (the identity of a guest cannot be verified automatically).
 */
class CancellationService
{
    public const ORDINARY = 'ordinary';
    public const EXTRAORDINARY = 'extraordinary';
    public const WITHDRAWAL = 'withdrawal';
    public const KINDS = [self::ORDINARY, self::EXTRAORDINARY, self::WITHDRAWAL];

    public const STATUS_RECEIVED = 'received';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    private const LABELS = [
        'en' => [self::ORDINARY => 'Ordinary cancellation', self::EXTRAORDINARY => 'Extraordinary cancellation', self::WITHDRAWAL => 'Withdrawal', 'earliest' => 'at the earliest possible date'],
        'de' => [self::ORDINARY => 'Ordentliche Kündigung', self::EXTRAORDINARY => 'Außerordentliche Kündigung', self::WITHDRAWAL => 'Widerruf', 'earliest' => 'zum nächstmöglichen Zeitpunkt'],
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly ServiceRepository $services,
        private readonly InvoiceService $invoices,
        private readonly ServiceManager $manager,
        private readonly Mailer $mailer,
        private readonly AuditLogger $audit,
        private readonly UserRepository $users,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly \Nebrix\View\Translator $translator,
    ) {
    }

    /**
     * Records a declaration and confirms its receipt.
     *
     * @param array{kind: string, name: string, email: string, customer_reference?: ?string, contract?: ?string, reason?: ?string, requested_date?: ?string, service_id?: ?int} $data
     * @return array<string, mixed> the stored declaration
     */
    public function submit(array $data, ?User $user, ?string $ip): array
    {
        if (!in_array($data['kind'], self::KINDS, true)) {
            throw new \InvalidArgumentException('Unknown declaration type.');
        }
        // Only the signed-in owner can name a service directly.
        $service = $user !== null && ($data['service_id'] ?? null) !== null ? $this->services->findForUser((int) $data['service_id'], $user->id) : null;
        $contract = trim((string) ($data['contract'] ?? ''));
        if ($service !== null) {
            $contract = sprintf('%s (#%d)', $service->name, $service->id);
        }

        $now = $this->clock->now();
        $id = $this->db->insert('nx_cancellations', [
            'reference' => $this->newReference(),
            'kind' => $data['kind'],
            'user_id' => $user?->id,
            'service_id' => $service?->id,
            'name' => mb_substr(trim($data['name']), 0, 200),
            'email' => mb_strtolower(trim($data['email'])),
            'customer_reference' => ($data['customer_reference'] ?? '') !== '' ? mb_substr((string) $data['customer_reference'], 0, 255) : null,
            'contract' => mb_substr($contract, 0, 500),
            'reason' => ($data['reason'] ?? '') !== '' ? mb_substr((string) $data['reason'], 0, 5000) : null,
            'requested_date' => ($data['requested_date'] ?? '') !== '' ? $data['requested_date'] : null,
            'status' => self::STATUS_RECEIVED,
            'ip' => $ip,
            'received_at' => $now->format('Y-m-d H:i:s'),
        ]);
        $this->audit->log($user?->id, 'contract.' . $data['kind'] . '_received', 'cancellation', $id, ['service' => $service?->id], $ip);

        $declaration = $this->find($id) ?? throw new RuntimeException('Declaration not stored.');
        $this->confirmReceipt($declaration, $user);

        if ($service !== null && $data['kind'] === self::ORDINARY && in_array($service->status, [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED], true)) {
            $this->schedule($id, $service, $this->effectiveDate($service, $declaration['requested_date']), null);
            $declaration = $this->find($id) ?? $declaration;
        }

        return $declaration;
    }

    /**
     * End of the paid term, or the requested date if it is later.
     */
    public function effectiveDate(Service $service, ?string $requestedDate): DateTimeImmutable
    {
        $now = $this->clock->now();
        $earliest = $service->nextDueAt !== null ? new DateTimeImmutable($service->nextDueAt, new DateTimeZone('UTC')) : $now;
        if ($earliest < $now) {
            $earliest = $now;
        }
        if ($requestedDate !== null && $requestedDate !== '') {
            $requested = new DateTimeImmutable($requestedDate . ' 00:00:00', new DateTimeZone('UTC'));
            if ($requested > $earliest) {
                return $requested;
            }
        }

        return $earliest;
    }

    /**
     * Sets the date the service ends; no renewals after it.
     */
    public function schedule(int $cancellationId, Service $service, DateTimeImmutable $effective, ?int $actorId): void
    {
        $declaration = $this->find($cancellationId) ?? throw new RuntimeException('Declaration not found.');
        if (in_array($declaration['status'], [self::STATUS_COMPLETED, self::STATUS_REJECTED, self::STATUS_WITHDRAWN], true)) {
            throw new RuntimeException('This declaration is already closed.');
        }
        if (!in_array($service->status, [Service::STATUS_ACTIVE, Service::STATUS_SUSPENDED, Service::STATUS_PENDING, Service::STATUS_PROVISIONING, Service::STATUS_FAILED], true)) {
            throw new RuntimeException('The service is already terminated.');
        }
        $when = $effective->format('Y-m-d H:i:s');
        $previous = $service->attributes['cancellation_id'] ?? null;

        $this->db->transaction(function () use ($cancellationId, $service, $when, $actorId, $previous, $declaration): void {
            // A service ends through one declaration: an earlier scheduled one is replaced.
            if ($previous !== null && (int) $previous !== $cancellationId) {
                $this->db->update('nx_cancellations', [
                    'status' => self::STATUS_COMPLETED,
                    'staff_note' => 'Superseded by declaration ' . $declaration['reference'] . '.',
                    'processed_by' => $actorId,
                    'processed_at' => $this->clock->nowString(),
                ], ['id' => (int) $previous, 'status' => self::STATUS_SCHEDULED]);
            }
            $this->services->update($service->id, ['cancel_at' => $when, 'cancellation_id' => $cancellationId]);
            $this->db->update('nx_cancellations', [
                'status' => self::STATUS_SCHEDULED,
                'service_id' => $service->id,
                'user_id' => $service->userId,
                'effective_at' => $when,
                'processed_by' => $actorId,
                'processed_at' => $this->clock->nowString(),
            ], ['id' => $cancellationId]);
        });

        // Renewal invoices for periods after the end date are void.
        foreach ($this->db->fetchAll(
            "SELECT `id` FROM `nx_invoices` WHERE `service_id` = ? AND `type` = 'renewal' AND `status` = ? AND `due_at` >= ?",
            [$service->id, InvoiceRepository::STATUS_UNPAID, $when],
        ) as $invoice) {
            try {
                $this->invoices->cancel((int) $invoice['id']);
            } catch (RuntimeException $exception) {
                $this->logger->info('Renewal invoice not cancelled', ['invoice' => $invoice['id'], 'reason' => $exception->getMessage()]);
            }
        }

        $this->audit->log($actorId, 'contract.cancellation_scheduled', 'service', $service->id, ['cancellation' => $cancellationId, 'effective_at' => $when]);
        $owner = $this->users->find($service->userId);
        if ($owner !== null) {
            $this->mailer->queue($owner->email, $owner->displayName(), 'cancellation_confirmed', [
                'name' => $owner->displayName(),
                'reference' => (string) $declaration['reference'],
                'service_name' => $service->name,
                'effective_date' => $this->mailer->localeFor($owner->email) === 'de' ? $effective->format('d.m.Y') : $effective->format('Y-m-d'),
                'service_url' => $this->url('/services/' . $service->id),
            ]);
        }
    }

    /**
     * The customer takes back a scheduled cancellation before it takes effect.
     */
    public function withdraw(Service $service, ?int $actorId, ?string $ip): void
    {
        if ($service->attributes['cancel_at'] === null || $service->attributes['cancel_at'] <= $this->clock->nowString()) {
            throw new RuntimeException('There is no pending cancellation for this service.');
        }
        $cancellationId = $service->attributes['cancellation_id'] !== null ? (int) $service->attributes['cancellation_id'] : null;
        if (!$this->canWithdraw($service)) {
            throw new RuntimeException('This cancellation was confirmed by our staff. Please contact support to take it back.');
        }
        $this->db->transaction(function () use ($service, $cancellationId, $actorId): void {
            $this->services->update($service->id, ['cancel_at' => null, 'cancellation_id' => null]);
            if ($cancellationId !== null) {
                $this->db->update('nx_cancellations', ['status' => self::STATUS_WITHDRAWN, 'processed_by' => $actorId, 'processed_at' => $this->clock->nowString()], ['id' => $cancellationId, 'status' => self::STATUS_SCHEDULED]);
            }
        });
        $this->audit->log($actorId, 'contract.cancellation_withdrawn', 'service', $service->id, ['cancellation' => $cancellationId], $ip);
    }

    /**
     * Whether the customer may take the cancellation of the service back
     * themselves: only their own ordinary cancellation that took effect
     * automatically. Cancellations scheduled by staff (guest declarations,
     * extraordinary cancellations, withdrawals) are taken back through support.
     */
    public function canWithdraw(Service $service): bool
    {
        $cancelAt = $service->attributes['cancel_at'] ?? null;
        if ($cancelAt === null || $cancelAt <= $this->clock->nowString()) {
            return false;
        }
        $declaration = ($service->attributes['cancellation_id'] ?? null) !== null ? $this->find((int) $service->attributes['cancellation_id']) : null;

        return $declaration === null
            || ($declaration['kind'] === self::ORDINARY && $declaration['processed_by'] === null && $declaration['status'] === self::STATUS_SCHEDULED && (int) $declaration['user_id'] === $service->userId);
    }

    /**
     * Rejects a declaration. A scheduled end of the service is removed, so
     * the service continues (and is renewed) as before.
     */
    public function reject(int $id, string $note, int $actorId): void
    {
        $declaration = $this->open($id);
        $this->db->transaction(function () use ($id, $note, $actorId): void {
            $this->db->update('nx_cancellations', ['status' => self::STATUS_REJECTED, 'staff_note' => mb_substr($note, 0, 5000), 'processed_by' => $actorId, 'processed_at' => $this->clock->nowString()], ['id' => $id]);
            $this->db->run('UPDATE `nx_services` SET `cancel_at` = NULL, `cancellation_id` = NULL WHERE `cancellation_id` = ?', [$id]);
        });
        $this->audit->log($actorId, 'contract.cancellation_rejected', 'cancellation', $id, ['was_scheduled' => $declaration['status'] === self::STATUS_SCHEDULED]);
        $this->mailer->queue((string) $declaration['email'], (string) $declaration['name'], 'cancellation_rejected', [
            'name' => (string) $declaration['name'],
            'reference' => (string) $declaration['reference'],
            'note' => $note,
        ]);
    }

    /**
     * Closes a declaration that staff handled manually (e.g. a withdrawal
     * with refund). Scheduled declarations complete automatically when the
     * service ends; they can only be rejected.
     */
    public function complete(int $id, string $note, int $actorId): void
    {
        if ($this->open($id)['status'] === self::STATUS_SCHEDULED) {
            throw new RuntimeException('This declaration is scheduled and completes automatically when the service ends.');
        }
        $this->db->update('nx_cancellations', ['status' => self::STATUS_COMPLETED, 'staff_note' => $note !== '' ? mb_substr($note, 0, 5000) : null, 'processed_by' => $actorId, 'processed_at' => $this->clock->nowString()], ['id' => $id]);
        $this->audit->log($actorId, 'contract.cancellation_completed', 'cancellation', $id);
    }

    /**
     * Terminates services whose cancellation date has been reached.
     */
    public function executeDue(): int
    {
        $count = 0;
        // Only services whose declaration is still scheduled (not rejected or replaced).
        foreach ($this->db->fetchAll(
            "SELECT s.`id` FROM `nx_services` s JOIN `nx_cancellations` c ON c.`id` = s.`cancellation_id` AND c.`status` = 'scheduled'
              WHERE s.`cancel_at` IS NOT NULL AND s.`cancel_at` <= ? AND s.`status` IN ('active', 'suspended', 'pending', 'provisioning', 'failed')",
            [$this->clock->nowString()],
        ) as $row) {
            $service = $this->services->find((int) $row['id']);
            if ($service === null) {
                continue;
            }
            $cancellationId = $service->attributes['cancellation_id'] !== null ? (int) $service->attributes['cancellation_id'] : null;
            try {
                $this->manager->terminate($service, null, 'cancelled by the customer');
            } catch (RuntimeException $exception) {
                $this->logger->warning('Cancelled service not terminated', ['service' => $service->id, 'reason' => $exception->getMessage()]);
                continue;
            }
            if ($cancellationId !== null) {
                $this->db->update('nx_cancellations', ['status' => self::STATUS_COMPLETED], ['id' => $cancellationId, 'status' => self::STATUS_SCHEDULED]);
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM `nx_cancellations` WHERE `id` = ?', [$id]);
    }

    /**
     * @param array{status?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $where = '1 = 1';
        $params = [];
        if (($filters['status'] ?? '') !== '') {
            $where = 'c.`status` = ?';
            $params[] = $filters['status'];
        }

        return [
            'items' => $this->db->fetchAll(
                'SELECT c.*, s.`name` AS service_name FROM `nx_cancellations` c LEFT JOIN `nx_services` s ON s.`id` = c.`service_id` WHERE ' . $where . ' ORDER BY c.`id` DESC LIMIT ? OFFSET ?',
                [...$params, $perPage, max(0, ($page - 1) * $perPage)],
            ),
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_cancellations` c WHERE ' . $where, $params),
        ];
    }

    public function label(string $key, string $locale): string
    {
        return self::LABELS[$locale][$key] ?? self::LABELS['en'][$key] ?? $key;
    }

    /**
     * @return array<string, mixed>
     */
    private function open(int $id): array
    {
        $declaration = $this->find($id) ?? throw new RuntimeException('Declaration not found.');
        if (!in_array($declaration['status'], [self::STATUS_RECEIVED, self::STATUS_SCHEDULED], true)) {
            throw new RuntimeException('This declaration is already closed.');
        }

        return $declaration;
    }

    /**
     * Confirmation on a durable medium (e-mail) with content and receipt time.
     *
     * @param array<string, mixed> $declaration
     */
    private function confirmReceipt(array $declaration, ?User $user): void
    {
        $recipients = [(string) $declaration['email'] => (string) $declaration['name']];
        if ($user !== null && !isset($recipients[$user->email])) {
            $recipients[$user->email] = $user->displayName();
        }
        foreach ($recipients as $email => $name) {
            // Account language, else the language of the form the declaration was made in.
            $locale = $this->mailer->localeFor($email, $this->translator->locale());
            $this->mailer->queue($email, $name, 'cancellation_received', [
                'name' => (string) $declaration['name'],
                'reference' => (string) $declaration['reference'],
                'kind' => $this->label((string) $declaration['kind'], $locale),
                'contract' => (string) $declaration['contract'],
                'received_at' => (string) $declaration['received_at'] . ' UTC',
                'effective' => $declaration['requested_date'] !== null ? (string) $declaration['requested_date'] : $this->label('earliest', $locale),
                'reason' => (string) ($declaration['reason'] ?? '–'),
            ], $locale);
        }
    }

    private function newReference(): string
    {
        do {
            $reference = 'C-' . strtoupper(bin2hex(random_bytes(4)));
        } while ($this->db->fetchValue('SELECT 1 FROM `nx_cancellations` WHERE `reference` = ?', [$reference]) !== null);

        return $reference;
    }

    private function url(string $path): string
    {
        return rtrim($this->config->string('app.url'), '/') . $path;
    }
}
