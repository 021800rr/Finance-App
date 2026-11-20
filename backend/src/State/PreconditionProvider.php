<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Http\Preconditions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

/**
 * Evaluates If-Match and If-None-Match on an API Platform operation other than GET or HEAD (Preconditions): when one
 * fails, the request is refused with 412 and nothing changes. GET and HEAD: ConditionalGetSubscriber; the login and
 * token addresses, outside API Platform: PreconditionSubscriber.
 *
 * @implements ProviderInterface<object>
 */
final readonly class PreconditionProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(private ProviderInterface $decorated, private Preconditions $preconditions)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return object|array<mixed>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // After the record is loaded: errors found before the request content is processed come first, so a missing
        // record stays 404 (RFC 9110, section 13.2.1).
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        $request = $context['request'] ?? null;
        if (
            $request instanceof Request
            && !$request->isMethodCacheable()
            // A missing or unsupported Content-Type, which API Platform answers with 415, comes first as well.
            && !($operation instanceof HttpOperation && $operation->canDeserialize()
                && null === $request->attributes->get('input_format'))
            && null !== $refusal = $this->preconditions->refusal($request)
        ) {
            throw new PreconditionFailedHttpException($refusal);
        }

        return $data;
    }
}
