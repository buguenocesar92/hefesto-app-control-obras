# 04 - Instalación

Como levantar Control de Obras. **Nada de esto se ha ejecutado**: la maquina
donde se escribio el código no tiene Docker, ni PHP, ni Composer. Ver
[10](10-PRUEBAS-Y-ESTADO.md).

---

## 1. Requisitos

| Recurso | Minimo | Comentario |
|---|---|---|
| CPU | 2 vCPU | |
| RAM | 4 GB | Nuxt con SSR y PHP-FPM juntos. |
| Disco | 30 GB | PostgreSQL más imagenes de Docker. |
| SO | Ubuntu 22.04 o 24.04 LTS | Docker oficial de la distribucion. |
| Puertos | 80 y 443 | En producción, solo Caddy los pública. |

Verifica que Docker esta instalado y andando:

```bash
docker --version
docker compose version
```

## 2. Authentik

Authentik **no se incluye en este repositorio**. Es la pieza de identidad
compartida entre las aplicaciones de Hefesto, con su propia base de datos y su
propio ciclo de vida. Duplicarla aca haría que cada aplicación administrara su
propia identidad, y eso es exactamente lo que un proveedor de identidad
centralizado evita.

Lo que hace falta antes de seguir:

1. Que Authentik este levantado y accesible por HTTPS.
2. Un **provider OIDC** creado para esta aplicación, con el tipo de flujo
   *Authorization code* y el cliente público o confidencial según corresponda.
3. La **URL de redireccion** exacta:

   ```
   https://obras.ejemplo.cl/api/v1/auth/retorno
   ```

   En desarrollo: `http://localhost:8080/api/v1/auth/retorno`

4. El **issuer**, que es la URL que declara Authentik y que debe terminar con
   `/`. La aplicación NO escribe las URLs de los endpoints a mano: los pide al
   documento de descubrimiento (`/.well-known/openid-configuration`), que es lo
   que hace que el mismo código sirva en desarrollo y en producción.

5. Los **alcances** `openid email profile`. Pedir más de esos es pedir datos
   personales de más.

## 3. Despliegue de desarrollo

### 3.1 Configuración

```bash
cd apps/control-obras
cp .env.example .env
cp web/.env.example web/.env
```

En `.env` hay que completar, como minimo:

```dotenv
APP_KEY=
DB_PASSWORD=cambiar-en-produccion
REDIS_PASSWORD=cambiar-en-produccion
AUTHENTIK_ISSUER=https://identidad.ejemplo.cl/application/o/control-obras/
AUTHENTIK_CLIENT_ID=cambiar-en-produccion
AUTHENTIK_CLIENT_SECRET=cambiar-en-produccion
AUTHENTIK_REDIRECT_URI=http://localhost:8080/api/v1/auth/retorno
```

Genera `APP_KEY`:

```bash
docker compose -f deploy/docker-compose.dev.yml run --rm api php artisan key:generate --show
```

### 3.2 Levantar

```bash
docker compose -f deploy/docker-compose.dev.yml up -d
docker compose -f deploy/docker-compose.dev.yml ps
```

Los puertos `8080` (API) y `3000` (interfaz) quedan publicados en `127.0.0.1`
para poder trabajar en local. En producción no se publican: ver seccion 5.

### 3.3 Migraciones y semilla

En desarrollo `CONTROL_OBRAS_CORRER_MIGRACIONES=true`, así que el contenedor
aplica las migraciones al arrancar. Para cargar los datos de ejemplo:

```bash
docker compose -f deploy/docker-compose.dev.yml exec api php artisan migrate --seed
```

La semilla crea **dos empresas** con dos obras, etapas con fecha planificada y
real (una atrasada), una inspección conforme, una con hallazgos pendientes que
generan sus tareas con plazo, un accidente y un casi accidente por separado, y
pagos que dejan el avance fisico en 73% y el financiero en 45%. Ver
[06](06-PRIMERA-OBRA.md).

### 3.4 Verificar el aislamiento. OBLIGATORIO

```bash
docker compose -f deploy/docker-compose.dev.yml exec api php artisan obras:verificar-aislamiento
```

**Si este comando falla, el sistema no debe recibir personas.** No es una
advertencia: con el rol equivocado, PostgreSQL ignora las políticas y cada
constructora veria las obras de la otra **sin ningun error visible**.

## 4. Pruebas

Las pruebas de aislamiento necesitan **PostgreSQL de verdad**, porque el
aislamiento esta implementado con Row Level Security. Contra SQLite pasan sin
comprobar nada.

