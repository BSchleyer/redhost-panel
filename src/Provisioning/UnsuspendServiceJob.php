<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Queue\FailureAwareJobHandler;
use Nebrix\Queue\JobHandler;

final class UnsuspendServiceJob implements JobHandler
{
    public function __construct(private readonly ProvisioningService $provisioning)
    {
    }

    public function handle(array $payload): void
    {
        $this->provisioning->unsuspend((int) ($payload['service_id'] ?? 0));
    }
}
