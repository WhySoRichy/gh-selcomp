/**
 * APP - Orquestador Principal
 * Punto de entrada de la aplicación de notificaciones
 */

// Configurar endpoint global para compatibilidad
globalThis.NOTIFICACIONES_BASE_PATH = '/gh/notificaciones';
globalThis.API_ENDPOINT = `${globalThis.NOTIFICACIONES_BASE_PATH}/api.php`;

// Importar módulos
import { cacheElements, setupEventListeners } from './ui.js';
import { initFileUpload } from './files.js';
import { obtenerTokenCSRF } from './security.js';
import { recargarDatos } from './crud.js';
import { inicializarRespuestas } from './respuestas.js';

// Inicialización al cargar el DOM
document.addEventListener('DOMContentLoaded', async function() {
    try {
        // Cache elementos DOM
        cacheElements();
        
        // Obtener token CSRF
        await obtenerTokenCSRF();
        
        // Inicializar file upload
        initFileUpload();
        
        // Inicializar sistema de respuestas
        inicializarRespuestas();
        
        // Event listeners
        setupEventListeners();
        
        // Cargar datos iniciales
        await recargarDatos();
    } catch (error) {
        console.error('❌ Error al inicializar:', error);
    }
});

