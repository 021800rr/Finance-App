<?php

declare(strict_types=1);

namespace App\Tests\Translations;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class TranslationParityTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function domains(): iterable
    {
        yield 'messages' => ['messages'];
        yield 'validators' => ['validators'];
    }

    #[DataProvider('domains')]
    public function testPolishAndEnglishDefineExactlyTheSameKeys(string $domain): void
    {
        $polish = self::flattenKeys($domain, 'pl');
        $english = self::flattenKeys($domain, 'en');

        self::assertSame([], array_values(array_diff($english, $polish)), 'Keys missing in the Polish file.');
        self::assertSame([], array_values(array_diff($polish, $english)), 'Keys missing in the English file.');
    }

    /**
     * @return list<string>
     */
    private static function flattenKeys(string $domain, string $locale): array
    {
        $data = Yaml::parseFile(dirname(__DIR__, 2).sprintf('/translations/%s.%s.yaml', $domain, $locale));
        self::assertIsArray($data);

        $keys = [];
        $walk = static function (array $node, string $prefix) use (&$walk, &$keys): void {
            foreach ($node as $key => $value) {
                $path = '' === $prefix ? (string) $key : $prefix.'.'.$key;
                if (is_array($value)) {
                    $walk($value, $path);
                } else {
                    $keys[] = $path;
                }
            }
        };
        $walk($data, '');
        sort($keys);

        return $keys;
    }
}
