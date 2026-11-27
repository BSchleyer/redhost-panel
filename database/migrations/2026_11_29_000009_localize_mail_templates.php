<?php

declare(strict_types=1);

use Nebrix\Database\Connection;
use Nebrix\Database\Migration;

/*
 * E-mail templates per language: customised templates get a locale column
 * (primary key key + locale). Existing customisations are assigned to the
 * installation's default language. No data is removed.
 */
return new class implements Migration {
    public function up(Connection $db): void
    {
        $exists = (int) $db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nx_mail_templates' AND COLUMN_NAME = 'locale'",
        );
        if ($exists > 0) {
            return;
        }

        $default = $db->fetchValue("SELECT `value` FROM `nx_settings` WHERE `key` = 'general.default_language'");
        $locale = is_string($default) && preg_match('/^[a-z]{2}$/', $default) === 1 ? $default : 'en';

        $db->run("ALTER TABLE `nx_mail_templates` ADD COLUMN `locale` VARCHAR(5) NOT NULL DEFAULT 'en' AFTER `key`");
        $db->run('UPDATE `nx_mail_templates` SET `locale` = ?', [$locale]);
        $db->run('ALTER TABLE `nx_mail_templates` DROP PRIMARY KEY, ADD PRIMARY KEY (`key`, `locale`)');
    }
};
