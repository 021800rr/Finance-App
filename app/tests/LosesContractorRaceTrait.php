<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Contractor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Two requests with one new name can both pass the duplicate check, and then the database refuses the second INSERT.
 * This reproduces that without real concurrency: right before the flush, "the other request" stores the same name.
 */
trait LosesContractorRaceTrait
{
    private function loseTheNextContractorRace(KernelBrowser $client): void
    {
        // A reboot before the next request would replace the entity manager this listener is attached to.
        $client->disableReboot();

        $entityManager = $client->getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $entityManager->getEventManager()->addEventListener(Events::preFlush, new class {
            private bool $raceLost = false;

            public function preFlush(PreFlushEventArgs $args): void
            {
                $entityManager = $args->getObjectManager();

                foreach ($entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
                    if ($entity instanceof Contractor && !$this->raceLost) {
                        $this->raceLost = true;
                        $entityManager->getConnection()->executeStatement(
                            "INSERT INTO contractor (id, name) VALUES (nextval('contractor_id_seq'), :name)",
                            ['name' => $entity->getName()],
                        );
                    }
                }
            }
        });
    }
}
