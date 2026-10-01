<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\HttpKernel\KernelInterface;

trait DatabaseTestTrait
{
    private ?EntityManager $entityManager = null;

    private function initEntityManager(KernelInterface $kernel): void
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine = $kernel->getContainer()->get('doctrine');

        /** @var EntityManager $entityManager */
        $entityManager = $doctrine->getManager();
        $this->entityManager = $entityManager;
    }

    protected function closeEntityManager(): void
    {
        $this->em()->close();
        $this->entityManager = null;
    }

    protected function em(): EntityManager
    {
        if (null === $this->entityManager) {
            throw new LogicException('EntityManager is not initialized.');
        }

        return $this->entityManager;
    }
}
