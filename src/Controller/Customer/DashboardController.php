<?php

declare(strict_types=1);

namespace Nebrix\Controller\Customer;

use Nebrix\Database\Connection;
use Nebrix\Http\Controller;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Service\ServiceRepository;
use Nebrix\Settings\SettingsRepository;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly SettingsRepository $settings,
        private readonly Connection $db,
    ) {
    }

    public function show(Request $request): Response
    {
        $user = $this->user($request);
        $services = $this->services->forUser($user->id);
        $active = array_filter($services, static fn ($s): bool => $s->status === 'active');
        $unpaid = $this->db->fetchAll("SELECT `id`, `number`, `type`, `total`, `currency`, `due_at` FROM `nx_invoices` WHERE `user_id` = ? AND `status` = 'unpaid' ORDER BY `due_at`", [$user->id]);

        $upcoming = array_values(array_filter($services, static fn ($s): bool => $s->nextDueAt !== null && in_array($s->status, ['active', 'suspended'], true)));
        usort($upcoming, static fn ($a, $b): int => strcmp((string) $a->nextDueAt, (string) $b->nextDueAt));

        return $this->render($request, 'customer/dashboard.twig', [
            'services' => array_slice($services, 0, 6),
            'service_count' => count($services),
            'active_count' => count($active),
            'unpaid' => $unpaid,
            'unpaid_total' => array_sum(array_map(static fn (array $i): int => (int) $i['total'], $unpaid)),
            'upcoming' => array_slice($upcoming, 0, 5),
            'open_tickets' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM `nx_tickets` WHERE `user_id` = ? AND `status` <> 'closed'", [$user->id]),
            'notice' => $this->settings->string('branding.dashboard_welcome'),
            'currency' => $this->settings->string('general.default_currency') ?: 'EUR',
        ]);
    }
}
