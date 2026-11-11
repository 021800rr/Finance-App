<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use App\ApiResource\Input\ContractorInput;
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
        $input = $this->buildInput('New Contractor '.uniqid());

        $result = $this->processor->process($input, new Post());

        self::assertGreaterThan(0, $result->id);
        self::assertSame($input->name, $result->name);
        self::assertNotNull($this->em()->getRepository(Contractor::class)->find($result->id));
    }

    public function testProcessThrowsConflictOnDuplicateName(): void
    {
        $name = 'Duplicate Contractor '.uniqid();
        $this->processor->process($this->buildInput($name), new Post());

        $this->expectException(ConflictHttpException::class);

        $this->processor->process($this->buildInput($name), new Post());
    }

    public function testProcessThrowsUnprocessableWhenNameMissing(): void
    {
        $input = $this->buildInput(null);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenNameBlank(): void
    {
        $input = $this->buildInput('   ');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsWhenDataIsNotContractorInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->processor->process(new \stdClass(), new Post());
    }

    private function buildInput(?string $name): ContractorInput
    {
        $input = new ContractorInput();
        $input->name = $name;

        return $input;
    }
}
