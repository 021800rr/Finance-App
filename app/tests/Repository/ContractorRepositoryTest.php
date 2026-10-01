<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Contractor;
use App\Repository\ContractorRepository;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ContractorRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private ContractorRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var ContractorRepository $repository */
        $repository = $this->em()->getRepository(Contractor::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testFindOneByNameReturnsMatchingContractor(): void
    {
        $contractor = $this->createContractor('Unique Name ' . uniqid());

        $result = $this->repository->findOneByName($contractor->getName());

        self::assertNotNull($result);
        self::assertSame($contractor->getId(), $result->getId());
    }

    public function testFindOneByNameReturnsNullForNonExistentName(): void
    {
        $result = $this->repository->findOneByName('Nonexistent ' . uniqid());

        self::assertNull($result);
    }

    public function testSearchByNameFindsPartialMatch(): void
    {
        $this->createContractor('Żabka Unique ' . uniqid());

        $result = $this->repository->searchByName('Żabka');

        self::assertNotEmpty($result);
    }

    public function testSearchByNameIsCaseInsensitive(): void
    {
        $this->createContractor('CaseTest Unique ' . uniqid());

        $result = $this->repository->searchByName('casetest');

        self::assertNotEmpty($result);
    }

    public function testSearchByNameReturnsEmptyForBlankQuery(): void
    {
        $this->createContractor('Any Name ' . uniqid());

        $result = $this->repository->searchByName('');

        self::assertSame([], $result);
    }

    public function testSearchByNameReturnsEmptyForWhitespaceOnlyQuery(): void
    {
        $this->createContractor('Any Name ' . uniqid());

        $result = $this->repository->searchByName('   ');

        self::assertSame([], $result);
    }

    public function testSearchByNameReturnsEmptyForNonMatchingQuery(): void
    {
        $this->createContractor('Something ' . uniqid());

        $result = $this->repository->searchByName('nonexistent' . uniqid());

        self::assertSame([], $result);
    }

    public function testSearchByNameRespectsLimit(): void
    {
        $prefix = 'LimitTest' . uniqid();
        for ($i = 0; $i < 5; ++$i) {
            $this->createContractor("{$prefix}-{$i}");
        }

        $result = $this->repository->searchByName($prefix, 3);

        self::assertCount(3, $result);
    }

    public function testSearchByNameReturnsCorrectShape(): void
    {
        $contractor = $this->createContractor('ShapeTest ' . uniqid());

        $result = $this->repository->searchByName($contractor->getName());

        self::assertArrayHasKey('id', $result[0]);
        self::assertArrayHasKey('name', $result[0]);
        self::assertSame($contractor->getId(), $result[0]['id']);
        self::assertSame($contractor->getName(), $result[0]['name']);
    }

    public function testSearchByNameFindsSubstringAnywhereInNameButNotUnrelatedNames(): void
    {
        $marker = (string) random_int(100000, 999999);

        $this->createContractor("Alibaba-{$marker}");
        $this->createContractor("ABC Zdrowie-{$marker}");
        $this->createContractor("Aaa-Zdrowie-{$marker}");

        $result = $this->repository->searchByName('ab');

        $names = array_column($result, 'name');

        self::assertTrue(
            (bool) array_filter($names, static fn (string $name) => str_starts_with($name, 'Alibaba')),
        );
        self::assertTrue(
            (bool) array_filter($names, static fn (string $name) => str_starts_with($name, 'ABC Zdrowie')),
        );
        self::assertFalse(
            (bool) array_filter($names, static fn (string $name) => str_starts_with($name, 'Aaa-Zdrowie')),
        );
    }

    private function createContractor(string $name): Contractor
    {
        $contractor = new Contractor($name);
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }
}
