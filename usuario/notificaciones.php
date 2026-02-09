<?php
session_start();
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../administrador/csrf_protection.php';
require_once 'auth.php';

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuario_rol = $_SESSION['usuario_rol'] ?? 'usuario';

// Generar token CSRF
$csrf_token = generar_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Notificaciones - Portal Gestión Humana</title>
    
    <!-- Meta tags -->
    <meta name="description" content="Sistema de notificaciones">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    
    <!-- Favicon -->
    <link rel="icon" href="/gh/Img/logo.png" type="image/png">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Estilos -->
    <link rel="stylesheet" href="/gh/Css/navbar_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/header-universal.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/dashboard_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/notificaciones/css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/modals.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/notificaciones-usuario.css?v=<?= time() ?>">
</head>
<body>
    <div class="layout-admin">
        <?php include __DIR__ . '/Modulos/navbar_usuario.php'; ?>
        
        <main class="contenido-principal">
            <!-- Header con estadísticas -->
            <div class="header-universal">
                <div class="header-content">
                    <h2>
                        <i class="fas fa-bell"></i>
                        Mis Notificaciones
                    </h2>
                    <div class="header-stats">
                        <div class="stat-item">
                            <span class="stat-number" id="notificaciones-activas">-</span>
                            <span class="stat-label">ACTIVAS</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" id="total-notificaciones">-</span>
                            <span class="stat-label">TOTALES</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenedor principal -->
            <div class="dashboard-container">
                <!-- Búsqueda -->
                <div class="notificaciones-header">
                    <div class="busqueda-container busqueda-completa">
                        <input
                            type="text"
                            id="buscar-notificaciones"
                            class="input-buscar"
                            placeholder="Buscar notificaciones..."
                        >
                        <i class="fas fa-search"></i>
                    </div>
                </div>

                <!-- Estados -->
                <div id="loading-state" class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Cargando notificaciones...</p>
                </div>

                <div id="empty-state" class="empty-state" style="display: none;">
                    <i class="fas fa-inbox"></i>
                    <h3>No hay notificaciones</h3>
                    <p>No tienes notificaciones disponibles en este momento</p>
                </div>

                <!-- Grid de notificaciones -->
                <div id="notificaciones-grid" class="notificaciones-grid"></div>
            </div>
        </main>
    </div>

    <!-- Modal: Ver Detalles de Notificación (necesario para el JS compartido) -->
    <div id="modal-ver-notificacion" class="modal" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="ver-titulo">
                    <i class="fas fa-eye"></i> Detalles de la Notificación
                </h2>
                <button class="modal-close" id="cerrar-ver-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="notif-detalle" id="notif-detalle-contenido">
                    <!-- El contenido se carga dinámicamente -->
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cerrar-ver-detalle">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Centro de Respuestas -->
    <div id="modal-respuestas" class="modal-overlay">
        <div class="modal-respuestas">
            <div class="modal-header">
                <h2><i class="fas fa-comments"></i> Centro de Respuestas</h2>
                <button id="cerrar-modal-respuestas" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <!-- Lista de respuestas -->
                <div class="respuestas-section">
                    <h3><i class="fas fa-comment-dots"></i> Respuestas</h3>
                    <div id="lista-respuestas" class="respuestas-container">
                        <div class="loading-respuestas">
                            <i class="fas fa-spinner fa-spin"></i> Cargando respuestas...
                        </div>
                    </div>
                </div>

                <!-- Formulario de respuesta -->
                <div class="respuesta-form-section">
                    <h3><i class="fas fa-reply"></i> Tu Respuesta</h3>
                    <form id="form-respuesta" enctype="multipart/form-data">
                        <input type="hidden" name="notificacion_id" id="respuesta-notificacion-id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <div class="form-group">
                            <textarea
                                id="respuesta-texto"
                                name="respuesta"
                                class="form-control"
                                rows="4"
                                placeholder="Escribe tu respuesta aquí..."
                                maxlength="2000"
                                required
                            ></textarea>
                            <span class="char-counter">0 / 2000 caracteres</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="respuesta-archivo">
                                <i class="fas fa-paperclip"></i> Adjuntar archivos (opcional, máx 5 archivos de 10MB c/u)
                            </label>
                            <p class="form-help">Formatos permitidos: PDF, Word, Excel, Imágenes, ZIP, RAR</p>

                            <input
                                type="file"
                                id="respuesta-archivo"
                                name="archivos[]"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.txt,.zip,.rar"
                                multiple
                                class="form-control"
                            >

                            <div id="archivos-preview" class="archivos-preview"></div>
                        </div>

                        <button type="submit" class="btn-primary btn-block">
                            <i class="fas fa-paper-plane"></i> Enviar Respuesta
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Variables globales
        window.USUARIO_ID = <?= json_encode($usuario_id) ?>;
        window.ES_ADMIN = false; // Usuarios normales NO son admin
        window.USUARIO_NOMBRE = <?= json_encode($usuario_nombre) ?>;

        // Configuración del sistema
        window.NotificacionesConfig = {
            API_BASE_URL: '/gh/notificaciones/api.php',
            CSRF_TOKEN_REFRESH_INTERVAL: 25 * 60 * 1000,
            DEBOUNCE_DELAY: 300,
            MAX_FILE_SIZE: 10 * 1024 * 1024,
            MAX_FILES_PER_NOTIFICATION: 10,
            ALLOWED_FILE_TYPES: ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.jpg', '.jpeg', '.png', '.gif', '.txt']
        };

        // Estado global
        window.NotificacionesState = {
            csrf_token: <?= json_encode($csrf_token) ?>,
            filtroActual: 'todas',
            notificaciones: [],
            busqueda: ''
        };
    </script>

    <!-- Sistema de notificaciones (mismo del admin) -->
    <script type="module" src="/gh/notificaciones/js/app.js?v=<?= time() ?>"></script>
    
    <!-- Script de búsqueda local para usuarios -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputBuscar = document.getElementById('buscar-notificaciones');
        
        if (inputBuscar) {
            let timeoutBusqueda;
            
            inputBuscar.addEventListener('input', function() {
                clearTimeout(timeoutBusqueda);
                const termino = this.value.toLowerCase().trim();
                
                timeoutBusqueda = setTimeout(() => {
                    const notificaciones = window.NotificacionesState?.notificaciones || [];
                    const grid = document.getElementById('notificaciones-grid');
                    const emptyState = document.getElementById('empty-state');
                    
                    if (!grid) return;
                    
                    // Si no hay término de búsqueda, mostrar todas
                    if (!termino) {
                        // Mostrar todas las tarjetas
                        const cards = grid.querySelectorAll('.notificacion-card');
                        cards.forEach(card => card.style.display = '');
                        
                        if (cards.length === 0 && emptyState) {
                            emptyState.style.display = 'flex';
                            grid.style.display = 'none';
                        } else if (emptyState) {
                            emptyState.style.display = 'none';
                            grid.style.display = 'grid';
                        }
                        return;
                    }
                    
                    // Filtrar tarjetas visualmente
                    const cards = grid.querySelectorAll('.notificacion-card');
                    let visibles = 0;
                    
                    cards.forEach(card => {
                        const titulo = card.querySelector('.notificacion-titulo')?.textContent.toLowerCase() || '';
                        const cuerpo = card.querySelector('.notificacion-cuerpo')?.textContent.toLowerCase() || '';
                        
                        if (titulo.includes(termino) || cuerpo.includes(termino)) {
                            card.style.display = '';
                            visibles++;
                        } else {
                            card.style.display = 'none';
                        }
                    });
                    
                    // Mostrar/ocultar estado vacío
                    if (visibles === 0 && emptyState) {
                        emptyState.style.display = 'flex';
                        grid.style.display = 'none';
                    } else if (emptyState) {
                        emptyState.style.display = 'none';
                        grid.style.display = 'grid';
                    }
                }, 300);
            });
        }
    });
    </script>
</body>
</html>
