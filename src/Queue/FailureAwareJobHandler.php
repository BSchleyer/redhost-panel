<?php

declare(strict_types=1);

namespace Nebrix\Queue;

/**
 * A handler that needs to react when its job finally fails (all attempts
 * used or a permanent failure), e.g. to mark a service as failed.
 */
interface FailureAwareJobHandler extends JobHandler
{
    /**
     * @param array<string, mixed> $payload
     */
    public function failed(array $payload, string $error): void;
}
