<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\DatabaseLimits;
use App\Entity\CurrencyExchangeRate;
use App\Form\CurrencyExchangeRateType;
use App\Repository\CurrencyExchangeRateRepository;
use App\Service\CurrencyExchangeRateFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/rate')]
class CurrencyExchangeRateController extends AbstractController
{
    #[Route(path: '', name: 'rate', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        CurrencyExchangeRateRepository $currencyExchangeRateRepository,
        CurrencyExchangeRateFactory $currencyExchangeRateFactory,
    ): Response {
        $form = $this->createForm(CurrencyExchangeRateType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rate = $currencyExchangeRateFactory->createFromForm($form);

            $currencyExchangeRateRepository->save($rate, flush: true);

            return $this->redirectToRoute('rate');
        }

        $rates = $currencyExchangeRateRepository->findAllOrderedForList();
        $chartData = $currencyExchangeRateRepository->findHistoricalRatesForChart();

        return $this->render('rate/index.html.twig', [
            'form' => $form->createView(),
            'rates' => $rates,
            'chartData' => $chartData,
        ]);
    }

    #[Route(
        path: '/{id}/delete',
        name: 'rate_delete',
        requirements: ['id' => Requirement::DIGITS],
        methods: ['POST'],
        condition: "params['id'] <= ".DatabaseLimits::POSTGRES_INT4_MAX
    )]
    public function delete(
        CurrencyExchangeRate $rate,
        Request $request,
        CurrencyExchangeRateRepository $currencyExchangeRateRepository,
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('rate-delete', is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $currencyExchangeRateRepository->remove($rate, true);

        return $this->redirectToRoute('rate');
    }
}
