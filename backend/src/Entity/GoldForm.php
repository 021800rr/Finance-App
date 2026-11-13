<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\GoldFormRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GoldFormRepository::class)]
#[ORM\Table(name: 'gold_form')]
#[ORM\UniqueConstraint(name: 'uniq_gold_form_name', columns: ['name'])]
class GoldForm
{
    use TimestampTrait;
    use BlankStringGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(length: 50)]
    private string $name;

    public function __construct(string $name)
    {
        $this->setName($name);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        self::assertNotBlank($name, 'Gold form name');
        $this->name = $name;
    }
}
