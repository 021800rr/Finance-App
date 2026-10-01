<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\ContractorResource;
use App\Entity\Contractor;
use App\State\ContractorProvider;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ContractorProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private ContractorProvider $provider;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var ContractorProvider $provider */
        $provider = self::getContainer()->get(ContractorProvider::class);
        $this->provider = $provider;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProvideReturnsResourceForExistingId(): void
    {
        $contractor = $this->createContractor('ACME '.uniqid());

        $result = $this->provider->provide(new Get(), ['id' => $contractor->getId()]);

        self::assertInstanceOf(ContractorResource::class, $result);
        self::assertSame($contractor->getId(), $result->id);
        self::assertSame($contractor->getName(), $result->name);
    }

    public function testProvideReturnsNullForNonExistentId(): void
    {
        $result = $this->provider->provide(new Get(), ['id' => 999999999]);

        self::assertNull($result);
    }

    public function testProvideCollectionOrdersByNameAscending(): void
    {
        $zebra = $this->createContractor('Zebra '.uniqid());
        $alpha = $this->createContractor('Alpha '.uniqid());

        /** @var list<ContractorResource> $result */
        $result = $this->provider->provide(new GetCollection());

        $names = array_map(static fn (ContractorResource $r): ?string => $r->name, $result);
        $positionOfAlpha = array_search($alpha->getName(), $names, true);
        $positionOfZebra = array_search($zebra->getName(), $names, true);

        self::assertIsInt($positionOfAlpha);
        self::assertIsInt($positionOfZebra);
        self::assertLessThan($positionOfZebra, $positionOfAlpha);
    }

    private function createContractor(string $name): Contractor
    {
        $contractor = new Contractor($name);
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }

    public function testProvideFiltersByPartialNameWhenNameFilterGiven(): void
    {
        $this->createContractor('Żabka '.uniqid());
        $this->createContractor('Biedronka '.uniqid());

        /** @var list<ContractorResource> $result */
        $result = $this->provider->provide(new GetCollection(), [], ['filters' => ['name' => 'Ża']]);

        self::assertNotNull($result[0]->name);
        self::assertStringStartsWith('Żabka', $result[0]->name);
    }

    public function testProvideReturnsAllContractorsWhenNoNameFilterGiven(): void
    {
        $this->createContractor('Alpha '.uniqid());

        /** @var list<ContractorResource> $result */
        $result = $this->provider->provide(new GetCollection(), [], []);

        self::assertNotEmpty($result);
    }
}
