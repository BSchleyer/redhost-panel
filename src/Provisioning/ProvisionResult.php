<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

final class ProvisionResult
{
    /**
     * @param array<string, mixed> $remote
     * @param array<string, scalar|null> $credentials stored encrypted
     */
    public function __construct(
        public readonly string $externalId,
        public readonly array $remote = [],
        #[\SensitiveParameter] public readonly array $credentials = [],
    ) {
    }
}
