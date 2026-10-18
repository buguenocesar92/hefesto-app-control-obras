# 01 - Arquitectura

> PostgreSQL corre con dos roles: uno de arranque y otro de aplicación. Solo el
> de aplicación conecta con el sistema, y es el que las políticas de
> aislamiento pueden alcanzar. Ver [03](03-MULTITENANCY-Y-ROLES.md).

Como esta armado Control de Obras, y por que esta armado así.

---

## 1. La idea del producto, en una linea

Que la decisión quede registrada, con fecha y responsable.

Todo lo demas del sistema se deriva de eso. Las tareas con plazo existen para
que un acuerdo de reunion no se pierda. La bitacora existe para que "se
acordo" tenga autor. La inspección conforme también se registra para que se
pueda demostrar que la revisión se hizo.

## 2. Las tres capas

```
                    Navegador
                        |
                  (mismo origen)
                        |
            +---------------+----------------+
            |                                |
      Nuxt (Node, SSR)                Solo lo ve Nuxt
            |                          el navegador
            |  cookie de sesion + CSRF
            v
      API Laravel
            |
            |  app.tenant_id (alcance de transaccion)
            v
      PostgreSQL con RLS
            ^
            |
      Redis (cache y colas)
```

**El navegador nunca habla con la API.** La interfaz de Nuxt corre en Node y es
Node quien reenvia las peticiones a la API reenviando la cookie de sesión. Eso
tiene una consecuencia concreta: los datos de una obra no viajan en el HTML
que se envia al navegador, salvo los que la pagina decide mostrar.

Es una diferencia que no es solo de estilo. Con una protección hecha solo en el
cliente, los datos habrian viajado igual en la respuesta y después se habrian
escondido con JavaScript.

## 3. Por que el tenant es la constructora y no la obra

La unidad de aislamiento es la **empresa constructora**, no la obra.

La razon es de responsabilidad: la empresa es quien responde por el maestro, por
el inspector y por el estado de cuenta. Si el aislamiento fuera por obra, cada
obra sería un silo y no habría forma de que el mismo maestro apareciera en dos
obras de la misma empresa, ni de que un administrador de la empresa viera todo
su negocio.

## 4. Los modulos y donde viven

| Modulo | Tabla principal | Servicio | Prueba que lo sostiene |
|---|---|---|---|
| Obras | `obras` | - | `AislamientoDeDatosTest` |
| Etapas y atraso | `etapas` | `Etapa::desviacionDias()` | `DesviacionDeEtapaTest` |
| Tareas con plazo | `tareas` | `Tarea` | `HallazgoGeneraTareaTest` |
| Inspecciones | `inspecciones` | - | `HallazgoGeneraTareaTest` |
| Hallazgos y corrección | `hallazgos` | `GeneradorTareasCorreccion` | `HallazgoGeneraTareaTest` |
| Seguridad | `registros_seguridad` | - | `RegistroDeSeguridadTest` |
| Avances fisicos | `etapas` | `CalculadorAvanceFisico` | `AvanceFisicoYFinancieroTest` |
| Avances financieros | `avances_pago` | `CalculadorAvanceFinanciero` | `AvanceFisicoYFinancieroTest` |
| Estado de cuenta | las dos anteriores | `EstadoDeCuentaObra` | `AvanceFisicoYFinancieroTest` |
| Bitacora | `bitacora_obra` | - | - |

## 5. Las tres decisiones de diseno que sostienen el producto

### 5.1 El atraso se CALCULA, no se guarda

`etapas` tiene `fecha_planificada` y `fecha_real`. El atraso es la diferencia, y
se calcula cada vez que se lee.

Si el atraso fuera una columna, el día que se reprograma el plan habría que
recalcular todas las etapas, y la que se olvidara quedaria mostrando un atraso
que ya no existe. Calculandolo siempre desde las dos fechas, no hay nada que se
pueda quedar desactualizado.

### 5.2 El hallazgo genera su tarea DENTRO de la misma transacción

`GeneradorTareasCorreccion` corre dentro de `ContextoTenant::ejecutarAislado()`,
y el controlador guarda inspección, hallazgos y tareas generadas en un solo
bloque transaccional.

Si la inspección quedara guardada y la tarea fallara, la obra tendria un
hallazgo sin nadie responsable de corregirlo, que es exactamente el escenario
que el modulo vino a eliminar.

### 5.3 Los dos avances viven en clases que no se conocen

`CalculadorAvanceFisico` lee etapas. `CalculadorAvanceFinanciero` lee pagos.
Ninguno llama al otro, y ninguno guarda su resultado en la obra.

`EstadoDeCuentaObra` los junta para mostrarlos juntos y calcula la **resta**.
No calcula el promedio, y en `AvanceFisicoYFinancieroTest` hay una prueba
explicita de que el promedio no aparece en ninguna parte de la respuesta.

## 6. La tecnologia, y por que

| Pieza | Versión fijada | Motivo |
|---|---|---|
| Laravel | 13.x | Ecosistema de Policies y FormRequest, que es donde vive la autorización. |
| PostgreSQL | 18.6 | RLS. Es el motivo de la eleccion: el aislamiento se implementa en la base. |
| Nuxt | 3.x | SSR, para que el HTML llegue renderizado en conexiones lentas. |
| Redis | 8.x | Cache y colas. El worker necesita contexto de empresa por trabajo. |
| Authentik | 2025.10 | Identidad compartida entre aplicaciones de Hefesto. |
| Caddy | 2.11 | TLS automático. Un solo puerto abierto. |

## 7. Lo que NO esta hecho

Se deja escrito para que no se de por hecho:

- **Sin revisión de seguridad externa.** Ver docs/07.
- **Sin pruebas de penetracion.**
- **Sin auditoria de accesibilidad.** La interfaz es utilitaria y no ha sido
  revisada por un especialista.
- **Sin integración con el libro de obra.** El sistema es un registro de
  gestión al lado del libro, no su reemplazo.
- **Sin notificaciones por correo ni por teléfono.** Los avisos de vencimiento
  solo quedan en el log. Habría que decidir el canal antes de prometerlos.
- **Sin firma criptografica** de los acuerdos de la bitacora: se puede editar
  una fila con permiso de administrador, y queda rastro en el log, pero no hay
  firma que lo vuelva inalterable.

## 8. Deuda técnica consciente

- **nginx y PHP-FPM en un solo contenedor**, con supervisor. No es lo ideal;
  esta anotado en docs/08 y se acepta para no sumar servicios a un despliegue
  donde solo entra el proxy.
- **Los tipos de la interfaz están escritos a mano** en `web/app/tipos/api.ts`,
  no generados desde los Resources. Con seis pantallas, el costo de generarlos
  es mayor que el de mantenerlos.
- **Los plazos en días habiles no consideran feriados.** No hay tabla de
  feriados de Chile en el sistema: el plazo se ajusta al cargar el hallazgo.