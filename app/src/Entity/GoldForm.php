<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
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
    #[Assert\NotBlank(message: 'gold_form.name_not_blank')]
    #[Assert\Length(min: 2, max: 50, minMessage: 'gold_form.name_min_length', maxMessage: 'gold_form.name_max_length')]
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
