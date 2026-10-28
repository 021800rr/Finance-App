<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use ApiPlatform\OpenApi\Model\Tag;
use ApiPlatform\OpenApi\OpenApi;

final readonly class JwtDecorator implements OpenApiFactoryInterface
{
    /**
     * Desired order of resources on the API docs page. Anything not listed here
     * (e.g. a newly added resource) is appended alphabetically at the end.
     */
    private const array TAG_ORDER = [
        'Login Check',
        'Summary',
        'Account',
        'AccountOperation',
        'Contractor',
        'Transfer',
        'FixedExpense',
        'FixedExpensePlan',
        'CurrencyExchangeRate',
        'Currency',
        'GoldPrice',
        'GoldAsset',
        // the rest alphabetically (appended automatically below)
    ];

    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $components = $openApi->getComponents();
        $securitySchemes = $components->getSecuritySchemes() ?? new \ArrayObject();

        $securitySchemes['JWT'] = new SecurityScheme(
            type: 'http',
            description: 'Token obtained from POST /api/login_check',
            scheme: 'bearer',
            bearerFormat: 'JWT',
        );

        $openApi = $openApi->withComponents($components->withSecuritySchemes($securitySchemes));

        return $openApi->withTags($this->reorderTags($openApi->getTags()));
    }

    /**
     * @param Tag[] $tags
     *
     * @return Tag[]
     */
    private function reorderTags(array $tags): array
    {
        $byName = [];
        foreach ($tags as $tag) {
            $byName[$tag->getName()] = $tag;
        }

        $ordered = [];
        foreach (self::TAG_ORDER as $name) {
            // Some operations (e.g. /api/login_check) reference a tag by name without API
            // Platform ever registering a top-level Tag object for it. Swagger UI still groups
            // by that name, but it would otherwise be appended after everything we order here.
            // Synthesize a bare Tag so it takes its place in the explicit order too.
            $tag = $byName[$name] ?? new Tag(name: $name);
            $ordered[] = $tag;
            unset($byName[$name]);
        }

        // Anything not explicitly ordered above (e.g. a new resource) goes at the end, alphabetically.
        ksort($byName);
        foreach ($byName as $tag) {
            $ordered[] = $tag;
        }

        return $ordered;
    }
}
