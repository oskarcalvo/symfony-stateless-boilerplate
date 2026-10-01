---
name: formulario-web
description: Crea un formulario web server-rendered (Twig + Symfony Form) compatible con una app sin sesión, con CSRF sin estado, respuestas 422/303 para Turbo y sin flashes. Úsala para cualquier formulario HTML (registro, perfil, etc.).
---

# Formulario web sin estado

Referencia: `src/Identity/LogIn/Infrastructure/Http/` (`LoginFormData`, `LoginFormType`, `LogInWebController`)
y `templates/identity/log_in/login.html.twig`.

## Piezas (todas en `src/<Contexto>/<CasoDeUso>/Infrastructure/Http/`)

1. **`<Nombre>FormData`**: `final class` mutable (Form necesita escribir en él) con propiedades públicas `?tipo = null` y `#[Assert\...]`.
2. **`<Nombre>FormType`**: `final class ... extends AbstractType` con `@extends AbstractType<<Nombre>FormData>` y, en `configureOptions`:
   ```php
   'data_class' => <Nombre>FormData::class,
   'csrf_protection' => true,
   'csrf_token_id' => 'submit',   // o 'authenticate'/'logout': deben estar en stateless_token_ids
   ```
   Si usas un `csrf_token_id` nuevo, añádelo a `framework.csrf_protection.stateless_token_ids` en `config/packages/csrf.yaml`. Si no, Symfony intenta guardar el token en sesión, y no hay sesión.
3. **Controlador** `<CasoDeUso>WebController`: `handleRequest` → si es válido, llama al caso de uso → `redirectToRoute(..., status: 303)`. Los errores de dominio se convierten en `$form->addError(new FormError('...'))` y se vuelve a pintar el formulario. `AbstractController::render()` ya devuelve **422** si el formulario enviado es inválido (Turbo lo necesita).

## Cómo funciona el CSRF sin estado

El campo oculto vale `csrf-token`. Symfony valida el envío con `Sec-Fetch-Site`, `Origin` o `Referer`, o con la doble cookie que pone `assets/controllers/csrf_protection_controller.js`. No necesita sesión. En los tests:

- Mismo origen: `$client->submit($form, serverParameters: ['HTTP_ORIGIN' => 'http://localhost'])`.
- Ataque desde otra web: envía `HTTP_ORIGIN` **y** `HTTP_REFERER` de otro dominio. BrowserKit añade un `Referer` del propio sitio, que por sí solo daría el envío por válido.

## Prohibido

- `addFlash()` y `getSession()` (no hay sesión; `StatelessTest` los rechaza). Para "mensaje tras redirigir", redirige a una página que muestre el estado leyendo de la BD, o pinta la respuesta directamente.
- `form_login` y `TargetPathTrait`.
- URLs a mano en Twig: usa `path()`/`url()`.

## Tests

- `tests/Unit/.../<Nombre>FormDataTest.php`: validador con `Validation::createValidatorBuilder()->enableAttributeMapping()`.
- `tests/Unit/.../<Nombre>FormTypeTest.php`: `TypeTestCase` con `CsrfExtension` (copia `LoginFormTypeTest`).
- `tests/Functional/.../<CasoDeUso>WebControllerTest.php`: render, envío válido (303 + efecto), inválido (422), CSRF desde otra web (422).
