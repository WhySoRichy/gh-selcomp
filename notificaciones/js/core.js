/**
 * CORE - Configuración y State Management
 * Centraliza la configuración global y estado del sistema
 */

// =====================================================
// CONFIGURACIÓN GLOBAL
// =====================================================
export const NotificacionesConfig = {
    // Endpoints dinámicos (usan window.API_ENDPOINT configurado en app.js)
    get endpoints() {
        const base = globalThis.API_ENDPOINT || '/gh/notificaciones/api.php';
        return {
            listar: `${base}?accion=listar`,
            estadisticas: `${base}?accion=obtener_estadisticas`,
            crear: `${base}?accion=crear`,
            obtener: `${base}?accion=obtener`,
            actualizar: `${base}?accion=actualizar`,
            eliminar: `${base}?accion=eliminar`,
            cambiar_estado: `${base}?accion=cambiar_estado`,
            duplicar: `${base}?accion=duplicar`,
            token_csrf: `${base}?accion=obtener_token_csrf`,
            obtener_usuarios: `${base}?accion=obtener_usuarios`,
            scan_orphans: `${base}?accion=scan_orphans`,
            clean_orphans: `${base}?accion=clean_orphans`
        };
    },
    
    // Configuración de seguridad
    security: {
        max_file_size: 10 * 1024 * 1024, // 10MB
        allowed_extensions: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'],
        timeout: 30000 // 30 segundos
    }
};

// =====================================================
// ESTADO GLOBAL
// =====================================================
export const NotificacionesState = {
    // Cache de elementos DOM
    elements: {},
    
    // Datos
    filtros: {},
    notificaciones: [],
    usuariosDisponibles: [],
    usuariosSeleccionados: [],
    
    // Seguridad
    csrf_token: null,
    csrf_token_timestamp: null,
    csrf_token_expira_en: null,
    
    // Archivos
    archivosSeleccionados: null
};
