<template>
    <div class="entrar">
        <h1>Entrar</h1>

        <p class="nota">
            El acceso es por invitacion de la administracion de la empresa. El
            sistema no tiene registro abierto.
        </p>

        <div class="tarjeta">
            <h2>Empresa constructora</h2>
            <ul class="lista">
                <li v-for="empresa in empresas" :key="empresa.slug">
                    <a :href="entrarEn(empresa.slug)">{{ empresa.nombre }}</a>
                </li>
            </ul>

            <p v-if="empresas.length === 0" class="vacio">
                Todavía no hay empresas registradas en esta instalación.
            </p>

            <p v-if="error" class="error">{{ error }}</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { Empresa } from '~/tipos/api'

/**
 * Pantalla de entrada.
 *
 * La persona elige su empresa y desde ahí va a Authentik. La eleccion de
 * empresa es NECESARIA y no un adorno: el correo es único dentro de una
 * empresa, no en el sistema, así que dos constructoras pueden tener a alguien
 * con el mismo correo.
 *
 * La lista viene de `vw_tenants_publicos`, que expone solo slug, nombre y el
 * estado activo. No expone RUT ni contactos: eso se lee en la base y no por
 * la red.
 */
const ruta = useRoute()
const error = computed(() => (ruta.query.error as string | undefined) ?? null)

const { data } = await useFetch<{ empresas: Empresa[] }>('/api/v1/empresas')

const empresas = computed(() => data.value?.empresas ?? [])

const entrarEn = (slug: string) => `/api/v1/auth/entrar?empresa=${encodeURIComponent(slug)}`
</script>

<style scoped>
.entrar {
    max-width: 520px;
    margin: 3rem auto;
}

.nota {
    color: #6a6a60;
    font-size: 0.88rem;
}

.tarjeta {
    background: #ffffff;
    border: 1px solid #dcdcd4;
    border-radius: 6px;
    padding: 1rem 1.25rem;
}

.tarjeta h2 {
    margin: 0 0 0.75rem;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #55554d;
}

.lista {
    list-style: none;
    padding: 0;
    margin: 0;
}

.lista li {
    padding: 0.5rem 0;
    border-bottom: 1px solid #e8e8e0;
}

.lista a {
    color: #2f6f4f;
    text-decoration: none;
    font-weight: 600;
}

.vacio,
.error {
    margin-top: 0.75rem;
    font-size: 0.88rem;
}

.error {
    color: #a33c2b;
}
</style>