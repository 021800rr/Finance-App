<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AccountOperationRepository;
use App\Service\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/search')]
class SearchController extends AbstractController
{
    #[Route(path: '', name: 'global_search', methods: ['GET'])]
    public function index(Request $request, AccountOperationRepository $accountOperationRepository): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $total = 0;
        $operations = [];
        $paginator = Paginator::create(
            (int) $request->query->get('page', 1),
            0,
            Paginator::DEFAULT_PER_PAGE
        );

        if ('' !== $query) {
            $total = $accountOperationRepository->countByQuery($query);
            $paginator = Paginator::create(
                (int) $request->query->get('page', 1),
                $total,
                Paginator::DEFAULT_PER_PAGE
            );
            $operations = $accountOperationRepository
                ->searchByQueryPaged($query, $paginator->perPage, $paginator->offset);
        }

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'operations' => $operations,
            'page' => $paginator->page,
            'totalPages' => $paginator->totalPages,
            'total' => $total,
        ]);
    }
}
