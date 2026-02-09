/**
 * SECURITY - Módulo de Seguridad
 * Maneja CSRF tokens, validaciones y peticiones seguras
 */

import { NotificacionesConfig, NotificacionesState } from './core.js';

// =====================================================
// CSRF TOKEN CON RENOVACIÓN AUTOMÁTICA
// =====================================================

let renovacionTimeout = null;

export async function obtenerTokenCSRF(silencioso = false) {
    try {
        const response = await fetch(NotificacionesConfig.endpoints.token_csrf);
        const data = await response.json();
        
        if (data.success) {
            NotificacionesState.csrf_token = data.token;
            NotificacionesState.csrf_token_timestamp = data.timestamp;
            NotificacionesState.csrf_token_expira_en = data.expira_en_segundos;
            
            // Programar renovación automática 2 minutos antes de expirar
            programarRenovacionToken(data.expira_en_segundos);
            
            return data.token;
        } else {
            throw new Error('No se pudo obtener el token CSRF');
        }
    } catch (error) {
        console.error('Error al obtener token CSRF:', error);
        throw error;
    }
}

function programarRenovacionToken(expiraEnSegundos) {
    // Limpiar timeout anterior si existe
    if (renovacionTimeout) {
        clearTimeout(renovacionTimeout);
    }
    
    // Renovar 2 minutos antes de expirar (o a la mitad si es menos de 4 minutos)
    const tiempoRenovacion = Math.max(expiraEnSegundos - 120, expiraEnSegundos / 2);
    
    renovacionTimeout = setTimeout(async () => {
        try {
            await obtenerTokenCSRF(true);
        } catch (error) {
            console.error('❌ Error al renovar token CSRF:', error);
            // Reintentar en 30 segundos
            setTimeout(() => obtenerTokenCSRF(true), 30000);
        }
    }, tiempoRenovacion * 1000);
}

export function verificarTokenCSRF() {
    const ahora = Date.now() / 1000;
    const edad = ahora - (NotificacionesState.csrf_token_timestamp || 0);
    const tiempoVida = 1800; // 30 minutos
    
    if (edad > tiempoVida) {
        console.warn('⚠️ Token CSRF expirado, renovando...');
        return obtenerTokenCSRF(true);
    }
    
    return Promise.resolve(NotificacionesState.csrf_token);
}

// =====================================================
// CONFIGURACIÓN DE PETICIONES
// =====================================================

export async function configurarPeticion(options = {}) {
    // Verificar y renovar token si es necesario
    if (options.method === 'POST') {
        await verificarTokenCSRF();
    }
    
    const defaultOptions = {
        method: 'GET',
        headers: { 
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        },
        credentials: 'same-origin',
        cache: 'no-store' // Evitar caché del navegador
    };
    
    if (!(options.body instanceof FormData)) {
        defaultOptions.headers['Content-Type'] = 'application/json';
    }
    
    if (options.method === 'POST' && NotificacionesState.csrf_token && 
        !(options.body instanceof FormData)) {
        defaultOptions.headers['X-CSRF-Token'] = NotificacionesState.csrf_token;
    }
    
    return { ...defaultOptions, ...options };
}

// =====================================================
// MANEJO DE RESPUESTAS HTTP
// =====================================================

export async function manejarRespuestaHTTP(response) {
    if (!response.ok) {
        console.error('🔴 Error HTTP:', response.status, response.statusText, response.url);
        
        switch (response.status) {
            case 401:
                globalThis.location.href = '/gh/index.php?sesion=expirada';
                return;
            case 403:
                throw new Error('Acceso denegado. Verifique sus permisos.');
            default:
                if (response.status >= 500) {
                    response.text().then(text => {
                        console.error('🔍 Contenido del error 500:', text);
                    }).catch(() => {});
                    throw new Error(`Error interno del servidor (${response.status}). Revise la consola del navegador para más detalles.`);
                }
                throw new Error(`Error HTTP ${response.status}: ${response.statusText}`);
        }
    }
    
    const contentType = response.headers.get('content-type');
    if (!contentType?.includes('application/json')) {
        console.error('🔍 Content-Type incorrecto:', contentType);
        response.text().then(text => {
            console.error('🔍 Contenido de respuesta no-JSON:', text.substring(0, 500) + (text.length > 500 ? '...' : ''));
        }).catch(() => {});
        throw new Error('Respuesta no válida del servidor (no es JSON)');
    }
    
    return response.json();
}

// =====================================================
// PETICIÓN SEGURA CON RENOVACIÓN AUTOMÁTICA DE TOKEN
// =====================================================

export async function peticionSegura(url, options = {}) {
    const finalOptions = await configurarPeticion(options);
    
    try {
        const response = await fetch(url, finalOptions);
        return await manejarRespuestaHTTP(response);
    } catch (error) {
        console.error('💥 Error en petición:', error);
        
        // Si es error 403 por token expirado, renovar y reintentar
        if (error.message.includes('403') || error.message.includes('Token CSRF')) {
            await obtenerTokenCSRF(true);
            const retryOptions = await configurarPeticion(options);
            const retryResponse = await fetch(url, retryOptions);
            return await manejarRespuestaHTTP(retryResponse);
        }
        
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            throw new Error('Error de conexión. Verifique su conexión a internet.');
        }
        throw error;
    }
}

// =====================================================
// VALIDACIÓN Y ESCAPE
// =====================================================

export function escaparHTML(text) {
    if (!text || typeof text !== 'string') return '';
    const div = document.createElement('div');
    div.textContent = text.trim();
    return div.innerHTML;
}

export function validarEntradaTexto(texto, maxLength = 5000) {
    if (!texto || typeof texto !== 'string') {
        return { valido: false, mensaje: 'El texto es requerido' };
    }
    
    const textoLimpio = texto.trim();
    if (textoLimpio.length === 0) {
        return { valido: false, mensaje: 'El texto no puede estar vacío' };
    }
    
    if (textoLimpio.length > maxLength) {
        return { valido: false, mensaje: `El texto excede ${maxLength} caracteres` };
    }
    
    return { valido: true, texto: textoLimpio };
}
