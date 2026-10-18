# Control de Obras

Aplicación propia de **Hefesto Studio** para llevar el control de obras de
construcción: etapas, inspecciones, seguridad y estado de cuenta al cliente.

---

## Que problema resuelve, en una frase

Una obra se controla mal casi siempre por lo mismo: **las decisiones se
conversan y no quedan registradas**. El maestro sabe que hay que revisar la
fundación el jueves; lo anota; se enferma; nadie lo sabe; y un mes después
nadie sabe si se hizo.

Este sistema existe para que **la decisión quede escrita, con fecha y
responsable**. No es un sistema de planificación: es un registro que resiste al
cambio de turno.

---

## Advertencia importante

**Este sistema NO reemplaza el libro de obra ni acredita cumplimiento
normativo.**

El libro de obra es un documento legal, con formalidades propias establecidas
por la normativa chilena, y este software no lo suple, no lo genera y no lo
certifica.

Lo que este sistema hace es **registrar y ordenar**: anotaciones de obra,
fechas planificadas y reales, inspecciones con sus observaciones, tareas de
corrección con plazo y el estado de cuenta. Que en el sistema aparezca que una
fundación fue revisada el jueves es un dato de gestión de la obra, **no es
acreditacion de que se haya cumplido la normativa que exige esa revisión**.

Quien responde por el cumplimiento normativo sigue siendo el profesional
responsable de la obra.

Los datos de trabajadores y de clientes que se registran aquí están
sujetos a la **Ley 19.628** sobre protección de la vida privada. Ver
[docs/07](docs/07-SEGURIDAD.md).

---

## Estado real de este código

> **CÓDIGO ESCRITO, SIN EJECUTAR.**
>
> La maquina donde se escribio no tiene Docker, ni PHP, ni Composer. **Ninguna
> migración se ha aplicado, ninguna prueba se ha corrido y ninguna imagen se ha
> construido.** Todo lo que hay aquí esta escrito y revisado a mano, pero
> **nunca ha corrido**.
>
> Antes de usarlo con cualquier persona hay que seguir [docs/04](docs/04-INSTALACION.md)
> y correr las pruebas de [docs/10](docs/10-PRUEBAS-Y-ESTADO.md). Lo que se
> probó y lo que no, esta separado allí con todas las letras.

---

## Los módulos

| Módulo | Qué resuelve |
|---|---|
| **Proyectos y obras** | Ficha de la obra: cliente, dirección, tipo, inicio y estado. |
| **Etapas y tareas** | El avance se estructura en etapas con fecha planificada y fecha real. **La diferencia entre ambas es la única cifra que importa en una reunion de obra.** |
| **Inspecciones** | Con fecha, inspector, tipo y resultado. **Un hallazgo genera una tarea de corrección con plazo.** Sin ese vinculo, la inspección es un papel que nadie abre. |
| **Seguridad y accidentes** | Accidentes y **casi accidentes por separado**. El casi accidente no es un accidente: es el dato que permite prevenir antes de que se repita. |
| **Avances de pago** | Estado de cuenta con **avance fisico y avance financiero reportados por separado**, porque no son lo mismo. |
| **Administración** | Responsables de obra, inspectores, tipos de inspección y estados. |
| **Bitacora** | Donde las decisiones quedan escritas, con fecha y autor. |

---

## La separacion que define este sistema

Se puede tener **60% de avance fisico y 45% cobrado**. Son dos cifras, con dos
fuentes distintas, y este sistema **nunca las promedia**:

| | De donde sale | Que mide |
|---|---|---|
| **Avance fisico** | Las etapas: `peso` y `avance_real_pct` | Obra construida. |
| **Avance financiero** | Los pagos: `monto_cobrado` sobre `monto_contratado` | Plata cobrada. |

El promedio de 60 y 45 da 52,5%, y ese 52,5% no describe ni la obra ni el
dinero. La **diferencia** entre ambos es lo que hay que mirar en una reunion:
si el fisico va muy por delante del financiero, la obra esta trabajando con
plata que todavia no llego.

Implementacion: `api/app/Services/Avance/`. Clases separadas, que no se llaman
entre si.

---

## Aislamiento entre empresas

Cada **empresa constructora** es un tenant. El aislamiento se implementa con
**columna `tenant_id` más Row Level Security de PostgreSQL**, con `WITH CHECK`,
`FORCE ROW LEVEL SECURITY` y el contexto viajando con `set_config(..., true)`,
de alcance de transacción.

Es el mismo patron ya decidido y documentado en
`apps/portal-autoservicio/docs/03-MULTITENANCY.md`, aplicado aquí. Ver
[docs/03](docs/03-MULTITENANCY-Y-ROLES.md).

---

## Estructura

```
control-obras/
├── README.md
├── .env.example
├── docs/          01 a 10: arquitectura, datos, roles, instalación,
│                  seguridad, respaldos, API y estado de pruebas
├── api/           Laravel
├── web/           Nuxt (SSR)
└── deploy/        Dockerfiles, Compose de desarrollo y producción, Caddy
```

---

## Puesta en marcha rapida

```bash
cp .env.example .env
# Complete APP_KEY, DB_PASSWORD y las variables de Authentik.

docker compose -f deploy/docker-compose.dev.yml up -d
docker compose -f deploy/docker-compose.dev.yml exec api php artisan migrate --seed
docker compose -f deploy/docker-compose.dev.yml exec api php artisan obras:verificar-aislamiento
```

Ese último comando **tiene que terminar con exito**. Si falla, el aislamiento
entre constructoras no esta puesto y el sistema no debe recibir personas.
Ver [docs/04](docs/04-INSTALACION.md).

---

## Documentación

| Documento | De que trata |
|---|---|
| [01](docs/01-ARQUITECTURA.md) | Como esta armado el sistema y por que así. |
| [02](docs/02-MODELO-DE-DATOS.md) | Tablas, columnas y el por que de cada una. |
| [03](docs/03-MULTITENANCY-Y-ROLES.md) | Aislamiento entre empresas y roles. |
| [04](docs/04-INSTALACION.md) | Como levantarlo, paso a paso. |
| [05](docs/05-CONFIGURACION.md) | Cada variable y su efecto. |
| [06](docs/06-PRIMERA-OBRA.md) | Cargar una obra y su historial desde cero. |
| [07](docs/07-SEGURIDAD.md) | Datos personales, Ley 19.628 y medidas. |
| [08](docs/08-RESPALDOS.md) | Respaldo y restauración de la base. |
| [09](docs/09-API.md) | Endpoints y reglas de autorización. |
| [10](docs/10-PRUEBAS-Y-ESTADO.md) | **Que se ejecuto y que no.** |

---

Aplicación propia de Hefesto Studio. Software original.
No es un kit de despliegue de una herramienta de terceros.