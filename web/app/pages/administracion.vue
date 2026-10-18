<template>
    <div>
        <h1>Administracion</h1>

        <section class="bloque">
            <h2>Responsables</h2>
            <p class="nota">
                El acceso se concede por invitación. Una obra con los datos de
                sus trabajadores no se abre al que llega con un correo cualquiera.
            </p>
            <table class="cuadra">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Cargo</th>
                        <th>Rol</th>
                        <th>Activo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="usuario in usuarios" :key="usuario.id">
                        <td>{{ usuario.nombre }}</td>
                        <td>{{ usuario.correo }}</td>
                        <td>{{ usuario.cargo ?? '-' }}</td>
                        <td>{{ usuario.rol.replace('_', ' ') }}</td>
                        <td>{{ usuario.activo ? 'si' : 'no' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="bloque">
            <h2>Tipos de inspección</h2>
            <p class="nota">
                El catalogo es por empresa, no por obra: cada constructora
                define los suyos.
            </p>
            <ul class="lista">
                <li v-for="tipo in tipos" :key="tipo.id">
                    {{ tipo.nombre }}
                    <span class="nota">{{ tipo.descripcion }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>

<script setup lang="ts">
import type { Usuario } from '~/tipos/api'

definePageMeta({ middleware: 'auth' })

const { data: usuariosBruto } = await useFetch<Usuario[]>('/api/v1/usuarios')
const { data: tiposBruto } = await useFetch<{ id: string; nombre: string; descripcion?: string }[]>(
    '/api/v1/tipos-inspeccion',
)

const usuarios = usuariosBruto.value ?? []
const tipos = tiposBruto.value ?? []
</script>

<style scoped>
.bloque {
    margin-bottom: 1.75rem;
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

.lista {
    list-style: none;
    padding: 0;
    margin: 0;
}

.lista li {
    background: #ffffff;
    border: 1px solid #dcdcd4;
    border-radius: 4px;
    padding: 0.5rem 0.7rem;
    margin-bottom: 0.35rem;
    font-size: 0.9rem;
}
</style>