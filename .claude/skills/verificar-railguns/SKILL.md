---
name: verificar-railguns
description: Checklist final antes de dar por terminada cualquier tarea de código en este proyecto. Ejecuta los railguns (hexagonal, vertical slice, screaming, stateless, un test por clase, strict types), la suite completa y los linters, e interpreta los fallos. Úsala al terminar una funcionalidad, antes de commitear o cuando falle un test de tests/Architecture.
---

# Verificar railguns

## 1. Ejecuta, en este orden

```bash
ddev exec php bin/phpunit --testsuite Architecture
ddev exec php bin/phpunit
ddev exec bin/console lint:container
ddev exec bin/console lint:twig templates/
ddev exec bin/console lint:yaml config/
ddev exec bin/console doctrine:schema:validate
```

Todo en verde y **sin** "OK, but there were issues" (deprecations y notices cuentan como fallo).

## 2. Cómo leer un fallo de arquitectura

El mensaje indica `fichero → dependencia` o la ruta de test que falta. **Se arregla el código, nunca el test.**

| Test | Significa | Arreglo habitual |
|---|---|---|
| `HexagonalArchitectureTest::testDomainDependsOnlyOnTheDomain` | El dominio importa un framework, Application o Infrastructure | Define un puerto (interface) en `Domain/` y mueve la implementación a `Infrastructure/` con `#[AsAlias]` |
| `...::testApplicationDependsOnlyOnApplicationAndDomain` | Un caso de uso usa un adaptador o Symfony (Request, Doctrine...) | Recibe primitivos o VOs y usa puertos; la traducción HTTP queda en el controlador |
| `...::testEveryClassBelongsToALayer` | Hay un fichero fuera de `Domain`/`Application`/`Infrastructure` | Muévelo a la capa que le toca |
| `...::testControllersAreHttpAdapters` | Hay un controlador fuera de `Infrastructure/Http` | Muévelo a `<Slice>/Infrastructure/Http/` |
| `VerticalSliceTest::testSlicesDoNotDependOnOtherSlices` | Un slice importa otro | Sube la pieza compartida al `Domain/` o `Infrastructure/` del contexto |
| `VerticalSliceTest::testSlicesAreNamedAfterUseCases` | Un slice tiene nombre técnico | Renómbralo como el caso de uso |
| `ScreamingArchitectureTest::*` | Hay una carpeta técnica en `src/` o un contexto importa otro | Pon el código en su contexto; comparte por `App\Shared` o eventos |
| `StatelessTest::*` | Hay sesión, flashes o un firewall con estado | La identidad va en el JWT y el resto en la BD |
| `UnitTestPerClassTest` | Falta el test espejo de una clase | Créalo (skill `escribir-tests`) |
| `StrictTypesTest` | Falta `declare(strict_types=1);` | Añádelo tras `<?php` |

## 3. Revisión manual (los tests no lo ven todo)

- ¿Cada test nuevo prueba comportamiento, incluidas las ramas de error?
- ¿Algún secreto en `.env` u otro fichero versionado?
- ¿Cambios de esquema con migración aplicada en dev y test?
- ¿Endpoints públicos declarados explícitamente en `access_control`?
- ¿Algún `GET` con efectos? (con la cookie `SameSite=Lax`, los GET deben ser seguros).

## 4. Informa

Di qué se ejecutó y el resultado real (número de tests). Si algo no pudo ejecutarse, dilo.
