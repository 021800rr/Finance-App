<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFactoryTest extends KernelTestCase
{
    public function testCreatedUserHoldsAHashThatVerifiesAgainstThePlainPassword(): void
    {
        $user = $this->factory()->create('New@Example.com', 'correct horse');

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        self::assertNotSame('correct horse', $user->getPassword());
        self::assertTrue($hasher->isPasswordValid($user, 'correct horse'));
        self::assertFalse($hasher->isPasswordValid($user, 'wrong'));
        self::assertSame('new@example.com', $user->getEmail());
    }

    public function testTwoUsersWithTheSamePasswordGetDifferentHashes(): void
    {
        $first = $this->factory()->create('a@example.com', 'same password');
        $second = $this->factory()->create('b@example.com', 'same password');

        self::assertNotSame($first->getPassword(), $second->getPassword());
    }

    private function factory(): UserFactory
    {
        $factory = self::getContainer()->get(UserFactory::class);
        self::assertInstanceOf(UserFactory::class, $factory);

        return $factory;
    }
}
