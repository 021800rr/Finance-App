<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Refuses a request body that is not a JSON object, right before API Platform reads it. On its own it would answer
 * "Syntax error", name a PHP class (body: 5), or treat a list as an object with no fields.
 *
 * @implements ProviderInterface<object>
 */
final readonly class RequestBodyCheckProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(private ProviderInterface $decorated)
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
        // Runs after the record is loaded, so a missing record stays 404 whatever the body contains.
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        $request = $context['request'] ?? null;
        if (
            $operation instanceof HttpOperation
            && $operation->canDeserialize()
            && $request instanceof Request
            // Not set for a missing or unsupported Content-Type, which API Platform answers with 415.
            && null !== $request->attributes->get('input_format')
        ) {
            $this->assertJsonObject($request->getContent());
        }

        return $data;
    }

    private function assertJsonObject(string $content): void
    {
        try {
            $body = json_decode($content, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('The request body is not valid JSON.', $exception);
        }

        if (!$body instanceof \stdClass) {
            throw new BadRequestHttpException('The request body must be a JSON object.');
        }
    }
}
