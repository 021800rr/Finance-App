<?php

declare(strict_types=1);

namespace App\Tests\Api;

/**
 * Typed access to decoded JSON for API tests. json_decode() returns mixed, so every step is checked
 * at runtime: a response of the wrong shape fails the test with a clear message instead of a PHP warning.
 */
trait ReadsJsonTrait
{
    /**
     * @return array<string, mixed>
     */
    private static function decodeObject(string $json): array
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Expected a JSON object.');
        }

        return self::stringKeyed($data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function objectAt(array $data, string ...$path): array
    {
        $value = self::valueAt($data, $path);
        if (!is_array($value)) {
            throw new \UnexpectedValueException(self::describe($path, 'an object'));
        }

        return self::stringKeyed($value);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private static function rowsAt(array $data, string ...$path): array
    {
        $value = self::valueAt($data, $path);
        if (!is_array($value)) {
            throw new \UnexpectedValueException(self::describe($path, 'a list of objects'));
        }

        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                throw new \UnexpectedValueException(self::describe($path, 'a list of objects'));
            }
            $rows[] = self::stringKeyed($row);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function stringAt(array $data, string ...$path): string
    {
        $value = self::valueAt($data, $path);
        if (!is_string($value)) {
            throw new \UnexpectedValueException(self::describe($path, 'a string'));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function intAt(array $data, string ...$path): int
    {
        $value = self::valueAt($data, $path);
        if (!is_int($value)) {
            throw new \UnexpectedValueException(self::describe($path, 'an integer'));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     * @param array<string> $path
     */
    private static function valueAt(array $data, array $path): mixed
    {
        $current = $data;
        foreach ($path as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                throw new \UnexpectedValueException(self::describe($path, 'present'));
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param array<mixed> $array
     *
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * @param array<string> $path
     */
    private static function describe(array $path, string $expected): string
    {
        return sprintf('Expected "%s" in the JSON response to be %s.', implode('.', $path), $expected);
    }
}
