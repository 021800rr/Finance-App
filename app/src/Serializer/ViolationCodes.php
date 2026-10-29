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
 * Gives every API validation violation a stable "code" and its "params", so a client can show its own message
 * without parsing the English "message".
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
        // Not "{{ compared_value }}": compared with another field (propertyPath), it is that field's submitted value.
    ];

    private function __construct()
    {
    }

    /**
     * Rewrites "code" and adds "params" in the normalized violation rows, which API Platform emits one per
     * violation, in list order.
     *
     * @param array<mixed> $rows
     *
     * @return array<mixed>
     */
    public static function apply(array $rows, ConstraintViolationListInterface $violations): array
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row) || !is_int($index) || !$violations->has($index)) {
                continue;
            }

            $violation = $violations->get($index);
            $row['code'] = self::codeFor($violation);
            $row['params'] = ProblemResponse::jsonParams(self::paramsFor($violation));
            $rows[$index] = $row;
        }

        return $rows;
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
