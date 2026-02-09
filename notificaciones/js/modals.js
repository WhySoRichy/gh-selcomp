/**
 * MODALS - Módulo de Gestión de Modales
 * Maneja apertura, cierre y limpieza de modales
 */

import { NotificacionesState } from './core.js';
import { resetearEstadoArchivos } from './files.js';

// =====================================================
// APERTURA Y CIERRE
// =====================================================

export function abrirModal(modalElement) {
    if (!modalElement) return;
    
    // Agregar clase no-scroll al body
    document.body.classList.add('modal-open', 'no-scroll');
    
    // Mostrar modal
    modalElement.style.display = 'flex';
    modalElement.classList.add('show');
    
    // Trigger animación
    const modalContent = modalElement.querySelector('.modal-content');
    if (modalContent) {
        modalContent.classList.add('animate-in');
    }
}

export function cerrarModales() {
    const modales = document.querySelectorAll('.modal');
    for (const modal of modales) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        
        // Limpiar dataset si existe
        if (modal.dataset.currentNotificationId) {
            delete modal.dataset.currentNotificationId;
        }
    }
    
    // Limpiar formulario al cerrar modal
    if (NotificacionesState.elements.formNotificacion) {
        NotificacionesState.elements.formNotificacion.reset();
        
        // Limpiar ID hidden
        const idInput = document.getElementById('notificacion-id');
        if (idInput) {
            idInput.value = '';
        }
        
        // Restaurar título y botón por defecto
        if (NotificacionesState.elements.modalTitulo) {
            NotificacionesState.elements.modalTitulo.innerHTML = '<i class="fas fa-plus"></i> Nueva Notificación';
        }
        if (NotificacionesState.elements.textoBoton) {
            NotificacionesState.elements.textoBoton.textContent = 'Crear Notificación';
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
    }
    
    // Resetear completamente el estado de archivos
    resetearEstadoArchivos();
    
    // Limpiar usuarios específicos si existe el selector
    const usuariosIdsInput = document.getElementById('usuarios-ids');
    if (usuariosIdsInput) {
        usuariosIdsInput.value = '';
    }
    
    // Resetear checkboxes de usuarios
    const checkboxesUsuarios = document.querySelectorAll('.usuario-checkbox');
    checkboxesUsuarios.forEach(cb => cb.checked = false);
    
    // Ocultar contenedor de usuarios específicos
    const contenedorUsuarios = document.getElementById('usuarios-especificos-container');
    if (contenedorUsuarios) {
        contenedorUsuarios.style.display = 'none';
    }
    
    // Limpiar información de archivos existentes en edición
    const infoExistente = document.querySelector('.archivos-existentes-info');
    if (infoExistente) {
        infoExistente.remove();
    }
    
    // Permitir scroll del body nuevamente
    document.body.classList.remove('modal-open', 'no-scroll');
}

// =====================================================
// MODAL DE CREACIÓN
// =====================================================

export function abrirModalCrear() {
    const modal = NotificacionesState.elements.modalNotificacion;
    if (modal) {
        abrirModal(modal);
        
        // Configurar modal para crear
        if (NotificacionesState.elements.modalTitulo) {
            NotificacionesState.elements.modalTitulo.innerHTML = '<i class="fas fa-plus"></i> Nueva Notificación';
        }
        if (NotificacionesState.elements.textoBoton) {
            NotificacionesState.elements.textoBoton.textContent = 'Crear Notificación';
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
        
        // Limpiar formulario
        if (NotificacionesState.elements.formNotificacion) {
            NotificacionesState.elements.formNotificacion.reset();
            // Limpiar ID oculto para indicar que es creación
            const idInput = document.getElementById('notificacion-id');
            if (idInput) idInput.value = '';
        }
        
        // Limpiar información de archivos existentes
        const infoExistente = document.querySelector('.archivos-existentes-info');
        if (infoExistente) {
            infoExistente.remove();
        }
        
        // Enfocar primer campo
        if (NotificacionesState.elements.inputNombre) {
            NotificacionesState.elements.inputNombre.focus();
        }
    }
}
