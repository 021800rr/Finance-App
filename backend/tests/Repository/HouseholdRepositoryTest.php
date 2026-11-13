<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Household;
use App\Repository\HouseholdRepository;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The summary needs exactly one own-funds household. The database does not enforce it: the households are entered
 * once, before the application is used, so broken data must be reported as such.
 */
final class HouseholdRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private HouseholdRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var HouseholdRepository $repository */
        $repository = $this->em()->getRepository(Household::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testFindOwnFundsHouseholdNameReturnsTheHouseholdFromFixtures(): void
    {
        $result = $this->repository->findOwnFundsHouseholdName();

        self::assertSame('Nasze', $result);
    }

    public function testRefusesSeveralOwnFundsHouseholds(): void
    {
        $this->em()->persist(new Household('Second own funds', isOwnFunds: true));
        $this->em()->flush();

        $this->assertRefusedBecauseOf(NonUniqueResultException::class);
    }

    public function testRefusesWhenNoHouseholdHasOwnFunds(): void
    {
        $this->em()->createQuery(sprintf('UPDATE %s h SET h.isOwnFunds = false', Household::class))->execute();

        $this->assertRefusedBecauseOf(NoResultException::class);
    }

    /**
     * @param class-string<\Throwable> $cause what Doctrine reported about the data
     */
    private function assertRefusedBecauseOf(string $cause): void
    {
        try {
            $this->repository->findOwnFundsHouseholdName();
            self::fail('The own-funds household name was read from broken data.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Expected exactly one own-funds household.', $exception->getMessage());
            self::assertInstanceOf($cause, $exception->getPrevious());
        }
    }
}
