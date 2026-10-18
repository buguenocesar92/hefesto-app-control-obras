<template>
    <div>
        <p class="volver">
            <NuxtLink to="/obras">Volver a las obras</NuxtLink>
        </p>

        <h1>{{ cuenta.obra.codigo }} - {{ cuenta.obra.nombre }}</h1>
        <p class="subtitulo">
            {{ cuenta.obra.cliente_nombre }} - {{ cuenta.obra.direccion }}
        </p>

        <!--
            Los dos avances, en cajas separadas. Ver TarjetaAvances.vue: por
            que nunca se muestran como un solo numero.
        -->
        <TarjetaAvances
            :fisico="cuenta.avance_fisico"
            :financiero="cuenta.avance_financiero"
            :diferencia="cuenta.diferencia_puntos_porcentuales"
        />

        <section class="bloque">
            <h2>Etapas</h2>
            <p class="nota">
                La diferencia entre la fecha planificada y la fecha real es la
                unica medida de atraso de la obra.
            </p>
            <table class="cuadra">
                <thead>
                    <tr>
                        <th>Etapa</th>
                        <th>Planificada</th>
                        <th>Real</th>
                        <th>Desviacion</th>
                        <th>Avance</th>
                        <th>Peso</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="etapa in etapas" :key="etapa.id" :class="{ atrasada: etapa.atrasada }">
                        <td>{{ etapa.nombre }}</td>
                        <td>{{ etapa.fecha_planificada ?? 'sin plan' }}</td>
                        <td>{{ etapa.fecha_real ?? 'en curso' }}</td>
                        <td>
                            <span v-if="etapa.desviacion_dias === null">-</span>
                            <span v-else-if="etapa.desviacion_dias > 0" class="atraso">
                                +{{ etapa.desviacion_dias }} dias
                            </span>
                            <span v-else-if="etapa.desviacion_dias < 0" class="adelanto">
                                {{ etapa.desviacion_dias }} dias
                            </span>
                            <span v-else>en fecha</span>
                        </td>
                        <td>{{ etapa.avance_real_pct }}%</td>
                        <td>{{ etapa.peso }}%</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="bloque">
            <h2>Tareas</h2>
            <p class="nota">
                Cada correccion de una inspeccion nace con fecha de plazo. Las
                que estan vencidas son las unicas que presionan al equipo.
            </p>
            <ul class="tareas">
                <li v-for="tarea in tareas" :key="tarea.id" :class="{ vencida: tarea.vencida }">
                    <strong>{{ tarea.titulo }}</strong>
                    <span class="meta">
                        {{ tarea.estado.replace('_', ' ') }}
                        <template v-if="tarea.fecha_plazo"> - vence el {{ tarea.fecha_plazo }}</template>
                        <template v-if="tarea.responsable"> - {{ tarea.responsable.nombre }}</template>
                        <template v-if="tarea.origen === 'correccion_inspeccion'">
                            <em>(correccion de inspeccion)</em>
                        </template>
                    </span>
                </li>
            </ul>
        </section>

        <section class="bloque">
            <h2>Estado de cuenta</h2>
            <p class="nota">
                El avance fisico de cada pago es la foto que quedo al emitirlo.
                No es el avance fisico de la obra hoy.
            </p>
            <table class="cuadra">
                <thead>
                    <tr>
                        <th>N.</th>
                        <th>Emision</th>
                        <th>Estado</th>
                        <th>Neto</th>
                        <th>Cobrado</th>
                        <th>Saldo</th>
                        <th>Fisico al emitir</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="pago in cuenta.pagos" :key="pago.id">
                        <td>{{ pago.numero }}</td>
                        <td>{{ pago.fecha_emision }}</td>
                        <td>{{ pago.estado }}</td>
                        <td>{{ pago.monto_neto }}</td>
                        <td>{{ pago.monto_cobrado }}</td>
                        <td>{{ pago.saldo }}</td>
                        <td>{{ pago.avance_fisico_al_emitir_pct ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="bloque">
            <h2>Pendientes</h2>
            <ul>
                <li>{{ cuenta.pendientes.tareas_vencidas }} tarea(s) vencidas</li>
                <li>{{ cuenta.pendientes.tareas_sin_vencer }} tarea(s) sin vencer</li>
                <li>{{ cuenta.pendientes.hallazgos_pendientes }} hallazgo(s) sin corregir</li>
            </ul>
        </section>
    </div>
</template>

<script setup lang="ts">
import type { EstadoDeCuenta, Etapa, Tarea } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const ruta = useRoute()
const obraId = ruta.params.id as string

const { data: cuentaBruto } = await useFetch<EstadoDeCuenta>(
    `/api/v1/obras/${obraId}/estado-de-cuenta`,
)

const { data: etapasBruto } = await useFetch<{ data: Etapa[] }>(
    `/api/v1/obras/${obraId}/etapas`,
)

const { data: tareasBruto } = await useFetch<{ data: Tarea[] }>(
    `/api/v1/obras/${obraId}/tareas`,
)

const cuenta = cuentaBruto.value
const etapas = etapasBruto.value?.data ?? []
const tareas = tareasBruto.value?.data ?? []
</script>

<style scoped>
.volver {
    margin: 0 0 0.75rem;
    font-size: 0.85rem;
}

h1 {
    margin: 0 0 0.25rem;
    font-size: 1.35rem;
}

.subtitulo {
    margin: 0 0 1rem;
    color: #6a6a60;
    font-size: 0.9rem;
}

.bloque {
    margin-top: 1.75rem;
}

.bloque h2 {
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #55554d;
    margin: 0 0 0.25rem;
}

.nota {
    margin: 0 0 0.6rem;
    font-size: 0.82rem;
    color: #6a6a60;
}

.cuadra {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    border: 1px solid #dcdcd4;
}

.cuadra th,
.cuadra td {
    text-align: left;
    padding: 0.45rem 0.6rem;
    border-bottom: 1px solid #e8e8e0;
    font-size: 0.85rem;
}

.cuadra th {
    background: #efefeb;
}

.atrasada {
    background: #fdf3f0;
}

.atraso {
    color: #a33c2b;
    font-weight: 600;
}

.adelanto {
    color: #2f6f4f;
}

.tareas {
    list-style: none;
    padding: 0;
    margin: 0;
}

.tareas li {
    background: #ffffff;
    border: 1px solid #dcdcd4;
    border-radius: 4px;
    padding: 0.5rem 0.7rem;
    margin-bottom: 0.4rem;
}

.tareas li.vencida {
    border-left: 3px solid #a33c2b;
}

.meta {
    display: block;
    font-size: 0.8rem;
    color: #6a6a60;
}
</style>