# 05 - Configuración

Cada variable de entorno, que hace y que pasa si se deja vacia. El ejemplo
completo esta en [`.env.example`](../.env.example), que **no tiene valores
reales** y no debe tenerlos nunca.

---

## 1. Aplicación

| Variable | Por defecto | Que hace |
|---|---|---|
| `APP_NAME` | `Control de Obras` | Nombre visible. |
| `APP_ENV` | `production` | `local`, `production`, `testing`. |
| `APP_KEY` | **(obligatoria)** | Cifra cookies y sesiones. Sin ella, el sistema no arranca y el `entrypoint.sh` lo dice con un mensaje claro. |
| `APP_DEBUG` | `false` | **En producción, `false` siempre.** Con `true`, un error muestra la traza completa: rutas del servidor y valores de variables. |
| `APP_URL` | - | URL pública. Se usa en redirecciones y en la URL de retorno de Authentik. |

Generar `APP_KEY`:

```bash
docker compose run --rm api php artisan key:generate --show
```

Es el único secreto que **no se puede recuperar**. Si se pierde, todas las
cookies de sesión quedan invalidas y hay que cerrar sesión a todos. No es
catastrofico, pero molesta.

## 2. Base de datos

| Variable | Por defecto | Que hace |
|---|---|---|
| `DB_CONNECTION` | `pgsql` | **No cambiar a `sqlite` en producción.** SQLite no tiene RLS, y el aislamiento se implementa justamente con RLS. Ver [03](03-MULTITENANCY-Y-ROLES.md). |
| `DB_HOST` | `postgres` | |
| `DB_PORT` | `5432` | |
| `DB_DATABASE` | `control_obras` | |
| `DB_USERNAME` | `control_obras` | |
| `DB_PASSWORD` | `cambiar-en-produccion` | **Cambiarlo.** El valor del ejemplo esta publicado. |

### El requisito que no es una variable

**El rol de la base NO puede ser superusuario ni tener `BYPASSRLS`.**

Con superusuario, PostgreSQL ignora todas las políticas RLS. El sistema sigue
funcionando de aparente: las consultas responden, la interfaz se ve bien, y cada
constructora ve las obras de la otra. **No hay ningun error que lo delate.**

El rol correcto se crea así:

```sql
CREATE ROLE control_obras LOGIN PASSWORD 'una-clave-fuerte';
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO control_obras;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO control_obras;
```

Sin `SUPERUSER`, sin `BYPASSRLS`, sin `CREATEROLE`.

Después, en **cada despliegue**:

```bash
php artisan obras:verificar-aislamiento
```

## 3. Redis

| Variable | Por defecto | Que hace |
|---|---|---|
| `REDIS_HOST` | `redis` | |
| `REDIS_PORT` | `6379` | |
| `REDIS_PASSWORD` | `cambiar-en-produccion` | **Cambiarlo.** |
| `CACHE_STORE` | `redis` | |
| `QUEUE_CONNECTION` | `redis` | El worker de colas usa la misma base y necesita el mismo aislamiento. |

Redis no guarda datos de negocio: guarda cache y colas. Aun así lleva
contrasena, porque en un despliegue el cache suele tener sesiones y resultados
de consultas.

## 4. Sesión

| Variable | Por defecto | Que hace |
|---|---|---|
| `SESSION_DRIVER` | `database` | La sesión viaja en cookie; el estado queda en la base. |
| `SESSION_LIFETIME` | `480` | Minutos. 8 horas cubre una jornada de obra con horas de sobra. |
| `SESSION_SECURE_COOKIE` | `true` en producción | **Nunca `false` en producción.** Sin esto la cookie viaja por HTTP plano. |
| `SESSION_SAME_SITE` | `lax` | Evita que la cookie viaje en peticiones desde otro sitio. |

