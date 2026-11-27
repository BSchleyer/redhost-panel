<?php

declare(strict_types=1);

namespace Nebrix\Tests\Unit\Settings;

use Nebrix\Settings\BrandingAssets;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BrandingAssetsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/nebrix-branding-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/branding/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir . '/branding');
        @rmdir($this->dir);
    }

    private function tempFile(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return ['tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'name' => 'evil.php', 'type' => 'image/png'];
    }

    /** 1×1 transparent PNG */
    private function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);
    }

    public function testStoresValidPngWithRandomNameAndDetectedExtension(): void
    {
        $path = (new BrandingAssets($this->dir))->store($this->tempFile($this->png()), 'logo', false);

        self::assertMatchesRegularExpression('#^/uploads/branding/logo-[a-f0-9]{16}\.png$#', $path);
        self::assertFileExists($this->dir . '/branding/' . basename($path));
    }

    public function testRejectsSvgAndScripts(): void
    {
        $assets = new BrandingAssets($this->dir);

        foreach (['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', '<?php system($_GET["c"]);'] as $content) {
            try {
                $assets->store($this->tempFile($content), 'logo', false);
                self::fail('Upload should have been rejected.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Only PNG, JPEG, WebP or ICO', $exception->getMessage());
            }
        }
    }

    public function testRejectsPolyglotWithFakeHeader(): void
    {
        $this->expectException(RuntimeException::class);
        // Starts with the PNG magic bytes but is not a parseable image.
        (new BrandingAssets($this->dir))->store($this->tempFile("\x89PNG\r\n\x1a\n<?php echo 1; ?>"), 'favicon', false);
    }

    public function testRequiresARealHttpUploadByDefault(): void
    {
        $this->expectException(RuntimeException::class);
        (new BrandingAssets($this->dir))->store($this->tempFile($this->png()), 'logo');
    }

    public function testDeleteIgnoresPathsOutsideTheBrandingDirectory(): void
    {
        $outside = $this->dir . '/keep.txt';
        file_put_contents($outside, 'x');

        (new BrandingAssets($this->dir))->delete('/uploads/branding/../../keep.txt');

        self::assertFileExists($outside);
        unlink($outside);
    }
}
