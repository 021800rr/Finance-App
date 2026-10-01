<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
#[ORM\Table(name: 'currency')]
class Currency
{
    use TimestampTrait;
    use BlankStringGuardTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 3)]
    #[Assert\NotBlank(message: 'currency.code_not_blank')]
    #[Assert\Currency(message: 'currency.code_invalid_format')]
    private string $code;

    public function __construct(string $code)
    {
        self::assertNotBlank($code, 'Currency code');

        $normalizedCode = strtoupper($code);
        if (!Currencies::exists($normalizedCode)) {
            throw new \InvalidArgumentException('Currency code must be a valid ISO 4217 code.');
        }

        $this->code = $normalizedCode;
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
