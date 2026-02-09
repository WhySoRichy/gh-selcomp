<?php
session_start();
include 'auth.php';
require_once __DIR__ . '/../administrador/csrf_protection.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - Portal Usuario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/cambiar_contraseña.css">
    <link rel="icon" href="/gh/Img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include __DIR__ . "/Modulos/navbar_usuario.php"; ?>
    <main class="contenido-principal">
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
            <form method="POST" action="procesar_cambiar_contrasena.php" class="form-usuario-professional" id="form-cambiar-password">
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

                <div class="password-requirements">
                    <h4><i class="fas fa-info-circle"></i> Requisitos de seguridad:</h4>
                    <ul>
                        <li id="req-length"><i class="fas fa-check-circle"></i> Al menos 8 caracteres</li>
                        <li id="req-uppercase"><i class="fas fa-check-circle"></i> Al menos una mayúscula (A-Z)</li>
                        <li id="req-lowercase"><i class="fas fa-check-circle"></i> Al menos una minúscula (a-z)</li>
                        <li id="req-number"><i class="fas fa-check-circle"></i> Al menos un número (0-9)</li>
                        <li id="req-special"><i class="fas fa-check-circle"></i> Al menos un carácter especial (!@#$%^&*)</li>
                    </ul>
                </div>

                <div class="form-buttons-professional">
                    <button type="submit" class="btn-save-professional">
                        <i class="fas fa-save"></i> Cambiar Contraseña
                    </button>
                    <a href="seguridad.php" class="btn-cancel-professional">
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

    // Elementos para requisitos
    const reqLength = document.getElementById('req-length');
    const reqUppercase = document.getElementById('req-uppercase');
    const reqLowercase = document.getElementById('req-lowercase');
    const reqNumber = document.getElementById('req-number');
    const reqSpecial = document.getElementById('req-special');

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

    function validarRequisitos() {
        const password = nuevaPassword.value;
        
        // Validar longitud
        if (password.length >= 8) {
            reqLength.classList.add('cumplido');
        } else {
            reqLength.classList.remove('cumplido');
        }
        
        // Validar mayúscula
        if (/[A-Z]/.test(password)) {
            reqUppercase.classList.add('cumplido');
        } else {
            reqUppercase.classList.remove('cumplido');
        }
        
        // Validar minúscula
        if (/[a-z]/.test(password)) {
            reqLowercase.classList.add('cumplido');
        } else {
            reqLowercase.classList.remove('cumplido');
        }
        
        // Validar número
        if (/[0-9]/.test(password)) {
            reqNumber.classList.add('cumplido');
        } else {
            reqNumber.classList.remove('cumplido');
        }
        
        // Validar carácter especial
        if (/[!@#$%^&*]/.test(password)) {
            reqSpecial.classList.add('cumplido');
        } else {
            reqSpecial.classList.remove('cumplido');
        }
    }

    nuevaPassword.addEventListener('input', function() {
        validarPasswords();
        validarRequisitos();
    });
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
        
        // Validar complejidad de contraseña
        const tieneUpperCase = /[A-Z]/.test(nueva);
        const tieneLowerCase = /[a-z]/.test(nueva);
        const tieneNumero = /[0-9]/.test(nueva);
        const tieneEspecial = /[!@#$%^&*]/.test(nueva);
        
        if (!tieneUpperCase || !tieneLowerCase || !tieneNumero || !tieneEspecial) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Contraseña no segura',
                text: 'La contraseña debe incluir mayúsculas, minúsculas, números y al menos un carácter especial'
            });
            return false;
        }
    });
});
</script>
</body>
</html>
