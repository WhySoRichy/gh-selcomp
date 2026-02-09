/**
 * CRUD - Módulo de Operaciones
 * Maneja operaciones Create, Read, Update, Delete de notificaciones
 */

import { NotificacionesConfig, NotificacionesState } from './core.js';
import { peticionSegura, escaparHTML } from './security.js';
import { validarArchivo, getIconoArchivo, formatFileSize, limpiarArchivosExistentes, mostrarArchivosExistentes } from './files.js';
import { mostrarError, mostrarExito, formatearFechaCompleta, formatearFecha, getDestinoTexto } from './utils.js';
import { abrirModal, cerrarModales } from './modals.js';
import { mostrarEstado, renderizarNotificaciones } from './ui.js';

// =====================================================
// CARGA DE DATOS
// =====================================================

export async function cargarNotificaciones(filtros = {}) {
    try {
        mostrarEstado('loading');
        
        const params = new URLSearchParams(filtros);
        const url = NotificacionesConfig.endpoints.listar + (params.toString() ? '&' + params.toString() : '');
        
        const data = await peticionSegura(url);
        
        if (data?.success) {
            NotificacionesState.notificaciones = data.data || [];
            renderizarNotificaciones(NotificacionesState.notificaciones);
        } else {
            console.error('❌ Respuesta inválida:', data);
            throw new Error(data?.error || 'Error al cargar notificaciones');
        }
        
    } catch (error) {
        console.error('💥 Error completo:', error);
        mostrarError('Error al cargar las notificaciones: ' + error.message);
        mostrarEstado('empty');
    }
}

export async function actualizarEstadisticas() {
    try {
        const data = await peticionSegura(NotificacionesConfig.endpoints.estadisticas);
        
        if (data.success && data.data) {
            const stats = data.data;
            
            if (NotificacionesState.elements.totalNotificaciones) {
                NotificacionesState.elements.totalNotificaciones.textContent = stats.total || 0;
            }
            if (NotificacionesState.elements.notificacionesActivas) {
                NotificacionesState.elements.notificacionesActivas.textContent = stats.activas || 0;
            }
            if (NotificacionesState.elements.notificacionesArchivadas) {
                NotificacionesState.elements.notificacionesArchivadas.textContent = stats.archivadas || 0;
            }
        }
    } catch (error) {
        console.error('Error al actualizar estadísticas:', error);
    }
}

export async function recargarDatos() {
    try {
        await cargarNotificaciones();
    } catch (error) {
        console.error('❌ Error al recargar datos:', error);
        mostrarError('Error al actualizar los datos');
    }
}

export async function cargarNotificacionCompleta(id) {
    try {
        const url = `${NotificacionesConfig.endpoints.obtener}&id=${id}`;
        
        const data = await peticionSegura(url);
        
        if (data?.success) {
            return data.data;
        } else {
            throw new Error(data?.error || 'Error al cargar notificación');
        }
    } catch (error) {
        console.error('Error al cargar notificación completa:', error);
        throw new Error('Error al comunicarse con el servidor: ' + error.message);
    }
}

// =====================================================
// VALIDACIÓN Y ENVÍO DE FORMULARIO
// =====================================================

function validarFormularioNotificacion(formData) {
    const nombre = formData.get('nombre')?.trim();
    const cuerpo = formData.get('cuerpo')?.trim();
    const destino = formData.get('destino');
    
    if (!nombre || !cuerpo || !destino) {
        return { valido: false, error: 'El nombre, mensaje y destino son campos obligatorios' };
    }
    if (nombre.length < 2) {
        return { valido: false, error: 'El nombre debe tener al menos 2 caracteres' };
    }
    if (cuerpo.length < 5) {
        return { valido: false, error: 'El mensaje debe tener al menos 5 caracteres' };
    }
    
    if (destino === 'especificos') {
        const usuariosIds = formData.get('usuarios_ids');
        if (!usuariosIds || usuariosIds.trim() === '') {
            return { valido: false, error: 'Debe seleccionar al menos un usuario específico' };
        }
    }
    
    const fileInput = document.getElementById('archivo');
    if (fileInput?.files && fileInput.files.length > 0) {
        if (fileInput.files.length > 10) {
            return { valido: false, error: 'Máximo 10 archivos permitidos por notificación' };
        }
        
        for (const file of fileInput.files) {
            const errores = validarArchivo(file);
            if (errores.length > 0) {
                return { valido: false, error: `${file.name}: ${errores[0]}` };
            }
        }
    }
    
    return { valido: true };
}

