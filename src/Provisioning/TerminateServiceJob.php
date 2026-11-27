<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Queue\FailureAwareJobHandler;
use Nebrix\Queue\JobHandler;

final class TerminateServiceJob implements JobHandler
{
    public function __construct(private readonly ProvisioningService $provisioning)
    {
    }

    public function handle(array $payload): void
    {
        $this->provisioning->terminate((int) ($payload['service_id'] ?? 0));
    }
}
