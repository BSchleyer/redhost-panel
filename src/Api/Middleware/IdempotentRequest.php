<?php

declare(strict_types=1);

namespace Nebrix\Api\Middleware;

use Nebrix\Api\ApiException;
use Nebrix\Api\ApiToken;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Http\Middleware\Middleware;
use Nebrix\Http\Request;
use Nebrix\Http\Response;
use PDOException;

/**
 * Optional "Idempotency-Key" header for POST requests (orders, payments,
 * tickets, service actions). The first response for a key is stored for
 * 24 hours per token and replayed for retries with the same request; reusing
 * a key for a different request is rejected, a request still running answers
 * 409. Responses that a retry could change (401, 403, 409, 429, 5xx) are not
 * stored.
 */
final class IdempotentRequest implements Middleware
{
    private const NOT_STORED = [401, 403, 409, 429];

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $token = $request->attribute('api_token');
        if ($key === null || $request->method() !== 'POST' || !$token instanceof ApiToken) {
            return $next($request);
        }
        if (preg_match('/^[A-Za-z0-9_.:-]{8,128}$/', $key) !== 1) {
            throw new ApiException(400, 'invalid_idempotency_key', 'The Idempotency-Key must be 8–128 characters (letters, digits, "_", ".", ":", "-").');
        }

        $keyHash = hash('sha256', $key);
        $requestHash = hash('sha256', $request->method() . ' ' . $request->path() . "\n" . $request->body() . "\n" . json_encode($request->all()));

        try {
            $id = $this->db->insert('nx_api_idempotency', [
                'token_id' => $token->id,
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'created_at' => $this->clock->nowString(),
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            return $this->replay($token->id, $keyHash, $requestHash);
        }

        $response = $next($request);
        $status = $response->status();
        if ($status < 500 && !in_array($status, self::NOT_STORED, true)) {
            $this->db->update('nx_api_idempotency', ['status_code' => $status, 'response' => $response->body()], ['id' => $id]);
        } else {
            $this->db->run('DELETE FROM `nx_api_idempotency` WHERE `id` = ?', [$id]);
        }

        return $response;
    }

    private function replay(int $tokenId, string $keyHash, string $requestHash): Response
    {
        $row = $this->db->fetchOne('SELECT * FROM `nx_api_idempotency` WHERE `token_id` = ? AND `key_hash` = ?', [$tokenId, $keyHash]);
        if ($row === null) {
            throw ApiException::conflict('The request with this Idempotency-Key is still being processed.');
        }
        if (!hash_equals((string) $row['request_hash'], $requestHash)) {
            throw new ApiException(422, 'idempotency_key_reused', 'This Idempotency-Key was already used for a different request.');
        }
        if ($row['status_code'] === null) {
            throw ApiException::conflict('The request with this Idempotency-Key is still being processed.');
        }

        $response = new Response((string) $row['response'], (int) $row['status_code'], ['Content-Type' => 'application/json; charset=UTF-8']);
        $response->setHeader('Idempotent-Replayed', 'true');

        return $response;
    }
}
