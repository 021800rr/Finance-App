<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

trait TimestampTrait
{
    #[ORM\Column(type: 'datetime_immutable', insertable: false, generated: 'INSERT')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', insertable: false, updatable: false, generated: 'ALWAYS')]
    private \DateTimeImmutable $updatedAt;

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
