<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\ApiResource\AccountResource;
use App\ApiResource\Input\AccountInput;
use App\Entity\Account;
use App\State\AccountProvider;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AccountProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private AccountProvider $provider;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountProvider $provider */
        $provider = self::getContainer()->get(AccountProvider::class);
        $this->provider = $provider;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProvideReturnsResourceForExistingId(): void
    {
        $account = $this->createAccount(accountNumber: 'AAA-'.uniqid());

        $result = $this->provider->provide(new Get(), ['id' => $account->getId()]);

        self::assertInstanceOf(AccountResource::class, $result);
        self::assertSame($account->getId(), $result->id);
        self::assertSame($account->getAccountNumber(), $result->accountNumber);
    }

    public function testProvideForCollectionReturnsAllAccounts(): void
    {
        $account = $this->createAccount(accountNumber: 'COL-'.uniqid());

        $result = $this->provider->provide(new GetCollection());

        self::assertIsArray($result);
        self::assertCount($this->em()->getRepository(Account::class)->count([]), $result);
        self::assertContains(
            $account->getId(),
            array_map(static fn (AccountResource $resource): int => $resource->id, $result),
        );
    }

    public function testProvideThrowsNotFoundForNonExistentId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Account not found.');

        $this->provider->provide(new Get(), ['id' => 999999999]);
    }

    public function testProvideThrowsNotFoundForNonNumericId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Account not found.');

        $this->provider->provide(new Get(), ['id' => 'not-a-number']);
    }

    public function testProvideForGetIncludesBalances(): void
    {
        $account = $this->createAccount(accountNumber: 'BAL-'.uniqid());
        $this->createOperation($account, 10000, new \DateTimeImmutable('-1 day'));
        $this->createOperation($account, 2500, new \DateTimeImmutable('+3 days'));

        $result = $this->provider->provide(new Get(), ['id' => $account->getId()]);

        self::assertInstanceOf(AccountResource::class, $result);
        self::assertSame(['amount' => '125.00', 'currency' => 'PLN'], $result->saldo);
        self::assertSame(['amount' => '100.00', 'currency' => 'PLN'], $result->saldoToday);
        self::assertTrue($result->hasFutureOperations);
    }

    public function testProvideGivesPatchTheCurrentValuesWithoutWorkingOutBalances(): void
    {
        $account = $this->createAccount(accountNumber: 'BAL-'.uniqid(), hidden: true);

        $result = $this->provider->provide(new Patch(), ['id' => $account->getId()]);

        self::assertInstanceOf(AccountInput::class, $result);
        self::assertTrue($result->hidden);
        self::assertSame($account->isMain(), $result->isMain);
    }

    public function testProvideForCollectionLeavesTheBalancesOut(): void
    {
        $this->createAccount(accountNumber: 'COL-'.uniqid());

        $result = $this->provider->provide(new GetCollection());

        self::assertIsArray($result);
        foreach ($result as $resource) {
            self::assertInstanceOf(AccountResource::class, $resource);
            self::assertNull($resource->saldo);
            self::assertNull($resource->saldoToday);
            self::assertNull($resource->hasFutureOperations);
        }
    }
}
