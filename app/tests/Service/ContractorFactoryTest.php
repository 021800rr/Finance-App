<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ContractorFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ContractorFactoryTest extends TestCase
{
    private ContractorFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ContractorFactory();
    }

    public function testCreateFromJsonRequestCreatesContractorWithGivenName(): void
    {
        $request = Request::create('/contractor/create', 'POST', content: (string) json_encode(['name' => 'Écolier']));

        $contractor = $this->factory->createFromJsonRequest($request);

        self::assertSame('Écolier', $contractor->getName());
    }

    public function testCreateFromJsonRequestTrimsWhitespaceFromName(): void
    {
        $request = Request::create('/contractor/create', 'POST', content: (string) json_encode(['name' => '  Écolier  ']));

        $contractor = $this->factory->createFromJsonRequest($request);

        self::assertSame('Écolier', $contractor->getName());
    }

    public function testCreateFromJsonRequestThrowsForInvalidJson(): void
    {
        $request = Request::create('/contractor/create', 'POST', content: 'not-json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON body.');

        $this->factory->createFromJsonRequest($request);
    }

    public function testCreateFromJsonRequestThrowsForJsonScalar(): void
    {
        $request = Request::create('/contractor/create', 'POST', content: '"just a string"');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON body.');

        $this->factory->createFromJsonRequest($request);
    }

    public function testCreateFromJsonRequestThrowsForMissingNameKey(): void
    {
        $request = Request::create('/contractor/create', 'POST', content: (string) json_encode(['other' => 'value']));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name cannot be empty.');

        $this->factory->createFromJsonRequest($request);
    }

    public function testCreateFromJsonRequestThrowsForEmptyName(): void
    {
        $request = Request::create('/contractor/create', 'POST', content: (string) json_encode(['name' => '']));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name cannot be empty.');

        $this->factory->createFromJsonRequest($request);
    }

    public function testCreateFromJsonRequestThrowsForWhitespaceOnlyName(): void
    {
        $request = Request::create('/contractor/create', 'POST', content: (string) json_encode(['name' => '   ']));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name cannot be empty.');

        $this->factory->createFromJsonRequest($request);
    }

    public function testCreateFromJsonRequestThrowsWhenNameIsNotAString(): void
    {
        $request = Request::create('/contractor/create', 'POST', content: (string) json_encode(['name' => 12345]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name cannot be empty.');

        $this->factory->createFromJsonRequest($request);
    }
}
