<?php

declare(strict_types=1);

namespace Nebrix\Queue;

use RuntimeException;

/** Thrown by a job handler when retrying the job cannot succeed. */
final class PermanentJobFailure extends RuntimeException
{
}
