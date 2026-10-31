<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\GoldFormResource;
use App\Exception\RecordNotFoundException;
use App\Repository\GoldFormRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @return GoldFormResource|list<GoldFormResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GoldFormResource|array
    {
        if (isset($uriVariables['id'])) {
            $goldForm = $this->goldFormRepository->findByIdOrNull($uriVariables['id']);

            if (!$goldForm) {
                $exception = RecordNotFoundException::goldForm();

                throw new NotFoundHttpException($exception->getMessage(), $exception);
            }

            return GoldFormResource::fromEntity($goldForm);
        }

        $goldForms = $this->goldFormRepository->findBy([], ['name' => 'ASC']);

        return array_map(GoldFormResource::fromEntity(...), $goldForms);
    }
}