export async function enviarFormularioNotificacion(event) {
    event.preventDefault();
    
    try {
        const botonEnviar = NotificacionesState.elements.enviarNotificacion;
        if (botonEnviar) {
            botonEnviar.disabled = true;
            // Cambiar solo el icono y texto sin destruir la estructura del botón
            const icono = botonEnviar.querySelector('i');
            const textoBoton = botonEnviar.querySelector('#texto-boton');
            if (icono) icono.className = 'fas fa-spinner fa-spin';
            if (textoBoton) textoBoton.textContent = 'Procesando...';
        }
        
        const formData = new FormData(NotificacionesState.elements.formNotificacion);
        const notificacionId = document.getElementById('notificacion-id')?.value;
        
        const archivos = NotificacionesState.archivosSeleccionados;
        if (archivos && archivos.length > 0) {
            formData.delete('archivos[]');
            for (const archivo of archivos) {
                formData.append('archivos[]', archivo);
            }
        } else {
            const fileInput = document.getElementById('archivo');
            if (fileInput?.files && fileInput.files.length > 0) {
                for (const file of fileInput.files) {
                    formData.append('archivos[]', file);
                }
            }
        }
        
        const validacion = validarFormularioNotificacion(formData);
        if (!validacion.valido) {
            mostrarError(validacion.error, true);
            return;
        }
        
        const endpoint = notificacionId ? 
            NotificacionesConfig.endpoints.actualizar : 
            NotificacionesConfig.endpoints.crear;
        
        const response = await peticionSegura(endpoint, {
            method: 'POST',
            body: formData
        });
        
        if (response.success) {
            cerrarModales();
            
            // Recargar datos inmediatamente sin delay
            try {
                await recargarDatos();
                await actualizarEstadisticas();
            } catch (e) {
                console.warn('Error al recargar datos:', e);
            }
            
            mostrarExito(response.message || (notificacionId ? 'Notificación actualizada' : 'Notificación creada'), true);
        } else {
            mostrarError(response.error || 'Error al procesar la notificación', true);
        }
        
    } catch (error) {
        console.error('Error al enviar formulario:', error);
        mostrarError(error.message || 'Error al procesar el formulario', true);
    } finally {
        const botonEnviar = NotificacionesState.elements.enviarNotificacion;
        if (botonEnviar) {
            botonEnviar.disabled = false;
            
            const icono = botonEnviar.querySelector('i');
            if (icono) {
                icono.className = 'fas fa-paper-plane';
            }
            
            const textoBoton = botonEnviar.querySelector('#texto-boton');
            if (textoBoton) {
                const esActualizacion = document.getElementById('notificacion-id')?.value;
                textoBoton.textContent = esActualizacion ? 'Actualizar' : 'Crear Notificación';
            }
        }
    }
}

// =====================================================
// OPERACIONES INDIVIDUALES
// =====================================================

