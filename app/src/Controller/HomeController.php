<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constant\AccountTypeNames;
use App\Repository\AccountRepository;
use App\Service\SummaryService;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route(path: '/', name: 'home', methods: ['GET'])]
    public function index(SummaryService $summary, AccountRepository $accountRepository, Request $request): Response
    {
        $showHidden = (bool) $request->query->get('show_hidden', false);

        return $this->render('home/index.html.twig', array_merge(
            $summary->getSummary($showHidden),
            [
                'showHidden' => $showHidden,
                'rorTypeName' => AccountTypeNames::ROR,
                'wakacjeTypeName' => AccountTypeNames::WAKACJE,
                'mainAccountId' => $accountRepository->findMain()?->getId(),
            ]
        ));
    }
}
