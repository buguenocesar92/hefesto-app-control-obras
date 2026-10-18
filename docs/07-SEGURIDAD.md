# 07 - Seguridad

Que protege este sistema, como, y que **no** protege. La última parte es la más
importante.

---

## 1. Que hay que proteger y por que

Una obra maneja tres clases de dato sensible:

| Dato | De quien | Que pasa si se filtra |
|---|---|---|
| **Accidentes y casi accidentes** | Trabajadores de la obra | Dano directo a una persona: un accidente con lesión y nombre es información que el trabajador tiene derecho a que no se difunda. |
| **Datos de las personas** | Maestros, inspectores, clientes | Datos personales bajo la **Ley 19.628**. |
| **Estado de cuenta** | La constructora y el mandante | Información comercial de la relacion entre las partes. |

El caso más grave es el primero. Por eso el modulo de seguridad tiene más
restricciones que los demas.

## 2. Ley 19.628: como se aplica

La ley chilena de protección de la vida privada obliga a tratar los datos
personales **con finalidad**, sin exceso y con seguridad. Tres medidas concretas
en este sistema:

### 2.1 Finalidad: se pide lo que se usa y nada más

| Dato | Para que se usa | Por que es necesario |
|---|---|---|
| `usuarios.nombre` | Mostrar quien responde por una tarea | Sin nombre, una tarea no tiene dueño visible. |
| `usuarios.correo` | Iniciar sesión | Es la identidad en el proveedor. |
| `usuarios.rut` | Documentos de obra | **Opcional.** Se llena solo si la obra lo usa. |
| `usuarios.telefono` | Contacto urgente en obra | **Opcional.** |
| `registros_seguridad.persona_afectada_*` | Registro del accidente | Lo exige la normativa sectorial. |

**Lo que NO se pide**, aunque se podría agregar sin esfuerzo: foto, documento de
identidad, dirección particular, fecha de nacimiento, información de salud,
antecedentes.

Un accidente se describe por **la lesión en texto y las horas perdidas**, no por
el historial clinico de la persona. Guardar más sería recolectar dato sensible
sin uso.

### 2.2 Minimizacion en la sesión

`Usuario` tiene `rut` y `telefono` en `$hidden`. **No viajan a la interfaz.**

La sesión es lo único que llega al navegador, y quien opera una obra no necesita
el RUT de un compañero para ver una tarea. El dato existe en la base para lo que
la normativa exige y sale solo cuando corresponde.

### 2.3 Supresión: el campo `anonizado`

Los accidentes **no se borran**. Se anonimizan.

`RegistroSeguridad::anonizar()` deja el hecho, la lesión en texto, las horas
perdidas y las medidas correctivas, y quita nombre y RUT.

Por que no se borra el registro entero: **el hecho es lo que sirve para que no
se repita**. Borrarlo sería perder el dato más importante a cambio de una
formalidad.

El campo `anonizado` es un interruptor, no una convencion:

- si es `true`, `StoreRegistroSeguridadRequest` **rechaza** la petición si viene
  nombre o RUT. Un registro que dice estar anonimizado y tiene el dato es peor
  que uno sin la marca;
- `RegistroSeguridadResource` devuelve `persona_afectada: null` cuando esta
  anonimizado, para que la interfaz muestre "sin dato personal" y no un campo
  vacio que parece un error de carga.

**Cualquier persona de la empresa puede pedir la anonimizacion**
(`RegistroSeguridadPolicy::update()` devuelve `true`). La ley obliga a atender la
solicitud del titular del dato, y no tiene sentido que eso dependa de que un
cargo tenga permiso para escribir en la obra.

## 3. Lo que protege el sistema

### 3.1 Aislamiento entre empresas

Row Level Security de PostgreSQL, con `WITH CHECK` y `FORCE ROW LEVEL
SECURITY`. Detallado en [03](03-MULTITENANCY-Y-ROLES.md).

El punto que hay que repetir: con el rol de base equivocado, el aislamiento se
apaga **sin error**. Por eso `obras:verificar-aislamiento` es un paso
obligatorio del despliegue.

### 3.2 Autorización

Policies de Laravel, registradas **una por una** en `AppServiceProvider`. En este
esqueleto no se descubren solas, y esa obligacion es una ventaja: agregar un
modelo nuevo obliga a pensar ahí mismo en quien puede verlo.

Cuando una Policy rechaza, la respuesta es **404 y no 403**: un 403 confirmaria
que el recurso existe.

### 3.3 CSRF

Todas las rutas cuelgan del grupo `web` de Laravel, que trae el middleware de
CSRF. El token viaja en la cookie `XSRF-TOKEN` y se reenvia en la cabecera
`X-XSRF-TOKEN`; el proxy de Nuxt lo reenvia automáticamente.

