<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Contractor;
use App\Service\ContractorResolver;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ContractorResolverTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private ContractorResolver $resolver;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var ContractorResolver $resolver */
        $resolver = self::getContainer()->get(ContractorResolver::class);
        $this->resolver = $resolver;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testResolveForUpdateKeepsTheCurrentContractorWhenNothingChanged(): void
    {
        $current = $this->createContractor();

        $resolved = $this->resolver->resolveForUpdate($current->getId(), $current->getName(), $current);

        self::assertSame($current, $resolved);
    }

    public function testResolveForUpdateUsesANewNameEvenThoughTheCurrentIdIsStillPresent(): void
    {
        $current = $this->createContractor();
        $newName = 'Created by update '.uniqid();

        $resolved = $this->resolver->resolveForUpdate($current->getId(), $newName, $current);

        self::assertNotSame($current->getId(), $resolved->getId());
        self::assertSame($newName, $resolved->getName());
    }

    public function testResolveForUpdateReusesAnExistingContractorFoundByTheNewName(): void
    {
        $current = $this->createContractor();
        $existing = $this->createContractor();

        $resolved = $this->resolver->resolveForUpdate($current->getId(), $existing->getName(), $current);

        self::assertSame($existing->getId(), $resolved->getId());
    }

    public function testResolveForUpdateKeepsTheCurrentContractorWhenOnlyTheIdIsNull(): void
    {
        $current = $this->createContractor();

        $resolved = $this->resolver->resolveForUpdate(null, $current->getName(), $current);

        self::assertSame($current->getId(), $resolved->getId());
    }

    public function testResolveForUpdateRejectsABlankName(): void
    {
        $current = $this->createContractor();

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->resolver->resolveForUpdate($current->getId(), '   ', $current);
    }

    public function testResolveForUpdatePrefersAChangedIdOverAStaleName(): void
    {
        $current = $this->createContractor();
        $other = $this->createContractor();

        $resolved = $this->resolver->resolveForUpdate($other->getId(), $current->getName(), $current);

        self::assertSame($other->getId(), $resolved->getId());
    }

    public function testResolveForUpdateRejectsAnUnknownId(): void
    {
        $current = $this->createContractor();

        $this->expectException(NotFoundHttpException::class);

        $this->resolver->resolveForUpdate(999999999, $current->getName(), $current);
    }

    public function testResolveForUpdateRejectsAnUpdateThatClearsBothFields(): void
    {
        $current = $this->createContractor();

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Either contractorId or contractorName is required.');

        $this->resolver->resolveForUpdate(null, null, $current);
    }

    public function testResolveRejectsWhenBothFieldsAreNull(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Either contractorId or contractorName is required.');

        $this->resolver->resolve(null, null);
    }

    private function createContractor(): Contractor
    {
        $contractor = new Contractor('Resolver test '.uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }
}
