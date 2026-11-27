<?php

declare(strict_types=1);

namespace Nebrix\Settings;

use RuntimeException;

/**
 * Validates and stores uploaded logo/favicon files.
 *
 * - Only PNG, JPEG, WebP and ICO, detected from the file content (finfo +
 *   image header parsing) – never from the client-supplied name or type.
 * - SVG is rejected because it can carry scripts.
 * - Files get a random name with an extension derived from the detected
 *   type and are stored in public/uploads/branding, where the web server
 *   configuration disables script execution.
 */
class BrandingAssets
{
    private const MAX_BYTES = 1_048_576;

    private const TYPES = [
        'image/png' => ['png', IMAGETYPE_PNG],
        'image/jpeg' => ['jpg', IMAGETYPE_JPEG],
        'image/webp' => ['webp', IMAGETYPE_WEBP],
        'image/x-icon' => ['ico', IMAGETYPE_ICO],
        'image/vnd.microsoft.icon' => ['ico', IMAGETYPE_ICO],
    ];

    public function __construct(private readonly string $uploadPath)
    {
    }

    /**
     * @param array<string, mixed> $file entry from $_FILES
     * @param bool $requireHttpUpload false only in tests
     * @return string public path, e.g. /uploads/branding/logo-9f2c….png
     *
     * @throws RuntimeException with a user-safe message
     */
    public function store(array $file, string $kind, bool $requireHttpUpload = true): string
    {
        if (!in_array($kind, ['logo', 'favicon'], true)) {
            throw new RuntimeException('Unknown asset type.');
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($error === UPLOAD_ERR_NO_FILE ? 'Please choose a file.' : 'The upload failed.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp) || ($requireHttpUpload && !is_uploaded_file($tmp))) {
            throw new RuntimeException('The upload failed.');
        }

        $size = filesize($tmp);
        if ($size === false || $size === 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('The file must be smaller than 1 MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !isset(self::TYPES[$mime])) {
            throw new RuntimeException('Only PNG, JPEG, WebP or ICO images are allowed.');
        }

        [$extension, $imageType] = self::TYPES[$mime];
        $info = @getimagesize($tmp);
        if ($info === false || $info[2] !== $imageType || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096) {
            throw new RuntimeException('The file is not a valid image (max. 4096×4096 pixels).');
        }

        $directory = rtrim($this->uploadPath, '/') . '/branding';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('The upload directory is not writable.');
        }

        $name = sprintf('%s-%s.%s', $kind, bin2hex(random_bytes(8)), $extension);
        $target = $directory . '/' . $name;
        $moved = $requireHttpUpload ? move_uploaded_file($tmp, $target) : rename($tmp, $target);
        if (!$moved) {
            throw new RuntimeException('The file could not be saved.');
        }
        chmod($target, 0640);

        return '/uploads/branding/' . $name;
    }

    /** Deletes a previously stored asset; paths outside the branding directory are ignored. */
    public function delete(string $publicPath): void
    {
        if (preg_match('#^/uploads/branding/(logo|favicon)-[a-f0-9]{16}\.(png|jpg|webp|ico)$#', $publicPath) !== 1) {
            return;
        }

        $file = rtrim($this->uploadPath, '/') . '/branding/' . basename($publicPath);
        if (is_file($file)) {
            unlink($file);
        }
    }
}
