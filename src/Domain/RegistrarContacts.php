<?php

declare(strict_types=1);

namespace Nebrix\Domain;

use InvalidArgumentException;
use Nebrix\Auth\User;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;

/**
 * Registrant contacts at the registrar, created from the customer profile
 * and reused as long as the data does not change (new data = new contact,
 * as registries require for ownership changes).
 */
class RegistrarContacts
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return array{type: string, name: string, org: string, street: string, city: string, postcode: string, country: string, phone: string, email: string}
     *
     * @throws InvalidArgumentException with a customer-facing message when the profile is incomplete
     */
    public static function contactData(User $user): array
    {
        $name = trim((string) $user->firstName . ' ' . (string) $user->lastName);
        $street = trim((string) ($user->attribute('address_line1') ?? '') . ' ' . (string) ($user->attribute('address_line2') ?? ''));
        $country = strtoupper((string) ($user->attribute('country') ?? ''));
        $phone = PhoneNumber::toEpp((string) ($user->attribute('phone') ?? ''), $country);
        $missing = [];
        foreach (['name' => $name, 'address' => $street, 'postcode' => (string) ($user->attribute('postcode') ?? ''), 'city' => (string) ($user->attribute('city') ?? ''), 'country' => $country] as $field => $value) {
            if (trim($value) === '') {
                $missing[] = $field;
            }
        }
        if ($phone === null) {
            $missing[] = 'phone number (international format, e.g. +49 30 1234567)';
        }
        if ($missing !== [] || preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            throw new InvalidArgumentException('Domain registrations need a complete profile. Please add: ' . implode(', ', $missing ?: ['country']) . '.');
        }
        $company = trim((string) ($user->attribute('company') ?? ''));

        return [
            'type' => $company !== '' ? 'ORG' : 'PERSON',
            'name' => mb_substr($name, 0, 255),
            'org' => mb_substr($company, 0, 255),
            'street' => mb_substr($street, 0, 255),
            'city' => mb_substr((string) $user->attribute('city'), 0, 100),
            'postcode' => mb_substr((string) $user->attribute('postcode'), 0, 20),
            'country' => $country,
            'phone' => (string) $phone,
            'email' => $user->email,
        ];
    }

    /**
     * @throws InvalidArgumentException|\Nebrix\Integration\IntegrationException
     */
    public function ensure(User $user, int $integrationId, Registrar $registrar): string
    {
        $contact = self::contactData($user);
        $hash = hash('sha256', json_encode($contact, JSON_THROW_ON_ERROR));
        $existing = $this->db->fetchValue('SELECT `remote_id` FROM `nx_registrar_contacts` WHERE `user_id` = ? AND `integration_id` = ? AND `contact_hash` = ?', [$user->id, $integrationId, $hash]);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $remoteId = $registrar->createContact($contact);
        $this->db->insert('nx_registrar_contacts', [
            'user_id' => $user->id,
            'integration_id' => $integrationId,
            'contact_hash' => $hash,
            'remote_id' => $remoteId,
            'created_at' => $this->clock->nowString(),
        ]);

        return $remoteId;
    }
}
