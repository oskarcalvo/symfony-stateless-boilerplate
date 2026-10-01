<?php

declare(strict_types=1);

namespace App\Dashboard\ShowDashboard\Infrastructure\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ShowDashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard', methods: [Request::METHOD_GET])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(): Response
    {
        return $this->render('dashboard/show.html.twig');
    }
}
