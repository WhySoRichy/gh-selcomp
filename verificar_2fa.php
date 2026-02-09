<?php
/**
 * Verificación de Código 2FA
 * Esta página se muestra después del login cuando el usuario tiene 2FA activo
 */
session_start();
require_once 'config.php';
require_once 'conexion/conexion.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
        unset($_SESSION['2fa_email_parcial']);
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
$email_parcial = $_SESSION['2fa_email_parcial'] ?? '***@***.com';

// Procesar envío del código
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_ingresado = trim($_POST['codigo'] ?? '');

    if (empty($codigo_ingresado)) {
        $error = 'Por favor ingresa el código de verificación';
    } elseif (!preg_match('/^\d{6}$/', $codigo_ingresado)) {
        $error = 'El código debe ser de 6 dígitos';
    } else {
        try {
            // Buscar código válido para este usuario
            $stmt = $conexion->prepare("
                SELECT id, codigo_hash, intentos
                FROM codigos_2fa
                WHERE usuario_id = :usuario_id
                  AND expira_en > NOW()
                  AND usado = 0
                ORDER BY creado_en DESC
                LIMIT 1
            ");
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt->execute();
            $codigo_db = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$codigo_db) {
                $error = 'El código ha expirado. Por favor solicita uno nuevo.';
            } elseif ($codigo_db['intentos'] >= 5) {
                // Marcar como usado para bloquear más intentos
                $stmt = $conexion->prepare("UPDATE codigos_2fa SET usado = 1 WHERE id = :id");
                $stmt->execute(['id' => $codigo_db['id']]);
                $error = 'Demasiados intentos fallidos. Por favor solicita un nuevo código.';
            } elseif (password_verify($codigo_ingresado, $codigo_db['codigo_hash'])) {
                // ¡Código correcto! Completar el login
                $stmt = $conexion->prepare("UPDATE codigos_2fa SET usado = 1 WHERE id = :id");
                $stmt->execute(['id' => $codigo_db['id']]);

                // Obtener datos del usuario
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
                    unset($_SESSION['2fa_email_parcial']);

                    // Regenerar ID de sesión
                    session_regenerate_id(true);

                    // Registrar acceso exitoso
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $dispositivo = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
                    $detalles = 'Login exitoso con verificación 2FA';

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
                    // Usuario no encontrado - caso raro pero posible
                    error_log("Usuario 2FA no encontrado: ID {$usuario_id}");
                    $error = 'Error de verificación. Por favor intenta iniciar sesión nuevamente.';
                    // Limpiar sesión corrupta
                    unset($_SESSION['2fa_pendiente']);
                    unset($_SESSION['2fa_usuario_id']);
                    unset($_SESSION['2fa_email_parcial']);
                }
            } else {
                // Código incorrecto, incrementar intentos
                $stmt = $conexion->prepare("UPDATE codigos_2fa SET intentos = intentos + 1 WHERE id = :id");
                $stmt->execute(['id' => $codigo_db['id']]);
                $intentos_restantes = 5 - ($codigo_db['intentos'] + 1);

                if ($intentos_restantes <= 0) {
                    $error = "Código incorrecto. Has agotado todos los intentos. Solicita un nuevo código.";
                } else {
                    $error = "Código incorrecto. Te quedan {$intentos_restantes} intento" . ($intentos_restantes > 1 ? 's' : '') . ".";
                }
            }
        } catch (PDOException $e) {
            error_log("Error en verificación 2FA: " . $e->getMessage());
            $error = 'Error al verificar el código. Por favor intenta nuevamente.';
        }
    }
}

