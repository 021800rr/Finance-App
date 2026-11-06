<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\MeProvider;

#[ApiResource(
    shortName: 'Me',
    operations: [
        new Get(
            uriTemplate: '/me',
            openapi: new OpenApiOperation(
                tags: ['Login Check'],
                summary: 'Returns the logged-in user.',
                security: [['JWT' => []]],
            ),
            description: 'The user the JWT (the "Authorization: Bearer" header or the BEARER cookie) belongs to. '
            .'A browser cannot read its HttpOnly cookies, so it asks here whether it is logged in: 200 when it is, '
            .'401 when it is not.',
            provider: MeProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class MeResource
{
    public string $email = '';
}
