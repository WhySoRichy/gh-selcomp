/**
 * FILES - Módulo de Manejo de Archivos
 * Gestiona subida, validación y display de archivos adjuntos
 */

import { NotificacionesConfig, NotificacionesState } from './core.js';
import { escaparHTML } from './security.js';

// =====================================================
// INICIALIZACIÓN
// =====================================================

export function initFileUpload() {
    const fileInput = document.getElementById('archivo');
    const fileContainer = document.querySelector('.file-upload-container');
    
    if (!fileInput || !fileContainer) return;
    
    // Crear estructura HTML si no existe
    if (!fileContainer.querySelector('.file-upload-area')) {
        fileContainer.innerHTML = `
            <div class="file-upload-area">
                <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                <div class="file-upload-text">Seleccionar archivo o arrastrarlo aquí</div>
                <div class="file-upload-subtext">Haga clic o arrastre el archivo a esta área</div>
            </div>
            <div class="file-upload-info">
                <p>Formatos permitidos: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG, GIF, TXT</p>
                <p>Tamaño máximo: 10 MB | Recuerde que para cargar mas de 1 archivo deben estar en la misma ubicación</p>
            </div>
        `;
    }
    
    // Event listeners
    fileContainer.addEventListener('click', () => fileInput.click());
    fileContainer.addEventListener('dragover', handleDragOver);
    fileContainer.addEventListener('dragleave', handleDragLeave);
    fileContainer.addEventListener('drop', handleFileDrop);
    fileInput.addEventListener('change', handleFileSelect);
}

// =====================================================
// EVENT HANDLERS - DRAG & DROP
// =====================================================

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--modal-primary)';
    e.currentTarget.style.background = 'rgba(235, 0, 69, 0.05)';
}

function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = '#d1d5db';
    e.currentTarget.style.background = 'white';
}

function handleFileDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const container = e.currentTarget;
    container.style.borderColor = '#d1d5db';
    container.style.background = 'white';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        if (files.length > 10) {
            showErrors(['Máximo 10 archivos permitidos']);
            return;
        }
        
        NotificacionesState.archivosSeleccionados = files;
        updateFileDisplay(files);
    }
}

function handleFileSelect(e) {
    const files = e.target.files;
    if (files && files.length > 0) {
        if (files.length > 10) {
            showErrors(['Máximo 10 archivos permitidos']);
            e.target.value = '';
            return;
        }
        
        NotificacionesState.archivosSeleccionados = files;
        updateFileDisplay(files);
    }
}

// =====================================================
// DISPLAY DE ARCHIVOS
// =====================================================

