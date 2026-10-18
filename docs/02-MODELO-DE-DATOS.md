# 02 - Modelo de datos

Tablas, columnas y el por que de cada una. Un solo esquema para todas las
empresas: el aislamiento lo da la columna `tenant_id` más RLS, no un esquema por
empresa. Ver [03](03-MULTITENANCY-Y-ROLES.md).

---

## 1. Mapa de tablas

```
tenants (empresa constructora)          SIN RLS, a proposito
  |
  +-- usuarios
  +-- tipos_inspeccion
  +-- obras
        +-- etapas
        |     +-- tareas  (etapa_id, opcional)
        +-- tareas
        +-- inspecciones
        |     +-- hallazgos  <-->  tareas  (vinculo en los dos sentidos)
        +-- registros_seguridad
        +-- avances_pago
        +-- bitacora_obra

sessions                            SIN RLS (infraestructura, sin tenant_id)
```

Todas las tablas de negocio llevan `tenant_id` y por eso todas tienen política
RLS, salvo `tenants` y `sessions`. Ver [03](03-MULTITENANCY-Y-ROLES.md),
seccion 4.

## 2. Convenciones

| Convencion | Por que |
|---|---|
| Identificadores `uuid` | Dos empresas pueden numerar igual, y una obra importada de otro sistema no choca. |
| Fechas en `timestamptz` | Un accidente se registra con hora, no solo con día. |
| Fechas de plan en `date` | El plan se discute por días, no por horas. |
| Montos en `decimal(16,2)` | Los decimales de coma flotante pierden precisión al sumar. `float` esta prohibido para dinero. |
| `timestampsTz` en toda tabla | Un registro sin saber cuando se escribio es un registro que no se puede auditar. |

## 3. Tablas de negocio

### 3.1 `tenants`: la empresa constructora

| Columna | Tipo | Nota |
|---|---|---|
| `id` | uuid | |
| `razon_social` | string(180) | |
| `rut` | string(12) | Sin puntos ni guion, para poder indexarlo. Único. |
| `slug` | string(60) | Identificador en la URL de entrada. Único. |
| `activo` | boolean | Una empresa desactivada no aparece en el login y no entra al scheduler. |

**No lleva RLS.** Se consulta antes del login, cuando no hay sesión y por lo
tanto no hay `app.tenant_id` que poner. Compensaciones en
[03](03-MULTITENANCY-Y-ROLES.md), seccion 4.

### 3.2 `usuarios`: las personas de la empresa

| Columna | Tipo | Nota |
|---|---|---|
| `tenant_id` | uuid | RLS |
| `nombre`, `correo` | string | `correo` es único **dentro de la empresa**, no global. |
| `rut`, `telefono` | string, nullable | Datos personales opcionales. Ver [07](07-SEGURIDAD.md). |
| `cargo` | string, nullable | Texto libre a propósito: los cargos cambian más rapido que las migraciones. |
| `rol` | string(24) | `administrador`, `jefe_obra`, `maestro`, `inspector`, `cliente`. |
| `activo` | boolean | |

Por que el correo no es global: es normal que dos constructoras distintas tengan
a alguien con el mismo correo. Por eso el login lleva el **slug de la empresa**
además del correo.

`Rut` y `Telefono` están en `$hidden`: no viajan a la interfaz. La sesión de la
interfaz es lo único que llega al navegador, y esos datos no hacen falta para
operar una obra.

### 3.3 `obras`: la unidad de negocio

| Columna | Tipo | Nota |
|---|---|---|
| `codigo` | string(40) | Único **dentro de la empresa**. Cada constructora numera como quiere. |
| `cliente_nombre`, `cliente_rut`, `cliente_contacto` | | Datos del mandante. |
| `direccion`, `tipo` | | |
| `fecha_inicio` | date | |
| `fecha_termino_estimada` | date, nullable | |
| `monto_contratado` | decimal(16,2) | **Es el denominador del avance financiero.** |
| `estado` | string(24) | `planificacion`, `en_ejecucion`, `detenida`, `terminada`, `entregada`. |

