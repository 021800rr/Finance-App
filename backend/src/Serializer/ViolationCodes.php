<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Http\ProblemResponse;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Turns API Platform's violation rows into the items of "errors" (the shape of RFC 9457's example): "detail", where
 * the violation is ("pointer" into the content as a JSON Pointer, RFC 6901, or "parameter" of the query), a stable
 * "code" and its "params", so a client can show its own message without parsing the English "detail".
 *
 * API Platform puts Symfony's error UUID into "code" (e.g. the same one for every NotNull), which does not tell
 * the fields apart. The code is our translation key when the constraint uses one
 * (message: 'transfer.to_account_required'), otherwise "validation." plus the Symfony error name
 * (Type::INVALID_TYPE_ERROR → "validation.invalid_type").
 * Only whitelisted parameters are exposed, as strings: never the submitted value.
 */
final class ViolationCodes
{
    private const string OWN_KEY_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/';
    private const string FALLBACK_CODE = 'validation.invalid';

    /** Symfony placeholder => public parameter name */
    private const array PUBLIC_PARAMETERS = [
        '{{ limit }}' => 'limit',
        '{{ min }}' => 'min',
        '{{ max }}' => 'max',
        '{{ type }}' => 'type',
        // Not "{{ compared_value }}": compared with another field (propertyPath), it is that field's submitted value.
    ];

    /**
     * @codeCoverageIgnore The class has only static members: no instance is ever made, so this never runs.
     */
    private function __construct()
    {
    }

    /**
     * The items of "errors", from the normalized violation rows, which API Platform emits one per violation, in list
     * order.
     *
     * @param array<mixed> $rows
     * @param bool         $inQuery the violations are of query parameters, not of the content
     *
     * @return list<array<string, mixed>>
     */
    public static function errors(array $rows, ConstraintViolationListInterface $violations, bool $inQuery): array
    {
        $errors = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || !is_int($index) || !$violations->has($index)) {
                continue;
            }

            $violation = $violations->get($index);
            $path = $violation->getPropertyPath();
            $errors[] = [
                'detail' => (string) $violation->getMessage(),
                ...($inQuery ? ['parameter' => $path] : ['pointer' => self::pointer($path)]),
                'code' => self::codeFor($violation),
                'params' => ProblemResponse::jsonParams(self::paramsFor($violation)),
            ];
        }

        return $errors;
    }

    /**
     * A property path of the validator ("lines[0].amount") as a JSON Pointer in its URI fragment form, as in RFC
     * 9457's example ("#/lines/0/amount"); "#" is the whole content (RFC 6901, sections 4 to 6).
     */
    public static function pointer(string $propertyPath): string
    {
        $tokens = preg_split('/\.|\[|\]/', $propertyPath, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return '#'.implode('', array_map(
            static fn (string $token): string => '/'.rawurlencode(strtr($token, ['~' => '~0', '/' => '~1'])),
            $tokens,
        ));
    }

    private static function codeFor(ConstraintViolationInterface $violation): string
    {
        $template = $violation->getMessageTemplate();
        if (1 === preg_match(self::OWN_KEY_PATTERN, $template)) {
            return $template;
        }

        $errorCode = $violation->getCode();
        if (null === $errorCode) {
            return self::FALLBACK_CODE;
        }

        $constraint = $violation instanceof ConstraintViolation ? $violation->getConstraint() : null;
        try {
            $errorName = null !== $constraint ? $constraint::getErrorName($errorCode) : Type::getErrorName($errorCode);
        } catch (InvalidArgumentException) {
            return self::FALLBACK_CODE;
        }

        return 'validation.'.strtolower((string) preg_replace('/_ERROR$/', '', $errorName));
    }

    /**
     * @return array<string, string>
     */
    private static function paramsFor(ConstraintViolationInterface $violation): array
    {
        $params = [];
        foreach ($violation->getParameters() as $placeholder => $value) {
            if (isset(self::PUBLIC_PARAMETERS[$placeholder]) && is_scalar($value)) {
                $params[self::PUBLIC_PARAMETERS[$placeholder]] = (string) $value;
            }
        }

        return $params;
    }
}
