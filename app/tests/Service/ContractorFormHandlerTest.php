<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ContractorFormHandler;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

final class ContractorFormHandlerTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private ContractorFormHandler $handler;
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        $container = ContractorFormHandlerTest::getContainer();

        /** @var ContractorFormHandler $handler */
        $handler = $container->get(ContractorFormHandler::class);
        $this->handler = $handler;

        /** @var FormFactoryInterface $formFactory */
        $formFactory = $container->get('form.factory');
        $this->formFactory = $formFactory;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    private function buildContractorField(): \Symfony\Component\Form\FormInterface
    {
        $form = $this->formFactory->createNamed('', FormType::class, null, ['csrf_protection' => false]);
        $form->add('contractor', HiddenType::class, ['mapped' => false]);

        return $form->get('contractor');
    }

    public function testResolveReturnsErrorWhenContractorIdIsEmpty(): void
    {
        $field = $this->buildContractorField();
        $field->submit('');

        $request = new Request();

        $resolution = $this->handler->resolve($field, $request);

        self::assertNull($resolution->contractor);
        self::assertNull($resolution->contractorId);
        self::assertSame('contractor.required', $resolution->contractorErrorMessage);
    }

    public function testResolveReturnsErrorWhenContractorDoesNotExist(): void
    {
        $field = $this->buildContractorField();
        $field->submit('999999999');

        $request = new Request();

        $resolution = $this->handler->resolve($field, $request);

        self::assertNull($resolution->contractor);
        self::assertSame('999999999', $resolution->contractorId);
        self::assertSame('contractor.not_found', $resolution->contractorErrorMessage);
    }

    public function testResolveReturnsContractorWhenIdIsValid(): void
    {
        $contractor = $this->createContractor();

        $field = $this->buildContractorField();
        $field->submit((string) $contractor->getId());

        $request = new Request();

        $resolution = $this->handler->resolve($field, $request);

        self::assertNotNull($resolution->contractor);
        self::assertSame((string) $contractor->getId(), $resolution->contractorId);
        self::assertSame($contractor->getId(), $resolution->contractor->getId());
        self::assertSame($contractor->getName(), $resolution->contractorName);
        self::assertNull($resolution->contractorErrorMessage);
    }

    public function testResolveFallsBackToContractorInputWhenIdMissing(): void
    {
        $field = $this->buildContractorField();
        $field->submit('');

        $request = new Request(request: ['contractor_input' => 'Typed manually']);

        $resolution = $this->handler->resolve($field, $request);

        self::assertSame('Typed manually', $resolution->contractorName);
    }

    public function testResolveReturnsErrorWhenContractorIdIsNotNumeric(): void
    {
        $field = $this->buildContractorField();
        $field->submit('alamakota');

        $request = new Request();

        $resolution = $this->handler->resolve($field, $request);

        self::assertNull($resolution->contractor);
        self::assertSame('contractor.invalid_id', $resolution->contractorErrorMessage);
    }

    public function testResolveReturnsErrorWhenContractorIdExceedsIntegerRange(): void
    {
        $field = $this->buildContractorField();
        $field->submit('99999999999');

        $request = new Request();

        $resolution = $this->handler->resolve($field, $request);

        self::assertNull($resolution->contractor);
        self::assertSame('contractor.invalid_id', $resolution->contractorErrorMessage);
    }

    public function testResolveOverridesTypedNameWithDatabaseNameOnSuccess(): void
    {
        $contractor = $this->createContractor('Prawdziwa nazwa z bazy');

        $field = $this->buildContractorField();
        $field->submit((string) $contractor->getId());

        $request = new Request(request: ['contractor_input' => 'Other text typed by the user']);

        $resolution = $this->handler->resolve($field, $request);

        self::assertSame('Prawdziwa nazwa z bazy', $resolution->contractorName);
    }
}
