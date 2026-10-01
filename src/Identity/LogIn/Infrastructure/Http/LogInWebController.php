<?php

declare(strict_types=1);

namespace App\Identity\LogIn\Infrastructure\Http;

use App\Identity\Domain\InvalidCredentials;
use App\Identity\Infrastructure\Security\AccessTokenCookie;
use App\Identity\LogIn\Application\LogIn;
use App\Identity\LogIn\Infrastructure\LoginThrottle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogInWebController extends AbstractController
{
    #[Route('/login', name: 'identity_login', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function __invoke(Request $request, LogIn $logIn, LoginThrottle $throttle): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('dashboard');
        }

        $form = $this->createForm(LoginFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var LoginFormData $data */
            $data = $form->getData();
            $email = (string) $data->email;

            if (null !== $retryAfter = $throttle->attempt($email, $request->getClientIp())) {
                $minutes = max(1, (int) ceil(($retryAfter->getTimestamp() - time()) / 60));
                $form->addError(new FormError(\sprintf('Demasiados intentos. Vuelve a intentarlo en %d minuto(s).', $minutes)));

                return $this->render('identity/log_in/login.html.twig', ['form' => $form], new Response(
                    status: Response::HTTP_TOO_MANY_REQUESTS,
                    headers: ['Retry-After' => (string) max(1, $retryAfter->getTimestamp() - time())],
                ));
            }

            try {
                $accessToken = $logIn($email, (string) $data->password);
                $throttle->succeeded($email, $request->getClientIp());

                $response = $this->redirectToRoute('dashboard', status: Response::HTTP_SEE_OTHER);
                $response->headers->setCookie(AccessTokenCookie::for($accessToken, $request));

                return $response;
            } catch (InvalidCredentials) {
                $form->addError(new FormError('Email o contraseña incorrectos.'));
            }
        }

        // AbstractController::render() answers 422 when the submitted form is invalid (needed by Turbo).
        return $this->render('identity/log_in/login.html.twig', [
            'form' => $form,
        ]);
    }
}
