<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Http\ProblemResponse;

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
     * A whole list of the API, a JSON array of objects.
     *
     * @return list<array<string, mixed>>
     */
    private static function decodeList(string $json): array
    {
        return self::rowsAt(['rows' => json_decode($json, true, flags: JSON_THROW_ON_ERROR)], 'rows');
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
     * @param array<mixed>  $data
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

    /**
     * The code of a problem: its type without "urn:finance-app:problem:", or null for another type.
     *
     * @param array<string, mixed> $problem
     */
    private static function problemCode(array $problem): ?string
    {
        $type = $problem['type'] ?? null;

        return is_string($type) && str_starts_with($type, ProblemResponse::TYPE_PREFIX)
            ? substr($type, strlen(ProblemResponse::TYPE_PREFIX))
            : null;
    }

    /**
     * The extension members of a problem (RFC 9457, section 3.2): all but the standard ones and "errors".
     *
     * @param array<string, mixed> $problem
     *
     * @return array<string, mixed>
     */
    private static function problemMembers(array $problem): array
    {
        return array_diff_key($problem, array_flip(['type', 'title', 'status', 'detail', 'instance', 'errors']));
    }

    /**
     * The items of "errors" of a validation problem, each also with "propertyPath" (the property its "pointer" names,
     * "lines[0].amount" for "#/lines/0/amount", or its query "parameter") and "message" (its "detail").
     *
     * @param array<string, mixed> $problem
     *
     * @return list<array<string, mixed>>
     */
    private static function fieldErrors(array $problem): array
    {
        $errors = [];
        foreach (self::rowsAt($problem, 'errors') as $error) {
            $pointer = $error['pointer'] ?? null;
            $path = is_string($pointer)
                ? (string) preg_replace('/\.(\d+)(?=\.|$)/', '[$1]', implode('.', array_map(
                    static fn (string $token): string => strtr(rawurldecode($token), ['~1' => '/', '~0' => '~']),
                    array_slice(explode('/', $pointer), 1),
                )))
                : ($error['parameter'] ?? null);
            $errors[] = ['propertyPath' => $path, 'message' => $error['detail'] ?? null] + $error;
        }

        return $errors;
    }
}