`monto_contratado` vive en la obra y no en el pago porque una obra con cero
avances emitidos tiene que poder mostrar **0% cobrado**, y eso solo se puede
calcular si el denominador existe. Sin monto, el porcentaje se devuelve `null`,
nunca 0.

`detenida` existe como estado propio: una obra parada es normal, y si no
tuviera estado, alguien la cerraria para que dejara de aparecer en los informes
de atraso.

### 3.4 `etapas`: donde vive el avance fisico

| Columna | Tipo | Nota |
|---|---|---|
| `obra_id` | uuid | |
| `orden` | smallint | Único por obra con `obra_id`. |
| `fecha_planificada` | date, nullable | |
| `fecha_real` | date, nullable | La **la** cifra del modulo. |
| `avance_real_pct` | decimal(5,2) | De 0 a 100, dentro de la etapa. |
| `peso` | decimal(5,2) | De 0 a 100, parte de la obra que representa la etapa. |

**El atraso no es una columna.** Es `fecha_real - fecha_planificada`, calculado
en `Etapa::desviacionDias()`. Ver [01](01-ARQUITECTURA.md), seccion 5.1.

Cuando la etapa no tiene fecha real todavia, la comparacion es contra hoy: es
un atraso **en curso**, no un atraso de la nada. Sin fecha planificada se
devuelve `null`, que es distinto de cero.

La suma de `peso` por obra debe dar **100**. `EtapaController` lo comprueba al
guardar y avisa por log si no cuadra, sin bloquear: en una obra recien cargada
el plan va a estar incompleto a propósito.

### 3.5 `tareas`: el mecanismo que evita perder una decisión

| Columna | Tipo | Nota |
|---|---|---|
| `origen` | string(24) | `manual` o `correccion_inspeccion`. |
| `origen_hallazgo_id` | uuid, nullable | **Único con `tenant_id`.** Un hallazgo, una tarea. |
| `fecha_plazo` | date, nullable | Obligatoria mientras la tarea este abierta, si viene de una inspección. |
| `responsable_id` | uuid, nullable | |
| `creado_por_id` | uuid, nullable | Quien dejó escrita la tarea, que no siempre es quien la cumple. |

El indice único `(tenant_id, origen_hallazgo_id)` es la garantia de que un
hallazgo no genere dos tareas de corrección. En PostgreSQL un indice único
admite varias filas nulas, así que las tareas manuales no se ven afectadas.

`anulada` existe como estado para dejar constancia de que algo se dejó de
hacer, en vez de borrar la fila y perder la traza.

### 3.6 `inspecciones` y `hallazgos`: el vinculo que da sentido al modulo

`inspecciones` registra fecha (`fecha_inspeccion`), `inspector_id`, `tipo` y
`resultado`. **Una inspección conforme también se guarda**: es la prueba de que
la revisión se hizo.

`hallazgos` tiene `descripcion`, `gravedad` y `tarea_correccion_id`, que es
**único**.

La gravedad determina el plazo sugerido de la corrección:

| Gravedad | Días habiles sugeridos |
|---|---|
| `baja` | 15 |
| `media` | 7 |
| `alta` | 3 |
| `critica` | 1 |

Son una **sugerencia de negocio**, no una imposicion normativa. Quien inspecciona
puede ampliar el plazo, y el motivo queda en la bitacora. Lo que no se permite
es generar la tarea sin fecha.

Los plazos se cuentan en **días habiles**: un hallazgo del viernes con plazo de
3 días vence el miércoles, no el domingo.

**La inspección no tiene columna `estado` de "atrasada" ni la etapa tampoco.**
`EstadoEtapa` no incluye `atrasada` a propósito: el atraso se calcula, y si se
guardara dejaria de ser cierto el día que se reprograme el plan.

### 3.7 `registros_seguridad`: accidente y casi accidente

Una sola tabla, con `clase` que obliga a elegir entre `accidente` y
`casi_accidente`.

