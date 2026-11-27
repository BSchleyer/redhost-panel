<?php

declare(strict_types=1);

namespace Nebrix\Domain;

use Nebrix\Integration\HealthResult;
use Nebrix\Integration\IntegrationException;

/**
 * Domain registrar abstraction (implemented by INWX; other registrars can be
 * added with the same contract).
 */
interface Registrar
{
    public const RENEW = 'renew';
    public const EXPIRE = 'expire';
    public const DELETE = 'delete';

    public function healthCheck(): HealthResult;

    /** @throws IntegrationException */
    public function isAvailable(string $domain): bool;

    /**
     * Domain in the registrar account, or null when it is not in the account.
     *
     * @return array{status: string, expires: string|null, nameservers: list<string>, auth_code: string|null, registrant: string|null}|null
     *
     * @throws IntegrationException
     */
    public function info(string $domain): ?array;

    /**
     * @param array{type: string, name: string, org: string, street: string, city: string, postcode: string, country: string, phone: string, email: string} $contact
     * @return string remote contact handle
     *
     * @throws IntegrationException
     */
    public function createContact(array $contact): string;

    /**
     * @param list<string> $nameservers
     *
     * @throws IntegrationException
     */
    public function register(string $domain, string $contactId, array $nameservers, int $years): void;

    /**
     * @param list<string> $nameservers
     *
     * @throws IntegrationException
     */
    public function transfer(string $domain, #[\SensitiveParameter] string $authCode, string $contactId, array $nameservers): void;

    /**
     * @param list<string> $nameservers
     *
     * @throws IntegrationException
     */
    public function setNameservers(string $domain, array $nameservers): void;

    /**
     * @param string $mode Registrar::RENEW, EXPIRE or DELETE (at the end of the term)
     *
     * @throws IntegrationException
     */
    public function setRenewalMode(string $domain, string $mode): void;
}
