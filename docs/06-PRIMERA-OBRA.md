# 06 - Primera obra

Como se carga una obra desde cero, en el orden en que conviene. El orden no es
estetico: cada paso deja un dato que el siguiente necesita.

---

## 1. Antes de cargar nada

Tres definiciones que hay que tener claras. Si se saltan, se termina
renombrando columnas después.

**1.1 Que es una etapa y cual es su peso.**
Una etapa es una parte de la obra con un peso que indica que tanto por ciento
representa. La suma de los pesos de una obra da **100**. Si los pesos no cuadran,
el avance fisico ponderado deja de significar una parte de la obra.

**1.2 Que unidad monetaria usa el contrato.**
El sistema **no asume pesos ni UF**: guarda números y no sabe que unidad es.
Cada obra usa la de su contrato, y eso se escribe en el acuerdo. Una obra en UF
y otra en dolares pueden convivir sin problema.

**1.3 Quien es el responsable de cada etapa.**
No es obligatorio, pero una etapa sin responsable es una etapa sin dueño, que es
la situacion que el modulo quiere evitar. Mejor dejarlo por omision en la carga
inicial y asignarlo en la reunion.

## 2. Cargar la empresa

Antes de la obra, la empresa. Si ya existe, se omite.

```bash
docker compose -f deploy/docker-compose.dev.yml exec api php artisan obras:crear-empresa \
  --razon="Constructora Los Robles Ltda." \
  --rut=76123456 \
  --slug=los-robles \
  --admin-nombre="Patricia Nunez Rojas" \
  --admin-correo="p.nunez@los-robles.cl"
```

En el servidor de producción, este paso lo hace el operador **a mano**. No se usa
la semilla: la semilla crea usuarios con correos de ejemplo.

## 3. Dar de alta a las personas

Las personas son de la **empresa**, no de la obra. Se dan de alta una vez y
sirven para todas las obras.

Roles que conviene tener de entrada:

| Rol | Para que sirve |
|---|---|
| `administrador` | Da de alta personas y tipos de inspección. |
| `jefe_obra` | Responde por el avance de una obra. |
| `maestro` | Ejecuta etapas y deja las fechas reales. |
| `inspector` | Inspecciona y levanta hallazgos. |
| `cliente` | Ve su obra y el estado de cuenta, no edita. |

Desde la interfaz: **Administración -> Responsables**.

El correo es único **dentro de la empresa**, no global: dos constructoras pueden
tener a alguien con el mismo correo sin problema.

## 4. Definir los tipos de inspección

También son de la empresa. Si la constructora ya trabaja con nombres propios para
sus inspecciones, se usan esos.

Un tipo tipico:

| Nombre | Descripción |
|---|---|
| Inspección de fundaciones | Revisión de fecha, nivel y armadura antes de iniciar la cadena. |
| Control de hormigón | Control de armadura, resistencia y curado en cada tanda. |
| Recepción de obra gruesa | Verificación de humedad, plomos y terminaciones. |

Desactivar un tipo no borra las inspecciones ya realizadas con el: quedan
apuntando a el. Por eso el catalogo se desactiva y no se elimina.

## 5. Crear la obra

Campos que importan:

| Campo | Por que importa |
|---|---|
| `codigo` | Como lo numera la empresa. Único dentro de ella. |
| `nombre`, `direccion` | |
| `cliente_nombre`, `cliente_rut` | El RUT es dato personal del mandante: ver [07](07-SEGURIDAD.md). |
| `fecha_inicio` | |
| `monto_contratado` | **Sin esto no hay avance financiero.** Si falta, el porcentaje se muestra "sin dato", no 0%. |

Sobre `monto_contratado`: que el sistema **no lo adivine** y que muestre "sin
dato" en vez de 0% es deliberado. Un 0% cobrado sobre un contrato de cero es un
0% que no significa nada, y mostrarlo sería dar un dato falso con apariencia de
dato.

## 6. Cargar las etapas

**En orden, con los pesos pensados.** La suma tiene que dar 100.

Ejemplo para una ampliacion:

| Orden | Etapa | Peso | Planificada |
|---|---|---|---|
| 1 | Excavacion y fundaciones | 30 | 2026-06-20 |
| 2 | Estructura de hormigón armado | 35 | 2026-08-15 |
| 3 | Muro de contencion y cubierta | 20 | 2026-10-15 |
| 4 | Instalación electrica interior | 15 | 2026-10-05 |

