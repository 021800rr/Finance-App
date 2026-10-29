<?php

declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\State\ApiResource\Error;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Exception\BusinessError;
use App\Http\ProblemResponse;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Gives every API error response (RFC 9457 Problem Details) a stable "errorCode" and its "params", next to the
 * English "detail" meant for curl/Postman. A client builds its own message from the code and the params.
 *
 * - validation errors: "validation.failed"; each violation gets its own code and params (see ViolationCodes);
 * - a refusal (4xx) caused by a BusinessError (kept as the previous exception of the HTTP exception thrown by
 *   the processor): its code and params, e.g. "transfer.accounts_must_differ";
 * - anything else: "http." plus the status name, e.g. "http.not_found", "http.internal_server_error".
 *
 * "type" is derived from the code, so the two cannot drift apart; "title" is the HTTP status name.
 *
 * Works for every error format (JSON-LD, JSON, Problem JSON): it runs before API Platform's own error
 * normalizers, lets the serializer normalize the error as usual and then completes the result.
 */
final class ProblemNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'app_problem_normalizer_called';

    /**
     * @param array<string, mixed> $context
     */
    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): array|string|int|float|bool|\ArrayObject|null {
        $context[self::ALREADY_CALLED] = true;
        $context[AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS] = true;
        $normalized = $this->normalizer->normalize($data, $format, $context);
        if (!is_array($normalized) || !is_int($normalized['status'] ?? null)) {
            return $normalized;
        }

        $status = $normalized['status'];
        [$errorCode, $params] = $this->codeAndParams($data, $status);
        // Existing keys (type, title) keep their place, new ones go last: @context stays first in JSON-LD.
        foreach (ProblemResponse::fields($status, $errorCode, $params) as $key => $value) {
            $normalized[$key] = $value;
        }

        if ($data instanceof ValidationException && is_array($normalized['violations'] ?? null)) {
            $normalized['violations'] = ViolationCodes::apply(
                $normalized['violations'],
                $data->getConstraintViolationList(),
            );
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return ($data instanceof Error || $data instanceof ValidationException)
            && !isset($context[self::ALREADY_CALLED]);
    }

    /**
     * Not cacheable: support depends on the context (the second, inner call must be skipped).
     *
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [Error::class => false, ValidationException::class => false];
    }

    /**
     * @return array{string, array<string, string>}
     */
    private function codeAndParams(mixed $data, int $status): array
    {
        if ($data instanceof ValidationException) {
            return ['validation.failed', []];
        }

        // A server error never carries a business code or its params, even if its cause chain holds one.
        if ($data instanceof Error && $status < 500) {
            for ($previous = $data->getPrevious(); null !== $previous; $previous = $previous->getPrevious()) {
                if ($previous instanceof BusinessError) {
                    return [$previous->getErrorCode(), $previous->getErrorParams()];
                }
            }
        }

        return [ProblemResponse::httpErrorCode($status), []];
    }
}
