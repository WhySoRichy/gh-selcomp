/**
 * UI - Módulo de Interfaz de Usuario
 * Maneja elementos DOM, event listeners y renderizado
 */

import { NotificacionesState } from './core.js';
import { escaparHTML } from './security.js';
import { formatearFechaCompleta, truncarTexto, getDestinoTexto } from './utils.js';
import { obtenerRespuestasVistas } from './respuestas.js';

// =====================================================
// CACHÉ DE ELEMENTOS DOM
// =====================================================

export function cacheElements() {
    NotificacionesState.elements = {
        // Estados
        loadingState: document.getElementById('loading-state'),
        emptyState: document.getElementById('empty-state'),
        notificacionesGrid: document.getElementById('notificaciones-grid'),
        
        // Estadísticas
        totalNotificaciones: document.getElementById('total-notificaciones'),
        notificacionesActivas: document.getElementById('notificaciones-activas'),
        notificacionesArchivadas: document.getElementById('notificaciones-archivadas'),
        
        // Botones principales
        crearNotificacion: document.getElementById('crear-notificacion'),
        crearPrimeraNotificacion: document.getElementById('crear-primera-notificacion'),
        
        // Filtros rápidos
        filtroTodas: document.querySelector('[data-filtro="todas"]'),
        filtroActivas: document.querySelector('[data-filtro="activas"]'),
        filtroArchivadas: document.querySelector('[data-filtro="archivadas"]'),
        
        // Modales
        modalNotificacion: document.getElementById('modal-notificacion'),
        modalVerNotificacion: document.getElementById('modal-ver-notificacion'),
        
        // Formulario
        formNotificacion: document.getElementById('form-notificacion'),
        modalTitulo: document.getElementById('modal-titulo'),
        textoBoton: document.getElementById('texto-boton'),
        
        // Campos del formulario
        inputNombre: document.getElementById('nombre'),
        textareaCuerpo: document.getElementById('cuerpo'),
        selectDestino: document.getElementById('destino'),
        selectPrioridad: document.getElementById('prioridad'),
        checkboxPermitirRespuesta: document.getElementById('permitir_respuesta'),
        inputArchivo: document.getElementById('archivo'),
        
        // Usuarios específicos
        usuariosEspecificosContainer: document.getElementById('usuarios-especificos-container'),
        usuariosLista: document.getElementById('usuarios-lista'),
        usuariosSeleccionados: document.getElementById('usuarios-seleccionados'),
        buscarUsuarios: document.getElementById('buscar-usuarios'),
        usuariosIds: document.getElementById('usuarios-ids'),
        countUsuarios: document.getElementById('count-usuarios'),
        
        // Botones de modal
        cerrarModal: document.getElementById('cerrar-modal'),
        cancelarModal: document.getElementById('cancelar-modal'),
        enviarNotificacion: document.getElementById('enviar-notificacion'),
        cerrarVerModal: document.getElementById('cerrar-ver-modal'),
        cerrarVerDetalle: document.getElementById('cerrar-ver-detalle'),
        editarDesdeVer: document.getElementById('editar-desde-ver')
    };
}

// =====================================================
// EVENT LISTENERS
// =====================================================