Al guardar una etapa, si los pesos no cuadran a 100, el sistema **no bloquea**:
avisa por log. En una obra recien cargada el plan va a estar incompleto a
propósito, y obligar a que cuadre en cada etapa dejaria imposible cargar de a
poco.

## 7. Dejar la fecha real a medida que avanza

Esta es la parte que sostiene el modulo. **La fecha real se escribe cuando la
etapa termina, no al empezar.**

El atraso es la diferencia entre las dos fechas, y se calcula cada vez que se
lee. Si el atraso fuera un número guardado, habría que recalcular todas las
etapas cada vez que se reprograma el plan, y la que se olvidara quedaria
mostrando un atraso que ya no existe.

Una etapa sin fecha real que ya paso su fecha planificada aparece como **atrasada
en curso**. Es distinto de una etapa terminada tarde, y la interfaz los muestra
distinto.

## 8. Registrar la primera inspección

Inspección conforme también se registra. Es la prueba de que la revisión se
hizo, con fecha y con inspector.

Con hallazgos: cada hallazgo **nace con su tarea de corrección y su fecha de
plazo**, en la misma transacción. El plazo sale de la gravedad:

| Gravedad | Días habiles |
|---|---|
| Critica | 1 |
| Alta | 3 |
| Media | 7 |
| Baja | 15 |

Se cuentan días habiles: un hallazgo del viernes con plazo de 3 días vence el
miércoles.

**Los días festivos de Chile no están en el sistema.** Si la obra tiene días
feriados, hay que ajustar el plazo al cargar el hallazgo. Es una limitacion
conocida, no un error.

## 9. Registrar la reunion de obra en la bitacora

Cada acuerdo va con fecha y autor. El autor sale de la sesión, no se puede
elegir.

Ejemplo de lo que hay que escribir:

> **Asunto:** Revisión de fundación antes de la cadena
> **Cuerpo:** Se acordo revisar la fundación antes de iniciar la cadena. El
> inspector deja acta ese día.
> **Autor:** Patricia Nunez Rojas

Un acuerdo sin autor es una opinion, y tres meses después no sirve para nada.

Si el acuerdo genera trabajo, se crea además una tarea con plazo y se enlaza con
`tarea_relacionada_id`.

## 10. Emitir el primer avance de pago

El sistema calcula `monto_neto = monto_bruto - deducciones`. **No lo acepta desde
el navegador**: si lo mandara el cliente, un estado de cuenta podría traer un
neto que no cuadra con lo facturado, y eso se descubre cuando ya no se puede
corregir.

| Campo | Nota |
|---|---|
| `avance_fisico_pct` | **Opcional.** Es la foto del avance fisico al emitir. Si no se manda, el sistema toma el de hoy. No es el fisico de la obra: queda congelado para poder auditar después. |
| `estado` | `emitido`, `aprobado`, `pagado`, `rechazado`. |
| `monto_cobrado`, `fecha_cobro` | Si el estado es `pagado`, la fecha es **obligatoria**. |

**`aprobado` no es `pagado`.** Que el mandante apruebe un estado de cuenta no
significa que el dinero haya entrado. El avance financiero cuenta solo lo
cobrado, y el aprobado se reporta aparte como `aprobado_no_cobrado`.

## 11. Revisar el estado de cuenta

`GET /api/v1/obras/{id}/estado-de-cuenta` devuelve los dos avances por
separado:

```json
{
    "avance_fisico":     { "porcentaje": 73.0, "fuente": "etapas", ... },
    "avance_financiero": { "porcentaje": 45.0, "fuente": "avances_pago", ... },
    "diferencia_puntos_porcentuales": 28.0,
    "pendientes": { "tareas_vencidas": 1, "tareas_sin_vencer": 2, "hallazgos_pendientes": 2 }
}
```

73% fisico contra 45% cobrado: la obra va por delante de la plata. **Esa
diferencia es información, no un error que corregir.** Si el fisico se va muy por
delante, la obra esta trabajando con plata que todavia no llego, y eso se tiene
que mirar en la reunion.

## 12. Lo que queda hecho a la mano

Cosas que el sistema **no** hace, y hay que tener presentes:

- **No lleva el libro de obra.** Es un registro de gestión al lado.
- **No genera certificados ni documentos firmados.**
- **No manda recordatorios.** Los avisos de vencimiento quedan en el log; el
  correo no esta implementado.
- **No sabe los feriados de Chile.** El plazo se ajusta a mano.
- **No calcula el atraso en días habiles del calendario de la obra.** Compara
  fechas crudas, y el atraso en días habiles habría que calcularlo aparte.