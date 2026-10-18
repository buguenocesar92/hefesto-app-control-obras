# 09 - API

Endpoints del sistema. Prefijo `/api/v1`. Todas las respuestas son JSON.

---

## 1. Como se llama

La API **no acepta token**. No hay ni un header `Authorization` en todo el
sistema.

La sesión viaja en cookie, y la cookie la crea la API cuando la persona entra
desde Authentik. Las peticiones desde la interfaz pasan por el proxy de Nuxt, que
reenvia la cookie y el token CSRF.

| Petición desde el navegador | Petición desde el proxy |
|---|---|
| `GET /api/v1/obras` con `Cookie` | `GET /api/` + `Cookie` hacia la API |
| `X-XSRF-TOKEN` en las peticiones que escriben | Reenviado como `X-XSRF-TOKEN` y `X-CSRF-TOKEN` |

CSRF: las peticiones que no son `GET` ni `HEAD` necesitan el token. El middleware
de CSRF de Laravel va en el grupo `web`, del que cuelgan todas las rutas.

## 2. Públicas

| Metodo | Ruta | Que hace |
|---|---|---|
| `GET` | `/api/v1/salud` | Estado del servicio. Sin datos. |
| `GET` | `/api/v1/empresas` | Empresas para la pantalla de entrada. Lee `vw_tenants_publicos`: **solo `slug`, `nombre` y `activo`**. |
| `GET` | `/api/v1/auth/entrar?empresa={slug}` | Redirige a Authentik. |
| `GET` | `/api/v1/auth/retorno` | Retorno de Authentik: canjea el código, verifica el token y abre sesión. |
| `GET` | `/api/v1/auth/salir` | Cierra sesión. |

Si alguna ruta de datos de obra quedara abierta, la RLS la rechazaria con una
excepción, porque sin sesión no hay empresa en la conexión. **El aislamiento
falla cerrado.**

## 3. Sesión y catalogos

| Metodo | Ruta | Rol |
|---|---|---|
| `GET` | `/api/v1/sesion` | Cualquiera con sesión. Devuelve id, nombre, correo, cargo y rol. **Sin RUT ni teléfono.** |
| `GET` | `/api/v1/catalogos` | Cualquiera. Estados, gravedades, clases y roles, para que la interfaz no los escriba a mano. |

## 4. Obras

| Metodo | Ruta | Rol para escribir |
|---|---|---|
| `GET` | `/api/v1/obras` | Todos leen |
| `POST` | `/api/v1/obras` | administrador, jefe_obra, maestro |
| `GET` | `/api/v1/obras/{id}` | Todos leen |
| `PUT` | `/api/v1/obras/{id}` | administrador, jefe_obra, maestro |
| `DELETE` | `/api/v1/obras/{id}` | Solo `administrador`. Y **no borra**: marca la obra como terminada. |

Listado con filtros: `?estado=en_ejecucion`, `?q=texto`.

**El `tenant_id` nunca se acepta en el cuerpo.** Sale de la sesión. Aceptarlo
sería abrir la puerta a crear una obra en nombre de otra constructora.

## 5. Etapas

| Metodo | Ruta | Rol |
|---|---|---|
| `GET` | `/api/v1/obras/{id}/etapas` | Todos |
| `POST` | `/api/v1/obras/{id}/etapas` | administrador, jefe_obra, maestro |
| `PUT` | `/api/v1/etapas/{id}` | administrador, jefe_obra, maestro |

Cada etapa trae su atraso **calculado**:

```json
{
    "nombre": "Instalacion electrica interior",
    "fecha_planificada": "2026-10-05",
    "fecha_real": null,
    "desviacion_dias": 12,
    "atrasada": true,
    "avance_real_pct": 0,
    "peso": 15
}
```

`desviacion_dias` es positivo para atraso, negativo para adelanto, y `null` si no
hay fecha planificada con que comparar. `atrasada` va aparte porque una etapa
puede estar atrasada y todavia no tener fecha real.

