<?php

declare(strict_types=1);

namespace App\Identity\GetAuthenticatedUser\Infrastructure\Http;

use App\Identity\Domain\UserId;
use App\Identity\GetAuthenticatedUser\Application\GetAuthenticatedUser;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetAuthenticatedUserApiController extends AbstractController
{
    #[Route('/api/v1/me', name: 'api_v1_identity_me', methods: [Request::METHOD_GET], format: 'json')]
    #[IsGranted('ROLE_USER')]
    public function __invoke(#[CurrentUser] SecurityUser $user, GetAuthenticatedUser $getAuthenticatedUser): JsonResponse
    {
        return $this->json($getAuthenticatedUser(UserId::fromString($user->getUserIdentifier())));
    }
}
