<?php

//declare(strict_types=1);
//
//namespace App\Tests\Audit;
//
//use App\Constant\Contractors;
//use App\Entity\Account;
//use App\Entity\AccountOperation;
//use App\Entity\Contractor;
//use App\Entity\CurrencyExchangeRate;
//use App\Entity\FixedExpense;
//use App\Entity\GoldAsset;
//use App\Entity\GoldForm;
//use App\Entity\GoldPrice;
//use App\Tests\Controller\LogsInTrait;
//use App\Tests\DatabaseTestTrait;
//use PHPUnit\Framework\Attributes\DataProvider;
//use Symfony\Bundle\FrameworkBundle\KernelBrowser;
//use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
//
///**
// * Explicit diagnostic run only; outside tests/ so known defects do not break the normal test suite.
// * Assertions require correct behaviour. Failures reproduce defects; they do not declare HTTP 500 acceptable.
// */
//final class FormValidationAuditTest extends WebTestCase
//{
//    use DatabaseTestTrait;
//    use LogsInTrait;
//
//    private KernelBrowser $client;
//    private Account $account;
//    private Account $foreign;
//    private Contractor $contractor;
//    private GoldAsset $asset;
//    private FixedExpense $expense;
//    private AccountOperation $operation;
//
//    protected function setUp(): void
//    {
//        $this->client = self::createClient();
//        $this->initEntityManager($this->client->getKernel());
//        $this->logIn();
//        $account = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => 'Bank1-ROR-PLN']);
//        $foreign = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => 'Bank1-WAK-CHF']);
//        $form = $this->em()->getRepository(GoldForm::class)->findOneBy([]);
//        self::assertInstanceOf(Account::class, $account);
//        self::assertInstanceOf(Account::class, $foreign);
//        self::assertInstanceOf(GoldForm::class, $form);
//        $this->account = $account;
//        $this->foreign = $foreign;
//        $this->contractor = new Contractor('Audit contractor '.uniqid());
//        $this->asset = new GoldAsset($form, 10000, 'Original asset');
//        $this->expense = new FixedExpense($this->contractor, 10, -1000, $account->getCurrency());
//        $this->operation = new AccountOperation(
//            $account, $this->contractor, new \DateTimeImmutable('2026-01-15'), 1000, $account->getCurrency(),
//            description: 'Original operation',
//        );
//        foreach ([$this->contractor, $this->asset, $this->expense, $this->operation] as $entity) {
//            $this->em()->persist($entity);
//        }
//        $this->em()->flush();
//    }
//
//    protected function tearDown(): void
//    {
//        parent::tearDown();
//        $this->closeEntityManager();
//    }
//
//    #[DataProvider('formCases')]
//    public function testForm(
//        string $kind,
//        string $mode,
//        string $field,
//        string $variant,
//        mixed $value,
//        bool $valid,
//    ): void {
//        [$uri, $prefix, $selector, $values] = $this->formData($kind, $mode);
//        $crawler = $this->client->request('GET', $uri);
//        self::assertResponseIsSuccessful();
//        $parameters = $crawler->filter($selector)->form()->getPhpValues();
//        $parameters[$prefix] = array_replace($parameters[$prefix], $values);
//        if (in_array($kind, ['operation', 'expense'], true)) {
//            $parameters['contractor_input'] = $this->contractor->getName();
//        }
//        if ('missing' === $variant) {
//            unset($parameters[$prefix][$field]);
//        } elseif ('baseline' !== $variant) {
//            $parameters[$prefix][$field] = match ($variant) {
//                'same account' => (string) $this->account->getId(),
//                'other contractor' => (string) $this->contractor->getId(),
//                default => $value,
//            };
//        }
//        if ('same currency without amountTo' === $variant) {
//            $target = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => 'Bank1-WAK-PLN']);
//            self::assertInstanceOf(Account::class, $target);
//            $parameters[$prefix]['toAccount'] = (string) $target->getId();
//            unset($parameters[$prefix]['amountTo']);
//        }
//        $before = $this->snapshot();
//        $this->client->enableProfiler();
//        $this->client->request('POST', $uri, $parameters);
//        $this->record($kind.'/'.$mode, $field, $variant, $valid, $before);
//    }
//
//    /** @return iterable<string, array{string, string, string, string, mixed, bool}> */
//    public static function formCases(): iterable
//    {
//        $definitions = [
//            'operation' => [['new', 'edit'], ['account', 'contractor', 'operationDate', 'amount'], 'amount'],
//            'expense' => [['new', 'edit'], ['day', 'contractor', 'amount'], 'amount'],
//            'gold' => [['new', 'edit'], ['form', 'ounces', 'description'], 'ounces'],
//            'price' => [['new'], ['goldAsset', 'priceDate', 'pricePln'], 'pricePln'],
//            'rate' => [['new'], ['bank', 'currency', 'rateDate', 'rateToPln'], 'rateToPln'],
//            'transfer' => [['new'], ['fromAccount', 'toAccount', 'date', 'amountFrom'], 'amountFrom'],
//        ];
//        foreach ($definitions as $kind => [$modes, $required, $numeric]) {
//            foreach ($modes as $mode) {
//                yield "$kind/$mode baseline" => [$kind, $mode, '', 'baseline', null, true];
//                foreach ($required as $field) {
//                    if ('operation' === $kind && 'edit' === $mode && 'account' === $field) {
//                        continue; // This field is disabled and deliberately ignored on edit.
//                    }
//                    $emptyCases = ['missing' => null, 'empty' => '', 'spaces' => '   ', 'array' => ['x']];
//                    foreach ($emptyCases as $case => $value) {
//                        yield "$kind/$mode $field $case" => [$kind, $mode, $field, $case, $value, false];
//                    }
//                }
//                foreach (['letters' => 'abc', '17 digits' => str_repeat('9', 17)] as $case => $value) {
//                    yield "$kind/$mode $numeric $case" => [$kind, $mode, $numeric, $case, $value, false];
//                }
//                foreach ($required as $field) {
//                    if (in_array($field, ['account', 'contractor', 'form', 'goldAsset', 'bank', 'currency',
//                        'fromAccount', 'toAccount'], true)) {
//                        if ('operation' === $kind && 'edit' === $mode && 'account' === $field) {
//                            continue;
//                        }
//                        yield "$kind/$mode $field unknown" => [$kind, $mode, $field, 'unknown', '2147483647', false];
//                        yield "$kind/$mode $field huge ID" => [
//                            $kind, $mode, $field, 'huge ID', str_repeat('9', 33), false,
//                        ];
//                        yield "$kind/$mode $field ID above SQL limit" => [
//                            $kind, $mode, $field, 'ID above SQL limit', '2147483648', false,
//                        ];
//                    }
//                    if (in_array($field, ['operationDate', 'priceDate', 'rateDate', 'date'], true)) {
//                        foreach (['2026-02-31', '2024-12-31', '2071-01-01', 'tomorrow'] as $value) {
//                            yield "$kind/$mode $field $value" => [$kind, $mode, $field, $value, $value, false];
//                        }
//                    }
//                }
//                if (in_array($kind, ['operation', 'expense', 'gold', 'transfer'], true)) {
//                    foreach ([255, 256] as $length) {
//                        yield "$kind/$mode description $length" => [
//                            $kind, $mode, 'description', (string) $length, str_repeat('a', $length), 255 === $length,
//                        ];
//                    }
//                    if ('gold' !== $kind) {
//                        yield "$kind/$mode optional description" => [$kind, $mode, 'description', 'empty', '', true];
//                        yield "$kind/$mode description array" => [$kind, $mode, 'description', 'array', ['x'], false];
//                    }
//                }
//                yield "$kind/$mode invalid CSRF" => [$kind, $mode, '_token', 'invalid CSRF', 'invalid', false];
//                yield "$kind/$mode extra field" => [$kind, $mode, 'unknown', 'extra field', 'value', false];
//            }
//        }
//        foreach (['new', 'edit'] as $mode) {
//            foreach (['0', '29', '1.5', 'abc'] as $value) {
//                yield "expense/$mode day $value" => ['expense', $mode, 'day', $value, $value, false];
//            }
//        }
//        yield 'transfer same account' => ['transfer', 'new', 'toAccount', 'same account', null, false];
//        yield 'transfer other contractor' => ['transfer', 'new', 'contractor', 'other contractor', null, false];
//        yield 'transfer same currency without amountTo' => [
//            'transfer', 'new', 'amountTo', 'same currency without amountTo', null, true,
//        ];
//        foreach (['missing' => null, 'empty' => '', 'letters' => 'abc', 'array' => ['x']] as $case => $value) {
//            yield 'transfer amountTo '.$case => ['transfer', 'new', 'amountTo', $case, $value, false];
//        }
//        foreach (['new', 'edit'] as $mode) {
//            yield "operation/$mode confirmed missing" => ['operation', $mode, 'confirmed', 'missing', null, true];
//            yield "operation/$mode confirmed checked" => ['operation', $mode, 'confirmed', 'checked', '1', true];
//            yield "operation/$mode confirmed array" => ['operation', $mode, 'confirmed', 'array', ['x'], false];
//        }
//    }
//
//    #[DataProvider('contractorCases')]
//    public function testContractor(string $variant, mixed $name, bool $valid): void
//    {
//        $crawler = $this->client->request('GET', '/');
//        $csrf = $crawler->filter('meta[name="csrf-token-contractor-create"]')->attr('content');
//        $payload = 'missing' === $variant ? [] : ['name' => $name];
//        if ('duplicate' === $variant) {
//            $payload['name'] = $this->contractor->getName();
//        }
//        $before = $this->snapshot();
//        $this->client->enableProfiler();
//        $this->client->request('POST', '/contractor/create', server: [
//            'HTTP_X-CSRF-Token' => $csrf, 'CONTENT_TYPE' => 'application/json',
//        ], content: json_encode($payload, JSON_THROW_ON_ERROR));
//        $this->record('contractor/new', 'name', $variant, $valid, $before);
//    }
//
//    /** @return iterable<string, array{string, mixed, bool}> */
//    public static function contractorCases(): iterable
//    {
//        foreach (['missing' => null, 'empty' => '', 'spaces' => '   ', 'array' => ['x'], 'short' => 'x',
//            'padded short' => ' x ', 'long' => str_repeat('a', 256), 'duplicate' => ''] as $case => $value) {
//            yield $case => [$case, $value, false];
//        }
//        yield 'minimum' => ['minimum', 'xy', true];
//        yield 'maximum' => ['maximum', str_repeat('a', 255), true];
//        yield 'unicode maximum' => ['unicode maximum', str_repeat('ą', 255), true];
//    }
//
//    #[DataProvider('searchCases')]
//    public function testSearch(string $uri, string $variant, mixed $value): void
//    {
//        $before = $this->snapshot();
//        $this->client->enableProfiler();
//        $this->client->request('GET', $uri, 'missing' === $variant ? [] : ['q' => $value]);
//        $this->record($uri, 'q', $variant, 'array' !== $variant, $before, 'array' === $variant ? [400] : [200]);
//    }
//
//    /** @return iterable<string, array{string, string, mixed}> */
//    public static function searchCases(): iterable
//    {
//        foreach (['/search', '/contractor/search'] as $uri) {
//            foreach (['missing' => null, 'empty' => '', 'spaces' => '   ', 'array' => ['x'],
//                'long text' => str_repeat('a', 256), '33 digits' => str_repeat('9', 33),
//                'literal wildcard' => '%_'] as $case => $value) {
//                yield $uri.' '.$case => [$uri, $case, $value];
//            }
//        }
//    }
//
//    #[DataProvider('loginCases')]
//    public function testLogin(string $field, string $variant, mixed $value): void
//    {
//        $this->client->getCookieJar()->clear();
//        self::getContainer()->get('cache.rate_limiter')->clear();
//        $crawler = $this->client->request('GET', '/login');
//        $parameters = $crawler->filter('form')->form()->getPhpValues();
//        $parameters['_username'] = 'test@example.com';
//        $parameters['_password'] = 'test1234';
//        if ('missing' === $variant) {
//            unset($parameters[$field]);
//        } elseif ('valid' !== $variant) {
//            $parameters[$field] = $value;
//        }
//        $before = $this->snapshot();
//        // This Symfony profiler version crashes while redacting an array-shaped password (see audit report).
//        if ('_password' !== $field || 'array' !== $variant || '1' === getenv('FORM_AUDIT_LOGIN_PROFILER')) {
//            $this->client->enableProfiler();
//        }
//        $this->client->request('POST', '/login', $parameters);
//        $this->record('login', $field, $variant, 'valid' === $variant, $before, [302, 400]);
//        if ($this->client->getResponse()->isRedirect()) {
//            self::assertResponseRedirects('valid' === $variant ? '/' : '/login');
//            if ('valid' !== $variant) {
//                $this->client->followRedirect();
//                self::assertSelectorExists('.alert-danger');
//            }
//        }
//    }
//
//    /** @return iterable<string, array{string, string, mixed}> */
//    public static function loginCases(): iterable
//    {
//        foreach (['_username', '_password'] as $field) {
//            foreach (['missing' => null, 'empty' => '', 'spaces' => '   ', 'array' => ['x'],
//                'long' => str_repeat('a', 5000)] as $case => $value) {
//                yield $field.' '.$case => [$field, $case, $value];
//            }
//        }
//        yield 'invalid email' => ['_username', 'invalid email', 'not-an-email'];
//        yield 'missing CSRF' => ['_csrf_token', 'missing', null];
//        yield 'invalid CSRF' => ['_csrf_token', 'invalid', 'invalid'];
//        yield 'valid' => ['', 'valid', null];
//    }
//
//    #[DataProvider('buttonCases')]
//    public function testButton(string $action, string $variant, mixed $token): void
//    {
//        $price = new GoldPrice(
//            $this->asset, new \DateTimeImmutable('2026-01-15'), 10000, $this->account->getCurrency(),
//        );
//        $rate = new CurrencyExchangeRate(
//            $this->foreign->getBank(), $this->foreign->getCurrency(), new \DateTimeImmutable('2026-01-15'), 40000,
//        );
//        $this->em()->persist($price);
//        $this->em()->persist($rate);
//        $this->em()->flush();
//        $uri = match ($action) {
//            'operation delete' => '/operation/'.$this->operation->getId().'/delete',
//            'expense delete' => '/fixed-expense/'.$this->expense->getId().'/delete',
//            'gold delete' => '/gold-assets/'.$this->asset->getId().'/delete',
//            'price delete' => '/gold-prices/'.$price->getId().'/delete',
//            'rate delete' => '/rate/'.$rate->getId().'/delete',
//            'account hide' => '/account/'.$this->account->getId().'/toggle-hidden',
//            'account main' => '/account/'.$this->foreign->getId().'/set-main',
//            'plan' => '/fixed-expense/plan',
//        };
//        $this->client->request('GET', '/');
//        $before = $this->snapshot();
//        $this->client->enableProfiler();
//        $this->client->request('POST', $uri, 'missing' === $variant ? [] : ['_token' => $token]);
//        $this->record('button/'.$action, '_token', $variant, false, $before, [400, 403]);
//    }
//
//    /** @return iterable<string, array{string, string, mixed}> */
//    public static function buttonCases(): iterable
//    {
//        foreach (['operation delete', 'expense delete', 'gold delete', 'price delete', 'rate delete',
//            'account hide', 'account main', 'plan'] as $action) {
//            foreach (['missing' => null, 'empty' => '', 'invalid' => 'invalid', 'array' => ['x']] as $case => $value) {
//                yield $action.' '.$case => [$action, $case, $value];
//            }
//        }
//    }
//
//    /** @return array{string, string, string, array<string, string>} */
//    private function formData(string $kind, string $mode): array
//    {
//        return match ($kind) {
//            'operation' => [
//                'new' === $mode ? '/operation/new' : '/operation/'.$this->operation->getId().'/edit',
//                'account_operation', '#operation_form', [
//                    'account' => (string) $this->account->getId(), 'contractor' => (string) $this->contractor->getId(),
//                    'operationDate' => '2026-02-15', 'amount' => '20.00', 'description' => 'Submitted operation',
//                ],
//            ],
//            'expense' => [
//                'new' === $mode ? '/fixed-expense' : '/fixed-expense/'.$this->expense->getId().'/edit',
//                'fixed_expense', '#fixed_expense_form', [
//                    'contractor' => (string) $this->contractor->getId(), 'day' => '20',
//                    'amount' => '-20.00', 'description' => 'Submitted expense',
//                ],
//            ],
//            'gold' => [
//                'new' === $mode ? '/gold-assets' : '/gold-assets/'.$this->asset->getId().'/edit',
//                'gold_asset', '#gold_asset_form', [
//                    'form' => (string) $this->asset->getForm()->getId(),
//                    'description' => 'Submitted asset', 'ounces' => '2.0000',
//                ],
//            ],
//            'price' => ['/gold-prices', 'gold_price', '#gold_price_form', [
//                'goldAsset' => (string) $this->asset->getId(), 'priceDate' => '2026-02-15', 'pricePln' => '20.00',
//            ]],
//            'rate' => ['/rate', 'currency_exchange_rate', '#rate_form', [
//                'bank' => (string) $this->foreign->getBank()->getId(), 'currency' => 'CHF',
//                'rateDate' => '2026-02-15', 'rateToPln' => '4.0000',
//            ]],
//            'transfer' => ['/transfer/new', 'transfer', '#transfer_form', [
//                'fromAccount' => (string) $this->account->getId(), 'toAccount' => (string) $this->foreign->getId(),
//                'date' => '2026-02-15',
//                'amountFrom' => '20.00', 'amountTo' => '5.00', 'description' => 'Submitted transfer',
//            ]],
//        };
//    }
//
//    /** @return array<string, mixed> */
//    private function snapshot(): array
//    {
//        $snapshot = [];
//        foreach (['account', 'contractor', 'account_operation', 'fixed_expense', 'gold_asset',
//            'gold_price', 'currency_exchange_rate'] as $table) {
//            $snapshot[$table] = $this->em()->getConnection()->fetchAllAssociative(
//                'SELECT * FROM '.$table.' ORDER BY id',
//            );
//        }
//
//        return $snapshot;
//    }
//
//    /**
//     * @param array<string, mixed> $before
//     * @param list<int>|null $readStatuses
//     */
//    private function record(
//        string $form,
//        string $field,
//        string $variant,
//        bool $valid,
//        array $before,
//        ?array $readStatuses = null,
//    ): void {
//        $response = $this->client->getResponse();
//        $status = $response->getStatusCode();
//        $snapshotError = null;
//        try {
//            $after = $this->snapshot();
//        } catch (\Doctrine\DBAL\Exception $error) {
//            // PostgreSQL can abort DAMA's test transaction after a failed SELECT. Do not hide the HTTP result.
//            $after = null;
//            $snapshotError = $error->getMessage();
//        }
//        $changed = null === $after ? null : array_keys(array_filter(
//            $after, static fn ($rows, $table) => $rows !== $before[$table], ARRAY_FILTER_USE_BOTH,
//        ));
//        $exception = null;
//        $profile = $this->client->getProfile();
//        if ($profile && $profile->getCollector('exception')->hasException()) {
//            $error = $profile->getCollector('exception')->getException();
//            $exception = [
//                'class' => $error->getClass(), 'message' => $error->getMessage(),
//                'file' => $error->getFile(), 'line' => $error->getLine(),
//            ];
//        }
//        $crawler = $this->client->getCrawler();
//        $errors = [];
//        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
//            $errors = $crawler->filter('.invalid-feedback, .alert-danger')
//                ->each(static fn ($node) => trim($node->text()));
//            $errors = array_values(array_filter(array_unique($errors)));
//        } else {
//            $json = json_decode((string) $response->getContent(), true);
//            $errors = is_array($json) ? ($json['errors'] ?? []) : [];
//        }
//        $ok = $valid ? ($status < 400 && [] !== $changed)
//            : ($status >= 400 && $status < 500 || 200 === $status && [] !== $errors) && [] === $changed;
//        if (null !== $readStatuses) {
//            $ok = in_array($status, $readStatuses, true) && [] === $changed;
//        }
//        $ok = $ok && null === $snapshotError;
//        $newOperations = array_values(array_filter($after['account_operation'] ?? [], static fn ($row) =>
//            !in_array($row['id'], array_column($before['account_operation'], 'id'), true)));
//        if ('other contractor' === $variant) {
//            $fixed = array_values(array_filter($before['contractor'], static fn ($row) =>
//                Contractors::SELF_TRANSFER_NAME === $row['name']));
//            // Both rejecting the field and ignoring it in favour of the fixed contractor are valid fixes.
//            $ok = $ok || (302 === $status && 2 === count($newOperations)
//                && array_unique(array_column($newOperations, 'contractor_id')) === [$fixed[0]['id']]);
//        }
//        fwrite(STDOUT, 'AUDIT '.json_encode([
//            'form' => $form, 'field' => $field, 'variant' => $variant, 'expected_valid' => $valid,
//            'status' => $status, 'changed_tables' => $changed, 'errors' => $errors,
//            'exception' => $exception, 'ok' => $ok,
//            'snapshot_error' => $snapshotError,
//            'operation_contractors' => array_column($newOperations, 'contractor_id'),
//            'submitted_other_contractor_id' => 'other contractor' === $variant ? $this->contractor->getId() : null,
//        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
//        self::assertTrue($ok, $form.' '.$field.' '.$variant.'; see AUDIT record above.');
//    }
//}
