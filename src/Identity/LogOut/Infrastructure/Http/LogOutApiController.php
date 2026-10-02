<?php

declare(strict_types=1);

namespace App\Identity\LogOut\Infrastructure\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Revokes the token the request was authenticated with: Security::logout() dispatches the LogoutEvent on
 * which Lexik's blocklist (blocklist_token) stores its "jti" until it expires. Other tokens of the same
 * user (other devices) stay valid.
 *
 * A controller instead of the firewall "logout" option, which would also answer GET. No CSRF token: the JWT
 * travels in a header, and the BEARER cookie is SameSite=Lax, so a cross-site POST does not carry it.
 */
final class LogOutApiController extends AbstractController
{
    #[Route('/api/v1/logout', name: 'api_v1_identity_logout', methods: [Request::METHOD_POST], format: 'json')]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Security $security): Response
    {
        $security->logout(validateCsrfToken: false);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
