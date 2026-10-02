<?php

declare(strict_types=1);

namespace App\Identity\RegisterUser\Infrastructure\Http;

use App\Identity\Domain\UserAlreadyExists;
use App\Identity\Infrastructure\Security\AccessTokenCookie;
use App\Identity\RegisterUser\Application\SignUp;
use App\Identity\RegisterUser\Infrastructure\RegistrationThrottle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public sign-up form. On success the new user is logged in (BEARER cookie) and sent to the dashboard.
 */
final class RegisterUserWebController extends AbstractController
{
    private const string TEMPLATE = 'identity/register_user/register.html.twig';

    #[Route('/register', name: 'identity_register', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function __invoke(Request $request, SignUp $signUp, RegistrationThrottle $throttle): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('dashboard');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (null !== $retryAfter = $throttle->attempt($request->getClientIp())) {
                $seconds = max(1, $retryAfter->getTimestamp() - time());
                $form->addError(new FormError(\sprintf('Demasiados registros desde tu conexión. Vuelve a intentarlo en %d minuto(s).', (int) ceil($seconds / 60))));

                return $this->render(self::TEMPLATE, ['form' => $form], new Response(
                    status: Response::HTTP_TOO_MANY_REQUESTS,
                    headers: ['Retry-After' => (string) $seconds],
                ));
            }

            /** @var RegistrationFormData $data */
            $data = $form->getData();

            try {
                $signedUp = $signUp((string) $data->email, (string) $data->name, (string) $data->password);

                $response = $this->redirectToRoute('dashboard', status: Response::HTTP_SEE_OTHER);
                $response->headers->setCookie(AccessTokenCookie::for($signedUp->accessToken, $request));

                return $response;
            } catch (UserAlreadyExists) {
                $form->get('email')->addError(new FormError('Ya existe una cuenta con este email.'));
            } catch (\InvalidArgumentException) {
                $form->addError(new FormError('Revisa el nombre y el email: alguno no es válido.'));
            }
        }

        // AbstractController::render() answers 422 when the submitted form is invalid (needed by Turbo).
        return $this->render(self::TEMPLATE, ['form' => $form]);
    }
}
