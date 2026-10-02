# CLAUDE.md

@AGENTS.md

Boilerplate de Symfony 8.1 / PHP 8.4 para aplicaciones **sin estado**: la identidad del usuario viaja
en un JWT (cookie HttpOnly `BEARER` en la web, `Authorization: Bearer` en la API) y cualquier dato del
usuario se lee de la base de datos en cada petición. Lo que dice este fichero tiene prioridad sobre
`AGENTS.md` cuando se contradicen.

## Decisiones ya tomadas (no preguntar)

`AGENTS.md` pide preguntar por persistencia, interfaz y autenticación. En este proyecto ya están decididas:

- **Persistencia**: Doctrine ORM sobre MariaDB, con **mapeo XML** en `Infrastructure/Persistence/Doctrine/Mapping`
  (excepción deliberada a "usa atributos": el dominio no puede depender de Doctrine). Value objects
  con tipos DBAL propios. Cambios de esquema solo con migraciones.
- **Interfaz**: web con Twig + Form, y API JSON versionada en `/api/v1/...`.
- **Autenticación**: `lexik/jwt-authentication-bundle`. El token lleva el id del usuario en `sub`. Se emite
  con el puerto `AccessTokenIssuer` y se revoca al hacer logout (`blocklist_token`).

## Railguns (no negociables)

Cada railgun está comprobado por un test en `tests/Architecture/`. Si uno falla, **se arregla el código,
nunca el test**. Cambiar un railgun es una decisión del usuario, no tuya.

| Railgun | Regla | Test que lo vigila |
|---|---|---|
| **Screaming** | `src/` contiene contextos de negocio (`Identity`, `Billing`...), nunca carpetas técnicas (`Controller`, `Entity`, `Service`...). Un contexto no importa a otro; lo compartido va en `App\Shared` o se comunica por eventos. | `ScreamingArchitectureTest` |
| **Hexagonal** | Las dependencias apuntan hacia dentro: `Infrastructure → Application → Domain`. `Domain` es PHP puro (solo se permite `Symfony\Component\Uid` y `Psr\Clock`). `Application` usa solo puertos del dominio. Los adaptadores (Doctrine, Lexik, HTTP, CLI) viven en `Infrastructure`. Los controladores, en `<Slice>/Infrastructure/Http`. | `HexagonalArchitectureTest` |
| **Vertical slice** | Cada caso de uso es una carpeta `src/<Contexto>/<CasoDeUso>/{Application,Infrastructure}`. Un slice no importa otro slice; lo que se comparte sube al `Domain`/`Infrastructure` del contexto. Los slices se llaman como el caso de uso (`LogIn`, `RegisterUser`), nunca como una capa técnica. | `VerticalSliceTest` |
| **Stateless** | `framework.session: false`. Todos los firewalls con `stateless: true`. Prohibido `getSession()`, `SessionInterface`, `$_SESSION` y flashes. La identidad va en el JWT y los datos en la BD. | `StatelessTest` |
| **Un test por clase** | Cada clase de `src/` tiene **su propio** test en la ruta espejo: `src/X/Y.php → tests/Unit/X/YTest.php`. Excepciones únicas: los controladores van en `tests/Functional/...` (petición HTTP real) y los repositorios Doctrine en `tests/Integration/...` (BD real). Las interfaces (puertos) se prueban a través de sus adaptadores. | `UnitTestPerClassTest` |
| **Strict types** | Todo fichero PHP (`src/`, `tests/`, `migrations/`) empieza por `<?php` + línea en blanco + `declare(strict_types=1);`. También los generados por `make:*`. | `StrictTypesTest` |

## Estructura

```
src/
├── Kernel.php
├── <Contexto>/                     # p. ej. Identity: screaming
│   ├── Domain/                     # agregados, value objects, excepciones, PUERTOS (interfaces)
│   ├── Infrastructure/             # adaptadores compartidos por los slices del contexto
│   │   ├── Persistence/Doctrine/   # repositorios, Mapping/*.orm.xml, Type/*
│   │   └── Security/               # SecurityUser, provider, emisor JWT, cookie...
│   └── <CasoDeUso>/                # vertical slice, p. ej. LogIn
│       ├── Application/            # el caso de uso (__invoke) y sus DTOs de salida
│       └── Infrastructure/         # Http/ (controladores, forms), Cli/ (comandos)...
tests/
├── Unit/          # espejo de src/: un test por clase, sin kernel ni BD
├── Integration/   # repositorios Doctrine (KernelTestCase + BD de test)
├── Functional/    # controladores (WebTestCase), flujos HTTP completos
├── Architecture/  # los railguns
└── Double/        # dobles reutilizables: InMemoryUserRepository, FakePasswordHasher, UserMother...
```

Ejemplos de referencia: `src/Identity/LogIn/` (slice con web, caso de uso y throttling) y
`src/Identity/GetAuthenticatedUser/` (slice de API).

## Comandos (todo dentro de DDEV)

```bash
ddev exec php bin/phpunit                          # todo
ddev exec php bin/phpunit --testsuite Architecture # solo railguns
ddev exec php bin/phpunit --testsuite Unit         # Unit | Integration | Functional
ddev exec bin/console lint:container
ddev exec bin/console lint:twig templates/
ddev exec bin/console lint:yaml config/
ddev exec bin/console make:migration --no-interaction
ddev exec bin/console doctrine:migrations:migrate --no-interaction            # y con --env=test
ddev exec bin/console identity:user:register <email> <password> <name>
```

`php` no está en el host: usa siempre `ddev exec`.

## Definición de terminado

Una tarea no está terminada hasta que:

1. Cada clase nueva o modificada tiene su test en la ruta espejo y ese test prueba comportamiento real.
2. `ddev exec php bin/phpunit` está en verde **sin** deprecations, notices ni warnings (`failOnDeprecation` está activo).
3. Pasan `lint:container`, `lint:twig` y `lint:yaml`.
4. Si cambia el esquema hay migración, y se ha aplicado en dev y test.

Usa la skill `verificar-railguns` antes de dar algo por terminado.

## Prohibido

- Sesiones, flashes, `form_login`/`json_login` con estado, `TargetPathTrait` (usa sesión).
- Atributos de Doctrine en clases de `Domain/` (el mapeo va en XML).
- Importar Symfony, Doctrine o Lexik desde `Domain/` o `Application/` (salvo `Uid` y `Psr\Clock`).
- Importar un slice desde otro, o un contexto desde otro.
- Guardar secretos en `.env` (solo valores por defecto). La `JWT_PASSPHRASE` real va en `.env.local`,
  `.env.test.local` o en el vault (`secrets:set`).
- Desactivar o "ajustar" un test de `tests/Architecture/` para que pase.

## Skills del proyecto (`.claude/skills/`)

- `crear-contexto`: nuevo bounded context (carpetas, mapeo Doctrine, railguns).
- `crear-agregado`: entidad de dominio, value objects, puerto de repositorio, mapeo XML, tipos DBAL, migración.
- `crear-slice`: nuevo caso de uso (Application e Infrastructure) con sus tests.
- `endpoint-api-v1`: endpoint JSON bajo `/api/v1` con JWT, `#[MapRequestPayload]` y errores JSON.
- `formulario-web`: formulario Twig con CSRF sin estado, 422/303 y sin flashes.
- `escribir-tests`: cómo escribir el test de cada tipo de clase y qué dobles usar.
- `verificar-railguns`: checklist final antes de dar una tarea por terminada.
