<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\AccountRepository;
use App\Service\FixedExpensePlannerService;
use App\Tests\DatabaseTestTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\Events;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Two simultaneous plannings used to pass the duplicate check together and book every expense twice. A second, real
 * database connection plays the other request: it must not get the main account's row while the planning writes.
 */
final class FixedExpensePlannerLockTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private const string LOCK_NOT_AVAILABLE = '55P03';

    private Connection $otherRequest;

    protected function setUp(): void
    {
        $this->initEntityManager(self::bootKernel());
        // Created directly, so it stays outside the transaction that wraps each test and sees only committed data.
        /** @var array{driver: 'pdo_pgsql'} $params */
        $params = $this->em()->getConnection()->getParams();
        $this->otherRequest = DriverManager::getConnection($params);
    }

    protected function tearDown(): void
    {
        $this->otherRequest->close();
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testMainAccountStaysLockedUntilThePlanningIsWritten(): void
    {
        /** @var AccountRepository $accounts */
        $accounts = self::getContainer()->get(AccountRepository::class);
        $accountId = $accounts->findMain()?->getId();
        self::assertNotNull($accountId, 'Fixtures must provide a main account.');
        self::assertFalse($this->isLockedForOtherRequests($accountId), 'Nothing should hold the row before planning.');

        $listener = new class($this->isLockedForOtherRequests(...), $accountId) {
            public ?bool $lockedDuringWrite = null;

            /**
             * @param \Closure(int): bool $isLocked
             */
            public function __construct(private readonly \Closure $isLocked, private readonly int $accountId)
            {
            }

            public function preFlush(): void
            {
                $this->lockedDuringWrite = ($this->isLocked)($this->accountId);
            }
        };
        $this->em()->getEventManager()->addEventListener(Events::preFlush, $listener);

        /** @var FixedExpensePlannerService $planner */
        $planner = self::getContainer()->get(FixedExpensePlannerService::class);
        $planner->planNextMonth();

        self::assertTrue($listener->lockedDuringWrite);
    }

    private function isLockedForOtherRequests(int $accountId): bool
    {
        try {
            $this->otherRequest->fetchOne('SELECT id FROM account WHERE id = ? FOR UPDATE NOWAIT', [$accountId]);
        } catch (DriverException $exception) {
            self::assertSame(self::LOCK_NOT_AVAILABLE, $exception->getSQLState());

            return true;
        }

        return false;
    }
}
