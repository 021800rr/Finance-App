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
        $contractor = $this->createContractor('Écolier Unique ' . uniqid());

        $ids = array_column($this->repository->searchByName('Écolier', 1000), 'id');

        self::assertContains($contractor->getId(), $ids);
    }

    public function testSearchByNameIsCaseInsensitive(): void
    {
        $contractor = $this->createContractor('CaseTest Unique ' . uniqid());

        $ids = array_column($this->repository->searchByName('casetest', 1000), 'id');

        self::assertContains($contractor->getId(), $ids);
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

    public function testSearchByNameTreatsUnderscoreAsLiteral(): void
    {
        $marker = uniqid();
        $withUnderscore = $this->createContractor("snake_case {$marker}");
        $plain = $this->createContractor("snakeXcase {$marker}");

        $ids = array_column($this->repository->searchByName("snake_case {$marker}", 100), 'id');

        self::assertContains($withUnderscore->getId(), $ids);
        self::assertNotContains($plain->getId(), $ids);
    }

    public function testSearchByNameTreatsPercentAsLiteral(): void
    {
        $marker = uniqid();
        $withPercent = $this->createContractor("Discount 100% {$marker}");
        $plain = $this->createContractor("Discount 1000 {$marker}");

        $ids = array_column($this->repository->searchByName('100%', 100), 'id');

        self::assertContains($withPercent->getId(), $ids);
        self::assertNotContains($plain->getId(), $ids);
    }

    public function testSearchByNameTreatsBackslashAsLiteral(): void
    {
        $marker = uniqid();
        $withBackslash = $this->createContractor('Path a\\b '.$marker);
        $plain = $this->createContractor('Path ab '.$marker);

        $ids = array_column($this->repository->searchByName('a\\b '.$marker, 100), 'id');

        self::assertContains($withBackslash->getId(), $ids);
        self::assertNotContains($plain->getId(), $ids);
    }

    public function testSearchByNameFindsNameEndingWithBackslash(): void
    {
        $marker = uniqid();
        $contractor = $this->createContractor("Dir {$marker}\\");

        $ids = array_column($this->repository->searchByName("{$marker}\\", 100), 'id');

        self::assertContains($contractor->getId(), $ids);
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

        $result = $this->repository->searchByName('ab', 1000);

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
