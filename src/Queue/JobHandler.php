<?php

declare(strict_types=1);

namespace Nebrix\Queue;

interface JobHandler
{
    /**
     * Executes the job. Implementations must be idempotent: a job can run
     * again after a worker crash or a manual retry.
     *
     * @param array<string, mixed> $payload
     *
     * @throws PermanentJobFailure when retrying cannot succeed
     */
    public function handle(array $payload): void;
}
