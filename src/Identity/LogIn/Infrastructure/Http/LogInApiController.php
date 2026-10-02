<?php

declare(strict_types=1);

namespace App\Identity\LogIn\Infrastructure\Http;

use App\Identity\Domain\InvalidCredentials;
use App\Identity\LogIn\Application\LogIn;
use App\Identity\LogIn\Infrastructure\LoginThrottle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Login for API clients: answers a JWT to send as "Authorization: Bearer". No cookie is set.
 * Counts against the same rate limits as the web form.
 */
final class LogInApiController extends AbstractController
{
    #[Route('/api/v1/login', name: 'api_v1_identity_login', methods: [Request::METHOD_POST], format: 'json')]
    public function __invoke(Request $request, #[MapRequestPayload] LogInRequest $payload, LogIn $logIn, LoginThrottle $throttle): JsonResponse
    {
        if (null !== $retryAfter = $throttle->attempt($payload->email, $request->getClientIp())) {
            return $this->json(
                ['code' => 'too_many_login_attempts', 'message' => 'Too many login attempts. Try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) max(1, $retryAfter->getTimestamp() - time())],
            );
        }

        try {
            $accessToken = $logIn($payload->email, $payload->password);
        } catch (InvalidCredentials) {
            return $this->json(['code' => 'invalid_credentials', 'message' => 'Invalid email or password.'], Response::HTTP_UNAUTHORIZED);
        }

        $throttle->succeeded($payload->email, $request->getClientIp());

        return $this->json([
            'token' => $accessToken->value,
            'expires_at' => $accessToken->expiresAt->format(\DATE_ATOM),
        ]);
    }
}
