# 08 - Respaldos

Como respaldar y como restaurar. Un respaldo que nunca se restaura no es un
respaldo: es la intencion de respaldar.

---

## 1. Que hay que respaldar

| Que | Donde | Si se pierde |
|---|---|---|
| **Base de datos** | Volumen `postgres-datos` | Todo: obras, inspecciones, accidentes, pagos. **No se puede reconstruir.** |
| **Archivos de la interfaz** | Volumen de Nuxt | Se reconstruyen con `npm run build`. No critico. |
| **Certificados de Caddy** | Volumen `caddy-datos` | Se vuelven a emitir. Molesto, no grave. |
| **Archivo `.env.prod`** | En el servidor | **Critico y es el más facil de olvidar.** Sin el no se reconstruye el despliegue. |

**El archivo `.env.prod` no esta en el repositorio y no debe estar.** Es el
primer elemento del respaldo, y el que nadie incluye.

## 2. El respaldo diario

```bash
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
  exec -T postgres \
  pg_dump -U control_obras -Fc control_obras > /var/backups/control-obras-$(date +%F).dump
```

`-Fc` es formato **personalizado** (comprimido y restaurable). El formato SQL
plano comprime peor y no permite restaurar tablas sueltas.

Verificar que el archivo salio bien:

```bash
ls -lh /var/backups/control-obras-*.dump
```

**Un archivo de cero bytes es un respaldo fallido que parece exitoso.** Por eso
el paso siguiente no es opcional.

## 3. Verificar el respaldo

Este es el paso que casi todos se saltan, y es el que distingue un respaldo real
de la intencion de respaldar.

```bash
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
  exec -T postgres \
  pg_restore -l /dev/stdin < /var/backups/control-obras-2026-10-17.dump | head -40
```

`pg_restore -l` lista el contenido del archivo **sin restaurarlo**. Si lista las
tablas, el respaldo tiene contenido.

Verificación más fuerte: restaurar en una base limpia de verdad.

```bash
# Crear una base de prueba vacia
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
  exec -T postgres psql -U control_obras -d postgres -c "CREATE DATABASE restauracion_prueba;"

# Restaurar adentro
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
  exec -T postgres pg_restore -U control_obras -d restauracion_prueba --clean --if-exists \
  < /var/backups/control-obras-2026-10-17.dump

# Contar filas de las tablas que importan
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
  exec -T postgres psql -U control_obras -d restauracion_prueba -c \
  "SELECT (SELECT COUNT(*) FROM obras) AS obras, (SELECT COUNT(*) FROM registros_seguridad) AS seguridad, (SELECT COUNT(*) FROM tenants) AS empresas;"

# Bajar la base de prueba
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
  exec -T postgres psql -U control_obras -d postgres -c "DROP DATABASE restauracion_prueba;"
```

**Hay que hacer esto una vez al mes, no el primer día.** Un respaldo que se
restaura bien el primer día y después se rompe todos los meses no sirve el día
que se necesita.

## 4. Restaurar

**Esto reemplaza los datos actuales.** Con el servicio detenido:

```bash
# 1. Detener todo menos la base
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod stop api cola web

# 2. Restaurar
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
  exec -T postgres pg_restore -U control_obras -d control_obras --clean --if-exists \
  < /var/backups/control-obras-2026-10-17.dump

# 3. Verificar el aislamiento ANTES de levantar el servicio
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
  run --rm api php artisan obras:verificar-aislamiento

# 4. Levantar
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod up -d
```

El paso 3 no es opcional. Un respaldo tomado con el rol equivocado, restaurado
con el rol equivocado, deja la base sin políticas activas.

## 5. Política de retencion

| Días | Copias |
|---|---|
| 7 | diarios |
| 4 | semanales |
| 12 | mensuales |

O sea: 23 copias. Con la base de una constructora mediana, que es de quelques
miles de filas, el espacio no es el problema.

Lo que **si** es problema: la cantidad de respaldos nunca se borra y alguien
llena el disco. Un disco lleno deja la base sin escribir, y ahí el problema es
la obra, no el respaldo.

## 6. Automatizar

Cron del sistema, no un cron dentro del contenedor: un contenedor que se reinicia
no se lleva el calendario con el.

```cron
# Respaldar a las 02:17. Desplazado a proposito de los minutos en punto:
# a las 02:00 estan respaldando todos los servidores de Hefesto a la vez.
17 2 * * * cd /opt/control-obras && /usr/local/bin/respaldar-control-obras.sh >> /var/log/respaldos.log 2>&1
```

El script tiene que **fallar visible**. Si `pg_dump` falla, el script tiene que
devolver un código distinto de cero y enviar una notificación. Un respaldo que
falla en silencio es peor que no tener respaldo, porque da falsa tranquilidad.

## 7. Datos personales y respaldo

Las copias de la base **contienen datos personales** de trabajadores y de
clientes: RUT, nombres, telefonos, y los registros de accidentes.

Eso obliga a:

- **Guardar los respaldos cifrados**, no en texto claro.
- **Controlar quien puede leerlos**: son un volcado de todo el sistema.
- **Borrarlos cuando se cumple el plazo de retencion.** Una copia de hace dos
  años sigue siendo un tratamiento de datos personales, y sigue cayendo bajo
  las mismas obligaciones.

Los `.dump` con datos en claro **no** van a un bucket público, ni a un
repositorio, ni a un disco compartido sin cifrar.

## 8. Debilidades conocidas

| Debilidad | Estado |
|---|---|
| El respaldo no incluye el volumen de Redis | **Correcto:** Redis guarda cache y colas, no datos de negocio. Perderlo significa cache frío y colas perdidas, no información perdida. |
| No hay respaldo automático configurado en el repositorio | Se entrega el cron y la política; el script queda en `/usr/local/bin/`, fuera del repositorio. |
| No hay verificación automática | La restauración de prueba es manual y mensual. Un sistema de verificación continua daría más confianza. |
| No hay replica | Un solo volumen. Si el disco muere, hay que restaurar desde el último respaldo, y hay ventana de perdida. **Una replica o un destino remoto sería lo siguiente.** |
| `APP_KEY` no esta en el respaldo | Si se pierde, todas las sesiones quedan invalidas. No se recuperan las sesiones, solo habría que reiniciar la sesión de todos. Ver [05](05-CONFIGURACION.md). |