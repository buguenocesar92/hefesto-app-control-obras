import type { Usuario } from '~/tipos/api'

/**
 * Sesión del usuario.
 *
 * Única fuente de verdad para "quien esta conectado". Se pide una vez al
 * montar la aplicación y se comparte, para no hacer la misma consulta en cada
 * pantalla.
 */
export const useSesion = () => {
    const usuario = useState<Usuario | null>('usuario', () => null)

    const cargar = async () => {
        try {
            usuario.value = await $fetch<Usuario>('/api/v1/sesion')
        } catch {
            usuario.value = null
        }
    }

    const cerrarSesion = async () => {
        await $fetch('/api/v1/auth/salir', { method: 'GET' })
        usuario.value = null
        await navigateTo('/entrar')
    }

    const esCliente = computed(() => usuario.value?.rol === 'cliente')

    return { usuario, cargar, cerrarSesion, esCliente }
}