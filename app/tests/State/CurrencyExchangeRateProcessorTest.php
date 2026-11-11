<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\ApiResource\CurrencyExchangeRateResource;
use App\ApiResource\Input\CurrencyExchangeRateInput;
use App\Entity\CurrencyExchangeRate;
use App\State\CurrencyExchangeRateProcessor;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CurrencyExchangeRateProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private CurrencyExchangeRateProcessor $processor;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var CurrencyExchangeRateProcessor $processor */
        $processor = self::getContainer()->get(CurrencyExchangeRateProcessor::class);
        $this->processor = $processor;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProcessCreatesRateSuccessfully(): void
    {
        $bank = $this->createBank();

        $input = $this->buildInput(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: '4.3210');

        $result = $this->processor->process($input, new Post());

        self::assertInstanceOf(CurrencyExchangeRateResource::class, $result);
        self::assertGreaterThan(0, $result->id);
        self::assertSame($bank->getId(), $result->bankId);
        self::assertSame('CHF', $result->currencyCode);
        self::assertSame('4.3210', $result->rateToPln);
    }

    public function testProcessAllowsMultipleRatesForSameBankCurrencyAndDate(): void
    {
        $bank = $this->createBank();

        $first = $this->processor->process(
            $this->buildInput(
                bankId: $bank->getId(),
                currencyCode: 'CHF',
                rateDate: '2026-09-29',
                rateToPln: '4.3210',
            ),
            new Post(),
        );

        $second = $this->processor->process(
            $this->buildInput(
                bankId: $bank->getId(),
                currencyCode: 'CHF',
                rateDate: '2026-09-29',
                rateToPln: '4.4000',
            ),
            new Post(),
        );

        self::assertInstanceOf(CurrencyExchangeRateResource::class, $first);
        self::assertInstanceOf(CurrencyExchangeRateResource::class, $second);
        self::assertNotSame($first->id, $second->id);
        self::assertSame('4.3210', $first->rateToPln);
        self::assertSame('4.4000', $second->rateToPln);
        // For the same date, the highest ID determines the current rate.
        self::assertGreaterThan($first->id, $second->id);
    }

    public function testProcessThrowsUnprocessableWhenBankIdMissing(): void
    {
        $input = $this->buildInput(bankId: null, currencyCode: 'CHF', rateToPln: '4.3210');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsNotFoundWhenBankDoesNotExist(): void
    {
        $input = $this->buildInput(bankId: 999999999, currencyCode: 'CHF', rateToPln: '4.3210');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenCurrencyCodeMissing(): void
    {
        $bank = $this->createBank();
        $input = $this->buildInput(bankId: $bank->getId(), currencyCode: null, rateToPln: '4.3210');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsNotFoundWhenCurrencyDoesNotExist(): void
    {
        $bank = $this->createBank();
        $input = $this->buildInput(bankId: $bank->getId(), currencyCode: 'ZZZ', rateToPln: '4.3210');

        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessTreatsAMissingRateDateAsABug(): void
    {
        $bank = $this->createBank();
        $input = $this->buildInput(
            bankId: $bank->getId(),
            currencyCode: 'CHF',
            rateToPln: '4.3210',
            rateDate: null,
        );

        // The API validates the date first (NotBlank + BusinessDate), so reaching here is a bug.
        $this->expectException(\LogicException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessTreatsAnUnvalidatedRateDateAsABug(): void
    {
        $bank = $this->createBank();
        $input = $this->buildInput(
            bankId: $bank->getId(),
            currencyCode: 'CHF',
            rateToPln: '4.3210',
            rateDate: 'not-a-date',
        );

        // The API validates the date first (NotBlank + BusinessDate), so reaching here is a bug.
        $this->expectException(\LogicException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenRateToPlnMissing(): void
    {
        $bank = $this->createBank();
        $input = $this->buildInput(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: null);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenRateToPlnNotDecimal(): void
    {
        $bank = $this->createBank();
        $input = $this->buildInput(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: 'string');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsUnprocessableWhenRateToPlnOutOfRange(): void
    {
        $bank = $this->createBank();
        $input = $this->buildInput(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: '150.0000');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    public function testProcessThrowsWhenDataIsNotCurrencyExchangeRateInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->processor->process(new \stdClass(), new Post());
    }

    public function testProcessDeletesExistingRateOnDelete(): void
    {
        $bank = $this->createBank();
        $created = $this->processor->process(
            $this->buildInput(bankId: $bank->getId(), currencyCode: 'CHF', rateToPln: '4.3210'),
            new Post(),
        );
        self::assertInstanceOf(CurrencyExchangeRateResource::class, $created);

        $result = $this->processor->process(null, new Delete(), ['id' => $created->id]);

        self::assertNull($result);
        self::assertNull($this->em()->getRepository(CurrencyExchangeRate::class)->find($created->id));
    }

    public function testProcessThrowsNotFoundOnDeleteWhenRateMissing(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(null, new Delete(), ['id' => 999999999]);
    }

    /**
     * Sentinel distinguishing "argument omitted" (auto-fill today's date) from an explicit
     * null passed by a test that wants to verify the missing-date validation path.
     */
    private const string AUTO_DATE = '__auto_date__';

    private function buildInput(
        ?int $bankId = null,
        ?string $currencyCode = null,
        ?string $rateToPln = '4.3210',
        ?string $rateDate = self::AUTO_DATE,
    ): CurrencyExchangeRateInput {
        $input = new CurrencyExchangeRateInput();
        $input->bankId = $bankId;
        $input->currencyCode = $currencyCode;
        $input->rateToPln = $rateToPln;
        $input->rateDate = self::AUTO_DATE === $rateDate
            ? (new \DateTimeImmutable())->format('Y-m-d')
            : $rateDate;

        return $input;
    }
}