```bash
# Crear la base de pruebas
docker compose -f deploy/docker-compose.dev.yml exec postgres \
  psql -U control_obras -d postgres -c "CREATE DATABASE control_obras_test;"

# Suite de aislamiento
docker compose -f deploy/docker-compose.dev.yml exec api \
  php artisan test --testsuite=Aislamiento

# Reglas de negocio: hallazgos con plazo, avances por separado, seguridad
docker compose -f deploy/docker-compose.dev.yml exec api \
  php artisan test --testsuite=Reglas

# Todas
docker compose -f deploy/docker-compose.dev.yml exec api php artisan test
```

Ver [10](10-PRUEBAS-Y-ESTADO.md) para lo que esas pruebas comprueban y lo que
todavia **no** se ha ejecutado.

## 5. Producción

### 5.1 Construir y etiquetar las imagenes

```bash
docker build -f deploy/api/Dockerfile -t registro.ejemplo.cl/hefesto/control-obras-api:0.1.0 .
docker build -f deploy/web/Dockerfile -t registro.ejemplo.cl/hefesto/control-obras-web:0.1.0 .
docker push registro.ejemplo.cl/hefesto/control-obras-api:0.1.0
docker push registro.ejemplo.cl/hefesto/control-obras-web:0.1.0
```

**Por etiqueta y no por `latest`:** un despliegue tiene que poder decir que
versión esta en pie, y volver atras tiene que ser escribir la etiqueta
anterior.

### 5.2 Configurar

```bash
cp .env.example .env.prod
chmod 600 .env.prod
```

Complete todo. **En particular:**

- `APP_DEBUG=false`. Con `true`, un error muestra la traza completa, con rutas y
  valores de variables.
- `SESSION_SECURE_COOKIE=true`. Sin esto, la cookie de sesión viaja por HTTP
  plano, donde cualquiera que este en medio la puede leer.
- `DB_PASSWORD` y `REDIS_PASSWORD`: los valores por defecto son de ejemplo y
  **están publicados en el repositorio**.

### 5.3 Cambiar el dominio en el Caddyfile

`deploy/caddy/Caddyfile` tiene `obras.ejemplo.cl`. Con el dominio de ejemplo,
Caddy no puede emitir certificado y el sitio queda sirviendo por HTTP.

### 5.4 Levantar, en este orden

```bash
# 1. Base de datos y cache primero: la API no arranca sin eso.
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod up -d postgres redis

# 2. Aplicar migraciones, A MANO. Nunca en cada arranque.
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
  run --rm api php artisan migrate --force

# 3. Verificar el aislamiento. Si falla, PARAR.
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
  run --rm api php artisan obras:verificar-aislamiento

# 4. Ahora si, todo lo demas.
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod up -d
```

El orden importa. Las migraciones se aplican a mano porque un despliegue que
cambia el esquema sin que nadie lo decida es un despliegue que puede cortar el
servicio.

### 5.5 La primera empresa

```bash
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
  run --rm api php artisan obras:crear-empresa
```

**En producción NO se usa `--seed`.** La semilla crea usuarios de ejemplo con
correos conocidos. La primera cuenta de una instancia la define el operador del
servidor, no una semilla que cualquiera puede correr.

## 6. Desinstalar

```bash
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod down
```

Esto **no borra los datos**: los volumenes quedan. Para borrar también los
datos, y perderlos:

```bash
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod down -v
```

Antes de correr eso, verifica que tenes respaldo. Ver [08](08-RESPALDOS.md).

## 7. Problemas frecuentes

| Sintoma | Causa probable | Que hacer |
|---|---|---|
| `No hay contexto de empresa en la conexion` | Consulta fuera del middleware, o `set_config` de alcance de sesión | Revisar que la operación pase por `ContextoTenant::ejecutarAislado()`. |
| El aislamiento falla al verificar | El rol de la base es superusuario o tiene `BYPASSRLS` | Crear un rol de aplicación sin esos atributos. Ver [03](03-MULTITENANCY-Y-ROLES.md), seccion 8. |
| La semilla no escribe nada | Se corrio sin contexto de empresa | La semilla ya usa `ejecutarAislado()`: verificar que las migraciones, sobre todo la 000014, se aplicaron. |
| Errores 401 en la interfaz | Cookie de sesión que no viaja | Verificar `SESSION_SECURE_COOKIE` y que el proxy de Nuxt reenvia `cookie`. |
| Authentik devuelve un error de redirect | La URL de redireccion no coincide exactamente | Debe coincidir carácter por carácter con lo configurado en Authentik. |
| El progreso de cada petición es lento | RLS evalua la política por fila | Es el costo del aislamiento. Con miles de filas por tabla conviene paginar, y las consultas ya lo hacen. |