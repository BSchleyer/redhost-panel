<?php

declare(strict_types=1);

namespace Nebrix\Mail;

use Nebrix\Queue\JobHandler;

final class SendMailJob implements JobHandler
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly \Nebrix\Billing\Documents\InvoiceDocuments $documents,
    ) {
    }

    public function handle(array $payload): void
    {
        // Invoice documents are generated here, outside the transaction that created the invoice.
        $this->mailer->sendQueued($payload, fn (int $invoiceId): array => $this->documents->attachments($invoiceId));
    }
}
