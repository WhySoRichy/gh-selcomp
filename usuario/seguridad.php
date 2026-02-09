<?php
session_start();
include 'auth.php';
require_once __DIR__ . '/../administrador/csrf_protection.php';
require_once __DIR__ . '/../conexion/conexion.php';

// Obtener estado actual de 2FA del usuario
$tiene_2fa = 0;
try {
    $stmt = $conexion->prepare("SELECT tiene_2fa FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $tiene_2fa = $resultado['tiene_2fa'] ?? 0;
} catch (PDOException $e) {
    // Si la columna no existe, simplemente dejamos en 0
    $tiene_2fa = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguridad - Portal Usuario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/usuarios.css">
    <link rel="stylesheet" href="/gh/Css/dashboard_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/cambiar_contraseña.css">
    <link rel="stylesheet" href="/gh/Css/seguridad.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/historial-accesos.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/modals.css?v=<?= time() ?>">
    <link rel="icon" href="/gh/Img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/gh/Js/seguridad.js" defer></script>
    <script src="/gh/Js/exportar-historial.js" defer></script>
</head>
<body>
<?php include __DIR__ . "/Modulos/navbar_usuario.php"; ?>
    <!-- Overlay para modales -->
    <div class="modal-overlay" id="modal-overlay"></div>
    
    <main class="contenido-principal">
        <div class="header-universal">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-shield-alt"></i>
                    <h1>Seguridad de la Cuenta</h1>
                </div>
                <div class="header-info">
                    <span class="info-text">Administra la seguridad de tu cuenta</span>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon card-icon-password">
                            <i class="fas fa-key"></i>
                        </div>
                        <h3>Cambiar Contraseña</h3>
                    </div>
                    <div class="card-content">
                        <p>Actualiza tu contraseña para mantener tu cuenta segura.</p>
                        <a href="#" onclick="mostrarFormularioCambioContraseña()" class="btn-card">
                            <i class="fas fa-lock"></i>
                            Actualizar Contraseña
                        </a>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon card-icon-history">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3>Historial de Acceso</h3>
                    </div>
                    <div class="card-content">
                        <p>Revisa los últimos accesos a tu cuenta.</p>
                        <a href="#" onclick="mostrarHistorialAccesos()" class="btn-card">
                            <i class="fas fa-list"></i>
                            Ver Historial
                        </a>
                    </div>
                </div>

                <!-- Tarjeta de Autenticación 2FA -->
                <div class="dashboard-card card-2fa <?php echo $tiene_2fa ? 'card-2fa-activo' : ''; ?>">
                    <div class="card-header">
                        <div class="card-icon card-icon-2fa">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Verificación en 2 Pasos</h3>
                        <?php if ($tiene_2fa): ?>
                            <span class="badge-activo"><i class="fas fa-check-circle"></i> Activo</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-content">
                        <?php if ($tiene_2fa): ?>
                            <p>La verificación en 2 pasos está <strong>activada</strong>. Recibirás un código por email cada vez que inicies sesión.</p>
                            <a href="#" onclick="toggle2FA(false)" class="btn-card btn-danger">
                                <i class="fas fa-toggle-off"></i>
                                Desactivar 2FA
                            </a>
                        <?php else: ?>
                            <p>Añade una capa extra de seguridad. Al activar, recibirás un código por email para verificar tu identidad.</p>
                            <a href="#" onclick="toggle2FA(true)" class="btn-card btn-success">
                                <i class="fas fa-toggle-on"></i>
                                Activar 2FA
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Token CSRF oculto para operaciones AJAX (2FA toggle) - Token dedicado -->
        <?php 
        // Generar token dedicado para 2FA que no interfiera con el del formulario
        $token_2fa = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_2fa'] = $token_2fa;
        $_SESSION['csrf_token_2fa_time'] = time();
        ?>
        <input type="hidden" id="csrf_token_2fa" value="<?php echo $token_2fa; ?>">

        <!-- Formulario de cambio de contraseña (oculto inicialmente) -->
        <div class="form-container-professional" id="form-cambio-contraseña" style="display: none;">
            <div class="form-header-professional">
                <h2><i class="fas fa-key"></i> Cambiar Contraseña</h2>
                <button type="button" onclick="ocultarFormulario('form-cambio-contraseña')" class="btn-close" title="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" action="procesar_cambiar_contrasena.php" class="form-usuario-professional" id="form-cambiar-password">
                <?php echo campo_csrf_token(); ?>
                
                <div class="form-section-professional">
                    <h3 class="section-title-professional">
                        <i class="fas fa-shield-alt"></i> Verificación de Seguridad
                    </h3>
                    <div class="form-grid-professional">
                        <div class="input-group-professional">
                            <label for="actual_contrasena">Contraseña Actual</label>
                            <input type="password" 
                                   id="actual_contrasena" 
                                   name="actual_contrasena" 
                                   placeholder="Ingresa tu contraseña actual" 
                                   required>
                        </div>
                        <div class="input-group-professional">
                            <label for="nueva_contrasena">Nueva Contraseña</label>
                            <input type="password" 
                                   id="nueva_contrasena" 
                                   name="nueva_contrasena" 
                                   placeholder="Mínimo 8 caracteres" 
                                   required
                                   minlength="8">
                            <small class="password-hint">Debe incluir mayúsculas, minúsculas, números y símbolos</small>
                        </div>
                        <div class="input-group-professional">
                            <label for="confirmar_contrasena">Confirmar Nueva Contraseña</label>
                            <input type="password" 
                                   id="confirmar_contrasena" 
                                   name="confirmar_contrasena" 
                                   placeholder="Confirma tu nueva contraseña" 
                                   required
                                   minlength="8">
                            <small class="password-match" id="password-match"></small>
                        </div>
                    </div>
                </div>

                <div class="form-buttons-professional">
                    <button type="button" onclick="ocultarFormulario('form-cambio-contraseña')" class="btn-cancel-professional">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-save-professional">
                        <i class="fas fa-check"></i> Actualizar
                    </button>
                </div>
            </form>
        </div>

        <!-- Contenedor para el historial de accesos (oculto inicialmente) -->
        <div class="historial-container" id="historial-accesos" style="display: none;">
            <div class="historial-header">
                <h2><i class="fas fa-history"></i> Historial de Accesos</h2>
                <button type="button" onclick="ocultarFormulario('historial-accesos')" class="btn-close" title="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="historial-content" id="contenido-historial">
                <p class="loading-text"><i class="fas fa-circle-notch fa-spin"></i> Cargando historial de accesos...</p>
            </div>
        </div>

        <?php require_once '../mensaje_alerta.php'; ?>
    </main>

    <script>
        // Variables para las notificaciones
        <?php if (isset($_SESSION['titulo']) && isset($_SESSION['mensaje']) && isset($_SESSION['tipo_alerta'])): ?>
        const mensajeTitulo = "<?php echo $_SESSION['titulo']; ?>";
        const mensajeTexto = "<?php echo $_SESSION['mensaje']; ?>";
        const mensajeTipo = "<?php echo $_SESSION['tipo_alerta']; ?>";
        <?php 
            // Limpiar variables de sesión
            unset($_SESSION['titulo']);
            unset($_SESSION['mensaje']);
            unset($_SESSION['tipo_alerta']);
        endif; 
        ?>
    </script>
</body>
</html>
