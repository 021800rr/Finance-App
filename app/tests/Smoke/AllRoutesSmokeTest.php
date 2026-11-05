<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Entity\FixedExpense;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Entity\Household;
use App\Tests\Controller\LogsInTrait;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Opens every GET route as a logged-in user, the API ones with a JWT, so each controller and provider really runs.
 * A route with a parameter gets the id of a real record; a new parameter without one fails the test instead of
 * being skipped.
 */
final class AllRoutesSmokeTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LogsInTrait;
    use JwtTrait;

    /**
     * Query parameters a route needs before its provider runs at all.
     */
    private const array QUERIES = ['/api/account_operations.{_format}' => ['q' => 'Smoke']];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->initEntityManager($this->client->getKernel());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testEveryGetRouteAnswersALoggedInUserWithoutAServerError(): void
    {
        $ids = $this->createOneRecordOfEachKind();
        $apiHeaders = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt()];
        $this->logIn();
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $checked = 0;

        foreach ($router->getRouteCollection() as $name => $route) {
            $methods = $route->getMethods();
            if ($this->isSkipped($name) || ([] !== $methods && !in_array('GET', $methods, true))) {
                continue;
            }

            $path = $route->getPath();
            /** @var list<string> $variables */
            $variables = $route->compile()->getPathVariables();
            $parameters = $this->parametersFor($path, $variables, $ids);
            $url = $router->generate($name, $parameters + (self::QUERIES[$path] ?? []));
            $this->client->request('GET', $url, server: str_starts_with($path, '/api') ? $apiHeaders : []);
            $response = $this->client->getResponse();
            $where = sprintf('Route "%s" (GET %s) answered %d', $name, $url, $response->getStatusCode());

            self::assertLessThan(500, $response->getStatusCode(), $where);
            self::assertNotContains($response->getStatusCode(), [401, 403], $where.': the login did not work');
            self::assertStringNotContainsString('/login', (string) $response->headers->get('Location'), $where);
            ++$checked;
        }

        // Guards against a filter that quietly skips everything.
        self::assertGreaterThan(40, $checked);
    }

    /**
     * Routes of the profiler and the debug toolbar, and the logout, which would end the session for the rest.
     */
    private function isSkipped(string $name): bool
    {
        return '_logout_main' === $name || (str_starts_with($name, '_') && !str_starts_with($name, '_api'));
    }

    /**
     * @param list<string>       $variables
     * @param array<string, int> $ids       record id by the path in front of "/{id}"
     *
     * @return array<string, int|string>
     */
    private function parametersFor(string $path, array $variables, array $ids): array
    {
        $parameters = [];
        foreach ($variables as $variable) {
            $parameters[$variable] = match ($variable) {
                'id' => $ids[strstr($path, '/{id}', true)]
                    ?? self::fail(sprintf('No record id for "%s": add one to createOneRecordOfEachKind().', $path)),
                'accountId' => $ids['/account'],
                'status' => 404,
                'shortName' => 'Account',
                'index' => 'index',
                '_format' => 'jsonld',
                default => self::fail(sprintf('No value for "{%s}" in "%s".', $variable, $path)),
            };
        }

        return $parameters;
    }

    /**
     * The test database has accounts and dictionaries but no operations, expenses, gold or rates.
     *
     * @return array<string, int>
     */
    private function createOneRecordOfEachKind(): array
    {
        $account = $this->first(Account::class);
        $contractor = new Contractor('Smoke test contractor');
        $today = new \DateTimeImmutable();
        $operation = new AccountOperation($account, $contractor, $today, -1000, $account->getCurrency());
        $fixedExpense = new FixedExpense($contractor, 10, -1000, $this->find(Currency::class, 'PLN'));
        $goldAsset = new GoldAsset($this->first(GoldForm::class), 10000, 'Smoke test gold');
        $goldPrice = new GoldPrice($goldAsset, $today, 100000, $this->find(Currency::class, 'PLN'));
        $rate = new CurrencyExchangeRate($this->first(Bank::class), $this->find(Currency::class, 'CHF'), $today, 43210);
        foreach ([$contractor, $operation, $fixedExpense, $goldAsset, $goldPrice, $rate] as $entity) {
            $this->em()->persist($entity);
        }
        $this->em()->flush();

        return [
            '/account' => $account->getId(),
            '/operation' => $operation->getId(),
            '/fixed-expense' => $fixedExpense->getId(),
            '/gold-assets' => $goldAsset->getId(),
            '/api/accounts' => $account->getId(),
            '/api/account_operations' => $operation->getId(),
            '/api/account_types' => $this->first(AccountType::class)->getId(),
            '/api/banks' => $this->first(Bank::class)->getId(),
            '/api/contractors' => $contractor->getId(),
            '/api/currency_exchange_rates' => $rate->getId(),
            '/api/fixed_expenses' => $fixedExpense->getId(),
            '/api/gold_assets' => $goldAsset->getId(),
            '/api/gold_forms' => $this->first(GoldForm::class)->getId(),
            '/api/gold_prices' => $goldPrice->getId(),
            '/api/households' => $this->first(Household::class)->getId(),
            // Identifiers API Platform generates itself: an unknown one must be a 404, not a 500.
            '/api/.well-known/genid' => 1,
            '/api/validation_errors' => 1,
        ];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function first(string $class): object
    {
        $entity = $this->em()->getRepository($class)->findOneBy([]);
        self::assertNotNull($entity, sprintf('Fixtures must provide a %s.', $class));

        return $entity;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function find(string $class, string $id): object
    {
        $entity = $this->em()->find($class, $id);
        self::assertNotNull($entity, sprintf('Fixtures must provide %s "%s".', $class, $id));

        return $entity;
    }
}
