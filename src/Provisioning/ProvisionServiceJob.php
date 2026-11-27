<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Queue\FailureAwareJobHandler;
use Nebrix\Queue\JobHandler;

final class ProvisionServiceJob implements FailureAwareJobHandler
{
    public function __construct(private readonly ProvisioningService $provisioning)
    {
    }

    public function handle(array $payload): void
    {
        $this->provisioning->provision((int) ($payload['service_id'] ?? 0));
    }

    public function failed(array $payload, string $error): void
    {
        $this->provisioning->provisioningFailed((int) ($payload['service_id'] ?? 0), $error);
    }
}
