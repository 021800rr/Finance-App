<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\DatabaseLimits;
use App\Entity\GoldAsset;
use App\Form\GoldAssetType;
use App\Repository\GoldAssetRepository;
use App\Service\GoldAssetFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/gold-assets')]
class GoldAssetController extends AbstractController
{
    #[Route(path: '', name: 'gold_assets', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        GoldAssetRepository $goldAssetRepository,
        GoldAssetFactory $goldAssetFactory,
    ): Response {
        $form = $this->createForm(GoldAssetType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $asset = $goldAssetFactory->createFromForm($form);
            $goldAssetRepository->save($asset, flush: true);

            return $this->redirectToRoute('gold_assets');
        }

        return $this->render('gold_asset/index.html.twig', [
            'form' => $form->createView(),
            'assets' => $goldAssetRepository->findAllOrdered(),
        ]);
    }

    #[Route(
        path: '/{id}/edit',
        name: 'gold_assets_edit',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['GET', 'POST'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function edit(
        GoldAsset $asset,
        Request $request,
        GoldAssetRepository $goldAssetRepository,
        GoldAssetFactory $goldAssetFactory,
    ): Response {
        $form = $this->createForm(GoldAssetType::class, options: [
            'existingAsset' => $asset,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $goldAssetFactory->updateFromForm($asset, $form);
            $goldAssetRepository->save($asset, flush: true);

            return $this->redirectToRoute('gold_assets');
        }

        return $this->render('gold_asset/edit.html.twig', [
            'form' => $form->createView(),
            'title' => 'gold_asset.edit_title',
        ]);
    }

    #[Route(
        path: '/{id}/delete',
        name: 'gold_assets_delete',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['POST'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function delete(
        GoldAsset $asset,
        Request $request,
        GoldAssetRepository $goldAssetRepository,
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('gold-asset-delete', is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $goldAssetRepository->remove($asset, true);

        return $this->redirectToRoute('gold_assets');
    }
}