export function verNotificacion(id) {
    const notificacion = NotificacionesState.notificaciones.find(n => n.id === id);
    
    if (!notificacion) {
        mostrarError('Notificación no encontrada');
        return;
    }
    
    cargarNotificacionCompleta(id).then(notifCompleta => {
        const contenidoElement = document.getElementById('notif-detalle-contenido');
        
        const contenido = `
            <div class="notification-detail-modern">
                <div class="detail-card">
                    <div class="card-header-detail">
                        <i class="fas fa-info-circle"></i>
                        <h3>Información General</h3>
                    </div>
                    <div class="card-body-detail">
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-label">
                                    <i class="fas fa-tag"></i>
                                    <span>Nombre</span>
                                </div>
                                <div class="info-value">${escaparHTML(notifCompleta.nombre)}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">
                                    <i class="fas fa-signal"></i>
                                    <span>Estado</span>
                                </div>
                                <div class="info-value">
                                    <span class="status-badge status-${notifCompleta.estado}">
                                        <i class="fas fa-circle"></i>
                                        ${notifCompleta.estado.toUpperCase()}
                                    </span>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Prioridad</span>
                                </div>
                                <div class="info-value">
                                    <span class="priority-badge priority-${notifCompleta.prioridad}">
                                        <i class="fas fa-flag"></i>
                                        ${notifCompleta.prioridad.toUpperCase()}
                                    </span>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">
                                    <i class="fas fa-users"></i>
                                    <span>Destino</span>
                                </div>
                                <div class="info-value">${getDestinoTexto(notifCompleta.destino)}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">
                                    <i class="fas fa-calendar-plus"></i>
                                    <span>Fecha de creación</span>
                                </div>
                                <div class="info-value">${formatearFechaCompleta(notifCompleta.fecha_creacion)}</div>
                            </div>
                            ${notifCompleta.fecha_actualizacion ? `
                            <div class="info-row">
                                <div class="info-label">
                                    <i class="fas fa-calendar-check"></i>
                                    <span>Última actualización</span>
                                </div>
                                <div class="info-value">${formatearFechaCompleta(notifCompleta.fecha_actualizacion)}</div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
                
                <div class="detail-card">
                    <div class="card-header-detail">
                        <i class="fas fa-envelope-open-text"></i>
                        <h3>Contenido del Mensaje</h3>
                    </div>
                    <div class="card-body-detail">
                        <div class="message-box">${escaparHTML(notifCompleta.cuerpo)}</div>
                    </div>
                </div>
                
                ${notifCompleta.archivos && notifCompleta.archivos.length > 0 ? `
                <div class="detail-card">
                    <div class="card-header-detail">
                        <i class="fas fa-paperclip"></i>
                        <h3>Archivos Adjuntos <span class="file-count">(${notifCompleta.archivos.length})</span></h3>
                    </div>
                    <div class="card-body-detail">
                        <div class="files-list">
                            ${notifCompleta.archivos.map(archivo => `
                                <div class="file-item">
                                    <div class="file-icon">
                                        <i class="${getIconoArchivo(archivo.nombre_original)}" style="color: #eb0045; font-size: 1.5rem;"></i>
                                    </div>
                                    <div class="file-info">
                                        <div class="file-name" title="${escaparHTML(archivo.nombre_original)}">
                                            ${escaparHTML(archivo.nombre_original)}
                                        </div>
                                        <div class="file-meta">
                                            <span class="file-size">
                                                <i class="fas fa-hdd"></i>
                                                ${formatFileSize(archivo.tamano)}
                                            </span>
                                            <span class="file-date">
                                                <i class="fas fa-clock"></i>
                                                ${formatearFecha(archivo.fecha_subida)}
                                            </span>
                                        </div>
                                    </div>
                                    <a href="descargar_archivo.php?id=${archivo.id}" 
                                       class="file-download-btn" 
                                       title="Descargar ${escaparHTML(archivo.nombre_original)}" 
                                       target="_blank">
                                        <i class="fas fa-download"></i>
                                        <span>Descargar</span>
                                    </a>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
                ` : '<div class="detail-card"><div class="card-body-detail"><div class="no-files"><i class="fas fa-folder-open"></i><p>No hay archivos adjuntos</p></div></div></div>'}
            </div>
        `;
        
        contenidoElement.innerHTML = contenido;
        
        const tituloElement = document.getElementById('ver-titulo');
        tituloElement.innerHTML = `<i class="fas fa-eye"></i> ${escaparHTML(notifCompleta.nombre)}`;
        
        const modal = NotificacionesState.elements.modalVerNotificacion;
        modal.dataset.currentNotificationId = notifCompleta.id;
        
        abrirModal(modal);
        
    }).catch(error => {
        console.error('Error al cargar notificación completa:', error);
        mostrarError('Error al cargar los detalles de la notificación');
    });
}

export function editarNotificacion(id) {
    const notificacion = NotificacionesState.notificaciones.find(n => n.id === id);
    
    if (!notificacion) {
        mostrarError('Notificación no encontrada');
        return;
    }
    
    const modal = NotificacionesState.elements.modalNotificacion;
    if (!modal) {
        mostrarError('Modal no disponible');
        return;
    }
    
    abrirModal(modal);
    
    if (NotificacionesState.elements.modalTitulo) {
        NotificacionesState.elements.modalTitulo.innerHTML = '<i class="fas fa-edit"></i> Editar Notificación';
    }
    if (NotificacionesState.elements.textoBoton) {
        NotificacionesState.elements.textoBoton.textContent = 'Actualizar';
    }
    
    // Restaurar estado del botón enviar
    const botonEnviar = NotificacionesState.elements.enviarNotificacion;
    if (botonEnviar) {
        botonEnviar.disabled = false;
        const icono = botonEnviar.querySelector('i');
        if (icono) {
            icono.className = 'fas fa-paper-plane';
        }
    }
    
    const idInput = document.getElementById('notificacion-id');
    if (idInput) idInput.value = notificacion.id;
    
    if (NotificacionesState.elements.inputNombre) {
        NotificacionesState.elements.inputNombre.value = notificacion.nombre || '';
    }
    if (NotificacionesState.elements.textareaCuerpo) {
        NotificacionesState.elements.textareaCuerpo.value = notificacion.cuerpo || '';
    }
    if (NotificacionesState.elements.selectDestino) {
        NotificacionesState.elements.selectDestino.value = notificacion.destino || 'todos';
    }
    if (NotificacionesState.elements.selectPrioridad) {
        NotificacionesState.elements.selectPrioridad.value = notificacion.prioridad || 'media';
    }
    if (NotificacionesState.elements.checkboxPermitirRespuesta) {
        NotificacionesState.elements.checkboxPermitirRespuesta.checked = notificacion.permitir_respuesta === 1 || notificacion.permitir_respuesta === true;
    }
    
    setTimeout(() => {
        NotificacionesState.archivosSeleccionados = null;
        
        cargarNotificacionCompleta(id)
            .then(notifCompleta => {
                if (notifCompleta?.archivos && Array.isArray(notifCompleta.archivos) && notifCompleta.archivos.length > 0) {
                    mostrarArchivosExistentes(notifCompleta.archivos);
                } else {
                    limpiarArchivosExistentes();
                }
            })
            .catch(error => {
                console.error('Error al cargar archivos:', error);
                limpiarArchivosExistentes();
            });
    }, 400);
    
    if (NotificacionesState.elements.inputNombre) {
        NotificacionesState.elements.inputNombre.focus();
    }
}

export async function eliminarNotificacion(id) {
    try {
        const result = await Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#eb0045',
            cancelButtonColor: '#404e62',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });
        
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', NotificacionesState.csrf_token);
            
            const data = await peticionSegura(
                NotificacionesConfig.endpoints.eliminar,
                { method: 'POST', body: formData }
            );
            
            if (data.success) {
                mostrarExito('Notificación eliminada correctamente');
                await recargarDatos();
                await actualizarEstadisticas();
            } else {
                throw new Error(data.error || 'Error al eliminar');
            }
        }
    } catch (error) {
        console.error('Error al eliminar notificación:', error);
        mostrarError('Error al eliminar la notificación: ' + error.message);
    }
}

