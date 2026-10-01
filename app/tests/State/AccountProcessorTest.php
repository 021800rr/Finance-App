<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Patch;
use App\ApiResource\AccountResource;
use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\Household;
use App\State\AccountProcessor;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AccountProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private AccountProcessor $processor;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountProcessor $processor */
        $processor = self::getContainer()->get(AccountProcessor::class);
        $this->processor = $processor;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProcessHidesAccountOnPatch(): void
    {
        $account = $this->createAccount();
        self::assertFalse($account->isHidden());

        $result = $this->processor->process(
            $this->buildResource(true),
            new Patch(),
            ['id' => $account->getId()],
        );

        self::assertInstanceOf(AccountResource::class, $result);
        self::assertSame($account->getId(), $result->id);
        self::assertTrue($result->hidden);

        $reloaded = $this->em()->getRepository(Account::class)->find($account->getId());
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isHidden());
    }

    public function testProcessUnhidesAccountOnPatch(): void
    {
        $account = $this->createAccount();
        $account->setHidden(true);
        $this->em()->flush();

        $result = $this->processor->process(
            $this->buildResource(false),
            new Patch(),
            ['id' => $account->getId()],
        );

        self::assertInstanceOf(AccountResource::class, $result);
        self::assertFalse($result->hidden);

        $reloaded = $this->em()->getRepository(Account::class)->find($account->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isHidden());
    }

    public function testProcessReturnsUnrelatedFieldsUnchanged(): void
    {
        $account = $this->createAccount();

        $result = $this->processor->process(
            $this->buildResource(true),
            new Patch(),
            ['id' => $account->getId()],
        );

        self::assertInstanceOf(AccountResource::class, $result);
        self::assertSame($account->getAccountNumber(), $result->accountNumber);
        self::assertSame($account->getBankName(), $result->bankName);
        self::assertSame($account->getCurrencyCode(), $result->currencyCode);
        self::assertSame($account->getTypeName(), $result->typeName);
        self::assertSame($account->getHouseholdName(), $result->householdName);
    }

    public function testProcessThrowsUnprocessableWhenHiddenMissing(): void
    {
        $account = $this->createAccount();

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($this->buildResource(null), new Patch(), ['id' => $account->getId()]);
    }

    public function testProcessThrowsNotFoundWhenAccountDoesNotExist(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->processor->process($this->buildResource(true), new Patch(), ['id' => 999999999]);
    }

    public function testProcessThrowsWhenDataIsNotAccountResource(): void
    {
        $account = $this->createAccount();

        $this->expectException(\InvalidArgumentException::class);

        $this->processor->process(new \stdClass(), new Patch(), ['id' => $account->getId()]);
    }

    private function buildResource(?bool $hidden): AccountResource
    {
        $resource = new AccountResource();
        $resource->hidden = $hidden;

        return $resource;
    }

    private function createAccount(): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find('PLN');
        if (null === $currency) {
            $currency = new Currency('PLN');
            $this->em()->persist($currency);
        }

        $accountType = new AccountType('Test Type ' . uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide exactly one own-funds household.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-' . uniqid(), $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }
}
