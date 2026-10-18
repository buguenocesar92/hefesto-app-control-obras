<template>
    <div>
        <h1>Obras</h1>

        <p v-if="pendientes.resumen" class="resumen">
            {{ obras.length }} obra(s) en este listado.
        </p>

        <table class="cuadra">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Obra</th>
                    <th>Cliente</th>
                    <th>Estado</th>
                    <th>Desde</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="obra in obras" :key="obra.id">
                    <td>
                        <NuxtLink :to="`/obras/${obra.id}`">{{ obra.codigo }}</NuxtLink>
                    </td>
                    <td>{{ obra.nombre }}</td>
                    <td>{{ obra.cliente_nombre }}</td>
                    <td>{{ obra.estado.replace('_', ' ') }}</td>
                    <td>{{ obra.fecha_inicio }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup lang="ts">
import type { Obra } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const { data } = await useFetch<{ data: Obra[] }>('/api/v1/obras')

const obras = computed(() => data.value?.data ?? [])
const pendientes = computed(() => ({ resumen: true }))
</script>

<style scoped>
h1 {
    margin-top: 0;
    font-size: 1.4rem;
}

.resumen {
    color: #6a6a60;
    font-size: 0.9rem;
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
    padding: 0.55rem 0.7rem;
    border-bottom: 1px solid #e8e8e0;
    font-size: 0.9rem;
}

.cuadra th {
    background: #efefeb;
    font-weight: 600;
}

.cuadra a {
    color: #2f6f4f;
}
</style>