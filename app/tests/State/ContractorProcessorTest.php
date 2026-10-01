<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use App\ApiResource\ContractorResource;
use App\Entity\Contractor;
use App\State\ContractorProcessor;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ContractorProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private ContractorProcessor $processor;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var ContractorProcessor $processor */
        $processor = self::getContainer()->get(ContractorProcessor::class);
        $this->processor = $processor;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProcessCreatesContractorSuccessfully(): void
    {
        $resource = $this->buildResource('New Contractor ' . uniqid());

        $result = $this->processor->process($resource, new Post());

        self::assertNotNull($result->id);
        self::assertSame($resource->name, $result->name);
        self::assertNotNull($this->em()->getRepository(Contractor::class)->find($result->id));
    }

    public function testProcessThrowsConflictOnDuplicateName(): void
    {
        $name = 'Duplicate Contractor ' . uniqid();
        $this->processor->process($this->buildResource($name), new Post());

        $this->expectException(ConflictHttpException::class);

        $this->processor->process($this->buildResource($name), new Post());
    }

    public function testProcessThrowsUnprocessableWhenNameMissing(): void
    {
        $resource = $this->buildResource(null);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsUnprocessableWhenNameBlank(): void
    {
        $resource = $this->buildResource('   ');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($resource, new Post());
    }

    public function testProcessThrowsWhenDataIsNotContractorResource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->processor->process(new \stdClass(), new Post());
    }

    private function buildResource(?string $name): ContractorResource
    {
        $resource = new ContractorResource();
        $resource->name = $name;

        return $resource;
    }
}
