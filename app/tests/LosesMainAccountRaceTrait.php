<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Two requests can mark different accounts as main at the same moment, and then the database refuses the second one.
 * This reproduces that without real concurrency: right before this request's account becomes main, "the other
 * request" has marked the rival account.
 */
trait LosesMainAccountRaceTrait
{
    private function loseTheNextMainAccountRace(KernelBrowser $client, int $rivalAccountId): void
    {
        // A reboot before the next request would replace the entity manager this listener is attached to.
        $client->disableReboot();

        $entityManager = $client->getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $entityManager->getEventManager()->addEventListener(Events::preFlush, new class($rivalAccountId) {
            private bool $raceLost = false;

            public function __construct(private readonly int $rivalAccountId)
            {
            }

            public function preFlush(PreFlushEventArgs $args): void
            {
                $entityManager = $args->getObjectManager();
                $unitOfWork = $entityManager->getUnitOfWork();

                foreach ($unitOfWork->getIdentityMap()[Account::class] ?? [] as $account) {
                    $becomesMain = $account instanceof Account
                        && $account->isMain()
                        && false === ($unitOfWork->getOriginalEntityData($account)['isMain'] ?? null);

                    if ($becomesMain && !$this->raceLost) {
                        $this->raceLost = true;
                        $entityManager->getConnection()->executeStatement(
                            'UPDATE account SET is_main = true WHERE id = :id',
                            ['id' => $this->rivalAccountId],
                        );
                    }
                }
            }
        });
    }
}
