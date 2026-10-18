/**
 * Tipos de la API, copiados del contrato que devuelve el backend.
 *
 * Están escritos a mano y NO se generan desde los Resources de Laravel.
 * Es una deuda consciente: si un día hay que generarlos, el lugar natural es
 * una ruta de API que exponga los Resources como OpenAPI. Hoy son pocas
 * pantallas y el costo de mantenerlos a mano es menor que el de agregar una
 * cadena de herramientas para seis interfaces.
 *
 * `AvanceFisico` y `AvanceFinanciero` son TIPOS DISTINTOS y a propósito. No
 * hay un tipo `Avance` único: si lo hubiera, sería facil "acomodar" uno donde
 * va el otro, que es exactamente el error que este sistema existe para
 * evitar.
 */

export interface Empresa {
    slug: string
    nombre: string
}

export interface Usuario {
    id: string
    nombre: string
    correo: string
    cargo?: string | null
    rol: 'administrador' | 'jefe_obra' | 'maestro' | 'inspector' | 'cliente'
    activo: boolean
}

export interface Obra {
    id: string
    codigo: string
    nombre: string
    cliente_nombre: string
    cliente_rut?: string | null
    direccion: string
    tipo: string
    estado: 'planificacion' | 'en_ejecucion' | 'detenida' | 'terminada' | 'entregada'
    fecha_inicio?: string | null
    fecha_termino_estimada?: string | null
    monto_contratado: number
}

export interface Etapa {
    id: string
    orden: number
    nombre: string
    estado: 'pendiente' | 'en_ejecucion' | 'terminada'
    fecha_planificada?: string | null
    fecha_real?: string | null
    /** Positivo es atraso, negativo es adelanto. */
    desviacion_dias?: number | null
    atrasada: boolean
    avance_real_pct: number
    peso: number
    responsable?: { id: string; nombre: string } | null
}

export interface Tarea {
    id: string
    titulo: string
    descripcion?: string | null
    estado: 'pendiente' | 'en_ejecucion' | 'completada' | 'anulada'
    origen: 'manual' | 'correccion_inspeccion'
    fecha_plazo?: string | null
    fecha_cierre?: string | null
    vencida: boolean
    dias_para_vencer?: number | null
    responsable?: { id: string; nombre: string } | null
    hallazgo_origen?: {
        id: string
        gravedad: 'baja' | 'media' | 'alta' | 'critica'
        estado: string
        inspeccion_id: string
    } | null
}

export interface Hallazgo {
    id: string
    descripcion: string
    gravedad: 'baja' | 'media' | 'alta' | 'critica'
    estado: 'pendiente' | 'en_proceso' | 'corregido' | 'descartado'
    tiene_tarea_correccion: boolean
    inspeccion_id: string
    tarea_correccion?: {
        id: string
        titulo: string
        estado: string
        fecha_plazo?: string | null
        vencida: boolean
    } | null
}

export interface Inspeccion {
    id: string
    fecha_inspeccion: string
    resultado: 'conforme' | 'con_hallazgos' | 'rechazada'
    numero_acta?: string | null
    observaciones?: string | null
    tipo?: { id: string; nombre: string } | null
    inspector?: { id: string; nombre: string } | null
    hallazgos: Hallazgo[]
    pendientes?: number
}

export interface RegistroSeguridad {
    id: string
    clase: 'accidente' | 'casi_accidente'
    es_accidente: boolean
    fecha_ocurrencia: string
    lugar?: string | null
    descripcion: string
    lesionado: boolean
    horas_perdidas: number
    medidas_correctivas?: string | null
    falta_medidas_correctivas: boolean
    anonizado: boolean
    persona_afectada?: { nombre?: string | null; rut?: string | null } | null
}

/**
 * Avance FISICO: sale de las etapas. No tiene nada que ver con el dinero.
 */
export interface AvanceFisico {
    porcentaje: number | null
    fuente: 'etapas'
    unidad: string
    suma_pesos: number
    etapas_total: number
    etapas_terminadas: number
    etapas_atrasadas: number
    mayor_atraso_dias: number | null
}

/**
 * Avance FINANCIERO: sale de los pagos. Solo cuenta lo cobrado de verdad.
 */
export interface AvanceFinanciero {
    porcentaje: number | null
    fuente: 'avances_pago'
    unidad: string
    monto_contratado: number
    monto_cobrado: number
    saldo_por_cobrar: number
    por_emitir: number
    aprobado_no_cobrado: number
    lineas_pagadas: number
    lineas_totales: number
}

export interface LineaPago {
    id: string
    numero: number
    fecha_emision?: string | null
    estado: 'emitido' | 'aprobado' | 'pagado' | 'rechazado'
    monto_neto: number
    monto_cobrado: number
    saldo: number
    /** FOTO del fisico al emitir. NO es el fisico de la obra hoy. */
    avance_fisico_al_emitir_pct: number | null
}

export interface EstadoDeCuenta {
    obra: {
        id: string
        codigo: string
        nombre: string
        cliente_nombre: string
        direccion: string
        estado: string
        fecha_inicio?: string | null
        monto_contratado: number
    }
    avance_fisico: AvanceFisico
    avance_financiero: AvanceFinanciero
    /** Resta, no promedio. */
    diferencia_puntos_porcentuales: number | null
    pagos: LineaPago[]
    pendientes: {
        tareas_vencidas: number
        tareas_sin_vencer: number
        hallazgos_pendientes: number
    }
}