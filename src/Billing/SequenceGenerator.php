<?php

declare(strict_types=1);

namespace Nebrix\Billing;

use Nebrix\Database\Connection;

class SequenceGenerator
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Returns the next value of a named sequence. Must be called inside the
     * transaction that persists the numbered document.
     */
    public function next(string $name): int
    {
        return $this->db->transaction(function () use ($name): int {
            $this->db->run('INSERT IGNORE INTO `nx_sequences` (`name`, `value`) VALUES (?, 0)', [$name]);
            $current = (int) $this->db->fetchValue('SELECT `value` FROM `nx_sequences` WHERE `name` = ? FOR UPDATE', [$name]);
            $this->db->run('UPDATE `nx_sequences` SET `value` = ? WHERE `name` = ?', [$current + 1, $name]);

            return $current + 1;
        });
    }
}