## 6. Tareas

| Metodo | Ruta | Rol |
|---|---|---|
| `GET` | `/api/v1/obras/{id}/tareas` | Todos leen |
| `GET` | `/api/v1/obras/{id}/tareas-vencidas` | Todos leen |
| `POST` | `/api/v1/obras/{id}/tareas` | Todos menos el cliente |
| `PUT` | `/api/v1/tareas/{id}` | Todos menos el cliente |

Filtros: `?estado=pendiente`, `?vencidas=true`.

**Completar una tarea de corrección cierra el hallazgo.** En sentido inverso no:
un hallazgo no se cierra sin que su tarea se haya completado. Por eso no hay
endpoint para "cerrar el hallazgo" por su cuenta.

**El `origen` no se puede cambiar por la API.** Una corrección de inspección no
se convierte en tarea manual porque le moleste a alguien: se perderia el rastro
del hallazgo.

## 7. Inspecciones y hallazgos

| Metodo | Ruta | Rol |
|---|---|---|
| `GET` | `/api/v1/obras/{id}/inspecciones` | Todos leen |
| `POST` | `/api/v1/obras/{id}/inspecciones` | administrador, jefe_obra, maestro, inspector |
| `POST` | `/api/v1/hallazgos/{id}/cerrar` | Todos menos el cliente |

Al crear una inspección con hallazgos, cada hallazgo **genera su tarea de
corrección con plazo**, y la respuesta dice exactamente cual:

```json
{
    "data": { "...": "la inspeccion con sus hallazgos" },
    "tareas_generadas": [
        { "hallazgo_id": "...", "tarea_id": "...", "fecha_plazo": "2026-08-19" }
    ]
}
```

Todo ocurre en una transacción: o se guarda la inspección con sus tareas, o no
se guarda nada.

Corpo del `POST`:

```json
{
    "tipo_inspeccion_id": "...",
    "fecha_inspeccion": "2026-08-12",
    "inspector_id": "...",
    "resultado": "con_hallazgos",
    "numero_acta": "ACTA-004",
    "observaciones": "Dos observaciones en la tanda de columnas.",
    "hallazgos": [
        { "descripcion": "Falta compactacion en el trasdoso.", "gravedad": "baja" },
        { "descripcion": "Armadura expuesta.", "gravedad": "alta", "responsable_id": "..." }
    ]
}
```

Reglas: `resultado: conforme` con hallazgos es un **422**. `fecha_inspeccion`
futura es un **422**.

## 8. Seguridad

| Metodo | Ruta | Rol |
|---|---|---|
| `GET` | `/api/v1/obras/{id}/registros-seguridad?clase=` | Todos leen |
| `POST` | `/api/v1/obras/{id}/registros-seguridad` | administrador, jefe_obra, maestro, inspector |
| `POST` | `/api/v1/registros-seguridad/{id}/anonimizar` | **Cualquiera de la empresa** |

El listado trae el resumen **por clase y sin sumar**:

```json
{
    "data": [],
    "resumen": {
        "accidentes": 1,
        "casi_accidentes": 1,
        "nota": "Los accidentes y los casi accidentes no se suman: son clases distintas."
    }
}
```

Reglas de validación, todas en **422** con mensaje util:

- un accidente sin `lesionado` o sin `horas_perdidas`;
- un casi accidente **con** `lesionado` o **con** `horas_perdidas`
  (si hay horas perdidas, es un accidente y hay que decirlo);
- `anonizado: true` con nombre o RUT.

## 9. Pagos y estado de cuenta

| Metodo | Ruta | Rol |
|---|---|---|
| `GET` | `/api/v1/obras/{id}/avances-pago` | Todos leen |
| `POST` | `/api/v1/obras/{id}/avances-pago` | Todos menos el cliente |
| `GET` | `/api/v1/obras/{id}/estado-de-cuenta` | Todos leen |

`monto_neto` **no se recibe**: lo calcula el controlador como
`monto_bruto - deducciones`.

