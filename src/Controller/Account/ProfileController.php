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

        $this->users->update($user->id, $data);
        $this->audit($request, 'account.profile_updated', 'user', $user->id);
        $this->flash($request, 'success', 'Your profile has been saved.');

        return $this->redirect('/account/profile');
    }
}
