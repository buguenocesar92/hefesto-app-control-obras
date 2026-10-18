# 10 - Pruebas y estado real

Este documento separa **lo que se ejecuto** de **lo que no se ejecuto**. Con
muchas letras, porque la diferencia entre las dos cosas es justo lo que hace que
este sistema sirva.

---

## 1. Estado, en una linea

> **CÓDIGO ESCRITO, SIN EJECUTAR.**
>
> En la maquina donde se escribio este proyecto **no hay Docker, ni PHP, ni
> Composer**. Se comprobo antes de escribir una sola linea:
>
> ```
> php      : NO ENCONTRADO
> composer : NO ENCONTRADO
> docker   : NO ENCONTRADO
> ```
>
> **Ninguna migración se aplicó. Ninguna prueba se corrio. Ninguna imagen se
> construyo. El sistema nunca ha arrancado.**

Lo que si se hizo, en la maquina donde se escribio:

| Si se hizo | Como |
|---|---|
| Revisar la estructura de archivos | Validador de aplicaciones de Hefesto (`tools/validar_apps.py`). |
| Revisar la sintaxis YAML de los Compose | El mismo validador, con PyYAML. |
| Revisar la sintaxis de los scripts de shell | El mismo validador, con `bash -n` de Git para Windows. |
| Buscar caracteres CJK y cirilicos | Barrido automático sobre todos los archivos. |
| Buscar secretos reales | Barrido automático. |
| Revisar a mano el aislamiento | Lectura del código contra el patron documentado. |

## 2. Lo que NO se hizo, y no se presenta como hecho

| No se hizo | Que implica |
|---|---|
| **Correr `php artisan migrate`** | No se sabe si las 14 migraciones se aplican. En particular, la dependencia circular entre `hallazgos` y `tareas` esta resuelta en dos pasos **por diseno**, pero no esta verificada contra PostgreSQL. |
| **Correr las pruebas** | Las 25 pruebas de este documento **no se han ejecutado nunca**. |
| **`obras:verificar-aislamiento`** | No se ha corrido contra ninguna base. No sabemos si el SQL de la migración 000014 es valido tal como esta escrito. |
| **Correr la semilla** | No se ha probado que las dos empresas, las dos obras y todos sus datos se carguen. |
| **Construir las imagenes** | Los `Dockerfile` no se construyeron. Las rutas de `COPY` no se verificaron. |
| **Levantar Authentik** | El flujo OIDC esta escrito contra el estandar, pero no se probo contra un Authentik real. |
| **Compilar el frontend** | Nuxt no se instalo ni se compilo. Los `.vue` y el proxy no se probaron. |
| **Conectar la interfaz con la API** | Los nombres de los endpoints se verificaron uno por uno contra `routes/web.php`, pero no se probaron en ejecución. |
| **Revisión de seguridad** | No se hizo, ni interna ni externa. |

## 3. Las pruebas escritas

Están en `api/tests/`. **Ninguna se ha ejecutado.** Se describen por lo que
comprueban, no por lo que devolvieron.

### 3.1 Aislamiento entre empresas
`tests/Feature/Aislamiento/AislamientoDeDatosTest.php` (8 pruebas)

| Prueba | Que comprueba |
|---|---|
| `test_sin_contexto_de_empresa_la_consulta_falla_en_vez_de_traer_datos` | Sin empresa en la conexión, la consulta **lanza excepción**. No devuelve cero filas: el aislamiento falla **cerrado**. |
| `test_cada_constructora_solo_ve_sus_propias_obras` | Con el contexto de cada una, cada empresa ve **solo** las suyas. |
| `test_la_obra_de_otra_constructora_no_se_puede_leer_ni_escribir` | Una obra ajena no aparece, y además no se puede modificar: la política tiene `WITH CHECK`. |
| `test_no_se_puede_insertar_una_fila_con_la_empresa_de_otra` | No se puede crear una fila en nombre de otra empresa. |
| `test_el_contexto_de_empresa_no_sobrevive_a_la_transaccion` | **La prueba más importante para el worker de colas**: al terminar la transacción, la variable de contexto desaparece. |
| `test_el_contexto_de_empresa_se_descarta_tambien_al_revertir` | Un rollback también la descarta. |
| `test_la_funcion_de_contexto_falla_cerrada_sin_empresa` | `app_tenant_id()` lanza excepción sin contexto. |
| `test_las_tareas_de_otra_constructora_tampoco_se_ven` | El aislamiento no se limita a `obras`. |

**Por que PostgreSQL y no SQLite:** el aislamiento esta implementado con Row
Level Security, que es una función de PostgreSQL. Contra SQLite estas pruebas
**pasarian sin comprobar nada**, y ese es el peor resultado posible: pruebas
verdes que no dicen nada. `phpunit.xml` fija `DB_CONNECTION=pgsql` justamente
para que no se puedan correr por error.