export function updateFileDisplay(files) {
    const container = document.querySelector('.file-upload-container');
    const area = container?.querySelector('.file-upload-area');
    
    if (!container || !area) return;
    
    const filesArray = Array.from(files || []);
    
    if (filesArray.length > 0) {
        // Validar cada archivo
        let todosValidos = true;
        const errores = [];
        
        filesArray.forEach((file) => {
            const fileErrors = validarArchivo(file);
            if (fileErrors.length > 0) {
                todosValidos = false;
                errores.push(`${file.name}: ${fileErrors.join(', ')}`);
            }
        });
        
        if (!todosValidos) {
            showErrors(errores);
            const fileInput = document.getElementById('archivo');
            if (fileInput) fileInput.value = '';
            return;
        }
        
        // Modo edición: archivos pendientes
        const archivosPendientesList = document.getElementById('archivos-pendientes-list');
        if (archivosPendientesList) {
            archivosPendientesList.innerHTML = filesArray.map(file => {
                const icono = getIconoArchivo(file.name);
                return `
                    <div class="archivo-item-pendiente" style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #fff3e0; border-radius: 8px; border: 1px dashed #ff9800; opacity: 0.7; transition: all 0.2s;">
                        <i class="${icono}" style="font-size: 1.5rem; color: #ff9800; width: 24px; text-align: center;"></i>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 500; color: #333; font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escaparHTML(file.name)}">
                                ${escaparHTML(file.name)}
                            </div>
                            <div style="color: #666; font-size: 0.8rem; margin-top: 2px;">
                                ${formatFileSize(file.size)}
                            </div>
                        </div>
                        <i class="fas fa-clock" style="color: #ff9800; font-size: 1.2rem;" title="Pendiente de guardar"></i>
                    </div>
                `;
            }).join('');
            
            const cantidadGuardados = document.querySelectorAll('.archivo-item-guardado').length;
            const notaInfo = document.querySelector('.archivos-existentes-info small span');
            if (notaInfo) {
                notaInfo.textContent = `Guardados: ${cantidadGuardados} | Pendientes: ${filesArray.length}`;
            }
            
            container.classList.add('has-file');
            const icon = area.querySelector('.file-upload-icon');
            const text = area.querySelector('.file-upload-text');
            const subtext = area.querySelector('.file-upload-subtext');
            
            if (icon) icon.className = 'fas fa-check-circle file-upload-icon';
            if (text) text.textContent = `${filesArray.length} archivo(s) nuevo(s) seleccionado(s)`;
            if (subtext) subtext.textContent = 'Presiona "Actualizar" para guardar los cambios';
            
        } else {
            // Modo creación
            container.classList.add('has-file');
            container.classList.remove('has-error');
            
            const icon = area.querySelector('.file-upload-icon');
            const text = area.querySelector('.file-upload-text');
            const subtext = area.querySelector('.file-upload-subtext');
            
            if (icon) icon.className = 'fas fa-check-circle file-upload-icon';
            
            if (filesArray.length === 1) {
                if (text) text.textContent = filesArray[0].name;
                if (subtext) subtext.textContent = `${formatFileSize(filesArray[0].size)} - Archivo seleccionado correctamente`;
            } else {
                if (text) text.textContent = `${filesArray.length} archivos seleccionados`;
                const totalSize = filesArray.reduce((sum, f) => sum + f.size, 0);
                const fileList = filesArray.map(f => f.name).join(', ');
                if (subtext) {
                    subtext.innerHTML = `
                        <strong>${formatFileSize(totalSize)} total</strong><br>
                        <small style="font-size: 12px; color: #6b7280;">${fileList}</small>
                    `;
                }
            }
        }
    } else {
        // Resetear display
        container.classList.remove('has-file', 'has-error');
        const icon = area.querySelector('.file-upload-icon');
        const text = area.querySelector('.file-upload-text');
        const subtext = area.querySelector('.file-upload-subtext');
        
        if (icon) icon.className = 'fas fa-cloud-upload-alt file-upload-icon';
        if (text) text.textContent = 'Seleccionar archivo o arrastrarlo aquí';
        if (subtext) subtext.textContent = 'Haga clic o arrastre el archivo a esta área';
        
        const archivosPendientesList = document.getElementById('archivos-pendientes-list');
        if (archivosPendientesList) {
            archivosPendientesList.innerHTML = '';
        }
    }
}

// =====================================================
// VALIDACIÓN DE ARCHIVOS (VERSIÓN CONSOLIDADA)
// =====================================================

