<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * A refresh token of the API. Its fields and their mapping come from gesdinet/jwt-refresh-token-bundle; only the
 * hash of the token is stored, and every refresh replaces it with a new one of the same family.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_token')]
class RefreshToken extends BaseRefreshToken
{
}