### 3.2 El hallazgo genera su tarea con plazo
`tests/Feature/Inspecciones/HallazgoGeneraTareaTest.php` (8 pruebas)

| Prueba | Que comprueba |
|---|---|
| `test_un_hallazgo_genera_una_tarea_de_correccion_con_plazo_y_responsable` | La tarea existe, con `fecha_plazo` y `responsable_id`. |
| `test_el_vinculo_esta_escrito_en_los_dos_sentidos` | El hallazgo apunta a la tarea y la tarea al hallazgo y a la inspección. |
| `test_el_plazo_depende_de_la_gravedad` | Un hallazgo grave tiene menos plazo que uno leve. |
| `test_el_plazo_se_cuenta_en_dias_habiles` | Un plazo de 1 día habil desde un viernes vence el lunes. |
| `test_generar_dos_veces_no_duplica_la_tarea` | La generación es idempotente: un hallazgo, una tarea. |
| `test_no_se_puede_generar_una_correccion_sin_plazo` | Un plazo de 0 días se rechaza. |
| `test_el_hallazgo_se_cierra_cuando_su_tarea_se_completa` | El hallazgo se cierra por el camino largo, completando la tarea. |
| `test_un_hallazgo_de_otra_constructora_no_genera_tarea_aqui` | El vinculo respeta el aislamiento. |

### 3.3 Avance fisico y financiero por separado
`tests/Feature/Avances/AvanceFisicoYFinancieroTest.php` (8 pruebas)

| Prueba | Que comprueba |
|---|---|
| `test_el_avance_fisico_se_pondera_por_el_peso_de_cada_etapa` | El fisico es ponderado: pesos 40/30/30 con avances 50/100/0 dan 50%. |
| `test_el_avance_financiero_cuenta_solo_lo_pagado` | 1080 cobrados de 2400 son 45%. **El pago aprobado de 540 no cuenta.** |
| `test_una_obra_puede_tener_73_fisico_y_45_cobrado_y_son_dos_cifras` | El caso exacto del modulo, y que **no se promedian**. |
| `test_el_estado_de_cuenta_reporta_los_dos_avances_por_separado_con_su_fuente` | Dos bloques, cada uno con su `fuente`, y `diferencia` como resta. Comprueba además que **no exista** un campo `avance` único. |
| `test_sin_monto_contratado_el_avance_financiero_es_null_y_no_cero` | Sin contrato, `null` y no 0%. |
| `test_sin_etapas_con_peso_el_avance_fisico_es_null_y_no_cero` | Sin plan cargado, `null` y no 0%. |
| `test_el_calculo_del_avance_fisico_no_usa_los_pagos` | Cobrar el 100% no construye nada. |
| `test_el_calculo_del_avance_financiero_no_usa_las_etapas` | Terminar la obra no es cobrarla. |

Las dos ultimas son las que impiden que alguien "simplifique" el código metiendo
el avance del otro. Son pruebas de **arquitectura**, no de resultado.

### 3.4 Seguridad: accidente y casi accidente
`tests/Feature/Seguridad/RegistroDeSeguridadTest.php` (7 pruebas)

| Prueba | Que comprueba |
|---|---|
| `test_un_casi_accidente_no_puede_tener_lesionado_ni_horas_perdidas` | Las dos reglas del casi accidente. |
| `test_un_accidente_exige_lesionado_y_horas_perdidas` | Las dos reglas del accidente. |
| `test_un_casi_accidente_valido_se_registra_sin_lesion` | El camino normal funciona. |
| `test_accidentes_y_casi_accidentes_se_cuentan_por_separado` | El resumen no los suma. |
| `test_un_registro_anonimizado_no_puede_traer_nombre_ni_rut` | Ley 19.628: la marca no es una convencion. |
| `test_anonizar_quita_el_dato_personal_y_conserva_el_hecho` | Anonimizar borra el dato y **conserva** el accidente. |
| `test_el_cliente_no_registra_accidentes` | El cliente no puede escribir en la historia de seguridad de la obra. |

### 3.5 El atraso de una etapa
`tests/Unit/DesviacionDeEtapaTest.php` (6 pruebas)

| Prueba | Que comprueba |
|---|---|
| `test_una_terminada_despues_es_un_atraso` | Positivo es atraso. |
| `test_una_terminada_antes_es_un_adelanto` | Negativo es adelanto, y no cuenta como atraso. |
| `test_una_terminada_en_fecha_no_tiene_desviacion` | Cero. |
| `test_sin_fecha_real_el_atraso_se_mide_contra_hoy` | El atraso en curso se mide contra la fecha de hoy. |
| `test_sin_fecha_planificada_no_hay_desviacion` | `null`, que es distinto de cero. |
| `test_el_aporte_al_avance_pondera_por_el_peso_de_la_etapa` | Peso 20 al 50% aporta 10 puntos. |

## 4. Lo que las pruebas NO cubren

Con las mismas letras:

