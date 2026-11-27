<?php

declare(strict_types=1);

namespace Nebrix\Mail;

use Nebrix\Queue\JobHandler;

final class SendMailJob implements JobHandler
{
    public function __construct(private readonly Mailer $mailer)
    {
    }

    public function handle(array $payload): void
    {
        $this->mailer->sendQueued($payload);
    }
}
