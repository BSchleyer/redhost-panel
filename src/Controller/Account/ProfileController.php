<?php

declare(strict_types=1);

namespace Nebrix\Controller\Account;

use Nebrix\Http\Controller;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use Nebrix\Repository\UserRepository;
use Nebrix\Settings\SettingsRepository;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function show(Request $request): Response
    {
        return $this->render($request, 'account/profile.twig', [
            'profile' => $this->user($request)->attributes,
            'vat_status' => $this->container->get(\Nebrix\Billing\Tax\TaxService::class)->vatIdStatus($this->user($request)),
            'languages' => array_intersect_key(
                ['en' => 'English', 'de' => 'Deutsch'],
                array_flip($this->settings->list('general.available_languages') ?: ['en']),
            ),
        ]);
    }

    public function update(Request $request): Response
    {
        $user = $this->user($request);
        $languages = implode(',', $this->settings->list('general.available_languages') ?: ['en']);

        // E-mail and role cannot be changed here (mass-assignment protection).
        $data = $this->validate($request, [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'company' => 'nullable|string|max:150',
            'vat_id' => 'nullable|string|max:50',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|country',
            'phone' => 'nullable|string|max:50',
            'language' => 'required|in:' . $languages,
            'auto_pay_credit' => 'bool',
        ]);
        $data['auto_pay_credit'] = $data['auto_pay_credit'] ? 1 : 0;
        // Business customers receive e-invoices, which require the full address (XRechnung BR-DE-8/9).
        if (trim((string) ($data['company'] ?? '')) !== '' || trim((string) ($data['vat_id'] ?? '')) !== '') {
            $missing = [];
            foreach (['address_line1' => 'Please enter the street and number.', 'postcode' => 'Please enter the postcode.', 'city' => 'Please enter the city.', 'country' => 'Please enter the country.'] as $field => $message) {
                if (trim((string) ($data[$field] ?? '')) === '') {
                    $missing[$field] = $message;
                }
            }
            if ($missing !== []) {
                throw \Nebrix\Security\ValidationException::withMessages($missing);
            }
        }

        $this->users->update($user->id, $data);
        $this->audit($request, 'account.profile_updated', 'user', $user->id);
        $this->flash($request, 'success', 'Your profile has been saved.');

        // VAT ID or country changed: verify the VAT ID with VIES (reverse charge).
        if (($data['vat_id'] ?? null) !== ($user->attributes['vat_id'] ?? null) || ($data['country'] ?? null) !== ($user->attributes['country'] ?? null)) {
            $updated = $this->users->find($user->id);
            if ($updated !== null && trim((string) ($data['vat_id'] ?? '')) !== '') {
                $taxes = $this->container->get(\Nebrix\Billing\Tax\TaxService::class);
                $taxes->verifyUser($updated, ($data['vat_id'] ?? null) !== ($user->attributes['vat_id'] ?? null));
                $this->flashVatStatus($request, $taxes->vatIdStatus($this->users->find($user->id) ?? $updated));
            } elseif ($updated !== null) {
                $this->container->get(\Nebrix\Billing\Tax\TaxService::class)->verifyUser($updated);
            }
        } elseif (($data['company'] ?? null) !== ($user->attributes['company'] ?? null) && trim((string) ($data['vat_id'] ?? '')) !== '') {
            // The company name decides whether the VAT ID belongs to the customer.
            $updated = $this->users->find($user->id);
            if ($updated !== null) {
                $this->flashVatStatus($request, $this->container->get(\Nebrix\Billing\Tax\TaxService::class)->vatIdStatus($updated));
            }
        }

        return $this->redirect('/account/profile');
    }

    private function flashVatStatus(Request $request, string $status): void
    {
        $this->flash($request, $status === 'verified' ? 'success' : 'warning', match ($status) {
            'verified' => 'Your VAT ID has been verified.',
            'invalid' => 'Your VAT ID could not be confirmed by the EU VIES service. Please check it.',
            'company_missing' => 'Please enter your company name: invoices without VAT (reverse charge) need it.',
            'name_mismatch' => 'Your VAT ID is registered to a different company name. Enter the company name as registered, or contact our support.',
            default => 'Your VAT ID could not be checked right now; we will try again automatically.',
        });
    }
}
