<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Form\AccountOperationType;
use App\Form\CurrencyExchangeRateType;
use App\Form\GoldPriceType;
use App\Form\TransferType;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;

final class BusinessDateTest extends KernelTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        $this->initEntityManager(self::bootKernel());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    /**
     * @param class-string<FormTypeInterface> $formClass
     */
    #[DataProvider('dates')]
    public function testDateValidationOnTheServer(
        string $formClass,
        string $field,
        bool $editing,
        string $date,
        bool $valid
    ): void
    {
        /** @var FormFactoryInterface $factory */
        $factory = self::getContainer()->get('form.factory');
        $options = ['csrf_protection' => false];
        if ($editing) {
            $account = $this->account('Bank1-ROR-PLN');
            $options['existingOperation'] = new AccountOperation(
                $account,
                new Contractor('Test'),
                new \DateTimeImmutable('2026-01-15'),
                1000,
                $account->getCurrency(),
            );
        }

        $payload = $this->payloadFor($formClass) + [$field => $date];
        $form = $factory->create($formClass, null, $options);
        $form->submit($payload);

        self::assertSame($valid, $form->isValid(), (string) $form->getErrors(true));
        self::assertSame($valid, $form->get($field)->isValid());
        if ($valid) {
            $parsed = $form->get($field)->getData();
            self::assertInstanceOf(\DateTimeImmutable::class, $parsed);
            self::assertSame($date, $parsed->format('Y-m-d'));
        } elseif (in_array($date, ['2024-12-31', '2071-01-01'], true)) {
            // A translated field error, with both limits, instead of an exception from the entity.
            $errors = (string) $form->get($field)->getErrors();
            self::assertStringContainsString('2025-01-01', $errors);
            self::assertStringContainsString('2070-12-31', $errors);
            self::assertStringNotContainsString('common.date_out_of_range', $errors);
        }
    }

    /**
     * @return iterable<string, array{class-string<FormTypeInterface>, string, bool, string, bool}>
     */
    public static function dates(): iterable
    {
        $forms = [
            'new operation' => [AccountOperationType::class, 'operationDate', false],
            'edit operation' => [AccountOperationType::class, 'operationDate', true],
            'transfer' => [TransferType::class, 'date', false],
            'gold price' => [GoldPriceType::class, 'priceDate', false],
            'exchange rate' => [CurrencyExchangeRateType::class, 'rateDate', false],
        ];
        foreach ($forms as $name => [$formClass, $field, $editing]) {
            foreach (['2025-01-01', '2070-12-31', '2028-02-29'] as $date) {
                yield $name.' / '.$date => [$formClass, $field, $editing, $date, true];
            }
            foreach (['2024-12-31', '2071-01-01', '2026-02-31', ''] as $date) {
                yield $name.' / '.$date => [$formClass, $field, $editing, $date, false];
            }
        }
    }

    /**
     * @param class-string<FormTypeInterface> $formClass
     * @return array<string, string>
     */
    private function payloadFor(string $formClass): array
    {
        if (GoldPriceType::class === $formClass) {
            $goldForm = $this->em()->getRepository(GoldForm::class)->findOneBy([]);
            self::assertNotNull($goldForm);
            $asset = new GoldAsset($goldForm, 10000, 'Date validation gold');
            $this->em()->persist($asset);
            $this->em()->flush();

            return ['goldAsset' => (string) $asset->getId(), 'pricePln' => '10000'];
        }

        $account = $this->account('Bank1-ROR-PLN');

        return match ($formClass) {
            AccountOperationType::class => [
                'account' => (string) $account->getId(),
                'contractor' => 'Test',
                'amount' => '10',
            ],
            TransferType::class => [
                'fromAccount' => (string) $account->getId(),
                'toAccount' => (string) $this->account('Bank1-WAK-PLN')->getId(),
                'amountFrom' => '10',
            ],
            CurrencyExchangeRateType::class => [
                'bank' => (string) $account->getBank()->getId(),
                'currency' => 'CHF',
                'rateToPln' => '4',
            ],
            default => throw new \InvalidArgumentException('Unknown form: '.$formClass),
        };
    }

    private function account(string $number): Account
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => $number]);
        self::assertNotNull($account);

        return $account;
    }
}
