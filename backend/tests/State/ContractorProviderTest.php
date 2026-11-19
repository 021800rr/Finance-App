<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\ContractorResource;
use App\State\ContractorProvider;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ContractorProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

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

    public function testProvideThrowsNotFoundForNonExistentId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Contractor not found.');

        $this->provider->provide(new Get(), ['id' => 999999999]);
    }

    public function testProvideOrdersEquallyGoodMatchesByName(): void
    {
        $marker = uniqid();
        $zebra = $this->createContractor("Zebra {$marker}");
        $alpha = $this->createContractor("Alpha {$marker}");

        /** @var list<ContractorResource> $result */
        $result = $this->provider->provide(new GetCollection(), [], ['filters' => ['name' => $marker]]);

        $names = array_map(static fn (ContractorResource $r): string => $r->name, $result);
        self::assertSame([$alpha->getName(), $zebra->getName()], $names);
    }

    public function testProvideFiltersByPartialNameWhenNameFilterGiven(): void
    {
        $this->createContractor('Écolier '.uniqid());
        $this->createContractor('Supermarket '.uniqid());

        /** @var list<ContractorResource> $result */
        $result = $this->provider->provide(new GetCollection(), [], ['filters' => ['name' => 'Éc']]);

        self::assertStringStartsWith('Écolier', $result[0]->name);
    }

    /**
     * There is no list of all contractors: the API refuses a request without the filter before the provider runs,
     * and the provider itself, called without one, finds nothing.
     */
    public function testProvideFindsNothingWithoutANameFilter(): void
    {
        $this->createContractor('Alpha '.uniqid());

        self::assertSame([], $this->provider->provide(new GetCollection(), [], []));
    }
}
