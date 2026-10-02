# Stateless

Boilerplate de Symfony 8.1 / PHP 8.4 para aplicaciones **sin estado**. No hay sesión de PHP: la identidad
del usuario viaja en un JWT firmado por el portal y cualquier dato del usuario se lee de la base de datos
en cada petición.

- En el **navegador**, el JWT va en la cookie HttpOnly `BEARER`, que pone el propio portal al iniciar sesión
  o al registrarse.
- En la **API**, el cliente guarda el token y lo envía en la cabecera `Authorization: Bearer <jwt>`.

Las reglas de arquitectura (hexagonal, vertical slices, un test por clase...) están en [`CLAUDE.md`](CLAUDE.md).

## Índice

1. [Puesta en marcha](#puesta-en-marcha)
2. [Consola](#consola)
3. [Navegador](#navegador)
4. [API v1](#api-v1)
5. [El token (JWT)](#el-token-jwt)
6. [Límites y seguridad](#límites-y-seguridad)
7. [Tests y comprobaciones](#tests-y-comprobaciones)

---

## Puesta en marcha

Todo se ejecuta dentro de [DDEV](https://ddev.com); `php` no está en el host.

```bash
ddev start
ddev composer install
ddev exec bin/console lexik:jwt:generate-keypair        # crea config/jwt/{private,public}.pem (no se versionan)
ddev exec bin/console doctrine:migrations:migrate --no-interaction
ddev exec bin/console doctrine:migrations:migrate --no-interaction --env=test
```

La aplicación queda en <https://stateless.ddev.site>.

**Variables de entorno.** Los valores por defecto están en `.env.dist`, que es el que se versiona. `.env`
está en `.gitignore`: si no existe, Symfony carga `.env.dist`, así que un clon recién hecho arranca sin
copiar nada. Para cambiar un valor solo en tu máquina, usa `.env.local` (o copia `.env.dist` a `.env`).

**Secretos.** Nunca en `.env.dist`. La `JWT_PASSPHRASE` real va en `.env.local`,
`.env.test.local` o en el vault (`bin/console secrets:set JWT_PASSPHRASE`). Debe ser la misma con la que se
generaron las claves.

---

## Consola

Los comandos propios de la aplicación están en el espacio de nombres `identity`:

```bash
ddev exec bin/console list identity
```

### Dar de alta un usuario

```bash
ddev exec bin/console identity:user:register <email> <password> <name>
```

| Argumento  | Regla |
|------------|-------|
| `email`    | Email válido, de 180 caracteres como máximo. Se guarda en minúsculas y sin espacios alrededor. Debe ser único. |
| `password` | La contraseña en claro; se guarda hasheada. **La consola no exige longitud mínima** (la web y la API sí: 8). |
| `name`     | De 1 a 100 caracteres. Se recortan los espacios de los extremos y se colapsan los repetidos. Si lleva espacios, entre comillas. |

```bash
$ ddev exec bin/console identity:user:register ana@example.com 's3cret-Passw0rd' 'Ana García'
 [OK] User "ana@example.com" registered with id 01a0fcbb-321f-7f8a-a2ca-35a014bd51a9.
```

Devuelve el código `1` y un mensaje de error si el email ya existe o si el email o el nombre no son válidos.
Pensado para administradores: crea la cuenta, pero **no** emite ningún token. El usuario lo obtiene después
iniciando sesión en `/login` (navegador) o con `POST /api/v1/login` (API).

> La contraseña pasada como argumento queda en el historial de la shell. En máquinas compartidas, bórrala
> del historial o registra al usuario por la web o la API.

### Comandos útiles de Symfony

| Comando | Para qué |
|---|---|
| `ddev exec bin/console debug:router` | Todas las rutas (web y API). |
| `ddev exec bin/console lexik:jwt:check-config` | Comprueba que las claves y la passphrase del JWT casan. |
| `ddev exec bin/console lexik:jwt:generate-keypair` | Genera las claves (`--overwrite` para rotarlas: invalida todos los tokens). |
| `ddev exec bin/console doctrine:migrations:migrate` | Aplica migraciones (`--env=test` para la BD de tests). |
| `ddev exec bin/console make:migration --no-interaction` | Genera una migración tras cambiar un mapeo XML. |
| `ddev exec bin/console lint:container` / `lint:twig templates/` / `lint:yaml config/` | Linters. |

> `lexik:jwt:generate-token` **no** sirve en este proyecto: no encuentra a los usuarios por su id. Para
> obtener un token, usa `POST /api/v1/login`.

---

## Navegador

| Ruta | Método | Acceso | Qué hace |
|---|---|---|---|
| `/register` | GET, POST | Público | Formulario de registro. Al terminar, la sesión ya queda iniciada. |
| `/login` | GET, POST | Público | Formulario de inicio de sesión. |
| `/` | GET | Autenticado | Inicio (dashboard). Sin token, redirige a `/login`. |
| `/user` | GET | Autenticado | "Mis datos": nombre, email e id leídos de la BD. Sin token, responde **401** (no redirige). |
| `/logout` | POST | Autenticado | Cierra la sesión (botón "Cerrar sesión"). |

### Registrarse (`/register`)

Campos: **Nombre**, **Email**, **Contraseña** y **Repite la contraseña**.

- Si todo es válido, se crea la cuenta, se pone la cookie `BEARER` y se redirige a `/` con un **303**.
- Si hay errores, se vuelve a pintar el formulario con un **422** y los mensajes junto a cada campo:
  - campos vacíos, email mal formado, nombre de más de 100 caracteres;
  - contraseña de menos de 8 caracteres, o las dos contraseñas no coinciden;
  - "Ya existe una cuenta con este email.";
  - "Demasiados registros desde tu conexión..." (**429** con `Retry-After`; ver [límites](#límites-y-seguridad)).
- Si ya has iniciado sesión, `/register` te lleva a `/`.
- Desde `/login` se llega con el enlace "¿No tienes cuenta? Crea una", y desde `/register` se vuelve a `/login`.

### Iniciar sesión (`/login`)

Email y contraseña. Si son correctos, pone la cookie `BEARER` y redirige a `/` con un **303**. Si no, muestra
"Email o contraseña incorrectos." con un **422**. El mensaje es el mismo exista o no el email. Tras demasiados
intentos responde **429**.

### Cerrar sesión

El botón "Cerrar sesión" de `/` y `/user` hace `POST /logout` con un token CSRF. El token queda **revocado**
hasta que caduca (aunque alguien lo hubiera copiado), se borra la cookie y se redirige a `/login`.

### La cookie `BEARER`

`HttpOnly` (el JavaScript de la página no puede leerla), `SameSite=Lax`, `Path=/`, `Secure` cuando la
petición llega por HTTPS, y caduca a la vez que el JWT. Si llega un token no válido (manipulado, caducado,
firmado por otro, revocado o de un usuario que ya no existe), se borra y se muestra "Acceso no permitido"
con un **401**.

### CSRF sin sesión

Los formularios llevan un campo oculto `_token`. Symfony lo valida sin sesión, mirando las cabeceras `Origin`
/ `Sec-Fetch-Site` / `Referer` o la doble cookie que pone `assets/controllers/csrf_protection_controller.js`.
Un envío desde otra web se rechaza con un **422**.

---

## API v1

- Base: `/api/v1`. Peticiones y respuestas en JSON (`Content-Type: application/json`).
- Autenticación: `Authorization: Bearer <jwt>`. La API **nunca** pone cookies ni redirige.
- Sin CSRF: el token va en una cabecera que el navegador no envía solo.

| Endpoint | Método | Acceso | Qué hace |
|---|---|---|---|
| `/api/v1/register` | POST | Público | Crea una cuenta y devuelve un token para usarla. |
| `/api/v1/login` | POST | Público | Cambia email y contraseña por un token. |
| `/api/v1/me` | GET | Bearer | Datos del usuario del token. |
| `/api/v1/logout` | POST | Bearer | Revoca el token enviado. |

### `POST /api/v1/register`

```bash
curl -X POST https://stateless.ddev.site/api/v1/register \
  -H 'Content-Type: application/json' \
  -d '{"name": "Ana García", "email": "ana@example.com", "password": "s3cret-Passw0rd"}'
```

| Campo | Tipo | Regla |
|---|---|---|
| `name` | string | Obligatorio, 1 a 100 caracteres (se recortan y colapsan los espacios). |
| `email` | string | Obligatorio, email válido, 180 caracteres como máximo, único (se guarda en minúsculas). |
| `password` | string | Obligatorio, de 8 a 4096 caracteres. |

**201 Created**

```json
{
  "id": "01a0fcba-c8cf-7d7f-b8ab-670064e7d1ce",
  "email": "ana@example.com",
  "name": "Ana García",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "expires_at": "2026-10-02T14:08:15+00:00"
}
```

Guarda `token` y envíalo como `Authorization: Bearer <token>` hasta `expires_at`.

**Errores**

| Estado | Cuándo | Cuerpo |
|---|---|---|
| 400 | El cuerpo no es JSON válido. | Problem details (`title`, `detail`). |
| 405 | Método distinto de POST. | |
| 409 | El email ya está registrado. | `{"code": "user_already_exists", "message": "..."}` |
| 422 | Falla la validación de algún campo. | Problem details con `violations[]` (ver abajo). |
| 422 | El email pasa la validación del formulario pero no las reglas del dominio (p. ej., la parte local supera los 64 caracteres). | `{"code": "invalid_user_data", "message": "..."}` |
| 429 | Demasiados registros desde la misma IP. Cabecera `Retry-After` en segundos. | `{"code": "too_many_registrations", "message": "..."}` |

Ejemplo de 422 por validación (recortado):

```json
{
  "type": "https://symfony.com/errors/validation",
  "title": "Validation Failed",
  "status": 422,
  "violations": [
    {"propertyPath": "name",     "title": "This value should not be blank."},
    {"propertyPath": "email",    "title": "This value is not a valid email address."},
    {"propertyPath": "password", "title": "This value is too short. It should have 8 characters or more."}
  ]
}
```

En `dev` las respuestas de error incluyen además `class` y `trace`; en `prod` no.

### `POST /api/v1/login`

Para usuarios que ya existen, se crearan por la consola, la web o la API, o cuyo token haya caducado.

```bash
curl -X POST https://stateless.ddev.site/api/v1/login \
  -H 'Content-Type: application/json' \
  -d '{"email": "ana@example.com", "password": "s3cret-Passw0rd"}'
```

| Campo | Tipo | Regla |
|---|---|---|
| `email` | string | Obligatorio, email válido. No distingue mayúsculas. |
| `password` | string | Obligatorio. |

**200 OK**

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "expires_at": "2026-10-02T14:15:58+00:00"
}
```

No pone cookie: el cliente guarda el token y lo envía como `Authorization: Bearer <token>`.

**Errores**

| Estado | Cuándo | Cuerpo |
|---|---|---|
| 400 | El cuerpo no es JSON válido. | Problem details (`title`, `detail`). |
| 401 | Email o contraseña incorrectos. La respuesta es la misma tanto si el email existe como si no. | `{"code": "invalid_credentials", "message": "Invalid email or password."}` |
| 405 | Método distinto de POST. | |
| 422 | Falta un campo o el email está mal formado. | Problem details con `violations[]`. |
| 429 | Demasiados intentos (mismos límites que el formulario web, y **comparten** contador). Cabecera `Retry-After` en segundos. Mientras dura, se rechaza incluso la contraseña correcta. | `{"code": "too_many_login_attempts", "message": "..."}` |

### `GET /api/v1/me`

```bash
curl https://stateless.ddev.site/api/v1/me -H "Authorization: Bearer $TOKEN"
```

**200 OK**

```json
{
  "id": "01a0fcba-c8cf-7d7f-b8ab-670064e7d1ce",
  "email": "ana@example.com",
  "name": "Ana García",
  "roles": ["ROLE_USER"],
  "registeredAt": "2026-10-02T15:08:15+02:00"
}
```

**401** si falta el token (`{"code": 401, "message": "JWT Token not found"}`), o si está caducado, manipulado,
firmado con otra clave, emitido por otra aplicación (`iss`), revocado o es de un usuario que ya no existe.
También acepta la cookie `BEARER`, para que una página del propio portal pueda llamar a la API.

### `POST /api/v1/logout`

```bash
curl -X POST https://stateless.ddev.site/api/v1/logout -H "Authorization: Bearer $TOKEN"
```

Sin cuerpo. Responde **204 No Content**, sin cuerpo ni cookies.

- Revoca **solo el token enviado**: a partir de ese momento, cualquier petición con él recibe **401**
  (`"Invalid JWT Token"`). Los demás tokens del mismo usuario (otros dispositivos u otros logins) siguen
  siendo válidos hasta que caducan.
- La revocación dura hasta la caducidad del token: se guarda su `jti` en `cache.app` (ver [el token](#el-token-jwt)).

| Estado | Cuándo |
|---|---|
| 401 | Sin token, o con uno caducado, manipulado o ya revocado (cerrar sesión dos veces da 401 la segunda vez). |
| 405 | Método distinto de POST. Un GET nunca cierra sesión. |

Si lo llama una página del propio portal autenticada con la cookie `BEARER`, el token queda revocado, pero
la API no borra la cookie. Para el navegador, usa el botón "Cerrar sesión" (`POST /logout`).

### Flujo completo

```bash
# Alta (devuelve ya un token)...
curl -s -X POST https://stateless.ddev.site/api/v1/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"Ana García","email":"ana@example.com","password":"s3cret-Passw0rd"}'

# ...y, más adelante o cuando caduque, login para obtener otro.
TOKEN=$(curl -s -X POST https://stateless.ddev.site/api/v1/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"ana@example.com","password":"s3cret-Passw0rd"}' | jq -r .token)

curl https://stateless.ddev.site/api/v1/me -H "Authorization: Bearer $TOKEN"

# Cerrar sesión: el token deja de valer.
curl -X POST https://stateless.ddev.site/api/v1/logout -H "Authorization: Bearer $TOKEN"
```

### Lo que la API todavía no tiene

- **Refresco** del token: cuando caduca (1 hora), hay que volver a llamar a `POST /api/v1/login`.
- **Cerrar todas las sesiones** de un usuario a la vez: el logout revoca solo el token enviado.

---

## El token (JWT)

| Claim | Valor |
|---|---|
| `sub` | Id (UUID) del usuario. Es lo único que identifica al usuario: el resto se lee de la BD. |
| `iss` | `JWT_ISSUER` (por defecto `stateless-portal`). Se rechazan tokens sin `iss` o con otro valor. |
| `iat` / `exp` | Emisión y caducidad: dura **1 hora** (`token_ttl: 3600`). |
| `jti` | Id del token, usado para revocarlo al hacer logout (`POST /logout` o `POST /api/v1/logout`). |
| `roles` | Informativo: los roles efectivos se leen de la BD en cada petición. |

Firmado con RS256 (`config/jwt/private.pem`). Los tokens revocados se guardan en `cache.app` hasta que
caducan. Con varias instancias, ese pool debe ser compartido (Redis...).

---

## Límites y seguridad

| Limitador | Aplica a | Límite |
|---|---|---|
| `registration` | Registros por IP (web y API **comparten** contador). | 10 por hora |
| `login_local` | Intentos de login por email + IP (web y API **comparten** contador). Un login correcto lo pone a cero. | 5 por minuto |
| `login_global` | Intentos de login por IP, sea cual sea el email (web y API). | 25 por minuto |

Se configuran en `config/packages/rate_limiter.yaml`. Detrás de un proxy, define `SYMFONY_TRUSTED_PROXIES`:
sin ella, todas las peticiones parecen venir de la IP del proxy y comparten contador.

Otras notas:

- El registro dice si un email ya existe. Es inevitable en un formulario de alta, y el limitador lo frena.
  El login, en cambio, no lo revela.
- Las contraseñas se hashean con el algoritmo `auto` de Symfony y se rehashean al iniciar sesión si cambia
  el algoritmo.
- No hay sesión en ningún sitio (`framework.session: false`, firewalls `stateless`); los tests lo comprueban.

---

## Tests y comprobaciones

```bash
ddev exec php bin/phpunit                          # todo
ddev exec php bin/phpunit --testsuite Architecture # railguns de arquitectura
ddev exec php bin/phpunit --testsuite Unit         # Unit | Integration | Functional
ddev exec bin/console lint:container
ddev exec bin/console lint:twig templates/
ddev exec bin/console lint:yaml config/
```
