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
 * Gives every API error response (RFC 9457 Problem Details) its problem type, the type's title and extension
 * members, next to the English "detail" meant for curl/Postman. A client tells the problems apart by "type"
 * (section 3.1.1) and builds its own message from the members.
 *
 * - validation errors: "validation.failed", with "errors": one item per violation with its own code, its params and
 *   where it is: "pointer" into the content (JSON Pointer, RFC 6901), or "parameter" of the query (see
 *   ViolationCodes);
 * - a refusal (4xx) caused by a BusinessError (kept as the previous exception of the HTTP exception thrown by
 *   the processor): its own type, title and members, e.g. "transfer.amount_to_required_for_currency_exchange" with
 *   "fromCurrency" and "toCurrency";
 * - anything else: "http." plus the status name, e.g. "http.not_found", titled with the status name.
 *
 * No stack trace, not even in debug mode: "detail" helps the client correct the request rather than giving
 * debugging information (section 3.1.4); the logs keep the trace.
 *
 * Works for every error format: it runs before API Platform's own error normalizers, lets the serializer normalize
 * the error as usual and then completes the result.
 */
final class ProblemNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'app_problem_normalizer_called';

    /** What API Platform adds that the contract replaces or leaves out. */
    private const array REPLACED = ['type', 'title', 'status', 'detail', 'trace', 'violations', 'instance'];

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
        [$code, $title, $extensions] = $this->problemType($data, $status);
        $fields = ProblemResponse::fields($status, $code, $extensions, $title);

        $problem = [
            'type' => $fields['type'],
            'title' => $fields['title'],
            'status' => $status,
            // A server error names nothing of its cause, which in debug mode API Platform puts here.
            'detail' => $status < 500 && is_string($normalized['detail'] ?? null)
                ? $normalized['detail']
                : $fields['title'],
        ] + $fields;
        if ($data instanceof ValidationException && is_array($normalized['violations'] ?? null)) {
            // QueryValidationStatusSubscriber gives a violation of the query parameters 400, of the content 422.
            $problem['errors'] = ViolationCodes::errors(
                $normalized['violations'],
                $data->getConstraintViolationList(),
                inQuery: 400 === $status,
            );
        }

        return $problem + array_diff_key($normalized, array_flip(self::REPLACED));
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
     * @return array{string, string|null, array<string, string>} the code, the title (null: the status name) and
     *                                                           the extension members of the problem type
     */
    private function problemType(mixed $data, int $status): array
    {
        if ($data instanceof ValidationException) {
            return ['validation.failed', 'Validation failed', []];
        }

        // A server error never carries a business type or its members, even if its cause chain holds one.
        if ($data instanceof Error && $status < 500) {
            for ($previous = $data->getPrevious(); null !== $previous; $previous = $previous->getPrevious()) {
                if ($previous instanceof BusinessError) {
                    return [$previous->getErrorCode(), $previous->getErrorTitle(), $previous->getErrorParams()];
                }
            }
        }

        return [ProblemResponse::httpErrorCode($status), null, []];
    }
}
