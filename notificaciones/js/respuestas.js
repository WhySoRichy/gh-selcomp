/**
 * MÓDULO DE RESPUESTAS
 * Maneja el centro de respuestas tipo Instagram
 */

import { peticionSegura, escaparHTML } from './security.js';
import { mostrarExito, mostrarError } from './utils.js';

let notificacionActual = null;

/**
 * Formatea fecha en formato legible
 */
function formatearFecha(fecha) {
    const d = new Date(fecha);
    const ahora = new Date();
    const diff = Math.floor((ahora - d) / 1000);
    
    if (diff < 60) return 'Hace un momento';
    if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} horas`;
    
    const opciones = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return d.toLocaleDateString('es-ES', opciones);
}

/**
 * Inicializa el sistema de respuestas
 */
export function inicializarRespuestas() {
    const modal = document.getElementById('modal-respuestas');
    const btnCerrar = document.getElementById('cerrar-modal-respuestas');
    const formRespuesta = document.getElementById('form-respuesta');
    const textareaRespuesta = document.getElementById('respuesta-texto');
    const archivoInput = document.getElementById('respuesta-archivo');
    
    if (btnCerrar) {
        btnCerrar.addEventListener('click', cerrarModalRespuestas);
    }
    
    // Cerrar modal al hacer clic fuera del contenido (en el overlay)
    if (modal) {
        modal.addEventListener('click', (e) => {
            // Si el clic es directamente en el overlay (no en el contenido del modal)
            if (e.target === modal || e.target.classList.contains('modal-overlay')) {
                cerrarModalRespuestas();
            }
        });
    }
    
    if (formRespuesta) {
        formRespuesta.addEventListener('submit', enviarRespuesta);
    }
    
    // Contador de caracteres
    if (textareaRespuesta) {
        textareaRespuesta.addEventListener('input', (e) => {
            const contador = document.querySelector('.char-counter');
            if (contador) {
                contador.textContent = `${e.target.value.length} / 2000 caracteres`;
            }
        });
    }
    
    // Validar tamaño de archivo
    if (archivoInput) {
        archivoInput.addEventListener('change', validarArchivo);
    }
    
    // Event listener solo para eliminar respuesta (admins)
    document.addEventListener('click', (e) => {
        const btnEliminar = e.target.closest('.btn-eliminar-respuesta');
        if (btnEliminar && globalThis.ES_ADMIN) {
            const respuestaId = btnEliminar.dataset.respuestaId;
            eliminarRespuesta(respuestaId);
        }
    });
}

/**
 * Abre el modal de respuestas para una notificación
 */
export async function abrirModalRespuestas(notificacionId) {
    notificacionActual = notificacionId;
    
    const modal = document.getElementById('modal-respuestas');
    const inputId = document.getElementById('respuesta-notificacion-id');
    
    if (!modal || !inputId) return;
    
    inputId.value = notificacionId;
    modal.style.display = 'flex';
    
    // Ocultar el badge de respuestas de esta notificación (ya las vio el usuario)
    ocultarBadgeRespuestas(notificacionId);
    
    // Cargar info de la notificación
    await cargarInfoNotificacion(notificacionId);
    
    // Cargar respuestas
    await cargarRespuestas(notificacionId);
    
    // Limpiar formulario
    document.getElementById('form-respuesta').reset();
    document.querySelector('.char-counter').textContent = '0 / 2000 caracteres';
}

/**
 * Oculta el badge de respuestas de una notificación específica
 * y guarda en localStorage que el usuario ya vio las respuestas
 */
function ocultarBadgeRespuestas(notificacionId) {
    const btnRespuestas = document.querySelector(`button[data-action="respuestas"][data-id="${notificacionId}"]`);
    if (btnRespuestas) {
        const badge = btnRespuestas.querySelector('.badge-respuestas');
        if (badge) {
            // Guardar en localStorage el conteo actual como "ya visto"
            const conteoActual = parseInt(badge.textContent) || 0;
            marcarRespuestasVistas(notificacionId, conteoActual);
            // Ocultar el badge
            badge.style.display = 'none';
        }
    }
}

/**
 * Guarda en localStorage que el usuario ya vio X respuestas de una notificación
 */
function marcarRespuestasVistas(notificacionId, conteo) {
    try {
        const vistas = JSON.parse(localStorage.getItem('respuestas_vistas') || '{}');
        vistas[notificacionId] = conteo;
        localStorage.setItem('respuestas_vistas', JSON.stringify(vistas));
    } catch (e) {
        console.warn('Error guardando respuestas vistas:', e);
    }
}

/**
 * Obtiene cuántas respuestas ya vio el usuario de una notificación
 */
export function obtenerRespuestasVistas(notificacionId) {
    try {
        const vistas = JSON.parse(localStorage.getItem('respuestas_vistas') || '{}');
        return vistas[notificacionId] || 0;
    } catch (e) {
        return 0;
    }
}

/**
 * Cierra el modal de respuestas
 */
function cerrarModalRespuestas() {
    const modal = document.getElementById('modal-respuestas');
    if (modal) {
        modal.style.display = 'none';
        notificacionActual = null;
    }
}

/**
 * Carga información básica de la notificación
 */
async function cargarInfoNotificacion(notificacionId) {
    try {
        const response = await peticionSegura(
            `/gh/notificaciones/api.php?accion=obtener&id=${notificacionId}`,
            { method: 'GET' }
        );
        
        // FIX: La API devuelve 'data', no 'notificacion'
        if (response.success && response.data) {
            const notif = response.data;
            const tituloEl = document.getElementById('respuestas-titulo');
            const descripcionEl = document.getElementById('respuestas-descripcion');
            const infoContainer = document.querySelector('.respuestas-notif-info');
            
            if (tituloEl && descripcionEl) {
                tituloEl.textContent = notif.nombre || '';
                descripcionEl.textContent = notif.cuerpo || '';
                
                // Ocultar si ambos están vacíos
                if (infoContainer) {
                    if (!notif.nombre && !notif.cuerpo) {
                        infoContainer.style.display = 'none';
                    } else {
                        infoContainer.style.display = 'block';
                    }
                }
            }
            
            // Deshabilitar formulario si la notificación está archivada o no permite respuestas
            const formulario = document.querySelector('.respuestas-form');
            const esArchivada = notif.estado === 'archivada';
            const permiteRespuesta = notif.permitir_respuesta == 1 || notif.permitir_respuesta === true;
            
            if (formulario) {
                // Limpiar avisos anteriores
                const avisoExistente = document.getElementById('aviso-no-respuestas');
                if (avisoExistente) avisoExistente.remove();
                
                if (esArchivada) {
                    formulario.style.display = 'none';
                    const container = document.querySelector('.modal-respuestas .modal-body');
                    if (container && !document.getElementById('aviso-no-respuestas')) {
                        const aviso = document.createElement('div');
                        aviso.id = 'aviso-no-respuestas';
                        aviso.className = 'no-respuestas';
                        aviso.innerHTML = '<i class="fas fa-archive"></i><p>Esta notificación está archivada. No se pueden agregar más respuestas.</p>';
                        container.appendChild(aviso);
                    }
                } else if (!permiteRespuesta) {
                    // FIX: Validar si la notificación permite respuestas
                    formulario.style.display = 'none';
                    const container = document.querySelector('.modal-respuestas .modal-body');
                    if (container && !document.getElementById('aviso-no-respuestas')) {
                        const aviso = document.createElement('div');
                        aviso.id = 'aviso-no-respuestas';
                        aviso.className = 'no-respuestas';
                        aviso.innerHTML = '<i class="fas fa-comment-slash"></i><p>Esta notificación no permite respuestas.</p>';
                        container.appendChild(aviso);
                    }
                } else {
                    formulario.style.display = 'block';
                }
            }
        }
    } catch (error) {
        console.error('Error al cargar info de notificación:', error);
    }
}

/**
 * Carga todas las respuestas de una notificación
 */
export async function cargarRespuestas(notificacionId) {
    const container = document.getElementById('lista-respuestas');
    if (!container) return;
    
    container.innerHTML = '<div class="loading-respuestas"><i class="fas fa-spinner fa-spin"></i> Cargando respuestas...</div>';
    
    try {
        const response = await peticionSegura(
            `/gh/notificaciones/api.php?accion=obtener_respuestas&notificacion_id=${notificacionId}`,
            {
                method: 'GET'
            }
        );
        
        if (response.success && response.respuestas) {
            renderizarRespuestas(response.respuestas);
        } else {
            container.innerHTML = '<div class="no-respuestas"><i class="fas fa-inbox"></i><p>No hay respuestas aún. ¡Sé el primero en responder!</p></div>';
        }
    } catch (error) {
        console.error('Error al cargar respuestas:', error);
        container.innerHTML = '<div class="error-respuestas"><i class="fas fa-exclamation-triangle"></i><p>Error al cargar respuestas</p></div>';
    }
}

/**
 * Renderiza las respuestas en el DOM
 */
function renderizarRespuestas(respuestas) {
    const container = document.getElementById('lista-respuestas');
    
    if (respuestas.length === 0) {
        container.innerHTML = '<div class="no-respuestas"><i class="fas fa-inbox"></i><p>No hay respuestas aún. ¡Sé el primero en responder!</p></div>';
        return;
    }
    
    const html = respuestas.map(r => {
        const tieneArchivos = r.archivos && r.archivos.length > 0;
        const esPropia = parseInt(r.usuario_id) === parseInt(globalThis.USUARIO_ID);
        
        // Generar HTML para los archivos
        let archivosHTML = '';
        if (tieneArchivos) {
            archivosHTML = '<div class="respuesta-archivos">';
            r.archivos.forEach(archivo => {
                // FIX Bug #1: URL correcta para descargar archivos de respuestas
                archivosHTML += `
                    <div class="respuesta-archivo">
                        <i class="fas fa-file-download"></i>
                        <a href="/gh/notificaciones/descargar_archivo.php?tipo=respuesta&ruta=${encodeURIComponent(archivo.ruta)}&nombre=${encodeURIComponent(archivo.nombre)}" target="_blank">
                            ${escaparHTML(archivo.nombre)}
                        </a>
                    </div>
                `;
            });
            archivosHTML += '</div>';
        }
        
        return `
            <div class="respuesta-item ${esPropia ? 'respuesta-propia' : ''}">
                <div class="respuesta-header">
                    <div class="respuesta-usuario">
                        <i class="fas fa-user-circle"></i>
                        <strong>${escaparHTML(r.usuario_nombre)}</strong>
                        ${esPropia ? '<span class="badge-tu">Tú</span>' : ''}
                    </div>
                    <div class="respuesta-meta">
                        <span class="respuesta-fecha">${formatearFecha(r.fecha_respuesta)}</span>
                        ${globalThis.ES_ADMIN ? `
                            <button class="btn-icon btn-eliminar-respuesta" data-respuesta-id="${r.id}" title="Eliminar respuesta">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
                <div class="respuesta-body">
                    <p>${escaparHTML(r.respuesta)}</p>
                    ${archivosHTML}
                </div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = html;
}

/**
 * Envía una nueva respuesta
 */
async function enviarRespuesta(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const btnSubmit = form.querySelector('button[type="submit"]');
    const textoOriginal = btnSubmit.innerHTML;
    
    try {
        // Validar archivos
        const archivos = document.getElementById('respuesta-archivo').files;
        if (archivos.length > 5) {
            await Swal.fire({
                icon: 'error',
                title: 'Demasiados archivos',
                text: 'Máximo 5 archivos por respuesta',
                customClass: { container: 'swal-over-modal' }
            });
            return;
        }
        
        // Validar tamaño de cada archivo
        for (let i = 0; i < archivos.length; i++) {
            if (archivos[i].size > 10 * 1024 * 1024) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Archivo muy grande',
                    text: `El archivo "${archivos[i].name}" supera los 10MB`,
                    customClass: { container: 'swal-over-modal' }
                });
                return;
            }
}
        
        // Agregar CSRF token (tomar del form actual como respaldo)
        const csrfToken = globalThis.NotificacionesState?.csrf_token || 
                         form.querySelector('input[name="csrf_token"]')?.value || '';
        formData.append('csrf_token', csrfToken);
        
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        
        // Enviar con fetch directo para soportar FormData con archivo
        const fetchResponse = await fetch('/gh/notificaciones/api.php?accion=responder_notificacion', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        });
        
        const response = await fetchResponse.json();
        
        if (response.success) {
            // Limpiar form
            form.reset();
            const preview = document.getElementById('archivos-preview');
            if (preview) {
                preview.innerHTML = '';
            }
            const contador = document.querySelector('.char-counter');
            if (contador) {
                contador.textContent = '0 / 2000 caracteres';
            }
            
            // FIX #6: Recargar respuestas para mostrar la nueva (no cerrar modal)
            await cargarRespuestas(notificacionActual);
            
            // Notificación toast de éxito (no modal bloqueante)
            Swal.fire({
                icon: 'success',
                title: 'Respuesta enviada',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                customClass: { container: 'swal-over-modal' }
            });
        } else {
            throw new Error(response.error || 'Error al enviar respuesta');
        }
    } catch (error) {
        console.error('Error al enviar respuesta:', error);
        await Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'No se pudo enviar la respuesta',
            customClass: { container: 'swal-over-modal' }
        });
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = textoOriginal;
    }
}

/**
 * Elimina una respuesta (solo admins)
 */
async function eliminarRespuesta(respuestaId) {
    const result = await Swal.fire({
        title: '¿Eliminar respuesta?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#eb0045',
        cancelButtonColor: '#404e62',
        reverseButtons: true,
        customClass: { container: 'swal-over-modal' }
    });
    
    if (!result.isConfirmed) return;
    
    try {
        const formData = new FormData();
        formData.append('respuesta_id', respuestaId);
        
        // Obtener CSRF token del input hidden en el formulario
        const form = document.getElementById('form-respuesta');
        const csrfToken = form?.querySelector('input[name="csrf_token"]')?.value || 
                         globalThis.NotificacionesState?.csrf_token || '';
        
        formData.append('csrf_token', csrfToken);
        
        const response = await peticionSegura(
            '/gh/notificaciones/api.php?accion=eliminar_respuesta',
            {
                method: 'POST',
                body: formData
            }
        );
        
        if (response.success) {
            mostrarExito('Respuesta eliminada correctamente', true);
            await cargarRespuestas(notificacionActual);
        } else {
            throw new Error(response.error || 'Error al eliminar respuesta');
        }
    } catch (error) {
        console.error('Error al eliminar respuesta:', error);
        mostrarError(`Error al eliminar respuesta: ${error.message}`, true);
    }
}

/**
 * Valida el archivo seleccionado
 */
function validarArchivo(e) {
    const files = e.target.files;
    const preview = document.getElementById('archivos-preview');
    const maxFiles = 5;
    const maxSize = 10 * 1024 * 1024; // 10MB
    
    if (!preview || files.length === 0) return;
    
    // Validar cantidad
    if (files.length > maxFiles) {
        preview.innerHTML = `<div class="file-error"><i class="fas fa-exclamation-triangle"></i> Máximo ${maxFiles} archivos permitidos</div>`;
        e.target.value = '';
        return;
    }
    
    const extensionesPermitidas = [
        '.pdf', '.doc', '.docx', '.xls', '.xlsx', 
        '.jpg', '.jpeg', '.png', '.txt', '.zip', '.rar'
    ];
    
    // Validar cada archivo
    let html = '';
    let hasError = false;
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const extension = '.' + file.name.split('.').pop().toLowerCase();
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        
        let status = 'ok';
        let errorMsg = '';
        
        if (file.size > maxSize) {
            status = 'error';
            errorMsg = 'Excede 10MB';
            hasError = true;
        } else if (!extensionesPermitidas.includes(extension)) {
            status = 'error';
            errorMsg = 'Formato no permitido';
            hasError = true;
        }
        
        html += `
            <div class="file-preview-item ${status === 'error' ? 'file-error' : ''}">
                <i class="fas fa-${status === 'error' ? 'exclamation-circle' : 'file'}"></i>
                <span class="file-name">${file.name}</span>
                <span class="file-size">${sizeMB} MB</span>
                ${status === 'error' ? `<span class="error-msg">${errorMsg}</span>` : '<i class="fas fa-check-circle file-ok"></i>'}
            </div>
        `;
    }
    
    preview.innerHTML = html;
    
    if (hasError) {
        setTimeout(() => {
            e.target.value = '';
            preview.innerHTML = '<div class="file-error"><i class="fas fa-exclamation-triangle"></i> Algunos archivos no son válidos</div>';
        }, 3000);
    }
}
