<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Integration\IntegrationException;
use Nebrix\Service\Service;

/**
 * Provisioners that can open a graphical (VNC) console for a service.
 */
interface ConsoleProvider
{
    /**
     * Opens a console on the remote system and returns the upstream
     * WebSocket target for the console proxy plus the VNC password the
     * browser uses.
     *
     * @return array{scheme: string, host: string, ip: string, port: int, path: string, headers: array<string, string>, verify_tls: bool, password: string}
     *
     * @throws IntegrationException
     */
    public function openConsole(Service $service): array;
}
