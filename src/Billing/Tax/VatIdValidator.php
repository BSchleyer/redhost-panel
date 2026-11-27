<?php

declare(strict_types=1);

namespace Nebrix\Billing\Tax;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Checks EU VAT identification numbers with the European Commission's VIES
 * service (fixed host, no user-controlled URL). Only the VAT ID itself is
 * transmitted.
 */
class VatIdValidator
{
    public const VALID = 'valid';
    public const INVALID = 'invalid';
    public const UNAVAILABLE = 'unavailable';

    private const ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/%s/vat/%s';

    public function __construct(
        private readonly ClientInterface $http,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * "DE 123.456.789" → ["DE", "123456789"], null for anything that is not
     * an EU VAT ID.
     *
     * @return array{0: string, 1: string}|null country prefix and number
     */
    public static function parse(string $vatId): ?array
    {
        $normalized = strtoupper((string) preg_replace('/[\s.\-\/]/', '', $vatId));
        if (preg_match('/^([A-Z]{2})([A-Z0-9+*]{2,12})$/', $normalized, $match) !== 1 || EuVat::countryForPrefix($match[1]) === null) {
            return null;
        }

        return [$match[1], $match[2]];
    }

    /**
     * @return array{status: string, name: string|null}
     */
    public function check(string $vatId): array
    {
        $parsed = self::parse($vatId);
        if ($parsed === null) {
            return ['status' => self::INVALID, 'name' => null];
        }

        try {
            $response = $this->http->request('GET', sprintf(self::ENDPOINT, $parsed[0], rawurlencode($parsed[1])), [
                'timeout' => 10,
                'connect_timeout' => 5,
                'http_errors' => false,
                'allow_redirects' => false,
                'headers' => ['Accept' => 'application/json'],
            ]);
            $data = json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $exception) {
            $this->logger->warning('VIES check failed', ['message' => $exception->getMessage()]);

            return ['status' => self::UNAVAILABLE, 'name' => null];
        }

        if (!is_array($data) || !array_key_exists('isValid', $data)) {
            return ['status' => self::UNAVAILABLE, 'name' => null];
        }
        $error = (string) ($data['userError'] ?? '');
        if ($data['isValid'] === true) {
            $name = is_string($data['name'] ?? null) && !in_array($data['name'], ['', '---'], true) ? mb_substr($data['name'], 0, 255) : null;

            return ['status' => self::VALID, 'name' => $name];
        }
        // "INVALID" is a definite answer; other errors (MS_UNAVAILABLE, TIMEOUT, …) are not.
        return ['status' => $error === 'INVALID' || $error === 'INVALID_INPUT' ? self::INVALID : self::UNAVAILABLE, 'name' => null];
    }
}
