/**
 * UTILS - Módulo de Utilidades
 * Funciones auxiliares reutilizables
 */

import { NotificacionesConfig, NotificacionesState } from './core.js';
import { peticionSegura } from './security.js';

// =====================================================
// FORMATO Y PRESENTACIÓN
// =====================================================

export function truncarTexto(texto, longitud) {
    return texto.length > longitud ? texto.substring(0, longitud) + '...' : texto;
}

export function formatearFecha(fecha, completa = false) {
    const options = {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    };
    
    if (completa) {
        options.hour = '2-digit';
        options.minute = '2-digit';
    }
    
    return new Date(fecha).toLocaleString('es-ES', options);
}

export const formatearFechaCompleta = fecha => formatearFecha(fecha, true);

export function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Number.parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// =====================================================
// TEXTOS DE DESTINOS
// =====================================================

const destinosTexto = {
    'todos': 'Todos los usuarios',
    'administradores': 'Administradores',
    'regulares': 'Usuarios regulares',
    'especificos': 'Usuarios específicos'
};

export const getDestinoTexto = destino => destinosTexto[destino] || destino;

// =====================================================
// NOTIFICACIONES AL USUARIO
// =====================================================

export function mostrarNotificacion(tipo, mensaje, opciones = {}) {
    if (typeof Swal === 'undefined') {
        console.error('SweetAlert2 no disponible:', mensaje);
        return;
    }
    
    const config = {
        error: { icon: 'error', title: 'Error', timer: 4000, color: '#eb0045' },
        success: { icon: 'success', title: 'Éxito', timer: 3000, color: '#eb0045' },
        warning: { icon: 'warning', title: 'Advertencia', timer: 3500, color: '#eb0045' }
    };
    
    const settings = config[tipo] || config.error;
    
    if (opciones.modal) {
        Swal.fire({
            icon: settings.icon,
            title: settings.title,
            text: mensaje,
            confirmButtonText: 'Entendido',
            confirmButtonColor: settings.color,
            allowOutsideClick: false,
            customClass: { container: 'swal-over-modal' }
        });
    } else {
        Swal.fire({
            icon: settings.icon,
            title: settings.title,
            text: mensaje,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: settings.timer,
            customClass: { container: 'swal-high-z' }
        });
    }
}

export const mostrarError = (msg, modal = false) => mostrarNotificacion('error', msg, { modal });
export const mostrarExito = (msg, modal = false) => mostrarNotificacion('success', msg, { modal });
export const mostrarAdvertencia = (msg, modal = false) => mostrarNotificacion('warning', msg, { modal });

// =====================================================
// ESTADOS DE VISUALIZACIÓN
// =====================================================

export function cambiarEstadoVista(estado) {
    const loadingState = document.getElementById('loading-state');
    const emptyState = document.getElementById('empty-state');
    const notificacionesGrid = document.getElementById('notificaciones-grid');
    
    if (!loadingState || !emptyState || !notificacionesGrid) {
        console.error('❌ Elementos de estado no encontrados');
        return;
    }
    
    // Ocultar todos
    loadingState.style.display = 'none';
    emptyState.style.display = 'none';
    notificacionesGrid.style.display = 'none';
    
    // Mostrar el correspondiente
    switch (estado) {
        case 'loading':
            loadingState.style.display = 'flex';
            break;
        case 'empty':
            emptyState.style.display = 'flex';
            break;
        case 'content':
            notificacionesGrid.style.display = 'grid';
            break;
        default:
            console.warn('Estado no reconocido:', estado);
    }
}

// =====================================================
// MANTENIMIENTO DE ARCHIVOS
// =====================================================

export async function escanearArchivosHuerfanos() {
    try {
        const data = await peticionSegura(NotificacionesConfig.endpoints.scan_orphans);
        
        if (data.success) {
            mostrarResultadosEscaneo(data.data);
        } else {
            throw new Error(data.error || 'Error al escanear archivos');
        }
        
    } catch (error) {
        console.error('❌ Error al escanear:', error);
        mostrarError('Error al escanear archivos huérfanos: ' + error.message);
    }
}

