<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\GoldFormResource;
use App\Entity\GoldForm;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProviderInterface<GoldFormResource>
 */
final readonly class GoldFormProvider implements ProviderInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return GoldFormResource|list<GoldFormResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GoldFormResource|array|null
    {
        $repository = $this->em->getRepository(GoldForm::class);

        if (isset($uriVariables['id'])) {
            $goldForm = $repository->find($uriVariables['id']);

            return $goldForm ? GoldFormResource::fromEntity($goldForm) : null;
        }

        $goldForms = $repository->findBy([], ['name' => 'ASC']);

        return array_map(GoldFormResource::fromEntity(...), $goldForms);
    }
}
