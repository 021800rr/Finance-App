<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\DatabaseLimits;
use App\Entity\GoldPrice;
use App\Form\GoldPriceType;
use App\Repository\GoldPriceRepository;
use App\Service\GoldPriceFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/gold-prices')]
class GoldPriceController extends AbstractController
{
    #[Route(path: '', name: 'gold_prices', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        GoldPriceRepository $goldPriceRepository,
        GoldPriceFactory $goldPriceFactory,
    ): Response {
        $form = $this->createForm(GoldPriceType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $goldPrice = $goldPriceFactory->createFromForm($form);

            $goldPriceRepository->save($goldPrice, flush: true);

            return $this->redirectToRoute('gold_prices');
        }

        $prices = $goldPriceRepository->findLatestOrdered();
        $chartData = $goldPriceRepository->findHistoricalPricesForChart();

        return $this->render('gold_price/index.html.twig', [
            'form' => $form->createView(),
            'prices' => $prices,
            'chartData' => $chartData,
        ]);
    }

    #[Route(
        path: '/{id}/delete',
        name: 'gold_prices_delete',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['POST'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function delete(
        GoldPrice $price,
        Request $request,
        GoldPriceRepository $goldPriceRepository,
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('gold-price-delete', is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $goldPriceRepository->remove($price, true);

        return $this->redirectToRoute('gold_prices');
    }
}
