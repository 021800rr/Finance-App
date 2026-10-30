<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\ContractorRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContractorRepository::class)]
#[ORM\Table(name: 'contractor')]
#[ORM\UniqueConstraint(name: 'uniq_contractor_name', columns: ['name'])]
#[UniqueEntity(fields: ['name'], message: 'contractor.name_not_unique')]
class Contractor
{
    use TimestampTrait;
    use BlankStringGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'contractor.name_not_blank')]
    #[Assert\Length(max: 255, maxMessage: 'contractor.name_max_length')]
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
        self::assertNotBlank($name, 'Contractor name');
        $this->name = $name;
    }
}
