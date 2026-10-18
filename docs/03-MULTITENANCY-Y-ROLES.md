# 03 - Aislamiento entre empresas y roles

Este es el documento más importante del proyecto. El riesgo número uno no es
que falle una pantalla: es que la **Constructora Los Robles vea las obras de la
Constructora Cordillera**. Todo lo demas es menos importante.

---

## 1. Que es el tenant aquí

El tenant es la **empresa constructora**.

El aislamiento va en la empresa y no en la obra por una razon de
responsabilidad: la empresa es quien responde por el maestro, por el inspector y
por el estado de cuenta. Si el aislamiento fuera por obra, el mismo maestro no
podría aparecer en dos obras de la misma empresa, y el administrador no tendria
una vista de su negocio.

## 2. Por que columna única y no un esquema por empresa

La alternativa clasica de PostgreSQL es un esquema por constructora
(`los_robles.obras`, `cordillera.obras`). Se evaluo y se descarto, con estos
motivos:

**1. Las migraciones se multiplican.** Con esquema por empresa, cada migración
tiene que aplicarse en TODOS los esquemas. Con 30 empresas son 30 ejecuciones.
Con 300, 300. Cada ejecución es una oportunidad de que unas queden
desalineadas: una empresa con la columna nueva y otra sin ella, justo cuando una
consulta nueva recorre las dos.

**2. El pool de conexiones se vuelve el cuello de botella.** Se dimensiona por el
número de combinaciones (esquema, rol) que se usan de verdad. Con muchas
empresas, un pool razonable deja de alcanzar en horas punta.

**3. Laravel obliga a duplicar la lógica de esquema.** La conexión pasa a
depender del usuario conectado, y cada consulta, cada migración, cada prueba y
cada cola necesita acordarse del esquema correcto. Es la clase de acoplamiento
que produce fugas: un `DB::table(...)` olvidado, una relacion mal definida.

**4. La separacion queda en manos de la aplicación.** El `search_path` es un
parámetro de sesión. Si queda mal puesto (una conexión reutilizada del pool, un
error en el middleware), la consulta va al esquema equivocado y **no falla**:
devuelve datos de la constructora equivocada sin error visible. Es exactamente
el fallo que RLS evita, porque en RLS el tenant viaja en la propia fila y la
política compara fila por fila.

**5. Los respaldos se complican.** Respaldo por empresa obliga a scripts
propios, y el operador termina respaldando la base entera igual.

### Lo que se eligió: columna `tenant_id` + RLS

- Una sola migración, un solo esquema, un solo pool.
- La política se aplica en la base, no en PHP: no se puede "olvidar".
- Un `where tenant_id = ?` olvidado no fuga datos: filtra de más, que es un
  error visible, no invisible.
- Respaldo es `pg_dump` de una base, sin scripts por empresa.

El costo es que **todas** las consultas necesitan contexto de tenant. Ese costo
esta resuelto y forzado por el diseno (seccion 4).

## 3. Las dos capas

| Capa | Que hace | Que detecta |
|---|---|---|
| Aplicación (Policies y FormRequest) | Decide y filtra | Errores de lógica, consultas mal escritas |
| Base de datos (RLS) | Impide a PostgreSQL devolver filas ajenas | Todo lo que se le escape a la capa de arriba |

La base es la última linea. Si la aplicación tiene un error, la base sigue sin
entregar datos de otra constructora.

## 4. El mecanismo, en detalle

Definido en `api/database/migrations/2026_10_17_000014_activar_row_level_security.php`.

### 4.1 La función de contexto

```sql
CREATE OR REPLACE FUNCTION public.app_tenant_id() RETURNS uuid
...
    valor := current_setting('app.tenant_id', true);
    IF valor IS NULL OR valor = '' THEN
        RAISE EXCEPTION USING ERRCODE = 'insufficient_privilege', ...
```

La función **lanza excepción** si no hay empresa. Podría devolver `NULL` y
filtrar todo en silencio, pero se eligio que falle ruidosamente:

- Si devuelve `NULL`, la consulta devuelve cero filas sin aviso. Alguien lo
  "arregla" devolviendo `NULL` cuando hace falta (un reporte, un comando de
  consola) y la puerta queda abierta sin que nada lo delate.
- Si lanza excepción, el error aparece en el log **en el momento**, y se sabe
  exactamente que operación se escapo del middleware.

