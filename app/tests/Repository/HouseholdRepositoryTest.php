<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Household;
use App\Repository\HouseholdRepository;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
}
