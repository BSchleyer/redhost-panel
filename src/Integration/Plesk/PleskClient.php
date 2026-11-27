<?php

declare(strict_types=1);

namespace Nebrix\Integration\Plesk;

use DOMDocument;
use DOMElement;
use Nebrix\Integration\HealthResult;
use Nebrix\Integration\Http\ApiClient;
use Nebrix\Integration\IntegrationException;
use SimpleXMLElement;

/**
 * Plesk XML API (/enterprise/control/agent.php) for customers and
 * subscriptions (webspaces). Authentication with a secret key
 * ("plesk bin secret_key --create"), sent in the KEY header.
 *
 * Requests are built with DOMDocument (all values escaped); responses are
 * parsed without network access or DTDs (no external entities).
 */
class PleskClient
{
    public const ERROR_NOT_FOUND = 1013;
    public const STATUS_ACTIVE = 0;
    public const STATUS_SUSPENDED = 16;

    public function __construct(private readonly ApiClient $api)
    {
    }

    public function healthCheck(): HealthResult
    {
        try {
            $result = $this->operation('server', 'get', [['stat' => null]]);
        } catch (IntegrationException $exception) {
            return HealthResult::down($exception->getMessage());
        }
        $version = (string) ($result->stat->version->plesk_version ?? '');

        return HealthResult::healthy($version !== '' ? 'Plesk ' . $version : 'Plesk API reachable');
    }

    public function findCustomerByExternalId(string $externalId): ?int
    {
        try {
            $result = $this->operation('customer', 'get', [['filter' => [['external-id' => $externalId]]], ['dataset' => [['gen_info' => null]]]]);
        } catch (PleskException $exception) {
            if ($exception->errorCode === self::ERROR_NOT_FOUND) {
                return null;
            }
            throw $exception;
        }

        return (int) $result->id > 0 ? (int) $result->id : null;
    }