No hay guard con token de API, y es deliberado. Un maestro de obra trabaja desde
un notebook compartido en la obra; guardar un token de larga duracion en el
navegador es una mala idea que se evita haciendo que la sesión viva en el
servidor.

## 5. Authentik

| Variable | Por defecto | Que hace |
|---|---|---|
| `AUTHENTIK_ISSUER` | - | URL que declara Authentik. **Debe terminar en `/`.** |
| `AUTHENTIK_CLIENT_ID` | - | |
| `AUTHENTIK_CLIENT_SECRET` | - | |
| `AUTHENTIK_REDIRECT_URI` | - | Debe coincidir **exactamente** con lo configurado en Authentik. |
| `AUTHENTIK_SCOPES` | `openid email profile` | Pedir más alcances es pedir datos personales de más. |
| `AUTHENTIK_ALGORITMO` | `RS256` | **No cambiar a `none` ni a un algoritmo simetrico.** |

### Por que hay dos controles de seguridad aquí

**1. La firma del token se verifica contra las claves públicas del proveedor.**
Sin eso, el `id_token` llega desde el navegador y cualquiera podría escribir
`{"sub":"admin","email":"jefe@obra.cl"}` y entrar como jefe.

**2. Se comprueban emisor y audiencia.** Que la firma valga NO alcanza: un token
valido emitido para **otra** aplicación del mismo Authentik tiene una `audience`
que no es la nuestra, y hay que rechazarlo.

Las claves se cachean una hora (`oidc:jwks:*`). Si Authentik rota las claves
más seguido que eso, hay que bajar el TTL en
`App\Services\Auth\VerificadorIdToken`.

### El acceso es por invitacion

El correo no se registra solo: tiene que existir en `usuarios`. Una obra con los
nombres, RUT y telefonos de sus trabajadores **no es algo que se abra al que
llega con un correo cualquiera**.

Por eso el alta de personas la hace un administrador, y por eso la primera
cuenta de una instancia la define el operador del servidor.

## 6. Despliegue

| Variable | Que hace |
|---|---|
| `API_IMAGE`, `WEB_IMAGE` | Imagenes por **etiqueta**, no por `latest`. |
| `ACME_EMAIL` | Correo para el certificado de Let's Encrypt. |
| `LOG_LEVEL` | `info` en producción. `debug` solo para diagnosticar. |
| `LOG_DAILY_DAYS` | Días que se guardan los logs. |

## 7. Configuración del despliegue

| Variable | Por defecto | Que hace |
|---|---|---|
| `CONTROL_OBRAS_CORRER_MIGRACIONES` | `true` en desarrollo, `false` en producción | Si corre migraciones al arrancar. **En producción, `false` siempre:** un despliegue que cambia el esquema sin que nadie lo decida es un despliegue que puede cortar el servicio. |

## 8. Zonas horarias

El `php.ini` de la imagen fija `America/Santiago`, y no es un detalle menor: **el
atraso de una etapa se cuenta por fecha**. Con la zona horaria equivocada, la
diferencia entre la fecha planificada y la real cambia en un día entero, y el
maestro recibe un atraso que no tiene.

Cuando Chile cambie su horario de verano, hay que revisar esta linea y la
referencia a feriados en `GeneradorTareasCorreccion::fechaPlazo()`.

## 9. Variables que NO existen, y por que

| No existe | Por que |
|---|---|
| Una bandera para desactivar la RLS | Si alguna vez hiciera falta apagarla, el sistema quedaria abierto sin avisar. El aislamiento es parte del modelo, no una opción. |
| Una variable para el tipo de inspección o los estados de obra | Son parte del modelo. Si una obra necesita un estado nuevo, es un cambio de software, a propósito. |
| Un secreto de API para la interfaz | El navegador nunca habla con la API: el proxy de Nuxt reenvia la sesión. No hay token que gestionar. |
| Una unidad de moneda | La unidad la fija cada contrato. El sistema guarda números y no asume pesos ni UF. |