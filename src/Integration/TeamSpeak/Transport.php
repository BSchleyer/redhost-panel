<?php

declare(strict_types=1);

namespace Nebrix\Integration\TeamSpeak;

/**
 * Line-oriented connection to a TeamSpeak 3 ServerQuery interface.
 */
interface Transport
{
    /** Opens the connection and consumes the welcome banner. */
    public function connect(): void;

    public function writeLine(string $line): void;

    /** Reads one line without its terminator. */
    public function readLine(): string;

    public function close(): void;
}