export async function cambiarEstado(id, nuevoEstado) {
    try {
        const accion = nuevoEstado === 'activa' ? 'activar' : 'archivar';
        
        const result = await Swal.fire({
            title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} notificación?`,
            text: `¿Estás seguro que deseas ${accion} esta notificación?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#eb0045',
            cancelButtonColor: '#404e62',
            confirmButtonText: `Sí, ${accion}`,
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });
        
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('estado', nuevoEstado);
            formData.append('csrf_token', NotificacionesState.csrf_token);
            
            const data = await peticionSegura(
                NotificacionesConfig.endpoints.cambiar_estado,
                { method: 'POST', body: formData }
            );
            
            if (data.success) {
                mostrarExito(data.message || `Notificación ${accion}da correctamente`);
                await recargarDatos();
                await actualizarEstadisticas();
            } else {
                throw new Error(data.error || `Error al ${accion}`);
            }
        }
    } catch (error) {
        console.error('Error al cambiar estado:', error);
        mostrarError(`Error al cambiar el estado: ${error.message}`);
    }
}

export async function duplicarNotificacion(id) {
    try {
        const result = await Swal.fire({
            title: '¿Duplicar notificación?',
            text: 'Se creará una copia de esta notificación con todos sus archivos adjuntos',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, duplicar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#eb0045',
            cancelButtonColor: '#404e62',
            reverseButtons: true
        });
        
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Duplicando...',
                text: 'Por favor espera mientras se duplica la notificación',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
            
            const formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', NotificacionesState.csrf_token);
            
            const data = await peticionSegura(
                NotificacionesConfig.endpoints.duplicar,
                { method: 'POST', body: formData }
            );
            
            if (data.success) {
                let mensaje = data.message || 'Notificación duplicada correctamente';
                
                if (data.archivos_copiados !== undefined) {
                    mensaje += `\nArchivos copiados: ${data.archivos_copiados}/${data.total_archivos}`;
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'Duplicación exitosa',
                    text: mensaje,
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#eb0045'
                });
                
                await recargarDatos();
                await actualizarEstadisticas();
            } else {
                throw new Error(data.error || 'Error al duplicar');
            }
        }
    } catch (error) {
        console.error('Error al duplicar notificación:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error al duplicar',
            text: `No se pudo duplicar la notificación: ${error.message}`,
            confirmButtonText: 'Aceptar',
            confirmButtonColor: '#eb0045'
        });
    }
}
