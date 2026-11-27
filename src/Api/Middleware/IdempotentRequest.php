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
    /** Request attribute a controller sets for responses that must not be kept (one-time login URLs). */
    public const DO_NOT_STORE = '_idempotency_do_not_store';

    private const NOT_STORED = [401, 403, 409, 429];
    /** A request still "running" after this many seconds is treated as abandoned (crash, timeout). */
    private const ABANDONED_AFTER = 300;

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
        // serialize() cannot fail (unlike json_encode on invalid UTF-8), so every input part is covered.
        $requestHash = hash('sha256', serialize([$request->method(), $request->path(), $request->body(), $request->all()]));

        $id = $this->claim($token->id, $keyHash, $requestHash);
        if ($id === null) {
            return $this->replay($token->id, $keyHash, $requestHash);
        }

        $response = $next($request);
        $status = $response->status();
        if ($status < 500 && !in_array($status, self::NOT_STORED, true) && $request->attribute(self::DO_NOT_STORE) !== true) {
            $this->db->update('nx_api_idempotency', ['status_code' => $status, 'response' => $response->body()], ['id' => $id]);
        } else {
            $this->db->run('DELETE FROM `nx_api_idempotency` WHERE `id` = ?', [$id]);
        }

        return $response;
    }

    /**
     * Inserts the record for the key; returns null when it already exists.
     * A record of an abandoned request (no response after 5 minutes) is
     * replaced so the client can retry.
     */
    private function claim(int $tokenId, string $keyHash, string $requestHash): ?int
    {
        $now = $this->clock->now();
        $this->db->run(
            'DELETE FROM `nx_api_idempotency` WHERE `token_id` = ? AND `key_hash` = ? AND `status_code` IS NULL AND `created_at` < ?',
            [$tokenId, $keyHash, $now->modify('-' . self::ABANDONED_AFTER . ' seconds')->format('Y-m-d H:i:s')],
        );
        try {
            return $this->db->insert('nx_api_idempotency', [
                'token_id' => $tokenId,
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            return null;
        }
    }

    private function replay(int $tokenId, string $keyHash, string $requestHash): Response
    {
        $row = $this->db->fetchOne('SELECT * FROM `nx_api_idempotency` WHERE `token_id` = ? AND `key_hash` = ?', [$tokenId, $keyHash]);
        if ($row === null || $row['status_code'] === null) {
            if ($row !== null && !hash_equals((string) $row['request_hash'], $requestHash)) {
                throw new ApiException(422, 'idempotency_key_reused', 'This Idempotency-Key was already used for a different request.');
            }
            throw ApiException::conflict('The request with this Idempotency-Key is still being processed.');
        }
        if (!hash_equals((string) $row['request_hash'], $requestHash)) {
            throw new ApiException(422, 'idempotency_key_reused', 'This Idempotency-Key was already used for a different request.');
        }

        $response = new Response((string) $row['response'], (int) $row['status_code'], ['Content-Type' => 'application/json; charset=UTF-8']);
        $response->setHeader('Idempotent-Replayed', 'true');

        return $response;
    }
}