| Columna | Tipo | Nota |
|---|---|---|
| `clase` | string(20) | **Se elige al escribir.** No hay un estado "no paso nada". |
| `fecha_ocurrencia` | timestamptz | Con hora. |
| `lesionado` | boolean | Un accidente exige `true`. |
| `horas_perdidas` | decimal(7,2) | Un accidente exige `> 0`. Un casi accidente, `0`. |
| `persona_afectada_nombre`, `persona_afectada_rut` | nullable | Datos personales. Ver [07](07-SEGURIDAD.md). |
| `anonizado` | boolean | Cuando es `true`, nombre y RUT deben venir vacios. |
| `medidas_correctivas` | text, nullable | Un casi accidente sin medida es un susto anotado. |

Las reglas "accidente con lesionado y horas" y "casi accidente sin ellas" se
validan en el **FormRequest**, no en un trigger, para que el mensaje de error
sea util para quien esta en la obra.

### 3.8 `avances_pago`: el avance financiero

| Columna | Tipo | Nota |
|---|---|---|
| `numero` | smallint | Único por obra. |
| `avance_fisico_pct` | decimal(5,2), nullable | **FOTO** del fisico al emitir. |
| `monto_bruto`, `deducciones`, `monto_neto` | decimal(16,2) | `monto_neto` lo calcula el controlador: `bruto - deducciones`. |
| `monto_cobrado` | decimal(16,2) | **Solo esto entra en el avance financiero.** |
| `estado` | string(16) | `emitido`, `aprobado`, `pagado`, `rechazado`. |

**`avance_fisico_pct` NO es el avance fisico de la obra.** Es la foto del avance
al momento de emitir ese pago, y se congela aunque las etapas después cambien:
eso es lo que permite auditar después por que se emitio. Por eso en la interfaz
se llama `avance_fisico_al_emitir_pct` y no `avance_fisico`.

`aprobado` y `pagado` están separados porque **que el mandante apruebe un
estado de cuenta no es lo mismo que el dinero haya entrado**. El avance
financiero cuenta solo lo `pagado`.

### 3.9 `bitacora_obra`: donde quedan escritas las decisiones

| Columna | Tipo | Nota |
|---|---|---|
| `fecha` | timestamptz | Fecha del acuerdo, no de digitacion. |
| `clase` | string(16) | `decision`, `acuerdo`, `observacion`, `reclamo`. |
| `autor_id` | uuid | **No es opcional.** Un acuerdo sin autor es una opinion. |
| `tarea_relacionada_id` | uuid, nullable | Enlace con el tablero. |

## 4. La dependencia circular entre `hallazgos` y `tareas`

Los dos se referencian: la tarea dice de que hallazgo nacio, y el hallazgo dice
cual es su tarea de corrección.

Se resuelve en dos pasos:

1. `2026_10_17_000006_crear_tabla_tareas` crea `tareas` **sin** la llave foranea
   a hallazgos, porque la tabla no existe todavia.
2. `2026_10_17_000008_crear_tabla_hallazgos` crea `hallazgos` y después agrega
   esa llave con `Schema::table('tareas', ...)`.

El orden importa: agregar una llave antes de que exista la tabla referenciada
hace fallar la migración.

## 5. Indices que existen por una razon concreta

| Indice | Razon |
|---|---|
| `usuarios (tenant_id, correo)` único | El correo es único por empresa, no global. |
| `obras (tenant_id, codigo)` único | Cada constructora numera sus obras como quiere. |
| `etapas (obra_id, orden)` único | Dos etapas no ocupan la misma posición. |
| `tareas (tenant_id, origen_hallazgo_id)` único | Un hallazgo, una corrección. |
| `hallazgos (tarea_correccion_id)` único | Una tarea no corrige dos hallazgos. |
| `avances_pago (obra_id, numero)` único | Numeracion correlativa por obra. |
| `obras (tenant_id, cliente_rut)` | Buscar toda la obra de un cliente. |
| `etapas (tenant_id, fecha_planificada)` | Informes de atraso por periodo. |