---
name: crear-contexto
description: Crea un nuevo bounded context (módulo de negocio) en src/ siguiendo screaming architecture y hexagonal. Úsala cuando haya que añadir un área de negocio nueva (Billing, Catalog, Orders...) que no encaja en un contexto existente, antes de crear sus agregados o casos de uso.
---

# Crear un bounded context

Un contexto es un área de negocio con su propio lenguaje. Su nombre **grita** qué hace la aplicación.

## 1. Decide si de verdad es un contexto nuevo

- Si el concepto ya pertenece a un contexto existente (p. ej. algo de usuarios y acceso → `Identity`), crea un **slice** con la skill `crear-slice`.
- El nombre va en singular o como sustantivo de negocio: `Billing`, `Catalog`, `Ordering`. **Nunca** un nombre técnico (`Api`, `Services`, `Common`...). `ScreamingArchitectureTest` lo rechaza.

## 2. Estructura mínima

```
src/<Contexto>/
├── Domain/                      # se crea con el primer agregado (skill crear-agregado)
└── Infrastructure/
    └── Persistence/Doctrine/
        ├── Mapping/             # *.orm.xml
        └── Type/                # tipos DBAL de los value objects
```

No crees carpetas vacías "por si acaso": cada carpeta aparece con su primera clase.

## 3. Registra el mapeo Doctrine del contexto

En `config/packages/doctrine.yaml`, dentro de `doctrine.orm.mappings` (un bloque por contexto):

```yaml
            <Contexto>:
                type: xml
                is_bundle: false
                dir: '%kernel.project_dir%/src/<Contexto>/Infrastructure/Persistence/Doctrine/Mapping'
                prefix: 'App\<Contexto>\Domain'
                alias: <Contexto>
```

Los tipos DBAL se registran en `doctrine.dbal.types` con prefijo de contexto (`billing_invoice_id`) para evitar choques.

## 4. Comunicación con otros contextos

- **Prohibido** `use App\OtroContexto\...` (lo vigila `ScreamingArchitectureTest::testContextsDoNotDependOnEachOther`).
- Si dos contextos necesitan compartir algo (p. ej. un `UserId`), muévelo a `src/Shared/Domain/` (crea la carpeta en ese momento) o comunícalos con un evento de Messenger.
- Para saber quién es el usuario actual en otro contexto, usa el `sub` del JWT (el id) como valor primitivo o como `App\Shared\...`, nunca `App\Identity\Domain\User`.

## 5. Verifica

```bash
ddev exec bin/console doctrine:mapping:info
ddev exec php bin/phpunit --testsuite Architecture
```
