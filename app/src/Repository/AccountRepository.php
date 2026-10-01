<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Validator\IdValidator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
final class AccountRepository extends ServiceEntityRepository
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findByIdOrNull(mixed $rawId): ?Account
    {
        $id = IdValidator::tryParse($rawId);

        if (null === $id) {
            return null;
        }

        return $this->find($id);
    }

    public function createOrderedForSelectQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->join('a.bank', 'b')
            ->join('a.type', 't')
            ->join('a.currency', 'c')
            ->orderBy('b.name', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->addOrderBy('c.code', 'ASC')
            ->addOrderBy('a.accountNumber', 'ASC');
    }

    /**
     * The account used as the default counterparty/target for features like
     * "plan next month's fixed expenses" (see FixedExpensePlannerService).
     * There is at most one (enforced by the uniq_account_is_main partial
     * unique index) — null on a fresh install before any account has been
     * marked main via setAsMainAccount().
     */
    public function findMain(): ?Account
    {
        return $this->findOneBy(['isMain' => true]);
    }

    /**
     * Marks $account as the main account, unsetting the previous one (if
     * any) in the same transaction. This is the only place that should ever
     * set Account::isMain to true — do not call $account->setMain(true)
     * directly anywhere else, or two accounts could briefly (or, if a flush
     * is skipped, permanently) both claim to be main.
     *
     * Deliberately two flushes, not one: uniq_account_is_main is a plain
     * (non-deferrable) unique index, so it's checked after each statement,
     * not at COMMIT. In a single flush, Doctrine's write ordering between
     * the two UPDATEs isn't something this code controls, and it can emit
     * "set new account main" before "unset previous main", which the
     * database then rejects as a duplicate — confirmed by actually running
     * this against Postgres, not just reasoned about. Unsetting the
     * previous main account first and flushing immediately guarantees that
     * UPDATE lands before the one that sets the new main account, so the
     * constraint is never briefly violated.
     */
    public function setAsMainAccount(Account $account): void
    {
        $entityManager = $this->getEntityManager();

        $entityManager->wrapInTransaction(function () use ($entityManager, $account): void {
            $previousMain = $this->findMain();
            if (null !== $previousMain && $previousMain->getId() !== $account->getId()) {
                $previousMain->setMain(false);
                $entityManager->flush();
            }

            $account->setMain(true);
            $entityManager->flush();
        });
    }
}
