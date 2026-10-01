---
name: crear-slice
description: Crea un nuevo caso de uso como vertical slice (Application + Infrastructure) dentro de un bounded context, con todos sus tests. Úsala para cualquier funcionalidad nueva: una acción de usuario, un endpoint, un comando de consola, un handler de mensajes.
---

# Crear un vertical slice

Referencias: `src/Identity/LogIn/` (web + caso de uso), `src/Identity/GetAuthenticatedUser/` (API),
`src/Identity/RegisterUser/` (CLI).

## 1. Nombre y ubicación

`src/<Contexto>/<CasoDeUso>/`, con el caso de uso como verbo + objeto en PascalCase: `LogIn`, `RegisterUser`,
`CancelOrder`. **Nunca** `UserService`, `Controllers`, `Helpers` (`VerticalSliceTest`).

## 2. Application (`<CasoDeUso>/Application/`)

```php
<?php

declare(strict_types=1);

namespace App\<Contexto>\<CasoDeUso>\Application;

final class <CasoDeUso>
{
    public function __construct(
        private readonly <Agregado>Repository $repository,   // puertos de Domain, nunca adaptadores
    ) {
    }

    public function __invoke(/* primitivos o VOs */): /* VO, Id o DTO de salida */
    {
        // orquesta el dominio; las reglas de negocio viven en el agregado
    }
}
```

- Solo dependencias de `Domain` (puertos) y de su propio slice, más `Psr\Clock\ClockInterface` si necesita la hora.
- Recibe primitivos (los adaptadores no deben construir el dominio por él) y devuelve un DTO `final readonly class <Algo>View` con propiedades públicas, que el Serializer o Twig consumen tal cual.
- Señala los errores con excepciones de dominio, nunca con respuestas HTTP.

## 3. Infrastructure (`<CasoDeUso>/Infrastructure/`)

Una subcarpeta por canal de entrada, cada una un adaptador fino que traduce y llama al caso de uso:

- `Http/<CasoDeUso>WebController.php`: web (skill `formulario-web`).
- `Http/<CasoDeUso>ApiController.php`: API v1 (skill `endpoint-api-v1`).
- `Cli/<CasoDeUso>Command.php`: comando invocable con `#[AsCommand]` y `#[Argument]`/`#[Option]`.
- `Messaging/<CasoDeUso>Handler.php`: `#[AsMessageHandler]`.

Lo que necesiten **varios** slices (cookie del JWT, throttling compartido, `SecurityUser`...) sube a `src/<Contexto>/Infrastructure/`, nunca se importa de otro slice.

## 4. Tests obligatorios

Por cada clase, su test en la ruta espejo (detalles en la skill `escribir-tests`):

- `tests/Unit/<Contexto>/<CasoDeUso>/Application/<CasoDeUso>Test.php` con dobles de `tests/Double/` (casos felices **y** cada excepción).
- `tests/Unit/.../Infrastructure/...` para forms, comandos, listeners y DTOs.
- `tests/Functional/.../Infrastructure/Http/<Controller>Test.php` para cada controlador.

## 5. Verifica

Skill `verificar-railguns`.
