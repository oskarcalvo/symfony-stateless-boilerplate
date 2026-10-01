---
name: escribir-tests
description: Cómo escribir el test propio de cada clase (railgun "un test por clase"): ruta espejo, tipo de test según la clase, dobles disponibles y trampas conocidas de PHPUnit 13 y Symfony. Úsala siempre que crees o modifiques una clase en src/.
---

# Escribir tests

## Dónde va cada test

`src/<ruta>/<Clase>.php` → `tests/<Suite>/<ruta>/<Clase>Test.php`, namespace `App\Tests\<Suite>\<ruta>`.

| Tipo de clase | Suite | Base |
|---|---|---|
| Todo lo que no sea lo siguiente (dominio, casos de uso, tipos DBAL, listeners, forms, comandos, adaptadores) | `Unit` | `TestCase` (`TypeTestCase` para forms) |
| Controlador (`extends AbstractController`) | `Functional` | `IdentityWebTestCase` / `WebTestCase` |
| Repositorio Doctrine (`Infrastructure/Persistence/Doctrine/*Repository`) | `Integration` | `KernelTestCase` |

`UnitTestPerClassTest` falla si falta el fichero. Además, el test debe probar **comportamiento** (no basta con "se instancia").

## Reglas

- `declare(strict_types=1);`, `final class`, `#[CoversClass(<Clase>::class)]` en los unitarios.
- Nombres que describan comportamiento: `testAnUnknownEmailIsRejectedAfterSpendingAHash`.
- **Unit = sin kernel, sin BD y sin red.** Construye la clase a mano con dobles.
- Prueba el caso feliz **y** cada rama de error o excepción.

## Dobles disponibles (`tests/Double/`)

- `Identity\InMemoryUserRepository`: puerto `UserRepository` en memoria; `$saves` cuenta las escrituras.
- `Identity\FakePasswordHasher`: "hash" legible (`hashed:<plain>`); `$hashed` registra las llamadas; `needsRehash` configurable.
- `Identity\FakeAccessTokenIssuer`: devuelve `token-for-<id>`.
- `Identity\UserMother::create(email, passwordHash, id)`.
- `Symfony\Component\Clock\MockClock('2026-01-01 10:00:00', 'UTC')`: compara siempre en UTC.

Para un puerto nuevo crea su doble aquí; no uses mocks de interfaces de dominio.

## Clases `final` de terceros

No se pueden mockear. Usa la implementación real configurada para el test:
- `RateLimiterFactory` + `InMemoryStorage`
- `PasswordHasherFactory([PasswordAuthenticatedUserInterface::class => new NativePasswordHasher(cost: 4, algorithm: PASSWORD_BCRYPT)])`
- `new FirewallConfig('main', 'security.user_checker', stateless: true)`
- `new MariaDBPlatform()` para los tipos DBAL
- `Application::addCommand(new XCommand(...))` + `CommandTester` para los comandos invocables

Las interfaces (`UrlGeneratorInterface`, `JWTTokenManagerInterface`, `Security`) sí: `createStub()` si solo devuelven valores, y `createMock()` + `expects()` si verificas la llamada (`with()` sin `expects()` está deprecado en PHPUnit 13 y el proyecto falla con deprecations).

## Trampas conocidas

- BrowserKit reinicia el kernel entre peticiones: el estado compartido vive en la BD o en las cachés, que `IdentityWebTestCase::setUp()` limpia (`identity_user`, `cache.rate_limiter`, `cache.app`).
- Las cookies puestas a mano en el `CookieJar` necesitan `domain: 'localhost'` para que la respuesta pueda sustituirlas o borrarlas.
- Un servicio que nadie usa se elimina del contenedor: en `Integration`/`Functional` pide el puerto o la clase que sí se consume.
- `TypeTestCase` crea un mock interno del EventDispatcher: añade `#[AllowMockObjectsWithoutExpectations]` con un comentario.
