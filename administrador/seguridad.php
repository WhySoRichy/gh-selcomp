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

// Obtener estado actual de 2FA del usuario
$tiene_2fa = 0;
try {
    $stmt = $conexion->prepare("SELECT tiene_2fa FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $tiene_2fa = $resultado['tiene_2fa'] ?? 0;
} catch (PDOException $e) {
    $tiene_2fa = 0;
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
            </div>
        </div>

        <!-- Token CSRF oculto para operaciones AJAX (2FA toggle) - Token dedicado -->
        <?php 
        $token_2fa = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_2fa'] = $token_2fa;
        $_SESSION['csrf_token_2fa_time'] = time();
        ?>
        <input type="hidden" id="csrf_token_2fa" value="<?php echo $token_2fa; ?>">

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

        // Toggle 2FA
        async function toggle2FA(activar) {
            const accion = activar ? 'activar' : 'desactivar';
            const titulo = activar ? 'Activar Verificación en 2 Pasos' : 'Desactivar Verificación en 2 Pasos';
            const mensaje = activar 
                ? 'Al activar, recibirás un código por email cada vez que inicies sesión. ¿Deseas continuar?'
                : '¿Estás seguro de desactivar la verificación en 2 pasos? Tu cuenta será menos segura.';
            const iconoConfirm = activar ? 'question' : 'warning';
            
            const confirmacion = await Swal.fire({
                title: titulo,
                text: mensaje,
                icon: iconoConfirm,
                showCancelButton: true,
                confirmButtonColor: '#eb0045',
                cancelButtonColor: '#6b7280',
                confirmButtonText: activar ? 'Sí, activar' : 'Sí, desactivar',
                cancelButtonText: 'Cancelar'
            });
            
            if (!confirmacion.isConfirmed) {
                return;
            }
            
            Swal.fire({
                title: 'Procesando...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            try {
                const csrfInput = document.getElementById('csrf_token_2fa');
                const csrfToken = csrfInput ? csrfInput.value : '';
                
                if (!csrfToken) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Token de seguridad no encontrado. Recarga la página.',
                        icon: 'error',
                        confirmButtonText: 'Recargar'
                    }).then(() => window.location.reload());
                    return;
                }
                
                const formData = new FormData();
                formData.append('activar', activar.toString());
                formData.append('csrf_token', csrfToken);
                
                const response = await fetch('toggle_2fa.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await Swal.fire({
                        title: activar ? '¡Activado!' : 'Desactivado',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#eb0045'
                    });
                    
                    window.location.reload();
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'No se pudo actualizar la configuración',
                        icon: 'error',
                        confirmButtonText: 'Entendido'
                    });
                }
            } catch (error) {
                console.error('Error al toggle 2FA:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'Error de conexión. Por favor intenta nuevamente.',
                    icon: 'error',
                    confirmButtonText: 'Entendido'
                });
            }
        }

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
