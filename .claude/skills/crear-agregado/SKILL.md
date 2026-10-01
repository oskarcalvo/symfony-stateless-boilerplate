---
name: crear-agregado
description: Crea una entidad/agregado de dominio con sus value objects, el puerto de repositorio, el adaptador Doctrine con mapeo XML, los tipos DBAL y la migración. Úsala cuando haya que persistir un concepto de negocio nuevo o añadir campos a uno existente.
---

# Crear un agregado persistido

Referencia viva: `src/Identity/Domain/User.php` y `src/Identity/Infrastructure/Persistence/Doctrine/`.

## 1. Dominio (PHP puro, `src/<Contexto>/Domain/`)

- **Id**: `final readonly class <Agregado>Id` con `generate()` (UUID v7, `Symfony\Component\Uid` es lo único de Symfony permitido) y `fromString()` validando. Copia `UserId`.
- **Value objects**: `final readonly`, constructor privado y named constructor que valida y normaliza (`Email::fromString`). Lanzan `\InvalidArgumentException`.
- **Agregado**: `class` (no `final`: Doctrine crea proxies), constructor privado y named constructor de negocio (`register`, `open`...). Propiedades privadas; métodos con verbos de negocio (`grantRole`, `changePasswordHash`), nunca setters genéricos.
- **Sin atributos de Doctrine** ni imports de `Doctrine\`/`Symfony\` (`HexagonalArchitectureTest`).
- **Puerto**: `interface <Agregado>Repository` en `Domain/` con lenguaje de dominio: `save`, `remove`, `ofId`, `of<Criterio>`.
- **Excepciones de dominio**: `final class <Algo>NotFound extends \DomainException` con named constructor (`withId`).

## 2. Infraestructura (`src/<Contexto>/Infrastructure/Persistence/Doctrine/`)

- `Type/<VO>Type.php`: `final class` que extiende `Doctrine\DBAL\Types\Type` con `const NAME = '<contexto>_<vo>'`. Implementa `getSQLDeclaration`, `convertToPHPValue` y `convertToDatabaseValue`, admitiendo `null` y la instancia ya convertida. Copia `UserIdType`/`EmailType`.
- Regístralo en `config/packages/doctrine.yaml` → `doctrine.dbal.types`.
- `Mapping/<Agregado>.orm.xml`: namespace `http://doctrine-project.org/schemas/orm/doctrine-mapping` (**http**, no https; si no, falla la validación XSD). El nombre de tabla lleva prefijo de contexto (`identity_user`). Deja que la naming strategy underscore ponga el nombre de las columnas.
- `Doctrine<Agregado>Repository.php`: `final class` con `#[AsAlias(<Agregado>Repository::class)]`, que recibe `EntityManagerInterface` (composición, **no** `ServiceEntityRepository`).

## 3. Migración

```bash
ddev exec bin/console doctrine:schema:validate --skip-sync
ddev exec bin/console make:migration --no-interaction
# revisa el SQL generado y añade declare(strict_types=1) si faltara
ddev exec bin/console doctrine:migrations:migrate --no-interaction
ddev exec bin/console doctrine:migrations:migrate --no-interaction --env=test
```

Nunca `doctrine:schema:update` ni SQL a mano.

## 4. Tests obligatorios (railgun "un test por clase")

| Clase | Test |
|---|---|
| `Domain/<Agregado>.php`, `<Agregado>Id`, cada VO, cada excepción | `tests/Unit/<Contexto>/Domain/<Clase>Test.php` |
| `Type/<VO>Type.php` | `tests/Unit/.../Type/<VO>TypeTest.php` con `new MariaDBPlatform()` |
| `Doctrine<Agregado>Repository.php` | `tests/Integration/.../Doctrine<Agregado>RepositoryTest.php` (guardar, `clear()` y releer de la BD) |

Añade un doble en memoria en `tests/Double/<Contexto>/InMemory<Agregado>Repository.php` y un `<Agregado>Mother` para que los tests unitarios de los casos de uso no toquen la BD.