Es el mismo criterio que un candado: importa que falle cerrado.

### 4.2 La política

Para cada tabla de negocio:

```sql
ALTER TABLE public.obras ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.obras FORCE ROW LEVEL SECURITY;

CREATE POLICY politica_aislamiento_obras ON public.obras
    FOR ALL
    TO PUBLIC
    USING (tenant_id = public.app_tenant_id())
    WITH CHECK (tenant_id = public.app_tenant_id());
```

- `USING` filtra lo que se **lee**.
- `WITH CHECK` impide **escribir** una fila con un `tenant_id` distinto. Sin esto,
  un `INSERT` con el tenant equivocado pasaria la lectura y dejaria una fila
  invisible que nadie podría ver ni borrar.
- `FORCE ROW LEVEL SECURITY` hace que la política aplique **también al dueno de
  la tabla**. Sin FORCE, el dueno la saltaria. En desarrollo el rol de la
  aplicación suele ser el mismo que creo las tablas, o sea el dueno: sin FORCE,
  el aislamiento sería decorativo justo en el entorno donde se prueba.

### 4.3 Como viaja la empresa

Con `App\Services\Tenancy\ContextoTenant`:

```php
DB::select('SELECT set_config(?, ?, true)', ['app.tenant_id', $empresaId]);
```

El `true` final es lo más importante del archivo: significa **alcance de
transacción**. Al confirmar o revertir, PostgreSQL descarta el valor solo.

Con un `SET` normal (alcance de sesión), el valor sobreviviria a la petición. Y
el worker de colas **reutiliza la misma conexión** para trabajos de empresas
distintas: un `SET` a sesión significa que un trabajo de Los Robles podría leer
los datos de Cordillera. Ese es exactamente el fallo que este diseno evita, y
por eso `ContextoTenant::establecer()` **lanza excepción si no esta dentro de
una transacción**.

Hay una prueba dedicada a esto: `test_el_contexto_de_empresa_no_sobrevive_a_la_transaccion`.

### 4.4 Quien fija el contexto

Solo una clase: `ContextoTenant`. Y solo a traves del middleware
`App\Http\Middleware\ResolverTenant`, que envuelve la petición autenticada en
una transacción con la empresa ya fijada.

El middleware va **antes** del `auth` de la ruta, y resuelve el usuario por su
cuenta con el guard `web`.

## 5. El punto ciego: la tabla `tenants`

`tenants` **no lleva RLS**, a propósito: se consulta durante el login, cuando
todavia no hay sesión y por lo tanto no hay `app.tenant_id` que poner.

Es una excepción consciente, con dos compensaciones:

**Compensacion 1: la vista `vw_tenants_publicos`.** Antes del login la
aplicación no lee `tenants` cruda: lee esta vista, que expone **tres** columnas
(`slug`, `nombre`, `activo`) y solo de las empresas activas. No expone RUT,
razon social completa ni contactos.

**Compensacion 2: el rol de la aplicación no es superusuario.** Quien tenga
acceso SQL directo a la base puede leer `tenants` entera. Por eso el despliegue
exige verificar los permisos, y `obras:verificar-aislamiento` falla si el rol
tiene `BYPASSRLS` o es superusuario.

**Riesgo residual, dicho con todas las letras:** un `slug` se puede enumerar
antes de autenticarse, porque aparece en la URL de entrada y en los enlaces que
la empresa reparte. Un atacante puede descubrir que la Constructora Los Robles
usa el sistema. **No puede ver más que el nombre.** Si incluso eso se considera
inaceptable, la alternativa es dejar el campo en blanco hasta el login y
resolver siempre por dominio de correo; queda anotado como decisión pendiente
del cliente, no como algo resuelto.

## 6. La segunda capa: los roles

La RLS ya garantiza que nada sale de la empresa. Dentro de la empresa hay un
segundo filtro, en las Policies.

| Recurso | administrador | jefe_obra | maestro | inspector | cliente |
|---|---|---|---|---|---|
| Ver obras | todas | todas | todas | todas | todas |
| Crear y editar obras | si | si | si | no | no |
| Borrar obras (baja lógica) | si | no | no | no | no |
| Ver etapas y atraso | si | si | si | si | si |
| Editar etapas | si | si | si | no | no |
| Ver tareas | si | si | si | si | si |
| Crear y completar tareas | si | si | si | si | **no** |
| Registrar inspecciones | si | si | si | si | **no** |
| Registrar accidentes | si | si | si | si | **no** |
| Emitir pagos | si | si | si | si | **no** |
| Ver el estado de cuenta | si | si | si | si | si |
| Anonizar un accidente | si | si | si | si | si |
| Dar de alta personas | si | no | no | no | no |
| Escribir en la bitacora | si | si | si | si | **no** |

