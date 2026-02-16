<?php
/**
 * Verificación de Código TOTP (2FA por App de Autenticación)
 * Se muestra después del login cuando el usuario tiene 2FA activo con secreto configurado
 */
session_start();
require_once 'config.php';
require_once 'conexion/conexion.php';
require_once 'funciones/totp_helpers.php';
require_once __DIR__ . '/administrador/csrf_protection.php';

// Headers de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Verificar que hay una sesión 2FA pendiente
if (!isset($_SESSION['2fa_pendiente']) || !isset($_SESSION['2fa_usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Expiración de sesión 2FA pendiente (10 minutos)
if (isset($_SESSION['2fa_pendiente_time'])) {
    if (time() - $_SESSION['2fa_pendiente_time'] > 600) {
        unset($_SESSION['2fa_pendiente']);
        unset($_SESSION['2fa_usuario_id']);
        unset($_SESSION['2fa_pendiente_time']);
        $_SESSION['titulo'] = 'Sesión expirada';
        $_SESSION['mensaje'] = 'El tiempo para verificar el código ha expirado. Por favor inicia sesión nuevamente.';
        $_SESSION['tipo_alerta'] = 'warning';
        header('Location: index.php');
        exit;
    }
}

$error = '';
$usuario_id = $_SESSION['2fa_usuario_id'];

// Control de intentos fallidos
if (!isset($_SESSION['2fa_intentos'])) {
    $_SESSION['2fa_intentos'] = 0;
}

// Procesar envío del código
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página.';
    }

    if (!$error) {
    $codigo_ingresado = trim($_POST['codigo'] ?? '');

    if (empty($codigo_ingresado)) {
        $error = 'Por favor ingresa el código de tu app de autenticación.';
    } elseif (!preg_match('/^\d{6}$/', $codigo_ingresado)) {
        $error = 'El código debe ser de 6 dígitos.';
    } elseif ($_SESSION['2fa_intentos'] >= 5) {
        // Demasiados intentos
        unset($_SESSION['2fa_pendiente']);
        unset($_SESSION['2fa_usuario_id']);
        unset($_SESSION['2fa_pendiente_time']);
        unset($_SESSION['2fa_intentos']);
        $_SESSION['titulo'] = 'Acceso bloqueado';
        $_SESSION['mensaje'] = 'Demasiados intentos fallidos. Por favor inicia sesión nuevamente.';
        $_SESSION['tipo_alerta'] = 'error';
        header('Location: index.php');
        exit;
    } else {
        try {
            // Obtener secreto cifrado del usuario
            $stmt = $conexion->prepare("SELECT secreto_2fa FROM usuarios WHERE id = :id");
            $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['secreto_2fa'])) {
                $error = 'Error de configuración 2FA. Contacta al administrador.';
            } else {
                // Descifrar secreto y verificar código TOTP
                $secret = decrypt_2fa_secret($row['secreto_2fa']);

                if (verify_2fa_code($secret, $codigo_ingresado)) {
                    // ¡Código correcto! Completar el login
                    $stmt = $conexion->prepare("SELECT id, nombre, rol FROM usuarios WHERE id = :id");
                    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($usuario) {
                        // Establecer sesión completa
                        $_SESSION['usuario_id'] = $usuario['id'];
                        $_SESSION['usuario_nombre'] = $usuario['nombre'];
                        $_SESSION['usuario_rol'] = $usuario['rol'];
                        $_SESSION['hora_creacion_sesion'] = time();
                        $_SESSION['ultima_actividad'] = time();

                        // Limpiar datos 2FA de la sesión
                        unset($_SESSION['2fa_pendiente']);
                        unset($_SESSION['2fa_usuario_id']);
                        unset($_SESSION['2fa_pendiente_time']);
                        unset($_SESSION['2fa_intentos']);

                        // Regenerar ID de sesión
                        session_regenerate_id(true);

                        // Registrar acceso exitoso
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $dispositivo = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
                        $detalles = 'Login exitoso con verificación 2FA (App de Autenticación)';

                        try {
                            $stmt = $conexion->prepare("INSERT INTO historial_accesos
                                (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles)
                                VALUES (?, NOW(), ?, ?, ?, 1, ?)");
                            $stmt->execute([$usuario['id'], $ip, $dispositivo, $dispositivo, $detalles]);
                        } catch (PDOException $e) {
                            error_log("Error al registrar acceso 2FA: " . $e->getMessage());
                        }

                        // Redireccionar según rol
                        if ($usuario['rol'] === 'admin' || $usuario['rol'] === 'administrador') {
                            header('Location: administrador/index.php');
                        } else {
                            header('Location: usuario/index.php');
                        }
                        exit;
                    } else {
                        error_log("Usuario 2FA no encontrado: ID {$usuario_id}");
                        $error = 'Error de verificación. Por favor intenta iniciar sesión nuevamente.';
                        unset($_SESSION['2fa_pendiente']);
                        unset($_SESSION['2fa_usuario_id']);
                        unset($_SESSION['2fa_pendiente_time']);
                        unset($_SESSION['2fa_intentos']);
                    }
                } else {
                    // Código incorrecto
                    $_SESSION['2fa_intentos']++;
                    $intentos_restantes = 5 - $_SESSION['2fa_intentos'];

                    if ($intentos_restantes <= 0) {
                        unset($_SESSION['2fa_pendiente']);
                        unset($_SESSION['2fa_usuario_id']);
                        unset($_SESSION['2fa_pendiente_time']);
                        unset($_SESSION['2fa_intentos']);
                        $_SESSION['titulo'] = 'Acceso bloqueado';
                        $_SESSION['mensaje'] = 'Demasiados intentos fallidos. Por favor inicia sesión nuevamente.';
                        $_SESSION['tipo_alerta'] = 'error';
                        header('Location: index.php');
                        exit;
                    } else {
                        $error = "Código incorrecto. Te quedan {$intentos_restantes} intento" . ($intentos_restantes > 1 ? 's' : '') . ".";
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error en verificación 2FA TOTP: " . $e->getMessage());
            $error = 'Error al verificar el código. Por favor intenta nuevamente.';
        }
    }
    } // cierre if (!$error)
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Seguridad - Selcomp</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="Css/index.css">
    <link rel="icon" href="/gh/Img/Favicon.png" type="image/png">
    <style>
        .verificacion-container {
            max-width: 420px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .verificacion-icon {
            text-align: center;
            margin-bottom: 25px;
        }

        .verificacion-icon i {
            font-size: 60px;
            color: #eb0045;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .verificacion-titulo {
            text-align: center;
            margin-bottom: 10px;
            color: #333;
        }

        .verificacion-subtitulo {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .codigo-input {
            width: 100%;
            max-width: 280px;
            margin: 0 auto 20px auto;
            padding: 15px 20px;
            font-size: 24px;
            text-align: center;
            letter-spacing: 12px;
            text-indent: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            box-sizing: border-box;
            display: block;
        }

        .codigo-input:focus {
            border-color: #eb0045;
            outline: none;
            box-shadow: 0 0 10px rgba(235, 0, 69, 0.2);
        }

        .btn-verificar {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #eb0045, #c4003a);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
        }

        .btn-verificar:hover {
            background: linear-gradient(135deg, #c4003a, #a10030);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(235, 0, 69, 0.3);
        }

        .error-msg {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        .cancelar-link {
            text-align: center;
            margin-top: 15px;
        }

        .cancelar-link a {
            color: #999;
            text-decoration: none;
            font-size: 13px;
        }

        .cancelar-link a:hover {
            color: #eb0045;
        }

        .info-app {
            text-align: center;
            font-size: 12px;
            color: #888;
            margin-top: 15px;
        }

        .info-app i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="login-card verificacion-container">
        <div class="verificacion-icon">
            <i class="fas fa-shield-alt"></i>
        </div>

        <h2 class="verificacion-titulo">Verificación de Seguridad</h2>
        <p class="verificacion-subtitulo">
            Ingresa el código de 6 dígitos de tu<br>
            <strong>app de autenticación</strong>
        </p>

        <?php if ($error): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="verificar_2fa.php" id="form-2fa">
            <?php echo campo_csrf_token(); ?>
            <input type="text"
                   name="codigo"
                   class="codigo-input"
                   maxlength="6"
                   pattern="\d{6}"
                   placeholder="000000"
                   autocomplete="one-time-code"
                   inputmode="numeric"
                   autofocus
                   required>

            <button type="submit" class="btn-verificar">
                <i class="fas fa-check"></i> Verificar Código
            </button>
        </form>

        <div class="info-app">
            <i class="fas fa-mobile-alt"></i> Abre Google Authenticator o Microsoft Authenticator
        </div>

        <div class="cancelar-link">
            <a href="cerrar_sesion.php">
                <i class="fas fa-arrow-left"></i> Cancelar y volver al login
            </a>
        </div>
    </div>

    <script>
        // Auto-focus y formateo del input
        const codigoInput = document.querySelector('.codigo-input');

        codigoInput.addEventListener('input', function(e) {
            // Solo permitir números
            this.value = this.value.replace(/\D/g, '');

            // Auto-submit cuando se completan 6 dígitos
            if (this.value.length === 6) {
                setTimeout(() => {
                    document.getElementById('form-2fa').submit();
                }, 300);
            }
        });

        // Pegar código desde clipboard
        codigoInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text');
            const numeros = pasteData.replace(/\D/g, '').substring(0, 6);
            this.value = numeros;

            if (numeros.length === 6) {
                setTimeout(() => {
                    document.getElementById('form-2fa').submit();
                }, 300);
            }
        });
    </script>
</body>
</html>
