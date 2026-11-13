<?php

declare(strict_types=1);

namespace App\Tests\Translations;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Validation messages are translation keys. A key that is not defined in validators.<locale>.yaml is shown to the
 * user verbatim ("common.amount_cannot_be_zero"), in the web forms as well as in the API. TranslationParityTest only
 * compares the two files with each other, so it cannot notice a key that neither of them defines.
 */
final class ValidatorMessageKeysTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        yield 'pl' => ['pl'];
        yield 'en' => ['en'];
    }

    #[DataProvider('locales')]
    public function testEveryValidationMessageKeyUsedInTheSourceIsDefined(string $locale): void
    {
        $defined = self::definedKeys($locale);
        $used = self::usedKeys();

        self::assertNotSame([], $used, 'The scan found no validation message keys, the pattern is probably outdated.');
        self::assertSame(
            [],
            array_values(array_diff(array_keys($used), $defined)),
            sprintf('Validation message keys missing in validators.%s.yaml (key => first file using it).', $locale),
        );
    }

    /**
     * Keys passed as named message arguments (message:, minMessage:, notInRangeMessage: ...) or to buildViolation().
     *
     * @return array<string, string> key => first file using it
     */
    private static function usedKeys(): array
    {
        $key = '[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+';
        $patterns = [
            "/\\b\\w*[mM]essage\\w*\\s*:\\s*'(".$key.")'/",
            "/buildViolation\\(\\s*'(".$key.")'/",
        ];

        $used = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                dirname(__DIR__, 2).'/src',
                \FilesystemIterator::SKIP_DOTS
            )
        );
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || 'php' !== $file->getExtension()) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $source, $matches);
                foreach ($matches[1] as $usedKey) {
                    $used[$usedKey] ??= $file->getFilename();
                }
            }
        }
        ksort($used);

        return $used;
    }

    /**
     * @return list<string>
     */
    private static function definedKeys(string $locale): array
    {
        $data = Yaml::parseFile(dirname(__DIR__, 2).sprintf('/translations/validators.%s.yaml', $locale));
        self::assertIsArray($data);

        return self::flatten($data, '');
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private static function flatten(array $data, string $prefix): array
    {
        $keys = [];
        foreach ($data as $key => $value) {
            $path = $prefix.$key;
            if (is_array($value)) {
                array_push($keys, ...self::flatten($value, $path.'.'));
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    }
}
