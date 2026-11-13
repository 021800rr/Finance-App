<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use App\State\MeProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The API asks for a login before GET /api/me reaches the provider (MeApiTest); these call it directly.
 */
final class MeProviderTest extends TestCase
{
    public function testNamesTheLoggedInUser(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new InMemoryUser('user@example.com', null));

        $me = (new MeProvider($security))->provide(new Get());

        self::assertSame('user@example.com', $me->email);
    }

    /**
     * Without a login the API answers 401 before; reaching the provider so would be a mistake in the configuration.
     */
    public function testRefusesToRunWithoutALoggedInUser(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $this->expectException(\LogicException::class);

        (new MeProvider($security))->provide(new Get());
    }
}
