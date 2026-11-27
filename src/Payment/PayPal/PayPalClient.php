<?php

declare(strict_types=1);

namespace Nebrix\Payment\PayPal;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/**
 * PayPal REST API (Orders v2, Payments v2, Notifications v1).
 * https://developer.paypal.com/docs/api/orders/v2/
 */
class PayPalClient
{
    private ?string $token = null;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
        private readonly bool $sandbox,
    ) {
    }

    public function baseUrl(): string
    {
        return $this->sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    public function accessToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new PayPalException('PayPal credentials are not configured.');
        }

        $response = $this->send('POST', '/v1/oauth2/token', [
            'auth' => [$this->clientId, $this->clientSecret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ], false);

        $token = $response['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new PayPalException('PayPal did not return an access token.');
        }

        return $this->token = $token;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function createOrder(array $order, string $requestId): array
    {
        return $this->send('POST', '/v2/checkout/orders', ['json' => $order, 'headers' => ['PayPal-Request-Id' => $requestId, 'Prefer' => 'return=representation']]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(string $orderId): array
    {
        return $this->send('GET', '/v2/checkout/orders/' . $this->id($orderId));
    }

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(string $orderId): array
    {
        // A stable request ID makes repeated capture attempts idempotent on PayPal's side.
        return $this->send('POST', '/v2/checkout/orders/' . $this->id($orderId) . '/capture', [
            'headers' => ['PayPal-Request-Id' => 'capture-' . $orderId, 'Prefer' => 'return=representation', 'Content-Type' => 'application/json'],
            'body' => '{}',
        ]);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function refundCapture(string $captureId, array $body, string $requestId): array
    {
        return $this->send('POST', '/v2/payments/captures/' . $this->id($captureId) . '/refund', ['json' => $body, 'headers' => ['PayPal-Request-Id' => $requestId, 'Prefer' => 'return=representation']]);
    }

    /**
     * Verifies a webhook through PayPal's verification API.
     *
     * The event is forwarded exactly as received: re-encoding the decoded
     * JSON could change escaping or number formatting and make PayPal's
     * comparison fail.
     *
     * @param array<string, string> $headers transmission headers (lower-case names)
     * @param string $rawEvent webhook request body as received (a JSON object)
     */
    public function verifyWebhookSignature(array $headers, string $webhookId, string $rawEvent): bool
    {
        if (!is_array(json_decode($rawEvent, true)) || !str_starts_with(ltrim($rawEvent), '{')) {
            return false;
        }
        foreach (['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'] as $required) {
            if (($headers[$required] ?? '') === '') {
                return false;
            }
        }
        if ($webhookId === '') {
            return false;
        }

        $placeholder = '"__nebrix_webhook_event_' . bin2hex(random_bytes(8)) . '__"';
        $envelope = json_encode([
            'auth_algo' => $headers['paypal-auth-algo'],
            'cert_url' => $headers['paypal-cert-url'],
            'transmission_id' => $headers['paypal-transmission-id'],
            'transmission_sig' => $headers['paypal-transmission-sig'],
            'transmission_time' => $headers['paypal-transmission-time'],
            'webhook_id' => $webhookId,
            'webhook_event' => trim($placeholder, '"'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $response = $this->send('POST', '/v1/notifications/verify-webhook-signature', [
            'body' => str_replace($placeholder, trim($rawEvent), $envelope),
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        return ($response['verification_status'] ?? '') === 'SUCCESS';
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $options = [], bool $authenticate = true): array
    {
        $options['headers'] = ($options['headers'] ?? []) + ['Accept' => 'application/json'];
        if ($authenticate) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->accessToken();
        }
        $options += ['timeout' => 30, 'connect_timeout' => 10, 'http_errors' => false, 'allow_redirects' => false, 'verify' => true];

        try {
            $response = $this->http->request($method, $this->baseUrl() . $path, $options);
        } catch (GuzzleException $exception) {
            throw new PayPalException('PayPal is not reachable: ' . mb_substr($exception->getMessage(), 0, 200), 0, null, $exception);
        }

        $status = $response->getStatusCode();
        $json = json_decode((string) $response->getBody(), true);
        $json = is_array($json) ? $json : [];

        if ($status >= 400) {
            $issue = (string) ($json['details'][0]['issue'] ?? $json['name'] ?? $json['error'] ?? 'UNKNOWN');
            $description = (string) ($json['details'][0]['description'] ?? $json['message'] ?? $json['error_description'] ?? '');

            throw new PayPalException(trim(sprintf('PayPal error %d %s %s', $status, $issue, $description)), $status, $issue);
        }

        return $json;
    }

    private function id(string $id): string
    {
        if (preg_match('/^[A-Z0-9-]{5,64}$/i', $id) !== 1) {
            throw new PayPalException('Invalid PayPal identifier.');
        }

        return $id;
    }
}