export function setupEventListeners() {
    const { elements } = NotificacionesState;
    
    // Las funciones serán cargadas dinámicamente al hacer click para evitar imports circulares
        
    // Las funciones serán cargadas dinámicamente al hacer click para evitar imports circulares
    
    // Botones principales
    if (elements.crearNotificacion) {
        elements.crearNotificacion.addEventListener('click', async () => {
            const { abrirModalCrear } = await import('./modals.js');
            abrirModalCrear();
        });
    }
    
    if (elements.crearPrimeraNotificacion) {
        elements.crearPrimeraNotificacion.addEventListener('click', async () => {
            const { abrirModalCrear } = await import('./modals.js');
            abrirModalCrear();
        });
    }
    
    // Filtros rápidos
    if (elements.filtroTodas) {
        elements.filtroTodas.addEventListener('click', async () => {
            const { aplicarFiltroRapido } = await import('./users-selector.js');
            aplicarFiltroRapido('todas');
        });
    }
    if (elements.filtroActivas) {
        elements.filtroActivas.addEventListener('click', async () => {
            const { aplicarFiltroRapido } = await import('./users-selector.js');
            aplicarFiltroRapido('activas');
        });
    }
    if (elements.filtroArchivadas) {
        elements.filtroArchivadas.addEventListener('click', async () => {
            const { aplicarFiltroRapido } = await import('./users-selector.js');
            aplicarFiltroRapido('archivadas');
        });
    }
    
    // Selector de destino
    if (elements.selectDestino) {
        elements.selectDestino.addEventListener('change', async () => {
            const { manejarCambioDestino } = await import('./users-selector.js');
            manejarCambioDestino();
        });
    }
    
    // Búsqueda de usuarios específicos (con debounce)
    if (elements.buscarUsuarios) {
        let timeoutBusqueda;
        elements.buscarUsuarios.addEventListener('input', async function() {
            clearTimeout(timeoutBusqueda);
            timeoutBusqueda = setTimeout(async () => {
                const { filtrarUsuarios } = await import('./users-selector.js');
                filtrarUsuarios();
            }, 300);
        });
    }
    
    // Formulario de notificación
    if (elements.formNotificacion) {
        elements.formNotificacion.addEventListener('submit', async (e) => {
            e.preventDefault(); // Prevenir envío antes del import dinámico
            const { enviarFormularioNotificacion } = await import('./crud.js');
            enviarFormularioNotificacion(e);
        });
    }
    
    // Botones de cerrar modales
    const setupCloseButton = (element) => {
        if (element) {
            element.addEventListener('click', async () => {
                const { cerrarModales } = await import('./modals.js');
                cerrarModales();
            });
        }
    };
    
    setupCloseButton(elements.cerrarModal);
    setupCloseButton(elements.cancelarModal);
    setupCloseButton(elements.cerrarVerModal);
    setupCloseButton(elements.cerrarVerDetalle);
    
    // Event delegation para botones de notificaciones
    if (elements.notificacionesGrid) {
        elements.notificacionesGrid.addEventListener('click', async function(event) {
            const boton = event.target.closest('.btn-notificacion');
            if (!boton) return;
            
            event.preventDefault();
            event.stopPropagation();
            
            const accion = boton.dataset.action;
            const id = Number.parseInt(boton.dataset.id, 10);
            
            if (!id || !accion) return;
            
            const { verNotificacion, editarNotificacion, duplicarNotificacion, cambiarEstado, eliminarNotificacion } = await import('./crud.js');
            
            switch (accion) {
                case 'ver':
                    verNotificacion(id);
                    break;
                case 'respuestas':
                    const { abrirModalRespuestas } = await import('./respuestas.js');
                    abrirModalRespuestas(id);
                    break;
                case 'editar':
                    editarNotificacion(id);
                    break;
                case 'duplicar':
                    duplicarNotificacion(id);
                    break;
                case 'archivar':
                    cambiarEstado(id, 'archivada');
                    break;
                case 'activar':
                    cambiarEstado(id, 'activa');
                    break;
                case 'eliminar':
                    eliminarNotificacion(id);
                    break;
                default:
                    console.warn('Acción no reconocida:', accion);
            }
        });
    }
    
    // Botón "Editar desde ver" en el modal de detalles
    if (elements.editarDesdeVer) {
        elements.editarDesdeVer.addEventListener('click', async function() {
            const modalVer = NotificacionesState.elements.modalVerNotificacion;
            const notificacionId = modalVer.dataset.currentNotificationId;
            
            if (notificacionId) {
                const { cerrarModales } = await import('./modals.js');
                const { editarNotificacion } = await import('./crud.js');
                
                cerrarModales();
                setTimeout(() => {
                    editarNotificacion(Number.parseInt(notificacionId));
                }, 300);
            }
        });
    }
    
    // Cerrar modales con ESC
    document.addEventListener('keydown', async function(e) {
        if (e.key === 'Escape') {
            const { cerrarModales } = await import('./modals.js');
            cerrarModales();
        }
    });
    
    // Cerrar modal al hacer click fuera del contenido
    document.addEventListener('click', async function(e) {
        if (e.target.classList.contains('modal')) {
            const { cerrarModales } = await import('./modals.js');
            cerrarModales();
        }
    });
}

// =====================================================
// RENDERIZADO DE NOTIFICACIONES
// =====================================================

