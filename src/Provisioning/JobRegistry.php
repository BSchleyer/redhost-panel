<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Billing\InvoiceService;
use Nebrix\Queue\JobHandler;

final class JobRegistry
{
    /**
     * @return array<string, class-string<JobHandler>>
     */
    public static function handlers(): array
    {
        return [
            \Nebrix\Mail\Mailer::JOB_TYPE => \Nebrix\Mail\SendMailJob::class,
            InvoiceService::JOB_FULFIL_ORDER => FulfilOrderJob::class,
            ProvisioningService::JOB_PROVISION => ProvisionServiceJob::class,
            ProvisioningService::JOB_SUSPEND => SuspendServiceJob::class,
            InvoiceService::JOB_UNSUSPEND_SERVICE => UnsuspendServiceJob::class,
            ProvisioningService::JOB_TERMINATE => TerminateServiceJob::class,
        ];
    }
}
