<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use ApiPlatform\OpenApi\Model\Tag;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Runs after LexikJWT (the login) and gesdinet (the refresh) have documented their endpoints, see services.yaml.
 */
final readonly class JwtDecorator implements OpenApiFactoryInterface
{
    /** The refresh bundle documents its check_path as written, and it is the route name (only POST refreshes). */
    private const string REFRESH_ROUTE = 'api_token_refresh';

    private const string REFRESH_DESCRIPTION = 'Returns a new JWT and a new refresh token, in the body and in the '
        .'BEARER and refresh_token cookies. A browser sends no body: the refresh_token cookie carries the token. '
        .'A refresh token works once: the one sent stops working, and so does the JWT the request carried.';

    private const string LOGOUT_DESCRIPTION = 'Deletes the refresh token, refuses the JWT the request carried from '
        .'now on, and clears both cookies. A browser sends no body: the cookies carry the tokens. Other clients send '
        .'the refresh token in the body and the JWT as "Authorization: Bearer <token>".';

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
            description: 'Token obtained from POST /api/login_check or POST /api/token/refresh. A browser sends it '
                .'in the HttpOnly BEARER cookie instead.',
            scheme: 'bearer',
            bearerFormat: 'JWT',
        );

        $openApi = $openApi
            ->withComponents($components->withSecuritySchemes($securitySchemes))
            ->withPaths($this->withTokenPaths($openApi->getPaths()));

        return $openApi->withTags($this->reorderTags($openApi->getTags()));
    }

    private function withTokenPaths(Paths $paths): Paths
    {
        $documented = new Paths();
        foreach ($paths->getPaths() as $path => $pathItem) {
            if (!$pathItem instanceof PathItem) {
                continue;
            }
            $refresh = self::REFRESH_ROUTE === $path ? $pathItem->getPost() : null;
            null === $refresh
                ? $documented->addPath((string) $path, $pathItem)
                : $documented->addPath('/api/token/refresh', $pathItem->withPost(
                    $refresh->withDescription(self::REFRESH_DESCRIPTION)->withRequestBody($this->refreshTokenBody()),
                ));
        }

        $documented->addPath('/api/token/invalidate', new PathItem(post: new Operation(
            operationId: 'api_token_invalidate',
            tags: ['Login Check'],
            responses: ['204' => new Response('Logged out.')],
            summary: 'Logs out: invalidates the refresh token and the JWT.',
            description: self::LOGOUT_DESCRIPTION,
            requestBody: $this->refreshTokenBody(),
        )));

        return $documented;
    }

    /**
     * Optional: the bundle documents the body of the refresh as required, but a browser sends only the cookie.
     */
    private function refreshTokenBody(): RequestBody
    {
        return new RequestBody(
            'The refresh token, unless the refresh_token cookie carries it.',
            new \ArrayObject(['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['refresh_token' => ['type' => 'string']],
            ]]]),
        );
    }

    /**
     * @param array<mixed> $tags
     *
     * @return Tag[]
     */
    private function reorderTags(array $tags): array
    {
        $byName = [];
        foreach ($tags as $tag) {
            if ($tag instanceof Tag) {
                $byName[$tag->getName()] = $tag;
            }
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
