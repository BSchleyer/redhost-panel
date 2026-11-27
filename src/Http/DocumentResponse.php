<?php

declare(strict_types=1);

namespace Nebrix\Http;

/**
 * Download response for generated documents (invoice PDF/XML).
 */
final class DocumentResponse
{
    public static function make(string $content, string $filename, string $mime): Response
    {
        $response = new Response($content, 200, ['Content-Type' => $mime]);
        $response->setHeader('Content-Disposition', sprintf('attachment; filename="%s"', preg_replace('/[^A-Za-z0-9._-]/', '_', $filename)));
        $response->setHeader('Cache-Control', 'no-store, private');
        // Documents are never rendered as pages.
        $response->setHeader('Content-Security-Policy', "default-src 'none'; sandbox");

        return $response;
    }
}
