---
name: endpoint-api-v1
description: Añade un endpoint JSON versionado bajo /api/v1 autenticado con JWT (Authorization Bearer), con #[MapRequestPayload]/#[MapQueryString], validación y errores JSON. Úsala para cualquier endpoint de API, incluido el login de la API (POST /api/v1/login) para otros dispositivos.
---

# Endpoint de la API v1

Referencia: `src/Identity/GetAuthenticatedUser/Infrastructure/Http/GetAuthenticatedUserApiController.php`.

## Reglas de la API

- Ruta `/api/v1/<recurso>`, nombre de ruta `api_v1_<contexto>_<accion>`, `format: 'json'`.
- La sirve el firewall `api` (`^/api/`): stateless, JWT solo por cabecera y fallos en **401 JSON** (Lexik). Sin cookies ni redirecciones.
- Todo `^/api/` exige `IS_AUTHENTICATED` en `access_control`. Un endpoint **público** necesita su línea `PUBLIC_ACCESS` **encima** de la regla `^/api/` en `config/packages/security.yaml`.
- Sin CSRF: con token en cabecera no hay credenciales ambientales que se puedan forjar.

## Controlador

```php
<?php

declare(strict_types=1);

namespace App\<Contexto>\<CasoDeUso>\Infrastructure\Http;

final class <CasoDeUso>ApiController extends AbstractController
{
    #[Route('/api/v1/<recurso>', name: 'api_v1_<contexto>_<accion>', methods: [Request::METHOD_POST], format: 'json')]
    #[IsGranted('ROLE_USER')]
    public function __invoke(#[MapRequestPayload] <CasoDeUso>Request $payload, <CasoDeUso> $useCase): JsonResponse
    {
        return $this->json($useCase($payload->campo), Response::HTTP_CREATED);
    }
}
```

- El payload es un DTO `final readonly class <CasoDeUso>Request` con `#[Assert\...]`, en la misma carpeta `Http/`. **Nunca** `json_decode()` ni `$request->toArray()`. Si falla la validación, Symfony responde **422**.
- El usuario actual se obtiene con `#[CurrentUser] SecurityUser $user`, y su id con `$user->getUserIdentifier()`. Si el caso de uso necesita más datos del usuario, los lee de la BD con un puerto.
- Traduce las excepciones de dominio a HTTP en el propio controlador (`UserNotFound` → 404, `InvalidCredentials` → 401) devolviendo `$this->json(['code' => ..., 'message' => ...], $status)`.

## Login de la API (`POST /api/v1/login`)

- Línea en `access_control` encima de `^/api/`: `- { path: ^/api/v1/login$, roles: PUBLIC_ACCESS }`.
- Reutiliza el caso de uso `App\Identity\LogIn\Application\LogIn` y el limitador `App\Identity\LogIn\Infrastructure\LoginThrottle` (comparte límites con la web), como hace `LogInWebController`.
- El controlador va en `src/Identity/LogIn/Infrastructure/Http/LogInApiController.php` (mismo slice). Responde `{"token": "...", "expires_at": "ATOM"}`. **No** pone cookie.
- 401 ante credenciales incorrectas; 429 con `Retry-After` si se supera el límite.

## Tests

`tests/Functional/<ruta espejo>/<CasoDeUso>ApiControllerTest.php` extendiendo `App\Tests\Functional\Identity\IdentityWebTestCase`:
token válido → 2xx; sin token → 401; token manipulado → 401; payload inválido → 422; y cada error de dominio con su código.
Usa `$this->client->jsonRequest()` y `HTTP_AUTHORIZATION`.
