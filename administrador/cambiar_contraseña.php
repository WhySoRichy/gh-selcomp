<?php
session_start();
include 'auth.php';
include 'csrf_protection.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - Portal Gestión Humana</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar.css">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/usuarios.css">
    <link rel="icon" href="/gh/Img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="layout-admin">
    <?php include __DIR__ . "/Modulos/navbar.php"; ?>
    <main class="contenido-principal">
        <!-- Header universal -->
        <div class="header-universal">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-key"></i>
                    <h1>Cambiar Contraseña</h1>
                </div>
                <div class="header-info">
                    <span class="info-text">Actualiza tu contraseña de acceso al sistema</span>
                </div>
            </div>
        </div>

        <div class="form-container-professional">
            <form method="POST" action="procesar_cambiar_contraseña.php" class="form-usuario-professional" id="form-cambiar-password">
                <?php echo campo_csrf_token(); ?>
                
                <div class="form-section-professional">
                    <h3 class="section-title-professional">
                        <i class="fas fa-shield-alt"></i> Verificación de Seguridad
                    </h3>
                    <div class="input-group-professional">
                        <label for="actual_contrasena">Contraseña Actual</label>
                        <input type="password" 
                               id="actual_contrasena" 
                               name="actual_contrasena" 
                               placeholder="Ingresa tu contraseña actual" 
                               required>
                    </div>
                </div>

                <div class="form-section-professional">
                    <h3 class="section-title-professional">
                        <i class="fas fa-lock"></i> Nueva Contraseña
                    </h3>
                    <div class="form-grid-professional">
                        <div class="input-group-professional">
                            <label for="nueva_contrasena">Nueva Contraseña</label>
                            <input type="password" 
                                   id="nueva_contrasena" 
                                   name="nueva_contrasena" 
                                   placeholder="Mínimo 8 caracteres" 
                                   required
                                   minlength="8">
                            <small class="password-hint">La contraseña debe tener al menos 8 caracteres</small>
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
                    <button type="submit" class="btn-save-professional">
                        <i class="fas fa-save"></i> Cambiar Contraseña
                    </button>
                    <a href="mostrar_perfil.php" class="btn-cancel-professional">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
            <?php require_once '../mensaje_alerta.php'; ?>
        </div>
    </main>
</div>

<script>
// Validación en tiempo real de contraseñas
document.addEventListener('DOMContentLoaded', function() {
    const nuevaPassword = document.getElementById('nueva_contrasena');
    const confirmarPassword = document.getElementById('confirmar_contrasena');
    const matchIndicator = document.getElementById('password-match');
    const form = document.getElementById('form-cambiar-password');

    function validarPasswords() {
        const nueva = nuevaPassword.value;
        const confirmar = confirmarPassword.value;

        if (confirmar === '') {
            matchIndicator.textContent = '';
            matchIndicator.className = 'password-match';
            return;
        }

        if (nueva === confirmar) {
            matchIndicator.textContent = '✓ Las contraseñas coinciden';
            matchIndicator.className = 'password-match success';
        } else {
            matchIndicator.textContent = '✗ Las contraseñas no coinciden';
            matchIndicator.className = 'password-match error';
        }
    }

    nuevaPassword.addEventListener('input', validarPasswords);
    confirmarPassword.addEventListener('input', validarPasswords);

    // Validación del formulario
    form.addEventListener('submit', function(e) {
        const nueva = nuevaPassword.value;
        const confirmar = confirmarPassword.value;

        if (nueva !== confirmar) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error de validación',
                text: 'Las contraseñas no coinciden'
            });
            return false;
        }

        if (nueva.length < 8) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Contraseña muy corta',
                text: 'La nueva contraseña debe tener al menos 8 caracteres'
            });
            return false;
        }
    });
});
</script>
</body>
</html>
