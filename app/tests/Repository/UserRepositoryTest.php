<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private UserRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var UserRepository $repository */
        $repository = self::getContainer()->get(UserRepository::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testFindOneByEmailIgnoresCaseAndSurroundingSpaces(): void
    {
        $user = $this->persistUser('Stored@Example.com');

        self::assertSame($user, $this->repository->findOneByEmail('  STORED@example.COM '));
    }

    public function testFindOneByEmailReturnsNullForAnUnknownAddress(): void
    {
        self::assertNull($this->repository->findOneByEmail('nobody@example.com'));
    }

    public function testFindOneByEmailReturnsNullForAnAddressThatCanNeverBeStored(): void
    {
        self::assertNull($this->repository->findOneByEmail('not an address'));
        self::assertNull($this->repository->findOneByEmail(''));
    }

    public function testLoadUserByIdentifierUsesTheSameNormalizedLookup(): void
    {
        $user = $this->persistUser('login@example.com');

        self::assertSame($user, $this->repository->loadUserByIdentifier(' Login@Example.com'));
        self::assertNull($this->repository->loadUserByIdentifier('other@example.com'));
    }

    private function persistUser(string $email): User
    {
        $user = new User($email, 'hash');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }
}