    /**
     * @param array{name: string, company?: string, login: string, password: string, email: string, external_id: string} $customer
     */
    public function createCustomer(#[\SensitiveParameter] array $customer): int
    {
        $info = [];
        if (($customer['company'] ?? '') !== '') {
            $info[] = ['cname' => $customer['company']];
        }
        array_push($info, ['pname' => $customer['name']], ['login' => $customer['login']], ['passwd' => $customer['password']], ['status' => '0'], ['email' => $customer['email']], ['external-id' => $customer['external_id']]);

        return (int) $this->operation('customer', 'add', [['gen_info' => $info]])->id;
    }

    public function setCustomerPassword(int $customerId, #[\SensitiveParameter] string $password): void
    {
        $this->operation('customer', 'set', [['filter' => [['id' => (string) $customerId]]], ['values' => [['gen_info' => [['passwd' => $password]]]]]]);
    }

    /**
     * @return array{id: int, owner_id: int, status: int, name: string}|null
     */
    public function findWebspace(string $domain): ?array
    {
        try {
            $result = $this->operation('webspace', 'get', [['filter' => [['name' => $domain]]], ['dataset' => [['gen_info' => null]]]]);
        } catch (PleskException $exception) {
            if ($exception->errorCode === self::ERROR_NOT_FOUND) {
                return null;
            }
            throw $exception;
        }

        return [
            'id' => (int) $result->id,
            'owner_id' => (int) ($result->data->gen_info->{'owner-id'} ?? 0),
            'status' => (int) ($result->data->gen_info->status ?? 0),
            'name' => (string) ($result->data->gen_info->name ?? $domain),
        ];
    }

    public function createWebspace(string $domain, int $ownerId, string $ip, string $ftpLogin, #[\SensitiveParameter] string $ftpPassword, string $plan): int
    {
        return (int) $this->operation('webspace', 'add', [
            ['gen_setup' => [['name' => $domain], ['owner-id' => (string) $ownerId], ['htype' => 'vrt_hst'], ['ip_address' => $ip], ['status' => '0']]],
            ['hosting' => [['vrt_hst' => [
                ['property' => [['name' => 'ftp_login'], ['value' => $ftpLogin]]],
                ['property' => [['name' => 'ftp_password'], ['value' => $ftpPassword]]],
                ['ip_address' => $ip],
            ]]]],
            ['plan-name' => $plan],
        ])->id;
    }

    public function setWebspaceStatus(int $webspaceId, int $status): void
    {
        $this->operation('webspace', 'set', [['filter' => [['id' => (string) $webspaceId]]], ['values' => [['gen_setup' => [['status' => (string) $status]]]]]]);
    }

    public function deleteWebspace(int $webspaceId): void
    {
        try {
            $this->operation('webspace', 'del', [['filter' => [['id' => (string) $webspaceId]]]]);
        } catch (PleskException $exception) {
            if ($exception->errorCode !== self::ERROR_NOT_FOUND) {
                throw $exception;
            }
        }
    }

    /**
     * Creates a one-time login session for a Plesk user (single sign-on).
     *
     * @return string session ID for /enterprise/rsession_init.php
     */
    public function createSession(string $login, string $userIp): string
    {
        $result = $this->operation('server', 'create_session', [['login' => $login], ['data' => [['user_ip' => base64_encode($userIp)], ['source_server' => '']]]]);
        $id = (string) ($result->id ?? '');
        if (preg_match('/^[A-Za-z0-9]{8,128}$/', $id) !== 1) {
            throw new IntegrationException('Plesk did not return a login session.');
        }

        return $id;
    }

    // ------------------------------------------------------------------

    /**
     * Sends one operation and returns its <result> element.
     *
     * @param list<array<string, mixed>> $body nested [element => value|list|null] structure
     *
     * @throws PleskException|IntegrationException
     */
    private function operation(string $object, string $operation, array $body): SimpleXMLElement
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $packet = $document->createElement('packet');
        $document->appendChild($packet);
        $objectNode = $document->createElement($object);
        $packet->appendChild($objectNode);
        $operationNode = $document->createElement($operation);
        $objectNode->appendChild($operationNode);
        $this->append($document, $operationNode, $body);

        $response = $this->api->request('POST', '/enterprise/control/agent.php', [
            'body' => (string) $document->saveXML(),
            'headers' => ['Content-Type' => 'text/xml', 'Accept' => 'text/xml'],
            'action' => $object . '.' . $operation,
        ]);

        $xml = self::parse($response['body']);
        if (isset($xml->system)) {
            throw new PleskException(sprintf('Plesk API error %d: %s', (int) $xml->system->errcode, mb_substr((string) $xml->system->errtext, 0, 200)), (int) $xml->system->errcode);
        }
        $result = $xml->{$object}->{$operation}->result ?? null;
        if (!$result instanceof SimpleXMLElement || $result->getName() !== 'result') {
            throw new IntegrationException('Unexpected response from the Plesk API.');
        }
        if ((string) $result->status !== 'ok') {
            throw new PleskException(sprintf('Plesk API error %d: %s', (int) $result->errcode, mb_substr((string) $result->errtext, 0, 200)), (int) $result->errcode);
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $children
     */
    private function append(DOMDocument $document, DOMElement $parent, array $children): void
    {
        foreach ($children as $child) {
            foreach ($child as $name => $value) {
                if (preg_match('/^[a-z][a-z0-9_-]*$/', (string) $name) !== 1) {
                    throw new IntegrationException('Invalid XML element name.');
                }
                $element = $document->createElement((string) $name);
                if (is_array($value)) {
                    /** @var list<array<string, mixed>> $value */
                    $this->append($document, $element, $value);
                } elseif ($value !== null) {
                    $element->appendChild($document->createTextNode((string) $value));
                }
                $parent->appendChild($element);
            }
        }
    }

    /**
     * @throws IntegrationException
     */
    public static function parse(string $body): SimpleXMLElement
    {
        if (stripos($body, '<!DOCTYPE') !== false || stripos($body, '<!ENTITY') !== false) {
            throw new IntegrationException('Rejected Plesk response with a document type declaration.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($xml === false) {
            throw new IntegrationException('The Plesk API returned invalid XML.');
        }

        return $xml;
    }
}