export function validarArchivo(file) {
    if (!file) {
        console.warn('🔍 Validación: No se proporcionó archivo');
        return ['No se ha seleccionado ningún archivo'];
    }
    
    const errors = [];
    const { max_file_size, allowed_extensions } = NotificacionesConfig.security;
    
    // Validar tamaño
    if (file.size > max_file_size) {
        const errorMsg = `Archivo muy grande. Máximo ${(max_file_size / 1048576).toFixed(1)}MB`;
        console.error('❌ Error tamaño:', errorMsg, `Archivo: ${(file.size / 1048576).toFixed(2)}MB`);
        errors.push(errorMsg);
    }
    
    // Validar extensión
    const extension = file.name.split('.').pop().toLowerCase();
    if (!allowed_extensions.includes(extension)) {
        const errorMsg = `Formato no válido. Permitidos: ${allowed_extensions.join(', ')}`;
        errors.push(errorMsg);
    }
    
    // Validar nombre
    if (file.name.length > 255) {
        const errorMsg = 'Nombre de archivo muy largo (máximo 255 caracteres)';
        console.error('❌ Error nombre:', errorMsg, `Longitud: ${file.name.length}`);
        errors.push(errorMsg);
    }
    
    // Validar caracteres especiales peligrosos
    const caracteresProhibidos = /[\\/*?"<>|]/;
    if (caracteresProhibidos.test(file.name)) {
        const errorMsg = 'El nombre del archivo contiene caracteres no permitidos';
        console.error('❌ Error caracteres:', errorMsg, `Nombre: ${file.name}`);
        errors.push(errorMsg);
    }
    
    if (errors.length > 0) {
        console.error('🚨 Errores de validación:', errors);
    }
    
    return errors;
}

// =====================================================
// MANEJO DE ERRORES
// =====================================================

export function showErrors(errors) {
    if (!errors || !Array.isArray(errors) || errors.length === 0) {
        console.warn('🔍 showErrors: No hay errores para mostrar');
        return;
    }
    
    console.error('🚨 Mostrando errores:', errors);
    
    const container = document.querySelector('.file-upload-container');
    if (container) {
        container.classList.add('has-error');
        container.classList.remove('has-file');
    }
    
    const mensaje = errors.join('\n');
    
    if (globalThis.Swal) {
        Swal.fire({
            icon: 'error',
            title: 'Error en archivo',
            text: mensaje,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#eb0045',
            customClass: { container: 'swal-over-modal' }
        });
    } else {
        alert('Error en archivo:\n' + mensaje);
        console.error('⚠️ SweetAlert2 no disponible, usando alert nativo');
    }
}

// =====================================================
// UTILIDADES
// =====================================================

export function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Number.parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

export function getIconoArchivo(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    const iconMap = {
        pdf: 'fas fa-file-pdf',
        doc: 'fas fa-file-word',
        docx: 'fas fa-file-word',
        xls: 'fas fa-file-excel',
        xlsx: 'fas fa-file-excel',
        jpg: 'fas fa-file-image',
        jpeg: 'fas fa-file-image',
        png: 'fas fa-file-image',
        gif: 'fas fa-file-image',
        txt: 'fas fa-file-alt',
        default: 'fas fa-file'
    };
    return iconMap[ext] || iconMap.default;
}

export function resetearEstadoArchivos() {
    const fileInput = document.getElementById('archivo');
    if (fileInput) {
        fileInput.value = '';
    }
    
    NotificacionesState.archivosSeleccionados = null;
    updateFileDisplay(null);
    
    if (NotificacionesState) {
        NotificacionesState.archivoSeleccionado = null;
    }
    
    // Limpiar archivos existentes
    limpiarArchivosExistentes();
    
    const errorContainer = document.querySelector('.file-upload-errors');
    if (errorContainer) {
        errorContainer.remove();
    }
}

export function limpiarArchivosExistentes() {
    const infoContainer = document.querySelector('.archivos-existentes-info');
    if (infoContainer) {
        infoContainer.remove();
    }
}

export function mostrarArchivosExistentes(archivos) {
    const fileContainer = document.querySelector('.file-upload-container');
    if (!fileContainer) return;
    
    limpiarArchivosExistentes();
    
    const infoContainer = document.createElement('div');
    infoContainer.className = 'archivos-existentes-info';
    
    infoContainer.innerHTML = `
        <div style="margin-bottom: 20px;">
            <div class="archivos-list" id="archivos-guardados-list" style="display: flex; flex-direction: column; gap: 10px;">
                ${archivos.map(archivo => {
                    const icono = getIconoArchivo(archivo.nombre_original);
                    return `
                        <div class="archivo-item-guardado" style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e0e0e0; transition: all 0.2s;">
                            <i class="${icono}" style="font-size: 1.5rem; color: #eb0045; width: 24px; text-align: center;"></i>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 500; color: #333; font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escaparHTML(archivo.nombre_original)}">
                                    ${escaparHTML(archivo.nombre_original)}
                                </div>
                                <div style="color: #666; font-size: 0.8rem; margin-top: 2px;">
                                    ${formatFileSize(archivo.tamano)}
                                </div>
                            </div>
                            <i class="fas fa-check-circle" style="color: #4caf50; font-size: 1.2rem;" title="Guardado"></i>
                        </div>
                    `;
                }).join('')}
            </div>
            <div id="archivos-pendientes-list" style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
            </div>
            <div style="margin-top: 12px; padding: 10px; background: #fff0f3; border-radius: 6px; border-left: 3px solid #eb0045;">
                <small style="color: #c2185b; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-info-circle"></i>
                    <span>Guardados: ${archivos.length}. Puedes agregar más abajo.</span>
                </small>
            </div>
        </div>
    `;
    
    fileContainer.parentNode.insertBefore(infoContainer, fileContainer);
}
