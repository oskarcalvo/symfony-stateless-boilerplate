<?php

declare(strict_types=1);

namespace App\Identity\GetAuthenticatedUser\Infrastructure\Http;

use App\Identity\Domain\UserId;
use App\Identity\GetAuthenticatedUser\Application\GetAuthenticatedUser;
use App\Identity\Infrastructure\Security\RespondToRejectedWebToken;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Shows who is logged in without any session: the only thing the browser sends is the JWT cookie,
 * whose "sub" identifies the user; name and email are read from the database on this very request.
 * Visitors without a valid token are not sent to the login form: they get a 401 (see security.yaml).
 */
final class GetAuthenticatedUserWebController extends AbstractController
{
    #[Route('/user', name: 'identity_user', methods: [Request::METHOD_GET])]
    public function __invoke(GetAuthenticatedUser $getAuthenticatedUser): Response
    {
        // Not #[CurrentUser] ?SecurityUser: a nullable controller argument makes the container try to inject it as a service.
        $user = $this->getUser();
        if (!$user instanceof SecurityUser) {
            return $this->render(RespondToRejectedWebToken::ACCESS_DENIED_TEMPLATE, response: new Response(status: Response::HTTP_UNAUTHORIZED));
        }

        return $this->render('identity/get_authenticated_user/show.html.twig', [
            'user' => $getAuthenticatedUser(UserId::fromString($user->getUserIdentifier())),
        ]);
    }
}
