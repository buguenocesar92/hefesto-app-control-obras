/**
 * Middleware de ruta: exige sesión.
 *
 * Se ejecuta en el SERVIDOR, antes de renderizar. Si no hay sesión, no se
 * manda ni un dato de obra al navegador: la pagina se redirige a la pantalla
 * de entrada y la información nunca existio en el HTML.
 *
 * Esa diferencia importa. Con una protección solo en el cliente, los datos de
 * la obra habrian viajado igual en el HTML de la respuesta, y solo después
 * habrian desaparecido de la pantalla.
 */
export default defineNuxtRouteMiddleware(async (to) => {
    const { usuario, cargar } = useSesion()

    if (usuario.value === null) {
        await cargar()
    }

    if (usuario.value === null) {
        return navigateTo({ path: '/entrar', query: { destino: to.fullPath } })
    }
})