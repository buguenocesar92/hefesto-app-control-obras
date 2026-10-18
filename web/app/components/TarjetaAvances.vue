<template>
    <!--
        AVANCE FISICO Y AVANCE FINANCIERO, EN DOS CAJAS.

        Nunca en una sola cifra. Se pueden promediar sin querer al ponerlos
        juntos en un mismo bloque, así que la separación es física: dos cajas,
        dos títulos, dos fuentes.
    -->
    <div class="avances">
        <section class="caja caja-fisico">
            <h3>Avance fisico</h3>
            <p class="cifra">{{ porcentaje(fisico?.porcentaje) }}</p>
            <p class="fuente">
                Calculado desde las etapas.
                <template v-if="fisico">
                    {{ fisico.etapas_total }} etapas,
                    {{ fisico.etapas_terminadas }} terminadas.
                </template>
            </p>
            <p v-if="fisico?.mayor_atraso_dias" class="alerta">
                Mayor atraso: {{ fisico.mayor_atraso_dias }} días.
            </p>
            <p v-else-if="fisico?.porcentaje === null" class="vacio">
                Sin plan cargado: no hay etapas con peso.
            </p>
        </section>

        <section class="caja caja-financiero">
            <h3>Avance financiero</h3>
            <p class="cifra">{{ porcentaje(financiero?.porcentaje) }}</p>
            <p class="fuente">
                Cobrado sobre lo contratado.
                <template v-if="financiero">
                    {{ monto(financiero.monto_cobrado) }} de
                    {{ monto(financiero.monto_contratado) }}.
                </template>
            </p>
            <p v-if="financiero && financiero.aprobado_no_cobrado > 0" class="nota">
                Hay {{ monto(financiero.aprobado_no_cobrado) }} aprobado y no cobrado:
                no cuenta como avance.
            </p>
            <p v-else-if="financiero?.porcentaje === null" class="vacio">
                Sin monto contratado: no se puede calcular.
            </p>
        </section>

        <p v-if="diferencia !== null" class="diferencia">
            El fisico va {{ diferencia > 0 ? 'por delante' : 'por detras' }} de lo
            cobrado en {{ Math.abs(diferencia) }} puntos porcentuales.
            <span class="nota">
                Son dos cifras distintas, no un solo avance.
            </span>
        </p>
    </div>
</template>

<script setup lang="ts">
import type { AvanceFisico, AvanceFinanciero } from '~/tipos/api'

/**
 * Muestra los dos avances sin mezclarlos.
 *
 * Este componente es la parte de la interfaz donde el producto se juega. Si
 * aquí apareciera un único número de avance, el sistema habría perdidoso
 * justamente lo que lo diferencia de un reporte de avance de verdad.
 */
const props = defineProps<{
    fisico?: AvanceFisico | null
    financiero?: AvanceFinanciero | null
    diferencia?: number | null
}>()

const porcentaje = (valor: number | null | undefined): string =>
    valor === null || valor === undefined ? 'sin dato' : `${valor}%`

const monto = (valor: number): string =>
    new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(valor)
</script>

<style scoped>
.avances {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
}

.caja {
    background: #ffffff;
    border: 1px solid #dcdcd4;
    border-radius: 6px;
    padding: 1rem;
}

.caja-fisico {
    border-top: 3px solid #2f6f4f;
}

.caja-financiero {
    border-top: 3px solid #8a5a1f;
}

.caja h3 {
    margin: 0 0 0.25rem;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #55554d;
}

.cifra {
    margin: 0;
    font-size: 2.2rem;
    font-weight: 700;
}

.fuente {
    margin: 0.25rem 0 0;
    font-size: 0.82rem;
    color: #6a6a60;
}

.alerta {
    margin: 0.5rem 0 0;
    font-size: 0.85rem;
    color: #a33c2b;
}

.nota,
.vacio {
    margin: 0.5rem 0 0;
    font-size: 0.82rem;
    color: #6a6a60;
}

.diferencia {
    grid-column: 1 / -1;
    margin: 0;
    font-size: 0.9rem;
    color: #3a3a34;
}
</style>