export function renderizarNotificaciones(notificaciones) {
    const { notificacionesGrid } = NotificacionesState.elements;
    
    if (!notificacionesGrid) {
        console.error('notificacionesGrid no encontrado');
        return;
    }
    
    if (!notificaciones || notificaciones.length === 0) {
        mostrarEstado('empty');
        // Actualizar estadísticas
        import('./crud.js').then(({ actualizarEstadisticas }) => {
            actualizarEstadisticas();
        });
        return;
    }
    
    mostrarEstado('content');
    
    notificacionesGrid.innerHTML = notificaciones.map(notificacion => {
        const prioridadClass = {
            'alta': 'priority-high',
            'media': 'priority-medium',
            'baja': 'priority-low'
        }[notificacion.prioridad] || '';
        
        const estadoClass = notificacion.estado === 'activa' ? 'estado-activa' : 'estado-archivada';
        const estadoTexto = notificacion.estado === 'activa' ? 'Activa' : 'Archivada';
        const estadoIcono = notificacion.estado === 'activa' ? 'fa-circle-check' : 'fa-box-archive';
        
        // FIX #8: Usar conteos de la query optimizada
        const tieneArchivo = Number.parseInt(notificacion.archivos_count || 0) > 0;
        const respuestasTotales = Number.parseInt(notificacion.respuestas_count || 0);
        // Restar las respuestas que el usuario ya vio (guardadas en localStorage)
        const respuestasVistas = obtenerRespuestasVistas(notificacion.id);
        const cantidadRespuestas = Math.max(0, respuestasTotales - respuestasVistas);
        
        return `
            <div class="notificacion-card ${prioridadClass} ${estadoClass}">
                <div class="notificacion-header">
                    <h3 class="notificacion-titulo">${escaparHTML(notificacion.nombre)}</h3>
                    <div class="notificacion-badges">
                        <span class="badge badge-${notificacion.estado}">
                            <i class="fas ${estadoIcono}"></i> ${estadoTexto}
                        </span>
                        <span class="badge badge-prioridad-${notificacion.prioridad}">
                            ${notificacion.prioridad.toUpperCase()}
                        </span>
                    </div>
                </div>
                
                <div class="notificacion-body">
                    <p class="notificacion-cuerpo">${truncarTexto(escaparHTML(notificacion.cuerpo), 150)}</p>
                    
                    <div class="notificacion-meta">
                        <div class="meta-item" data-tooltip="${getDestinoTexto(notificacion.destino)}">
                            <i class="fas fa-users"></i>
                            <span>${getDestinoTexto(notificacion.destino)}</span>
                        </div>
                        <div class="meta-item" data-tooltip="${formatearFechaCompleta(notificacion.fecha_creacion)}">
                            <i class="fas fa-calendar-alt"></i>
                            <span>${formatearFechaCompleta(notificacion.fecha_creacion)}</span>
                        </div>
                        ${tieneArchivo ? '<div class="meta-item" data-tooltip="Archivo adjunto"><i class="fas fa-paperclip"></i><span>Adjunto</span></div>' : ''}
                    </div>
                </div>
                
                <div class="notificacion-footer">
                    <button class="btn-notificacion btn-icon" data-action="ver" data-id="${notificacion.id}" title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-notificacion btn-icon btn-respuestas" data-action="respuestas" data-id="${notificacion.id}" title="Ver respuestas">
                        <i class="fas fa-comments"></i>
                        ${cantidadRespuestas > 0 ? `<span class="badge-respuestas">${cantidadRespuestas}</span>` : ''}
                    </button>
                    ${globalThis.ES_ADMIN ? `
                    <button class="btn-notificacion btn-icon" data-action="editar" data-id="${notificacion.id}" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-notificacion btn-icon" data-action="duplicar" data-id="${notificacion.id}" title="Duplicar">
                        <i class="fas fa-copy"></i>
                    </button>
                    ${notificacion.estado === 'activa' ? 
                        `<button class="btn-notificacion btn-icon" data-action="archivar" data-id="${notificacion.id}" title="Archivar">
                            <i class="fas fa-box-archive"></i>
                        </button>` :
                        `<button class="btn-notificacion btn-icon" data-action="activar" data-id="${notificacion.id}" title="Activar">
                            <i class="fas fa-circle-check"></i>
                        </button>`
                    }
                    <button class="btn-notificacion btn-icon btn-danger" data-action="eliminar" data-id="${notificacion.id}" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                    ` : ''}
                </div>
            </div>
        `;
    }).join('');
    
    // Actualizar estadísticas
    import('./crud.js').then(({ actualizarEstadisticas }) => {
        actualizarEstadisticas();
    });
}

// =====================================================
// ESTADOS DE VISUALIZACIÓN
// =====================================================

export function mostrarEstado(estado) {
    const { loadingState, emptyState, notificacionesGrid } = NotificacionesState.elements;
    
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
