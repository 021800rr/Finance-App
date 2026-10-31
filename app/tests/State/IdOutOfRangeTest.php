<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccountOperationResource;
use App\ApiResource\AccountResource;
use App\ApiResource\CurrencyExchangeRateResource;
use App\ApiResource\FixedExpenseResource;
use App\ApiResource\GoldAssetResource;
use App\ApiResource\GoldPriceResource;
use App\ApiResource\TransferResource;
use App\Service\ContractorResolver;
use App\State\AccountOperationProcessor;
use App\State\AccountOperationProvider;
use App\State\AccountProcessor;
use App\State\AccountProvider;
use App\State\AccountTypeProvider;
use App\State\BankProvider;
use App\State\ContractorProvider;
use App\State\CurrencyExchangeRateProcessor;
use App\State\CurrencyExchangeRateProvider;
use App\State\FixedExpenseProcessor;
use App\State\FixedExpenseProvider;
use App\State\GoldAssetProcessor;
use App\State\GoldAssetProvider;
use App\State\GoldFormProvider;
use App\State\GoldPriceProcessor;
use App\State\GoldPriceProvider;
use App\State\HouseholdProvider;
use App\State\TransferProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ids are PostgreSQL "integer" (int4) columns, but PHP ints are 64-bit. Passing an id above int4 max to
 * find() makes the database raise "value out of range" (a 500) instead of simply finding no row (a 404).
 * Every API entry point that turns a client-supplied id into a find() call must treat such an id as
 * "not found".
 */
final class IdOutOfRangeTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function providerClasses(): iterable
    {
        yield 'account operation' => [AccountOperationProvider::class];
        yield 'account' => [AccountProvider::class];
        yield 'account type' => [AccountTypeProvider::class];
        yield 'bank' => [BankProvider::class];
        yield 'contractor' => [ContractorProvider::class];
        yield 'currency exchange rate' => [CurrencyExchangeRateProvider::class];
        yield 'fixed expense' => [FixedExpenseProvider::class];
        yield 'gold asset' => [GoldAssetProvider::class];
        yield 'gold form' => [GoldFormProvider::class];
        yield 'gold price' => [GoldPriceProvider::class];
        yield 'household' => [HouseholdProvider::class];
    }

    /**
     * @param class-string $providerClass
     */
    #[DataProvider('providerClasses')]
    public function testProviderThrowsNotFoundForIdAboveInt4Max(string $providerClass): void
    {
        self::bootKernel();
        $provider = self::getContainer()->get($providerClass);
        self::assertInstanceOf(ProviderInterface::class, $provider);

        $this->expectException(NotFoundHttpException::class);

        $provider->provide(new Get(), ['id' => PHP_INT_MAX]);
    }

    /**
     * @return iterable<string, array{class-string, Operation, ?object}>
     */
    public static function processorsLoadingByUriId(): iterable
    {
        yield 'account operation delete' => [AccountOperationProcessor::class, new Delete(), null];
        yield 'account operation patch' => [AccountOperationProcessor::class, new Patch(), new AccountOperationResource()];
        yield 'account patch' => [AccountProcessor::class, new Patch(), new AccountResource()];
        yield 'currency exchange rate delete' => [CurrencyExchangeRateProcessor::class, new Delete(), null];
        yield 'fixed expense delete' => [FixedExpenseProcessor::class, new Delete(), null];
        yield 'fixed expense patch' => [FixedExpenseProcessor::class, new Patch(), new FixedExpenseResource()];
        yield 'gold asset delete' => [GoldAssetProcessor::class, new Delete(), null];
        yield 'gold price delete' => [GoldPriceProcessor::class, new Delete(), null];
    }

    /**
     * @param class-string $processorClass
     */
    #[DataProvider('processorsLoadingByUriId')]
    public function testProcessorThrowsNotFoundForUriIdAboveInt4Max(string $processorClass, Operation $operation, ?object $data): void
    {
        self::bootKernel();
        $processor = self::getContainer()->get($processorClass);
        self::assertInstanceOf(ProcessorInterface::class, $processor);

        $this->expectException(NotFoundHttpException::class);

        $processor->process($data, $operation, ['id' => PHP_INT_MAX]);
    }

    /**
     * @return iterable<string, array{class-string, object}>
     */
    public static function processorsResolvingBodyId(): iterable
    {
        $operation = new AccountOperationResource();
        $operation->accountId = PHP_INT_MAX;
        yield 'account operation: accountId' => [AccountOperationProcessor::class, $operation];

        $rate = new CurrencyExchangeRateResource();
        $rate->bankId = PHP_INT_MAX;
        yield 'currency exchange rate: bankId' => [CurrencyExchangeRateProcessor::class, $rate];

        $asset = new GoldAssetResource();
        $asset->formId = PHP_INT_MAX;
        yield 'gold asset: formId' => [GoldAssetProcessor::class, $asset];

        $price = new GoldPriceResource();
        $price->goldAssetId = PHP_INT_MAX;
        yield 'gold price: goldAssetId' => [GoldPriceProcessor::class, $price];

        $transfer = new TransferResource();
        $transfer->fromAccountId = PHP_INT_MAX;
        yield 'transfer: fromAccountId' => [TransferProcessor::class, $transfer];
    }

    /**
     * @param class-string $processorClass
     */
    #[DataProvider('processorsResolvingBodyId')]
    public function testProcessorThrowsNotFoundForBodyIdAboveInt4Max(string $processorClass, object $data): void
    {
        self::bootKernel();
        $processor = self::getContainer()->get($processorClass);
        self::assertInstanceOf(ProcessorInterface::class, $processor);

        $this->expectException(NotFoundHttpException::class);

        $processor->process($data, new Post());
    }

    public function testContractorResolverThrowsNotFoundForContractorIdAboveInt4Max(): void
    {
        self::bootKernel();
        $resolver = self::getContainer()->get(ContractorResolver::class);
        self::assertInstanceOf(ContractorResolver::class, $resolver);

        $this->expectException(NotFoundHttpException::class);

        $resolver->resolve(PHP_INT_MAX, null);
    }
}