export async function limpiarArchivosHuerfanos() {
    try {
        const result = await Swal.fire({
            title: '¿Limpiar archivos huérfanos?',
            html: `
                <p><strong>⚠️ Esta acción es irreversible</strong></p>
                <p>Se eliminarán:</p>
                <ul style="text-align: left;">
                    <li>Archivos físicos sin referencia en BD</li>
                    <li>Referencias en BD sin archivo físico</li>
                    <li>Archivos de notificaciones eliminadas</li>
                </ul>
                <p>Se recomienda hacer un respaldo antes.</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#eb0045',
            cancelButtonColor: '#404e62',
            confirmButtonText: 'Sí, limpiar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });
        
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Limpiando archivos...',
                text: 'Por favor espere mientras se procesan los archivos',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
            
            const formData = new FormData();
            formData.append('csrf_token', NotificacionesState.csrf_token);
            
            const data = await peticionSegura(
                NotificacionesConfig.endpoints.clean_orphans,
                { method: 'POST', body: formData }
            );
            
            if (data.success) {
                mostrarResultadosLimpieza(data.data);
            } else {
                throw new Error(data.error || 'Error en la limpieza');
            }
        }
        
    } catch (error) {
        console.error('❌ Error al limpiar:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error en limpieza',
            text: `No se pudo completar la limpieza: ${error.message}`,
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#eb0045'
        });
    }
}

function mostrarResultadosEscaneo(results) {
    const total_orphans = 
        results.physical_orphans.length + 
        results.database_orphans.length + 
        results.notification_orphans.length;
    
    let html = `
        <div class="orphan-results">
            <h4>🔍 Resultados del Escaneo</h4>
            <p><strong>Total archivos huérfanos encontrados: ${total_orphans}</strong></p>
            
            <div class="orphan-category">
                <h5>📁 Archivos físicos sin BD (${results.physical_orphans.length})</h5>
                ${results.physical_orphans.length > 0 ? 
                    results.physical_orphans.map(f => `<li>${f.filename} (${formatFileSize(f.size)})</li>`).join('') : 
                    '<p>No se encontraron archivos físicos huérfanos</p>'
                }
            </div>
            
            <div class="orphan-category">
                <h5>📊 Referencias BD sin archivo (${results.database_orphans.length})</h5>
                ${results.database_orphans.length > 0 ? 
                    results.database_orphans.map(f => `<li>${f.original_name} (ID: ${f.id})</li>`).join('') : 
                    '<p>No se encontraron referencias huérfanas en BD</p>'
                }
            </div>
            
            <div class="orphan-category">
                <h5>🗑️ Archivos de notificaciones eliminadas (${results.notification_orphans.length})</h5>
                ${results.notification_orphans.length > 0 ? 
                    results.notification_orphans.map(f => `<li>${f.original_name} (Notif: ${f.notification_id})</li>`).join('') : 
                    '<p>No se encontraron archivos de notificaciones eliminadas</p>'
                }
            </div>
        </div>
    `;
    
    const buttons = total_orphans > 0 ? {
        showCancelButton: true,
        confirmButtonText: '🧹 Limpiar ahora',
        cancelButtonText: 'Solo escanear',
        confirmButtonColor: '#eb0045',
        cancelButtonColor: '#404e62'
    } : {
        confirmButtonText: 'Aceptar',
        confirmButtonColor: '#eb0045'
    };
    
    Swal.fire({
        title: 'Escaneo completado',
        html: html,
        icon: total_orphans > 0 ? 'warning' : 'success',
        width: '600px',
        ...buttons
    }).then((result) => {
        if (result.isConfirmed && total_orphans > 0) {
            limpiarArchivosHuerfanos();
        }
    });
}

function mostrarResultadosLimpieza(results) {
    const cleaned_count = results.cleaned_files?.length || 0;
    
    let html = `
        <div class="cleanup-results">
            <h4>✨ Limpieza Completada</h4>
            <p><strong>Archivos procesados: ${cleaned_count}</strong></p>
    `;
    
    if (cleaned_count > 0) {
        html += `
            <h5>Archivos eliminados:</h5>
            <ul>
                ${results.cleaned_files.map(f => {
                    let type_text = {
                        'physical_file_deleted': '📁 Archivo físico',
                        'database_reference_deleted': '📊 Referencia BD',
                        'notification_orphan_cleaned': '🗑️ Notificación eliminada'
                    }[f.type] || f.type;
                    return `<li>${type_text}: ${f.filename || f.id}</li>`;
                }).join('')}
            </ul>
        `;
    }
    
    html += '</div>';
    
    Swal.fire({
        icon: 'success',
        title: '✅ Limpieza exitosa',
        html: html,
        confirmButtonText: 'Aceptar',
        confirmButtonColor: '#eb0045',
        width: '600px'
    });
}

// =====================================================
// VERIFICACIÓN DE ARCHIVOS
// =====================================================

export async function verificarIntegridadArchivo(archivoId) {
    try {
        const response = await fetch(`verify_file.php?id=${archivoId}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        return data;
        
    } catch (error) {
        console.error('Error al verificar archivo:', error);
        return { valid: false, error: 'Error de conexión' };
    }
}

export async function descargarArchivo(archivoId, nombreArchivo) {
    try {
        const verificacion = await verificarIntegridadArchivo(archivoId);
        
        if (!verificacion.valid) {
            mostrarError(`Error en archivo "${nombreArchivo}": ${verificacion.error}`);
            return;
        }
        
        const downloadUrl = `descargar_archivo.php?id=${archivoId}`;
        window.open(downloadUrl, '_blank');
        
    } catch (error) {
        console.error('Error al descargar archivo:', error);
        mostrarError(`Error al descargar "${nombreArchivo}": ${error.message}`);
    }
}

// Exponer funciones globalmente para compatibilidad
globalThis.verificarIntegridadArchivo = verificarIntegridadArchivo;
globalThis.descargarArchivo = descargarArchivo;
