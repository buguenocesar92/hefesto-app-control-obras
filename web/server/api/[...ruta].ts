/**
 * Proxy del servidor: la interfaz NUNCA habla directo con la API.
 *
 * POR QUE NO HAY TOKEN EN EL NAVEGADOR
 * ------------------------------------
 * Si la interfaz llamara a la API desde el navegador, la cookie de sesión
 * tendria que ir acompanada y habría que exposed el mismo origen. Además
 * cualquier persona en la obra podría abrir las herramientas del navegador y
 * leer datos de otra obra.
 *
 * Con este proxy, toda la comunicacion ocurre en el servidor de Nuxt, que es
 * del mismo origen que las paginas. El navegador solo ve un mismo origen, y la
 * cookie de sesión viaja sola.
 *
 * `useRequestHeaders(['cookie'])` reenvia la cookie para que la API reconozca
 * la sesión, y `x-xsrf-token` se manda explicitamente porque el backend usa
 * CSRF con cookie./
export default defineEventHandler(async (event) => {
    const config = useRuntimeConfig(event)

    const ruta = event.path.replace(/^\/api\//, '')

    const metodo = event.method
    const esLectura = metodo === 'GET' || metodo === 'HEAD'

    const cuerpo = esLectura
        ? undefined
        : await readBody(event).catch(() => undefined)

    const cabeceras: Record<string, string> = {}

    const cookie = getRequestHeader(event, 'cookie')
    if (cookie) {
        cabeceras.cookie = cookie
    }

    const xsrf = getRequestHeader(event, 'x-xsrf-token')
    if (xsrf) {
        cabeceras['x-xsrf-token'] = xsrf
        cabeceras['x-csrf-token'] = xsrf
    }

    try {
        return await $fetch(`${config.apiBase}/api/${ruta}`, {
            method: metodo as any,
            body: cuerpo,
            headers: cabeceras,
        })
    } catch (error: any) {
        // El error de la API se reenvia tal cual: si es 404 por aislamiento,
        // la interfaz tiene que mostrar 404 y no un error generico.
        throw createError({
            statusCode: error?.statusCode ?? 500,
            statusMessage: error?.statusMessage ?? 'Error de la API',
            data: error?.data,
        })
    }
})