### Por que el cliente ve pero no toca

Ver el estado de cuenta y el estado de las tareas es **derecho del cliente**: es
su obra y es su plata, y tiene que poder preguntar en que se esta gastando.

Cambiarlo es otra cosa. Si el cliente pudiera completar una tarea de corrección
de un hallazgo, podría cerrar su propia inspección sin que nadie ejecutara la
corrección. Y si pudiera marcar un pago como cobrado, **el avance financiero lo
declararia el mismo cliente que lo recibe**, y la cifra dejaria de ser un
control.

Por eso el cliente no completa tareas, no registra accidentes y no emite pagos.

### Por que 404 y no 403

Cuando una Policy rechaza, la respuesta es **404, no 403**: un 403 confirmaria
que el recurso existe.

En la práctica el filtro por tenant ya impide que el recurso exista a ojos de la
otra empresa, así que 404 es además la respuesta correcta en el caso habitual:
si no lo ves, no existe. Ver `bootstrap/app.php`.

### Por que los tipos de inspección son tabla y los estados son enum

- **Tipo de inspección** es una tabla: lo define la obra, no el software.
  "Inspección de fundaciones", "control de hormigón" y "recepción de cubierta"
  son cosas que cada empresa define según su obra, y agregar una no debe
  requerir un despliegue.
- **Estados de obra, etapa, tarea e inspección** son enums: son parte del modelo
  y no pueden quedar a merced de que alguien escriba un estado nuevo que el
  sistema no conoce.

## 7. La marca automática de `tenant_id`

`App\Models\Concerns\MarcaEmpresa` es una red de seguridad del `INSERT`, no el
aislamiento.

Al crear una fila:

- si el usuario esta autenticado y la fila no trae `tenant_id`, se completa sola
  con la empresa del usuario;
- si trae un `tenant_id` **distinto** al del usuario, lanza una excepción con un
  mensaje que se entiende.

Sin eso, el error más comun sería olvidar el `tenant_id` al crear y receber un
error de PostgreSQL que no le dice nada a quien esta en la obra.

**Por que no es un GlobalScope:** un GlobalScope dependeria de que haya usuario
autenticado. En las pruebas de aislamiento, en los comandos de consola y en el
worker de colas no lo hay, y el scope filtraria todo sin avisar. El
aislamiento real es la RLS.

## 8. Que tiene que correr en cada despliegue

```bash
docker compose run --rm api php artisan obras:verificar-aislamiento
```

**Tiene que terminar con exito antes de publicar.** El comando falla si:

1. el rol es superusuario o tiene `BYPASSRLS`;
2. la función `app_tenant_id()` no existe, o no falla cerrada;
3. alguna tabla de negocio no tiene RLS activado;
4. alguna tabla tiene RLS pero **sin** `FORCE`, que el dueno la saltaria;
5. alguna tabla tiene RLS activado pero **sin ninguna política**.

Ese comando también lo ejecuta `entrypoint.sh` al arrancar, pero **solo avisa**.
Si bloqueara, un contenedor que arranca con la base todavia levantandose caeria
en bucle. El bloqueo real es un paso del despliegue.

## 9. Riesgo restante, sin adornos

| Riesgo | Estado |
|---|---|
| Fuga de datos entre constructoras | Mitigado en la base con RLS, `WITH CHECK` y `FORCE`. |
| Contexto arrastrado por el worker de colas | Mitigado con alcance de transacción, y con prueba dedicada. |
| Rol con `BYPASSRLS` | Detectado por `obras:verificar-aislamiento`. |
| Enumeracion de `slug` antes del login | **Riesgo aceptado y documentado.** Solo expone el nombre. |
| Revisión de seguridad externa | **NO HECHA.** |
| Pruebas de penetracion | **NO HECHAS.** |
| Pruebas de aislamiento ejecutadas | **NO.**están escritas pero nunca se corrieron: la maquina donde se escribieron no tiene PostgreSQL. Ver [10](10-PRUEBAS-Y-ESTADO.md). |

