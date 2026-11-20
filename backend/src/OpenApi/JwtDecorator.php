<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
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
                .'in the HttpOnly BEARER cookie instead (BearerCookie).',
            scheme: 'bearer',
            bearerFormat: 'JWT',
        );
        $securitySchemes['BearerCookie'] = new SecurityScheme(
            type: 'apiKey',
            description: 'The same JWT in the HttpOnly BEARER cookie the login and the refresh set (Path=/api).',
            name: 'BEARER',
            in: 'cookie',
        );

        $openApi = $openApi
            ->withComponents($components->withSecuritySchemes($securitySchemes))
            ->withPaths($this->withCookieAlternative(
                $this->withoutNullable($this->withTokenPaths($openApi->getPaths())),
            ));

        return $openApi->withTags($this->reorderTags($openApi->getTags()));
    }

    /**
     * OpenAPI 3.1 dropped "nullable" of 3.0 for the type "null", and the document is 3.2; the token bundles still write
     * "nullable": false into their schemas, which a validator refuses. false says no more than the type itself, so it
     * goes; true becomes the type "null".
     */
    private function withoutNullable(Paths $paths): Paths
    {
        $documented = new Paths();
        /** @var array<string, PathItem> $pathItems */
        $pathItems = $paths->getPaths();
        foreach ($pathItems as $path => $pathItem) {
            $documented->addPath($path, $pathItem
                ->withGet($this->operationWithoutNullable($pathItem->getGet()))
                ->withPost($this->operationWithoutNullable($pathItem->getPost()))
                ->withPut($this->operationWithoutNullable($pathItem->getPut()))
                ->withPatch($this->operationWithoutNullable($pathItem->getPatch()))
                ->withDelete($this->operationWithoutNullable($pathItem->getDelete())));
        }

        return $documented;
    }

    private function operationWithoutNullable(?Operation $operation): ?Operation
    {
        if (null === $operation) {
            return null;
        }

        $body = $operation->getRequestBody();
        $bodyContent = $body?->getContent();
        if (null !== $body && null !== $bodyContent) {
            $operation = $operation->withRequestBody($body->withContent(self::contentWithoutNullable($bodyContent)));
        }
        $responses = $operation->getResponses();
        if (null === $responses) {
            return $operation;
        }

        $documented = [];
        foreach ($responses as $status => $response) {
            $content = $response instanceof Response ? $response->getContent() : null;
            $documented[$status] = match (true) {
                $response instanceof Response && null !== $content => $response->withContent(
                    self::contentWithoutNullable($content),
                ),
                $response instanceof Response => $response,
                // The bundles give their responses as plain arrays.
                default => self::schemaWithoutNullable($response),
            };
        }

        return $operation->withResponses($documented);
    }

    /**
     * @param \ArrayObject<string, mixed> $content
     *
     * @return \ArrayObject<string, mixed>
     */
    private static function contentWithoutNullable(\ArrayObject $content): \ArrayObject
    {
        /** @var \ArrayObject<string, mixed> $documented */
        $documented = new \ArrayObject();
        foreach ($content as $mediaType => $media) {
            $schema = $media instanceof MediaType ? $media->getSchema() : null;
            $documented[$mediaType] = $media instanceof MediaType && null !== $schema
                ? $media->withSchema(new \ArrayObject((array) self::schemaWithoutNullable($schema->getArrayCopy())))
                : self::schemaWithoutNullable($media);
        }

        return $documented;
    }

    private static function schemaWithoutNullable(mixed $schema): mixed
    {
        if (!is_array($schema)) {
            return $schema;
        }
        if (array_key_exists('nullable', $schema)) {
            if (true === $schema['nullable'] && is_string($schema['type'] ?? null)) {
                $schema['type'] = [$schema['type'], 'null'];
            }
            unset($schema['nullable']);
        }

        return array_map(self::schemaWithoutNullable(...), $schema);
    }

    /**
     * An address that takes the JWT takes it either way: in the Authorization header or in the BEARER cookie.
     */
    private function withCookieAlternative(Paths $paths): Paths
    {
        $documented = new Paths();
        // Paths::addPath() takes nothing but a PathItem; getPaths() just does not say so in its type.
        /** @var array<string, PathItem> $pathItems */
        $pathItems = $paths->getPaths();
        foreach ($pathItems as $path => $pathItem) {
            $documented->addPath($path, $pathItem
                ->withGet($this->withCookie($pathItem->getGet()))
                ->withPost($this->withCookie($pathItem->getPost()))
                ->withPut($this->withCookie($pathItem->getPut()))
                ->withPatch($this->withCookie($pathItem->getPatch()))
                ->withDelete($this->withCookie($pathItem->getDelete())));
        }

        return $documented;
    }

    private function withCookie(?Operation $operation): ?Operation
    {
        return null !== $operation && [['JWT' => []]] === $operation->getSecurity()
            ? $operation->withSecurity([['JWT' => []], ['BearerCookie' => []]])
            : $operation;
    }

    private function withTokenPaths(Paths $paths): Paths
    {
        $documented = new Paths();
        // Paths::addPath() takes nothing but a PathItem; getPaths() just does not say so in its type.
        /** @var array<string, PathItem> $pathItems */
        $pathItems = $paths->getPaths();
        foreach ($pathItems as $path => $pathItem) {
            $refresh = self::REFRESH_ROUTE === $path ? $pathItem->getPost() : null;
            null === $refresh
                ? $documented->addPath($path, $pathItem)
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