### El endpoint más importante

```json
{
    "obra": { "id": "...", "codigo": "OB-2026-14", "monto_contratado": 2400.00 },
    "avance_fisico": {
        "porcentaje": 73.0,
        "fuente": "etapas",
        "suma_pesos": 100.0,
        "etapas_total": 4,
        "etapas_terminadas": 2,
        "etapas_atrasadas": 1,
        "mayor_atraso_dias": 12
    },
    "avance_financiero": {
        "porcentaje": 45.0,
        "fuente": "avances_pago",
        "monto_contratado": 2400.00,
        "monto_cobrado": 1080.00,
        "saldo_por_cobrar": 1320.00,
        "por_emitir": 0.00,
        "aprobado_no_cobrado": 540.00,
        "lineas_pagadas": 2,
        "lineas_totales": 3
    },
    "diferencia_puntos_porcentuales": 28.0,
    "pagos": [],
    "pendientes": {
        "tareas_vencidas": 1,
        "tareas_sin_vencer": 2,
        "hallazgos_pendientes": 2
    }
}
```

**No hay un campo `avance` único.** Cada bloque declara su `fuente`, y la
`diferencia` es una **resta**, no un promedio. Que no exista un número único es
parte del contrato, y hay una prueba que lo comprueba.

Cuando un porcentaje no se puede calcular, va `null`, nunca `0`:

| Situacion | Fisico | Financiero |
|---|---|---|
| Obra recien planificada, sin etapas con peso | `null` | el que corresponda |
| Contrato sin monto cargado | el que corresponda | `null` |

## 10. Bitacora y administración

| Metodo | Ruta | Rol |
|---|---|---|
| `GET` | `/api/v1/obras/{id}/bitacora` | Todos leen |
| `POST` | `/api/v1/obras/{id}/bitacora` | Todos menos el cliente |
| `GET` | `/api/v1/usuarios` | Todos leen |
| `POST` | `/api/v1/usuarios` | Solo `administrador` |
| `GET` | `/api/v1/tipos-inspeccion` | Todos leen |

`autor_id` en la bitacora sale de la sesión, nunca del cuerpo de la petición: si
se aceptara, el "quien acordo" sería tan editable como el acuerdo mismo.

La bitacora **no se edita**. Se puede leer siempre; escribir solo el
administrador. Un acuerdo no se corrige: se deja otro.

## 11. Respuestas de error

| Código | Cuándo |
|---|---|
| `401` | Sin sesión. |
| `403` | Rol insuficiente. Aplica a pocas rutas. |
| `404` | **Recurso de otra empresa, o recurso que la Policy rechaza.** No se distingue del inexistente, a propósito. |
| `409` | Conflicto de estado: por ejemplo, cerrar un hallazgo que ya no esta pendiente. |
| `422` | Validación. El cuerpo trae el detalle campo por campo, con mensajes en espanol. |

Una petición sin contexto de empresa que llega a una tabla protegida produce un
`422` con este mensaje, en vez de un `500` con un error de PostgreSQL:

```json
{
    "mensaje": "La operacion fue rechazada por el aislamiento de empresa. Revise que el recurso pertenezca a su empresa."
}
```

## 12. Comandos de consola

| Comando | Que hace |
|---|---|
| `obras:crear-empresa` | Alta de empresa y primer administrador. Envuelve la escritura en el contexto de empresa, porque `usuarios` tiene RLS. |
| `obras:verificar-aislamiento` | **Bloquea el despliegue** si el aislamiento no esta puesto. Ver [03](03-MULTITENANCY-Y-ROLES.md), seccion 8. |
| `obras:revisar-vencimientos` | Revisa tareas vencidas y por vencer, **empresa por empresa**. |

`obras:revisar-vencimientos` es un buen ejemplo de por que el contexto tiene
alcance de transacción: recorre todas las empresas con la misma conexión, y sin
`ejecutarAislado()` la primera empresa contaminaria a todas las siguientes.