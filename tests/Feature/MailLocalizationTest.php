<?php

declare(strict_types=1);

namespace Nebrix\Tests\Feature;

use Nebrix\Auth\Role;
use Nebrix\Mail\Mailer;
use Nebrix\Mail\MailTemplates;
use Nebrix\Tests\FeatureTestCase;

final class MailLocalizationTest extends FeatureTestCase
{
    public function testEveryTemplateHasAGermanVersionWithTheSamePlaceholders(): void
    {
        $defaults = require dirname(__DIR__, 2) . '/config/mail_templates.php';
        $german = require dirname(__DIR__, 2) . '/config/mail_templates_de.php';
        self::assertSame(array_keys($defaults), array_keys($german));

        $placeholders = static function (string $text): array {
            preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $text, $m);
            $names = array_values(array_unique($m[1]));
            sort($names);

            return $names;
        };
        foreach ($defaults as $key => $template) {
            self::assertSame($placeholders($template['subject'] . $template['html']), $placeholders($german[$key]['subject'] . $german[$key]['html']), $key);
        }
    }

    public function testMailsUseTheRecipientsLanguage(): void
    {
        $mailer = $this->app->container()->get(Mailer::class);
        $german = $this->createUser(Role::CUSTOMER, ['language' => 'de', 'first_name' => 'Erika']);
        $english = $this->createUser(Role::CUSTOMER, ['language' => 'en', 'first_name' => 'Ada']);

        $mailer->queue($german->email, 'Erika', 'ticket_reply', ['name' => 'Erika', 'ticket_subject' => 'Server', 'ticket_url' => 'https://x.test']);
        self::assertSame('Neue Antwort auf Ihr Ticket „Server“', $this->lastMail('ticket_reply')['subject']);

        $mailer->queue($english->email, 'Ada', 'ticket_reply', ['name' => 'Ada', 'ticket_subject' => 'Server', 'ticket_url' => 'https://x.test']);
        self::assertSame('New reply to your ticket "Server"', $this->lastMail('ticket_reply')['subject']);

        // Unknown addresses use the default language of the installation.
        $this->app->container()->get(\Nebrix\Settings\SettingsRepository::class)->set('general.default_language', 'de');
        $mailer->queue('stranger@example.test', null, 'test');
        self::assertSame('Test-E-Mail von Nebrix', $this->lastMail('test')['subject']);
    }

    public function testTemplatesAreEditedPerLanguage(): void
    {
        $this->actingAs($this->createUser(Role::SUPER_ADMIN));
        $page = $this->get('/admin/mail-templates/ticket_reply?locale=de');
        self::assertStringContainsString('Neue Antwort auf Ihr Ticket', $page->body());

        $this->post('/admin/mail-templates/ticket_reply?locale=de', ['locale' => 'de', 'subject' => 'Antwort vom Support', 'html' => '<p>Hallo {{ name }}</p>']);
        $templates = $this->app->container()->get(MailTemplates::class);
        self::assertSame('Antwort vom Support', $templates->get('ticket_reply', 'de')['subject']);
        self::assertSame('New reply to your ticket "{{ ticket_subject }}"', $templates->get('ticket_reply', 'en')['subject'], 'Other languages are unchanged.');
        self::assertSame('en', $templates->get('ticket_reply', 'xx')['locale']);

        $this->post('/admin/mail-templates/ticket_reply/reset?locale=de', ['locale' => 'de']);
        self::assertFalse($templates->get('ticket_reply', 'de')['customized']);
    }
}
