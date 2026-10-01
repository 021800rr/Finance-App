Sprawdźmy to precyzyjnie — trait **rozwiązuje** dokładnie ten problem, który mieliśmy przy dwóch **różnych** klasach bazowych (`KernelTestCase` i `WebTestCase`) — PHP **nie pozwala** na wielokrotne dziedziczenie klas, ale **pozwala** na **wielokrotne** użycie traitów w **różnych** klasach.

## Rozwiązanie — jeden trait, używany przez OBIE kategorie testów

```php
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
```

## Użycie w teście `KernelTestCase`

```php
final class CurrencyExchangeRateFactoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        // reszta setUp jak zwykle
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testCos(): void
    {
        $this->em()->flush();
    }
}
```

## Użycie w teście `WebTestCase`

```php
final class AccountControllerTest extends WebTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        $client = static::createClient();
        $this->initEntityManager($client->getKernel());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }
}
```

## Dlaczego trait jest LEPSZY niż dwie osobne klasy bazowe

**Jeden** plik do utrzymania (nie dwa, prawie identyczne). **Ta sama** logika `em()`/`closeEntityManager()` w **obu** kontekstach, bez duplikacji. **Elastyczność** — możesz **dowolnie** dopasować `setUp()` w każdym teście (bo trait **nie narzuca** struktury `setUp()`, tylko **dostarcza** metody pomocnicze), co jest **ważne**, bo `KernelTestCase` i `WebTestCase` mają **różne** sposoby uzyskania kernela (`bootKernel()` vs `createClient()->getKernel()`).

To jest **czystsze** rozwiązanie dla Twojego pytania — jeden trait, używany **wszędzie**, bez potrzeby dwóch równoległych hierarchii klas bazowych.
