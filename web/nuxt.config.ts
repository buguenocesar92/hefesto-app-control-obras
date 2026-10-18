import { defineNuxtConfig } from 'nuxt/config'

/**
 * Configuración de Nuxt para la interfaz de Control de Obras.
 *
 * SSR SIEMPRE ACTIVADO
 * --------------------
 * La aplicación se sirve renderizada desde el servidor porque opera datos de
 * obras que a veces son sensibles y porque en obra se usa una conexión mala:
 * cargar primero el HTML reduce la sensacion de que "no abre".
 */
export default defineNuxtConfig({
    compatibilityDate: '2026-01-15',
    ssr: true,

    devtools: { enabled: false },

    runtimeConfig: {
        // Solo el servidor usa esto. NUNCA se declara `public`.
        // La API no es alcanzable desde el navegador: el proxy corre en Node.
        apiBase: process.env.NUXT_API_BASE ?? 'http://api:8080',

        public: {
            baseUrl: process.env.NUXT_PUBLIC_BASE ?? 'http://localhost:3000',
        },
    },

    app: {
        head: {
            title: 'Control de Obras',
            htmlAttrs: { lang: 'es-CL' },
            meta: [
                { charset: 'utf-8' },
                { name: 'viewport', content: 'width=device-width, initial-scale=1' },
                // Una obra se consulta desde telefonos y notebooks portatiles
                // conectados a la obra. La pantalla se adapta.
                { name: 'description', content: 'Seguimiento de obras de construcción: etapas, inspecciones, seguridad y estado de cuenta.' },
            ],
        },
    },

    nitro: {
        // Compresion del HTML: importa en conexiones lentas de obra.
        compressPublicAssets: true,
    },
})