/**
 * USERS-SELECTOR - Módulo de Selección de Usuarios
 * Maneja la selección de usuarios específicos para notificaciones
 */

import { NotificacionesConfig, NotificacionesState } from './core.js';
import { peticionSegura, escaparHTML } from './security.js';
import { mostrarError } from './utils.js';
import { cargarNotificaciones } from './crud.js';

// =====================================================
// FILTROS RÁPIDOS
// =====================================================

export function aplicarFiltroRapido(tipo) {
    // Actualizar botones activos
    for (const btn of document.querySelectorAll('.filtro-btn')) {
        btn.classList.remove('active');
    }
    document.querySelector(`[data-filtro="${tipo}"]`).classList.add('active');
    
    // Aplicar filtro
    const filtros = {};
    if (tipo !== 'todas') {
        filtros.estado = tipo === 'activas' ? 'activa' : 'archivada';
    }
    
    cargarNotificaciones(filtros);
}

// =====================================================
// GESTIÓN DE DESTINO
// =====================================================

export function manejarCambioDestino() {
    const destino = NotificacionesState.elements.selectDestino.value;
    const container = NotificacionesState.elements.usuariosEspecificosContainer;
    
    if (destino === 'especificos') {
        container.style.display = 'block';
        cargarUsuarios();
    } else {
        container.style.display = 'none';
        limpiarSeleccionUsuarios();
    }
}

// =====================================================
// CARGA Y RENDERIZADO DE USUARIOS
// =====================================================

export async function cargarUsuarios() {
    try {
        const listaContainer = NotificacionesState.elements.usuariosLista;
        listaContainer.innerHTML = '<div class="usuarios-loading"><i class="fas fa-spinner fa-spin"></i> Cargando usuarios...</div>';
        
        const response = await peticionSegura(NotificacionesConfig.endpoints.obtener_usuarios);
        
        if (response.success && response.usuarios) {
            NotificacionesState.usuariosDisponibles = response.usuarios;
            renderizarUsuarios(response.usuarios);
        } else {
            throw new Error(response.error || 'Error al cargar usuarios');
        }
    } catch (error) {
        console.error('Error al cargar usuarios:', error);
        NotificacionesState.elements.usuariosLista.innerHTML = 
            '<div class="usuarios-error"><i class="fas fa-exclamation-triangle"></i> Error al cargar usuarios</div>';
        mostrarError('Error al cargar la lista de usuarios: ' + error.message);
    }
}

function renderizarUsuarios(usuarios) {
    const listaContainer = NotificacionesState.elements.usuariosLista;
    
    if (!usuarios || usuarios.length === 0) {
        listaContainer.innerHTML = '<div class="usuarios-empty">No hay usuarios disponibles</div>';
        return;
    }
    
    const html = usuarios.map(usuario => `
        <div class="usuario-item" data-user-id="${usuario.id}">
            <input type="checkbox" 
                   id="user-${usuario.id}" 
                   value="${usuario.id}">
            <label for="user-${usuario.id}" class="usuario-label">
                <div class="usuario-info">
                    <div class="usuario-nombre">${escaparHTML(usuario.nombre)} ${escaparHTML(usuario.apellido)}</div>
                    <div class="usuario-email">${escaparHTML(usuario.email)}</div>
                    ${usuario.cargo ? `<div class="usuario-cargo">${escaparHTML(usuario.cargo)}</div>` : ''}
                </div>
                <span class="usuario-rol badge ${usuario.rol}">${usuario.rol === 'admin' ? 'Admin' : 'Usuario'}</span>
            </label>
        </div>
    `).join('');
    
    listaContainer.innerHTML = html;
    
    // Usar event delegation
    listaContainer.addEventListener('change', function(e) {
        if (e.target.type === 'checkbox' && e.target.id.startsWith('user-')) {
            const userId = Number.parseInt(e.target.value);
            toggleUsuario(userId);
        }
    });
}

// =====================================================
// FILTRADO DE USUARIOS
// =====================================================

export function filtrarUsuarios() {
    const termino = NotificacionesState.elements.buscarUsuarios?.value?.toLowerCase();
    const usuarios = NotificacionesState.usuariosDisponibles;
    
    if (!usuarios || usuarios.length === 0) {
        return;
    }
    
    if (!termino || termino.length < 2) {
        renderizarUsuarios(usuarios);
        return;
    }
    
    const usuariosFiltrados = usuarios.filter(usuario => {
        const nombre = (usuario.nombre || '').toLowerCase();
        const apellido = (usuario.apellido || '').toLowerCase();
        const email = (usuario.email || '').toLowerCase();
        const cargo = (usuario.cargo || '').toLowerCase();
        
        return nombre.includes(termino) ||
               apellido.includes(termino) ||
               email.includes(termino) ||
               cargo.includes(termino);
    });
    
    renderizarUsuarios(usuariosFiltrados);
}

// =====================================================
// SELECCIÓN DE USUARIOS
// =====================================================

export function toggleUsuario(userId) {
    const checkbox = document.getElementById(`user-${userId}`);
    const usuario = NotificacionesState.usuariosDisponibles.find(u => u.id == userId);
    
    if (checkbox.checked) {
        // Agregar usuario si no está ya seleccionado
        if (!NotificacionesState.usuariosSeleccionados.some(u => u.id == userId)) {
            NotificacionesState.usuariosSeleccionados.push(usuario);
        }
    } else {
        // Remover usuario de seleccionados
        NotificacionesState.usuariosSeleccionados = 
            NotificacionesState.usuariosSeleccionados.filter(u => u.id != userId);
    }
    
    actualizarUsuariosSeleccionados();
}

function actualizarUsuariosSeleccionados() {
    const seleccionados = NotificacionesState.usuariosSeleccionados;
    const countElement = NotificacionesState.elements.countUsuarios;
    const idsInput = NotificacionesState.elements.usuariosIds;
    
    // Actualizar contador
    countElement.textContent = seleccionados.length;
    
    // Actualizar campo oculto con IDs
    idsInput.value = seleccionados.map(u => u.id).join(',');
}

export function limpiarSeleccionUsuarios() {
    NotificacionesState.usuariosSeleccionados = [];
    NotificacionesState.usuariosDisponibles = [];
    
    if (NotificacionesState.elements.usuariosIds) {
        NotificacionesState.elements.usuariosIds.value = '';
    }
    if (NotificacionesState.elements.countUsuarios) {
        NotificacionesState.elements.countUsuarios.textContent = '0';
    }
    if (NotificacionesState.elements.buscarUsuarios) {
        NotificacionesState.elements.buscarUsuarios.value = '';
    }
}