| No cubierto | Por que importa |
|---|---|
| **El flujo de OIDC contra un Authentik real** | Es lo más probable que falle en el primer arranque: la URL de redireccion, el emisor y los alcances tienen que coincidir exactamente. |
| **La compilacion del frontend** | Ni una prueba toca Nuxt. Un error de tipografia en un `.vue` no lo detecta nada de lo que hay aquí. |
| **Carga y rendimiento** | Que las políticas RLS con muchas filas no hagan la aplicación inutilizable. Sin medir. |
| **Accesibilidad** | Sin auditoria. |
| **Comportamiento con red lenta** | En obra se usa una conexión mala y **no se ha medido** ninguna pantalla en esa condición. |
| **Concurrencia** | Dos maestros guardando la fecha real de la misma etapa al mismo tiempo. |
| **Restauración desde respaldo** | Ver [08](08-RESPALDOS.md): el ciclo esta documentado pero **nunca se ejecuto**. |

## 5. Como correrlas

```bash
# 1. Crear la base de pruebas
docker compose -f deploy/docker-compose.dev.yml exec postgres \
  psql -U control_obras -d postgres -c "CREATE DATABASE control_obras_test;"

# 2. Aislamiento (lo mas importante)
docker compose -f deploy/docker-compose.dev.yml exec api \
  php artisan test --testsuite=Aislamiento

# 3. Reglas de negocio
docker compose -f deploy/docker-compose.dev.yml exec api \
  php artisan test --testsuite=Reglas

# 4. Todas
docker compose -f deploy/docker-compose.dev.yml exec api php artisan test
```

### Que resultado significa que esta bien

| Si ves | Significa |
|---|---|
| `Aislamiento` en verde | Una constructora no puede leer las obras de la otra, y el contexto no se arrastra. **Es lo más importante.** |
| `Reglas` en verde | Los hallazgos generan tareas con plazo, y los dos avances se reportan por separado. |
| `obras:verificar-aislamiento` en verde | El rol de la base no salta las políticas y todas las tablas tienen política. |
| Cualquier error de migración | **No sigas.** Revisa la migración antes de seguir; un esquema a medias es peor que no arrancar. |

## 6. Errores que es probable encontrar al primer arranque

Se anotan para que el que pruebe sepa donde mirar. **Son suposiciones, no
errores observados.**

| Probable error | Donde mirar |
|---|---|
| `Relation "tareas" does not exist` al correr migraciones | El orden de migraciones: `tareas` (000006) tiene que ir antes que `hallazgos` (000008). |
| Violacion de llave foranea en `hallazgos.tarea_correccion_id` | La llave circular: `tareas` crea primero su columna sin llave, y la llave se agrega al final de la migración de `hallazgos`. |
| `app_tenant_id()` no existe | La migración 000014 no se aplicó, o se aplicó en otra base. |
| La semilla no escribe nada | `FORCE ROW LEVEL SECURITY` hace que el dueno tampoco se salve la política. Hay que pasar por `ContextoTenant::ejecutarAislado()`, que es lo que hace. |
| `Obra::factory()` sin empresa | Las pruebas fijan la empresa con `fijarEmpresa()`. Sin eso, la RLS rechaza el `INSERT`. |
| 404 en todo | El middleware `ResolverTenant` no esta corriendo, o la sesión no tiene `tenant_id`. |

## 7. Como actualizar este documento

Cuando se corran las pruebas por primera vez, este documento hay que cambiar el
titulo de la seccion 1 y borrar la tabla de la seccion 2. **Un documento de
estado que dice "probado" cuando no se probo es peor que no tener documento.**

Última actualización: 17 de octubre de 2026. Estado: código escrito, sin ejecutar.

---

## Los dos roles de PostgreSQL: escritos, no ejecutados

La separación entre el rol de arranque (`control_obras_admin`) y el rol de la
aplicación (`control_obras`) está escrita en el `compose`, en
`deploy/postgres/inicializar-rol.sh` y en
[03-MULTITENANCY-Y-ROLES.md](03-MULTITENANCY-Y-ROLES.md). **No se ha ejecutado
ninguna vez.**

En el equipo donde se escribió este cambio no había PostgreSQL ni Docker. Queda
pendiente:

1. Levantar el `compose` con el volumen de PostgreSQL vacío y confirmar que el
   rol `control_obras` se crea sin error.
2. Confirmar con `SELECT rolname, rolsuper, rolbypassrls, rolcreaterole FROM
   pg_roles` que el rol de la conexión no es superusuario, no salta la RLS y no
   puede crear roles.
3. Correr `php artisan obras:verificar-aislamiento` y confirmar que sale con
   código 0.
4. Probar el escenario que motiva todo el cambio: una constructora intentando
   leer datos de otra.

Lo único verificado es estático: `bash -n` pasa sobre el script y el `compose`
es YAML válido, ambas cosas comprobadas por `tools/validar_apps.py`. El
razonamiento se apoya en la documentación oficial de PostgreSQL 18, no en una
ejecución.