### 3.4 Validación en la capa de petición

Toda entrada pasa por un `FormRequest`. Las reglas de negocio que se validan ahí
y no en la base:

| Regla | Donde |
|---|---|
| Un accidente exige lesionado y horas perdidas. | `StoreRegistroSeguridadRequest` |
| Un casi accidente NO puede tener lesionado ni horas perdidas. | `StoreRegistroSeguridadRequest` |
| Un registro anonimizado no puede traer nombre ni RUT. | `StoreRegistroSeguridadRequest` |
| Una inspección con hallazgos no puede declararse conforme. | `StoreInspeccionRequest` |
| La fecha de inspección no puede ser futura. | `StoreInspeccionRequest` |
| Una corrección de inspección no puede quedar sin plazo ni responsable. | `StoreTareaRequest` |
| `monto_neto` no se acepta: lo calcula el controlador. | `AvancePagoController` |
| Un pago marcado `pagado` necesita fecha de cobro. | `StoreAvancePagoRequest` |
| No se puede cobrar más que el monto bruto. | `StoreAvancePagoRequest` |
| Una etapa no puede marcarse terminada sin fecha planificada. | `StoreEtapaRequest` |

Los mensajes están escritos para quien esta en la obra, no para quien desarrollo
el sistema.

### 3.5 Autenticación

- **No hay contrasenas en esta aplicación.** La API nunca ve la contrasena del
  usuario: manda a Authentik y recibe un código.
- **El navegador nunca habla con la API.** Todo pasa por el proxy de Nuxt, del
  mismo origen, con la cookie de sesión.
- **El token de identidad se verifica con firma**, contra las claves públicas del
  proveedor, y se comprueban **emisor y audiencia**.
- **`state` y `nonce`** en el flujo OIDC, para impedir el ataque de fijar la
  sesión de otra victima.
- **La sesión se regenera** después del inicio de sesión, para que un
  identificador de sesión previo no siga valiendo.

### 3.6 No se pública la base

En producción, PostgreSQL y Redis **no tienen puertos publicados**. Para entrar
hay que usar un túnel SSH:

```bash
ssh -L 5432:localhost:5432 usuario@servidor
```

### 3.7 Registro de accesos

`AuditarAcceso` deja en el log quien consultó los registros de seguridad, las
inspecciones, los pagos y el estado de cuenta.

La RLS protege el dato, pero no deja rastro de quien pregunto por que. En una
obra con datos de trabajadores, "quien entro a mirar el registro de accidentes
del mes pasado" es una pregunta que hay que poder responder después.

## 4. Lo que el sistema NO protege

Con todas las letras:

| No esta | Por que importa |
|---|---|
| **Revisión de seguridad externa** | Nunca se hizo. Todo lo de esta lista son controles propios, no hallazgos de un tercero. |
| **Pruebas de penetracion** | No hay ni una. |
| **Las pruebas de aislamiento ejecutadas** | **Están escritas pero NO se corrieron.** La maquina donde se escribieron no tiene PostgreSQL. Ver [10](10-PRUEBAS-Y-ESTADO.md). |
| **MFA en la interfaz** | Depende de que Authentik lo fuerce. El sistema no lo exige por si mismo. |
| **Cifrado en reposo de la base** | Depende del disco y del volumen. No esta configurado aquí. |
| **Bitacora inalterable** | Un administrador puede editar una fila de la bitacora. Queda rastro en el log, pero **no hay firma criptografica** que lo vuelva inalterable. |
| **Alertas ante accesos raros** | El log existe, pero no hay nadie mirando ni alertas configuradas. |
| **Borrado automático de datos antiguos** | No hay retencion. Los registros se acumulan para siempre mientras la obra exista. |

## 5. Que falta antes de poner esto en producción con datos reales

En este orden:

1. **Correr las pruebas.** Ninguna se ha ejecutado. Empezando por
   `AislamientoDeDatosTest`.
2. **Verificar el aislamiento en el servidor real**, con el rol de base
   definitivo.
3. **Revisar el dominio y el certificado** del Caddy.
4. **Confirmar con el cliente que datos personales se guardan**, y dejarlos por
   escrito. La ley obliga a que la finalidad este declarada.
5. **Definir quien puede pedir la anonimizacion** y como se le avisa. Hoy puede
   cualquiera de la empresa, que es lo correcto por ley, pero hay que que
   alguien lo atienda.
6. **Contratar una revisión de seguridad externa.** Los controles propios de
   este documento son razonables, pero no son el mismo cosa que una revisión
   hecha por alguien de afuera.