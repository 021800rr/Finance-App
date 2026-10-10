<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\GoldFormResource;
use App\Repository\GoldFormRepository;

/**
 * @implements ProviderInterface<GoldFormResource>
 */
final readonly class GoldFormProvider implements ProviderInterface
{
    public function __construct(private GoldFormRepository $goldFormRepository)
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
        if (isset($uriVariables['id'])) {
            $goldForm = $this->goldFormRepository->findByIdOrNull($uriVariables['id']);

            return $goldForm ? GoldFormResource::fromEntity($goldForm) : null;
        }

        $goldForms = $this->goldFormRepository->findBy([], ['name' => 'ASC']);

        return array_map(GoldFormResource::fromEntity(...), $goldForms);
    }
}
