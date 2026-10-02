<?php

declare(strict_types=1);

namespace App\Identity\RegisterUser\Infrastructure\Http;

use App\Identity\Domain\UserAlreadyExists;
use App\Identity\RegisterUser\Application\SignUp;
use App\Identity\RegisterUser\Infrastructure\RegistrationThrottle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public sign-up for API clients. Answers the new user and a JWT to send as "Authorization: Bearer".
 * No cookie is set: API clients keep the token themselves.
 */
final class RegisterUserApiController extends AbstractController
{
    #[Route('/api/v1/register', name: 'api_v1_identity_register', methods: [Request::METHOD_POST], format: 'json')]
    public function __invoke(Request $request, #[MapRequestPayload] RegisterUserRequest $payload, SignUp $signUp, RegistrationThrottle $throttle): JsonResponse
    {
        if (null !== $retryAfter = $throttle->attempt($request->getClientIp())) {
            $seconds = max(1, $retryAfter->getTimestamp() - time());

            return $this->json(
                ['code' => 'too_many_registrations', 'message' => 'Too many sign-ups from this IP address. Try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) $seconds],
            );
        }

        try {
            $signedUp = $signUp($payload->email, $payload->name, $payload->password);
        } catch (UserAlreadyExists $e) {
            return $this->json(['code' => 'user_already_exists', 'message' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['code' => 'invalid_user_data', 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'id' => $signedUp->id,
            'email' => $signedUp->email,
            'name' => $signedUp->name,
            'token' => $signedUp->accessToken->value,
            'expires_at' => $signedUp->accessToken->expiresAt->format(\DATE_ATOM),
        ], Response::HTTP_CREATED);
    }
}
