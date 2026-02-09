<?php
/**
 * REDIRECT AUTOMÁTICO AL NUEVO SISTEMA DE NOTIFICACIONES
 * Este archivo redirige automáticamente a la nueva ubicación modular
 * Backup disponible en: notificaciones.php.backup
 */

session_start();
require_once 'auth.php'; // Verificar autenticación antes de redirigir

// Redirigir al nuevo sistema
header('Location: /gh/notificaciones/');
exit;


try {
    // Total de notificaciones
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM notificaciones");
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $estadisticas['total'] = (int)($resultado['total'] ?? 0);
    
    // Notificaciones activas
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM notificaciones WHERE estado = 'activa'");
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $estadisticas['activas'] = (int)($resultado['total'] ?? 0);
    
    // Notificaciones archivadas
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM notificaciones WHERE estado = 'archivada'");
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $estadisticas['archivadas'] = (int)($resultado['total'] ?? 0);
    
} catch (Exception $e) {
    // En caso de error, registrar y mantener valores por defecto
    error_log("Error al obtener estadísticas: " . $e->getMessage());
    // Las estadísticas permanecen en 0 como fallback
}

// Generar token CSRF para formularios
$csrf_token = generar_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - Portal Gestión Humana</title>
    
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- CSS del sistema -->
    <link rel="stylesheet" href="/gh/Css/navbar.css">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/modals.css?v=2711202506">
    <link rel="stylesheet" href="/gh/Css/notificaciones.css">
    
    <!-- Favicon -->
    <link rel="icon" href="/gh/Img/logo.png">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include_once __DIR__ . "/Modulos/navbar.php"; ?>
    
    <main class="dashboard-container">
        <!-- Header principal -->
        <header class="header-notificaciones">
            <div class="header-content">
                <h1><i class="fas fa-bell"></i> Sistema de Notificaciones</h1>
                <p class="subtitle">Gestiona y administra las notificaciones del sistema</p>
            </div>
            
            <div class="header-stats">
                <div class="stat-item">
                    <span class="stat-number" id="total-notificaciones"><?php echo $estadisticas['total']; ?></span>
                    <span class="stat-label">Total</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="notificaciones-activas"><?php echo $estadisticas['activas']; ?></span>
                    <span class="stat-label">Activas</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="notificaciones-archivadas"><?php echo $estadisticas['archivadas']; ?></span>
                    <span class="stat-label">Archivadas</span>
                </div>
            </div>
        </header>

        <!-- Controles principales -->
        <div class="controls-section">
            <button class="btn-crear-notificacion" id="crear-notificacion">
                <i class="fas fa-plus"></i> Nueva Notificación
            </button>
            
            <div class="filtros-rapidos">
                <button class="filtro-btn active" data-filtro="todas" id="filtro-todas">
                    <i class="fas fa-list"></i> Todas
                </button>
                <button class="filtro-btn" data-filtro="activas" id="filtro-activas">
                    <i class="fas fa-check-circle"></i> Activas
                </button>
                <button class="filtro-btn" data-filtro="archivadas" id="filtro-archivadas">
                    <i class="fas fa-archive"></i> Archivadas
                </button>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="content-section">
            <!-- Estado de carga -->
            <div class="loading-state" id="loading-state">
                <div class="loading-spinner"></div>
                <p>Cargando notificaciones...</p>
            </div>

            <!-- Estado vacío -->
            <div class="empty-state" id="empty-state" style="display: none;">
                <div class="empty-icon">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h3>No hay notificaciones</h3>
                <p>No se encontraron notificaciones que coincidan con los filtros aplicados.</p>
                <button class="btn-crear-notificacion-empty" id="crear-primera-notificacion">
                    <i class="fas fa-plus"></i> Crear primera notificación
                </button>
            </div>
            
            <div class="notificaciones-grid" id="notificaciones-grid">
                <!-- Las notificaciones se cargarán aquí dinámicamente -->
            </div>
        </div>

        <!-- Modales -->
        <div id="modal-notificacion" class="modal" style="display: none;">
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h2 id="modal-titulo">
                        <i class="fas fa-plus"></i> Nueva Notificación
                    </h2>
                    <button class="modal-close" id="cerrar-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="form-notificacion" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="id" id="notificacion-id">
                    
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nombre">
                                    <i class="fas fa-tag"></i> Nombre de la Notificación *
                                </label>
                                <input type="text" 
                                       id="nombre" 
                                       name="nombre" 
                                       required 
                                       maxlength="255"
                                       placeholder="Ej: Actualización de sistema, Mantenimiento programado...">
                                <small class="form-help">Título descriptivo y claro para la notificación</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="cuerpo">
                                <i class="fas fa-comment-alt"></i> Mensaje *
                            </label>
                            <textarea id="cuerpo" 
                                      name="cuerpo" 
                                      required 
                                      rows="6" 
                                      maxlength="5000"
                                      placeholder="Escribe el contenido del mensaje que verán los usuarios..."></textarea>
                            <small class="form-help">Contenido principal del mensaje (máximo 5000 caracteres)</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="destino">
                                    <i class="fas fa-users"></i> Destino
                                </label>
                                <select id="destino" name="destino" required>
                                    <option value="todos">Todos los usuarios</option>
                                    <option value="especificos">Usuarios específicos</option>
                                    <option value="administradores">Solo administradores</option>
                                    <option value="regulares">Solo usuarios regulares</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="prioridad">
                                    <i class="fas fa-exclamation-triangle"></i> Prioridad
                                </label>
                                <select id="prioridad" name="prioridad" required>
                                    <option value="baja">Baja</option>
                                    <option value="media" selected>Media</option>
                                    <option value="alta">Alta</option>
                                </select>
                            </div>
                        </div>

                        <!-- Selector de usuarios específicos en su propia fila -->
                        <div class="form-row usuarios-especificos" id="usuarios-especificos-container" style="display: none;">
                            <div class="form-group">
                                <div class="form-label">
                                    <i class="fas fa-user-friends"></i> Seleccionar Usuarios
                                </div>
                                <div class="usuarios-selector-container" id="usuarios-selector">
                                    <div class="usuarios-search">
                                        <label for="buscar-usuarios" class="sr-only">Buscar usuarios por nombre o email</label>
                                        <input type="text" 
                                               id="buscar-usuarios" 
                                               placeholder="Buscar usuarios..."
                                               class="form-control"
                                               aria-label="Buscar usuarios por nombre o email">
                                        <i class="fas fa-search" aria-hidden="true"></i>
                                    </div>
                                    <div class="usuarios-lista" id="usuarios-lista">
                                        <div class="usuarios-loading">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            Cargando usuarios...
                                        </div>
                                    </div>
                                    <div class="usuarios-seleccionados" id="usuarios-seleccionados">
                                        <small class="text-muted" id="usuarios-count-description">Usuarios seleccionados: <span id="count-usuarios">0</span></small>
                                    </div>
                                </div>
                                <!-- Campo oculto para enviar IDs -->
                                <input type="hidden" 
                                       id="usuarios-ids" 
                                       name="usuarios_ids" 
                                       value=""
                                       aria-describedby="usuarios-count-description">
                            </div>
                        </div>

                        <div class="form-group">
                            <fieldset>
                                <legend class="form-label">
                                    <i class="fas fa-cog"></i> Opciones
                                </legend>
                                <div class="checkbox-group" id="opciones-notificacion">
                                    <label class="checkbox-label" for="permitir_respuesta">
                                        <input type="checkbox" id="permitir_respuesta" name="permitir_respuesta" checked>
                                        <span class="checkmark"></span>
                                        Permitir respuesta de usuarios
                                    </label>
                                </div>
                            </fieldset>
                        </div>

                        <div class="form-group">
                            <div class="form-label">
                                <i class="fas fa-paperclip"></i> Archivos Adjuntos (opcional - máx. 10)
                            </div>
                            <div class="file-upload-container">
                                <label for="archivo" class="sr-only">Seleccionar archivos adjuntos</label>
                                <input type="file"
                                       id="archivo"
                                       name="archivos[]"
                                       multiple
                                       aria-label="Seleccionar archivos adjuntos (máximo 10)"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.gif">
                                <small class="file-info">
                                    <i class="fas fa-info-circle"></i> 
                                    Puedes seleccionar hasta 10 archivos. Formatos: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG, GIF, TXT
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" id="cancelar-modal">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn-primary" id="enviar-notificacion">
                            <i class="fas fa-paper-plane"></i> 
                            <span id="texto-boton">Crear Notificación</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal para ver detalles -->
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
                    <button type="button" class="btn-primary" id="editar-desde-ver">
                        <i class="fas fa-edit"></i> Editar Notificación
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Script del sistema -->
    <script src="../Js/notificaciones.js"></script>
</body>
</html>