La política protege **el dato**, no el archivo. No hay archivos en este sistema,
así que ese limite no aplica hoy. Si se agregaran adjuntos, habría que
comprobar la ruta en el controlador antes de servir el archivo.

---

## 10. Los dos roles de PostgreSQL

La base de Control de Obras corre con **dos** roles, no con uno. La separación es
deliberada: es lo que hace que el aislamiento entre constructoras funcione en
vez de quedar como decoración.

### Qué es cada rol

| Rol | Nombre | De dónde sale | Para qué sirve | ¿Lo usa la aplicación? |
|---|---|---|---|---|
| **Arranque** | `control_obras_admin` | `POSTGRES_USER` | Crear la base `control_obras` y las tareas administrativas del despliegue | **Nunca** |
| **Aplicación** | `control_obras` | `DB_USERNAME`, creado por `deploy/postgres/inicializar-rol.sh` | Todo el tráfico de la API | **Sí, este y solo este** |

Aquí hay una particularidad que conviene no pasar por alto: **el rol de
aplicación se llama igual que la base**, `control_obras`, y sin el sufijo
`_app`. El rol de arranque es el único que lleva `_admin`. Al leer un
`.env.prod`, `DB_USERNAME: control_obras` no es un error de tipeo.

El rol de arranque no es una convención del estudio: la imagen oficial de
PostgreSQL ejecuta `initdb --username="$POSTGRES_USER"`, así que ese rol nace
**superusuario**.

### Por qué con un solo rol no servía

La documentación de PostgreSQL 18 lo dice de forma textual:

> *«Superusers and roles with the `BYPASSRLS` attribute always bypass the row
> security system when accessing a table. Table owners normally bypass row
> security as well, though a table owner can choose to be subject to row
> security with `ALTER TABLE ... FORCE ROW LEVEL SECURITY`.*

La segunda frase es la clave. `FORCE ROW LEVEL SECURITY` sujeta al **dueño** de
la tabla, pero **no al superusuario**. Mientras la aplicación conectara con el
rol de `POSTGRES_USER`, ninguna política de este documento se iba a aplicar: una
constructora habría visto las obras, los presupuestos y el personal de todas las
demás, sin error y sin aviso. El aislamiento entre empresas era decorativo.

### Los atributos van todos escritos

`deploy/postgres/inicializar-rol.sh`, montado en
`/docker-entrypoint-initdb.d/`, crea el rol con `NOSUPERUSER`, `NOCREATEDB`,
`NOCREATEROLE`, `NOINHERIT` y `NOBYPASSRLS` **explícitos todos**, aunque
`NOBYPASSRLS` ya sea el valor por omisión.

Se escriben igual a propósito: un rol verificado no debería depender de un valor
por omisión que una versión nueva puede cambiar. El script también falla
ruidosamente si falta `POSTGRES_DB`, `APP_DB_ROLE` o `APP_DB_PASSWORD`.

### Por qué el rol de aplicación es dueño de la base

Justo después de crearlo, el script corre:

```sql
ALTER DATABASE control_obras OWNER TO control_obras;
```

Las migraciones de Laravel se ejecutan con el rol de la aplicación y necesitan
**crear tablas**. La única forma de darles ese permiso sin convertirlos en
superusuario es que ese rol sea el dueño de la base. Desde PostgreSQL 15 el
esquema `public` pertenece al dueño de la base, así que con esto el rol ya
puede crear tablas ahí.

Y este es el punto exacto del diseño: al ser el dueño, **las tablas quedan a su
nombre**, y por eso el `FORCE ROW LEVEL SECURITY` que usan las tablas sí lo
sujeto. Ser dueño no es saltarse la política; es lo que hace que la política lo
alcance. Un superusuario la saltearía siempre.

### Cómo se comprueba

```
php artisan obras:verificar-aislamiento
```

El prefijo del comando es `obras:`, no `control-obras:`. El comando consulta
`pg_roles` sobre el rol de la conexión y falla con código 1 si ese rol es
superusuario, tiene `BYPASSRLS` o puede crear roles. Para este caso la última
comprobación importa: un rol con `CREATEROLE` podríafabricarse otro con
`BYPASSRLS` y saltarse la RLS en la siguiente conexión.

**Este comando no se ha ejecutado.** En el equipo donde se escribió este cambio
no había PostgreSQL ni Docker. El diseño es correcto y está sostenido en la
documentación oficial, pero nadie lo ha corrido. Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).