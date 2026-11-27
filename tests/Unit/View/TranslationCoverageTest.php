<?php

declare(strict_types=1);

namespace Nebrix\Tests\Unit\View;

use Nebrix\Api\ApiScope;
use Nebrix\Authorization\Permission;
use Nebrix\Catalog\ProductCategory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every interface text has a German translation: strings translated in the
 * templates, flash messages of the controllers and the labels of settings,
 * product fields, permissions, API scopes and e-mail templates.
 */
final class TranslationCoverageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** @var array<string, string> */
    private array $german;

    protected function setUp(): void
    {
        $this->german = require self::ROOT . '/resources/lang/de.php';
    }

    public function testTemplateAndControllerTextsAreTranslated(): void
    {
        $texts = [];
        foreach ($this->files(self::ROOT . '/resources/views', '.twig') as $file) {
            if (str_contains($file, '/pdf/')) {
                continue; // PDF labels are localized in InvoiceDocuments
            }
            $source = (string) file_get_contents($file);
            preg_match_all("/'((?:[^'\\\\]|\\\\.)+)'\\s*\\|\\s*t\\b/", $source, $filters);
            preg_match_all("/f\\.(?:input|select|textarea|checkbox|secret|multiselect)\\(\\s*'[^']*'\\s*,\\s*'((?:[^'\\\\]|\\\\.)+)'/", $source, $labels);
            preg_match_all("/help:\\s*'((?:[^'\\\\]|\\\\.)+)'/", $source, $helps);
            foreach ([...$filters[1], ...$labels[1], ...$helps[1]] as $text) {
                $texts[stripslashes($text)] = basename($file);
            }
        }
        foreach ($this->files(self::ROOT . '/src/Controller', '.php') as $file) {
            preg_match_all("/flash\\(\\\$request,\\s*'[a-z]+',\\s*'((?:[^'\\\\]|\\\\.)+)'/", (string) file_get_contents($file), $flashes);
            foreach ($flashes[1] as $text) {
                $texts[stripslashes($text)] = basename($file);
            }
        }

        $this->assertTranslated($texts);
    }

    public function testDefinitionLabelsAreTranslated(): void
    {
        $texts = [];
        $add = static function (mixed $text, string $source) use (&$texts): void {
            if (is_string($text) && preg_match('/[a-z]/', $text) === 1) {
                $texts[$text] = $source;
            }
        };
        foreach (require self::ROOT . '/config/settings.php' as $key => $definition) {
            $add($definition['label'] ?? null, $key);
            $add($definition['help'] ?? null, $key);
            foreach ($definition['options'] ?? [] as $option) {
                $add($option, $key);
            }
        }
        foreach (ProductCategory::all() as $category => $definition) {
            $add($definition['label'] ?? null, $category);
            foreach ([...($definition['config'] ?? []), ...($definition['options'] ?? [])] as $field) {
                $add($field['label'] ?? null, $category);
                $add($field['help'] ?? null, $category);
            }
        }
        foreach (Permission::grouped() as $group => $permissions) {
            $add($group, 'permissions');
            foreach ($permissions as $description) {
                $add($description, 'permissions');
            }
        }
        foreach ([...ApiScope::customer(), ...ApiScope::admin()] as $description) {
            $add($description, 'api scopes');
        }
        foreach (require self::ROOT . '/config/mail_templates.php' as $key => $template) {
            $add($template['name'] ?? null, $key);
        }

        $this->assertTranslated($texts);
    }

    /**
     * @param array<string, string> $texts text => where it is used
     */
    private function assertTranslated(array $texts): void
    {
        $missing = [];
        foreach ($texts as $text => $source) {
            if (!isset($this->german[$text])) {
                $missing[] = $text . '  (' . $source . ')';
            }
        }
        self::assertSame([], $missing, 'Add these texts to resources/lang/de.php');
    }

    /**
     * @return list<string>
     */
    private function files(string $directory, string $suffix): array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if (str_ends_with($file->getFilename(), $suffix)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