// Función para reenviar código
if (isset($_GET['reenviar']) && $_GET['reenviar'] === '1') {
    // Rate limiting: mínimo 60 segundos entre reenvíos
    $ultimo_reenvio = $_SESSION['2fa_ultimo_reenvio'] ?? 0;
    $tiempo_espera = 60 - (time() - $ultimo_reenvio);

    if ($tiempo_espera > 0) {
        $_SESSION['2fa_mensaje'] = "Espera {$tiempo_espera} segundos para solicitar otro código.";
        header('Location: verificar_2fa.php');
        exit;
    }

    $_SESSION['2fa_ultimo_reenvio'] = time();

    try {
        // Obtener email del usuario
        $stmt = $conexion->prepare("SELECT email, nombre FROM usuarios WHERE id = :id");
        $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            // Invalidar códigos anteriores
            $stmt = $conexion->prepare("UPDATE codigos_2fa SET usado = 1 WHERE usuario_id = :id AND usado = 0");
            $stmt->execute(['id' => $usuario_id]);

            // Generar nuevo código
            $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $codigo_hash = password_hash($codigo, PASSWORD_DEFAULT);
            $ip = $_SERVER['REMOTE_ADDR'];

            $stmt = $conexion->prepare("
                INSERT INTO codigos_2fa (usuario_id, codigo, codigo_hash, expira_en, ip_solicitud)
                VALUES (:usuario_id, '******', :codigo_hash, DATE_ADD(NOW(), INTERVAL 5 MINUTE), :ip)
            ");
            $stmt->execute([
                'usuario_id' => $usuario_id,
                'codigo_hash' => $codigo_hash,
                'ip' => $ip
            ]);

            // Enviar email
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(SMTP_USER, config('SMTP_FROM_NAME', 'Selcomp - Portal GH'));
            $mail->addAddress($usuario['email']);
            $mail->Subject = 'Código de Verificación - Portal Gestión Humana';
            $mail->isHTML(true);

            // Adjuntar imágenes de firma usando CID
            $mail->addEmbeddedImage(__DIR__ . '/Img/OlvidoFirma1.png', 'firmaContacto');
            $mail->addEmbeddedImage(__DIR__ . '/Img/OlvidoFirma2.png', 'firmaLegal');

            $mail->Body = '
            <p>Hola <strong>' . htmlspecialchars($usuario['nombre']) . '</strong>,</p>
            <p>Tu código de verificación es:</p>
            <div style="background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #d1164c; border-radius: 8px; margin: 20px 0;">
                ' . $codigo . '
            </div>
            <p style="color: #666;">Este código expira en <strong>5 minutos</strong>.</p>
            <p style="color: #666;">Si no solicitaste este código, ignora este mensaje.</p>
            <br>
            <img src="cid:firmaContacto" alt="Firma contacto Selcomp" style="max-width:650px;width:100%;display:block;margin-left:0;"><br>
            <img src="cid:firmaLegal" alt="Aviso legal Selcomp" style="max-width:650px;width:100%;display:block;margin-left:0;">
            ';
            $mail->AltBody = "Tu código de verificación es: {$codigo}. Expira en 5 minutos.";
            $mail->send();

            $_SESSION['2fa_mensaje'] = 'Se ha enviado un nuevo código a tu correo.';
        }
    } catch (Exception $e) {
        error_log("Error al reenviar código 2FA: " . $e->getMessage());
        $error = 'No se pudo enviar el código. Por favor intenta nuevamente.';
    }

    header('Location: verificar_2fa.php');
    exit;
}

$mensaje_exito = $_SESSION['2fa_mensaje'] ?? '';
unset($_SESSION['2fa_mensaje']);
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
            color: #d1164c;
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
        }

        .codigo-input-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
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
            border-color: #d1164c;
            outline: none;
            box-shadow: 0 0 10px rgba(209, 22, 76, 0.2);
        }

        .btn-verificar {
            width: 100%;
            padding: 15px;
            background: #d1164c;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-verificar:hover {
            background: #b01341;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(209, 22, 76, 0.3);
        }

        .reenviar-link {
            text-align: center;
            margin-top: 20px;
        }

        .reenviar-link a {
            color: #d1164c;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .reenviar-link a:hover {
            text-decoration: underline;
            color: #b01341;
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

        .success-msg {
            background: #e8f5e9;
            color: #2e7d32;
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

        .expira-info {
            text-align: center;
            font-size: 12px;
            color: #888;
            margin-top: 15px;
        }

        .expira-info i {
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
            Hemos enviado un código de 6 dígitos a<br>
            <strong><?php echo htmlspecialchars($email_parcial); ?></strong>
        </p>

        <?php if ($error): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($mensaje_exito): ?>
            <div class="success-msg">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje_exito); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="verificar_2fa.php" id="form-2fa">
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

        <div class="expira-info">
            <i class="fas fa-clock"></i> El código expira en 5 minutos
        </div>

        <div class="reenviar-link">
            <a href="verificar_2fa.php?reenviar=1">
                <i class="fas fa-redo"></i> Reenviar código
            </a>
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
                // Pequeño delay para mejor UX
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
