<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Contractor;
use App\Service\FixedExpenseFactory;
use App\Tests\DatabaseTestTrait;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class FixedExpenseFactoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private FixedExpenseFactory $factory;
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        $container = self::getContainer();

        /** @var FixedExpenseFactory $factory */
        $factory = $container->get(FixedExpenseFactory::class);
        $this->factory = $factory;

        /** @var FormFactoryInterface $formFactory */
        $formFactory = $container->get('form.factory');
        $this->formFactory = $formFactory;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testCreateFromFormBuildsFixedExpenseWithCorrectAmount(): void
    {
        $form = $this->buildForm(day: 15, amount: -150.50, description: 'Czynsz');

        $fixedExpense = $this->factory->createFromForm($form, $this->createContractor());

        self::assertSame(15, $fixedExpense->getDay());
        self::assertSame(-15050, $fixedExpense->getAmount());
        self::assertSame('Czynsz', $fixedExpense->getDescription());
        self::assertSame('PLN', $fixedExpense->getCurrency()->getCode());
    }

    public function testCreateFromFormNormalizesBlankDescriptionToNull(): void
    {
        $form = $this->buildForm(day: 1, amount: -100.0, description: '   ');

        $fixedExpense = $this->factory->createFromForm($form, $this->createContractor());

        self::assertNull($fixedExpense->getDescription());
    }

    public function testUpdateFromFormUpdatesExistingFixedExpense(): void
    {
        $originalForm = $this->buildForm(day: 5, amount: -50.0, description: 'Stary opis');
        $fixedExpense = $this->factory->createFromForm($originalForm, $this->createContractor());

        $newContractor = $this->createContractor();
        $updateForm = $this->buildForm(day: 20, amount: -75.25, description: 'Nowy opis');
        $this->factory->updateFromForm($fixedExpense, $updateForm, $newContractor);

        self::assertSame(20, $fixedExpense->getDay());
        self::assertSame(-7525, $fixedExpense->getAmount());
        self::assertSame('Nowy opis', $fixedExpense->getDescription());
        self::assertSame($newContractor, $fixedExpense->getContractor());
    }

    public function testCreateFromFormThrowsWhenDayIsNotInteger(): void
    {
        $form = $this->buildFormWithInvalidDay();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Day must be an integer.');

        $this->factory->createFromForm($form, $this->createContractor());
    }

    #[DataProvider('provideBoundaryAmounts')]
    public function testCreateFromFormConvertsBoundaryAmountsToCorrectMinorUnits(float $amount, int $expectedMinorUnits): void
    {
        $form = $this->buildForm(day: 1, amount: $amount, description: null);

        $fixedExpense = $this->factory->createFromForm($form, $this->createContractor());

        self::assertSame($expectedMinorUnits, $fixedExpense->getAmount());
    }

    /**
     * @return array<string, array{0: float, 1: int}>
     */
    public static function provideBoundaryAmounts(): array
    {
        return [
            'penny' => [-0.01, -1],
            'near lower bound' => [-999999.98, -99999998],
            'lower bound' => [-999999.99, -99999999],
            'round ten cents' => [-0.10, -10],
        ];
    }

    /**
     * @return array<string, array{0: float}>
     */
    public static function provideOutOfRangeAmounts(): array
    {
        return [
            'exceeds lower bound by one grosz' => [-1000000.00],
            'far below lower bound' => [-5000000.00],
            'rounds to zero, violates negative constraint' => [-0.001],
        ];
    }

    #[DataProvider('provideOutOfRangeAmounts')]
    public function testCreateFromFormThrowsForAmountsOutsideEntityRange(float $amount): void
    {
        $form = $this->buildForm(day: 1, amount: $amount, description: null);

        $this->expectException(InvalidArgumentException::class);

        $this->factory->createFromForm($form, $this->createContractor());
    }

    private function createContractor(): Contractor
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }

    private function buildForm(int $day, float $amount, ?string $description): FormInterface
    {
        $form = $this->formFactory->createNamed('', FormType::class, null, ['csrf_protection' => false]);
        $form->add('day', IntegerType::class, ['mapped' => false]);
        $form->add('amount', MoneyType::class, ['mapped' => false, 'currency' => false, 'input' => 'string']);
        $form->add('description', HiddenType::class, ['mapped' => false, 'required' => false]);

        $form->get('day')->setData($day);
        $form->get('amount')->setData((string) $amount);
        $form->get('description')->setData($description);

        return $form;
    }

    private function buildFormWithInvalidDay(): FormInterface
    {
        $form = $this->formFactory->createNamed('', FormType::class, null, ['csrf_protection' => false]);
        $form->add('day', HiddenType::class, ['mapped' => false]);
        $form->add('amount', MoneyType::class, ['mapped' => false, 'currency' => false, 'input' => 'string']);
        $form->add('description', HiddenType::class, ['mapped' => false, 'required' => false]);

        $form->get('day')->setData('not-int');
        $form->get('amount')->setData('-50.0');
        $form->get('description')->setData(null);

        return $form;
    }
}
