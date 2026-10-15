<?php

declare(strict_types=1);

namespace App\Tests\Constant;

use App\Constant\Contractors;
use PHPUnit\Framework\TestCase;

final class ContractorsTest extends TestCase
{
    public function testSelfTransferNameStaysPolishBecauseTheUiAndTheSearchDependOnIt(): void
    {
        self::assertSame('Przelew własny', Contractors::SELF_TRANSFER_NAME);
    }
}
