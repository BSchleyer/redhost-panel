<?php

declare(strict_types=1);

namespace Nebrix\Network;

use Nebrix\Integration\IntegrationException;

/** No free address is left in an IP pool (provisioning stops with this message). */
final class PoolExhaustedException extends IntegrationException
{
}
