<?php
/**
 * Página de Seguridad - Panel de Administrador
 * Incluye: Cambio de contraseña, 2FA, Historial de accesos
 */
session_start();
include 'auth.php';
include 'csrf_protection.php';
require_once __DIR__ . '/../conexion/conexion.php';

// Verificar que el usuario tenga rol de administrador
if ($_SESSION['usuario_rol'] !== 'admin' && $_SESSION['usuario_rol'] !== 'administrador') {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguridad - Panel Administrador</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/usuarios.css">
    <link rel="stylesheet" href="/gh/Css/cambiar_contraseña.css">
    <link rel="stylesheet" href="/gh/Css/seguridad.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/historial-accesos.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/modals.css?v=<?= time() ?>">
    <link rel="icon" href="/gh/Img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include __DIR__ . "/Modulos/navbar.php"; ?>
    
    <main class="contenido-principal">
        <div class="header-universal">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-shield-alt"></i>
                    <h1>Seguridad de la Cuenta</h1>
                </div>
                <div class="header-info">
                    <span class="info-text">Administra la seguridad de tu cuenta de administrador</span>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="dashboard-grid">
                <!-- Tarjeta Cambiar Contraseña -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon card-icon-password">
                            <i class="fas fa-key"></i>
                        </div>
                        <h3>Cambiar Contraseña</h3>
                    </div>
                    <div class="card-content">
                        <p>Actualiza tu contraseña para mantener tu cuenta segura.</p>
                        <a href="cambiar_contraseña.php" class="btn-card">
                            <i class="fas fa-lock"></i>
                            Actualizar Contraseña
                        </a>
                    </div>
                </div>

                <!-- Tarjeta Historial de Acceso -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon card-icon-history">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3>Historial de Acceso</h3>
                    </div>
                    <div class="card-content">
                        <p>Revisa los últimos accesos a tu cuenta.</p>
                        <a href="mi_historial_accesos.php" class="btn-card">
                            <i class="fas fa-list"></i>
                            Ver Historial
                        </a>
                    </div>
                </div>

                <!-- Tarjeta Historial Global (Solo Admin) -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon card-icon-global">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Historial Global</h3>
                    </div>
                    <div class="card-content">
                        <p>Revisa los accesos de todos los usuarios del sistema.</p>
                        <a href="historial_accesos.php" class="btn-card">
                            <i class="fas fa-globe"></i>
                            Ver Historial Global
                        </a>
                    </div>
                </div>

                <!-- Tarjeta Restablecer MFA -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon card-icon-mfa">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h3>Restablecer MFA</h3>
                    </div>
                    <div class="card-content">
                        <p>Restablece la verificación 2FA de cualquier usuario del sistema.</p>
                        <a href="restablecer_mfa.php" class="btn-card">
                            <i class="fas fa-undo-alt"></i>
                            Gestionar MFA
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once '../mensaje_alerta.php'; ?>
    </main>

    <script>
        // Variables para las notificaciones
        <?php if (isset($_SESSION['titulo']) && isset($_SESSION['mensaje']) && isset($_SESSION['tipo_alerta'])): ?>
        const mensajeTitulo = "<?php echo addslashes($_SESSION['titulo']); ?>";
        const mensajeTexto = "<?php echo addslashes($_SESSION['mensaje']); ?>";
        const mensajeTipo = "<?php echo $_SESSION['tipo_alerta']; ?>";
        <?php 
            unset($_SESSION['titulo']);
            unset($_SESSION['mensaje']);
            unset($_SESSION['tipo_alerta']);
        ?>
        
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof mensajeTitulo !== 'undefined') {
                Swal.fire({
                    title: mensajeTitulo,
                    text: mensajeTexto,
                    icon: mensajeTipo || 'info',
                    confirmButtonText: 'Entendido'
                });
            }
        });
        <?php endif; ?>

        // Cerrar modal con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.form-container-professional.active, .historial-container.active');
                modals.forEach(modal => {
                    ocultarFormulario(modal.id);
                });
            }
        });
    </script>
</body>
</html>
