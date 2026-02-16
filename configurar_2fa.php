<?php
/**
 * Configurar Autenticación TOTP (2FA por App de Autenticación)
 * Muestra QR para escanear con Google Authenticator / Microsoft Authenticator
 * y solicita código de verificación para confirmar la configuración.
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

// Verificar que hay una sesión 2FA pendiente de configuración
// (viene de procesar_login.php para admins sin secreto, o de toggle_2fa para usuarios)
if (!isset($_SESSION['2fa_setup_pendiente']) || !isset($_SESSION['2fa_setup_usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Expiración de sesión de configuración (15 minutos)
if (isset($_SESSION['2fa_setup_time'])) {
    if (time() - $_SESSION['2fa_setup_time'] > 900) {
        unset($_SESSION['2fa_setup_pendiente']);
        unset($_SESSION['2fa_setup_usuario_id']);
        unset($_SESSION['2fa_setup_secret']);
        unset($_SESSION['2fa_setup_time']);
        unset($_SESSION['2fa_setup_email']);
        $_SESSION['titulo'] = 'Sesión expirada';
        $_SESSION['mensaje'] = 'El tiempo para configurar el 2FA ha expirado. Por favor inicia sesión nuevamente.';
        $_SESSION['tipo_alerta'] = 'warning';
        header('Location: index.php');
        exit;
    }
}

$usuario_id = $_SESSION['2fa_setup_usuario_id'];
$email = $_SESSION['2fa_setup_email'] ?? '';
$error = '';
$es_admin = $_SESSION['2fa_setup_es_admin'] ?? false;

// Generar secreto si no existe en sesión
if (!isset($_SESSION['2fa_setup_secret'])) {
    $_SESSION['2fa_setup_secret'] = generate_2fa_secret();
}
$secret = $_SESSION['2fa_setup_secret'];

// Procesar verificación del código
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página.';
    }

    if (!$error) {
    $codigo_ingresado = trim($_POST['codigo'] ?? '');

    if (empty($codigo_ingresado)) {
        $error = 'Por favor ingresa el código de verificación de tu app.';
    } elseif (!preg_match('/^\d{6}$/', $codigo_ingresado)) {
        $error = 'El código debe ser de 6 dígitos.';
    } else {
        // Verificar el código TOTP
        if (verify_2fa_code($secret, $codigo_ingresado)) {
            // Código correcto — guardar secreto cifrado en BD
            try {
                $secreto_cifrado = encrypt_2fa_secret($secret);
                $stmt = $conexion->prepare("UPDATE usuarios SET secreto_2fa = :secreto, tiene_2fa = 1 WHERE id = :id");
                $stmt->execute([
                    'secreto' => $secreto_cifrado,
                    'id' => $usuario_id
                ]);

                // Obtener datos del usuario para completar el login
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

                    // Limpiar datos de setup
                    unset($_SESSION['2fa_setup_pendiente']);
                    unset($_SESSION['2fa_setup_usuario_id']);
                    unset($_SESSION['2fa_setup_secret']);
                    unset($_SESSION['2fa_setup_time']);
                    unset($_SESSION['2fa_setup_email']);
                    unset($_SESSION['2fa_setup_es_admin']);

                    // Regenerar ID de sesión
                    session_regenerate_id(true);

                    // Registrar en historial
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $dispositivo = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
                    $detalles = 'Login exitoso - 2FA configurado por primera vez (App de Autenticación)';

                    try {
                        $stmt = $conexion->prepare("INSERT INTO historial_accesos
                            (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles)
                            VALUES (?, NOW(), ?, ?, ?, 1, ?)");
                        $stmt->execute([$usuario['id'], $ip, $dispositivo, $dispositivo, $detalles]);
                    } catch (PDOException $e) {
                        error_log("Error al registrar acceso 2FA setup: " . $e->getMessage());
                    }

                    // Redirigir según flujo
                    if (isset($_SESSION['2fa_redirect_after_setup'])) {
                        $redirect = $_SESSION['2fa_redirect_after_setup'];
                        unset($_SESSION['2fa_redirect_after_setup']);
                        header('Location: ' . $redirect);
                    } elseif ($usuario['rol'] === 'admin' || $usuario['rol'] === 'administrador') {
                        header('Location: administrador/index.php');
                    } else {
                        header('Location: usuario/index.php');
                    }
                    exit;
                }
            } catch (Exception $e) {
                error_log("Error al guardar secreto 2FA: " . $e->getMessage());
                $error = 'Error al guardar la configuración. Intenta nuevamente.';
            }
        } else {
            $error = 'Código incorrecto. Verifica que tu app muestre el código correcto y que la hora de tu dispositivo esté sincronizada.';
        }
    }
    } // cierre if (!$error)
}

// Generar QR SVG
$qr_svg = generate_2fa_qr_svg($secret, $email);

// Formatear secreto para mostrar (grupos de 4)
$secret_formateado = implode(' ', str_split($secret, 4));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Autenticación - Selcomp</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="Css/index.css">
    <link rel="icon" href="/gh/Img/Favicon.png" type="image/png">
    <style>
        /* Override base layout for this page */
        body {
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .setup-card {
            width: 980px;
            max-width: 95vw;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.10);
            padding: 0;
            overflow: hidden;
            font-family: 'Montserrat', sans-serif;
        }

        /* Header compacto */
        .setup-header {
            background: linear-gradient(135deg, #404e62 0%, #2d3748 100%);
            color: white;
            padding: 22px 32px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .setup-header i {
            font-size: 1.6rem;
            color: #eb0045;
            background: rgba(255,255,255,0.12);
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .setup-header-text h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .setup-header-text p {
            margin: 4px 0 0;
            font-size: 0.85rem;
            opacity: 0.75;
        }

        /* Admin banner inline */
        .admin-banner {
            background: #fef2f2;
            color: #404e62;
            border-bottom: 1px solid #fce4ec;
            padding: 10px 28px;
            font-size: 0.78rem;
            line-height: 1.4;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .admin-banner i {
            color: #eb0045;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        /* Body: dos columnas */
        .setup-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        /* Columna izquierda: QR */
        .setup-left {
            padding: 28px 28px;
            border-right: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .step-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #666;
        }

        .step-badge .num {
            width: 22px;
            height: 22px;
            background: linear-gradient(135deg, #eb0045, #c4003a);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .qr-wrap {
            background: #fafafa;
            border-radius: 12px;
            border: 1px solid #e8ecf0;
            padding: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-wrap svg {
            width: 190px;
            height: 190px;
        }

        .clave-manual {
            background: #f8f9fa;
            border: 1px dashed #cdd5e0;
            border-radius: 8px;
            padding: 10px 14px;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
        }

        .clave-manual-label {
            font-size: 0.68rem;
            color: #999;
            margin-bottom: 4px;
        }

        .clave-manual-value {
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            font-weight: 700;
            color: #404e62;
            letter-spacing: 2px;
            user-select: all;
        }

        .btn-copiar {
            background: none;
            border: 1px solid #cdd5e0;
            color: #666;
            padding: 3px 10px;
            border-radius: 5px;
            font-size: 0.68rem;
            cursor: pointer;
            margin-top: 6px;
            transition: all 0.2s;
            font-family: 'Montserrat', sans-serif;
        }

        .btn-copiar:hover {
            background: #eb0045;
            color: white;
            border-color: #eb0045;
        }

        /* Columna derecha: pasos 1 y 3 */
        .setup-right {
            padding: 28px 28px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 16px;
        }

        /* Apps sugeridas */
        .apps-section {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .apps-row {
            display: flex;
            gap: 8px;
        }

        .app-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f0f4f8;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            color: #555;
            font-weight: 500;
        }

        /* Divider */
        .setup-divider {
            border: none;
            border-top: 1px solid #eee;
            margin: 0;
        }

        /* Código input */
        .verify-section {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .codigo-input {
            width: 100%;
            padding: 14px 16px;
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 10px;
            text-indent: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .codigo-input:focus {
            border-color: #eb0045;
            outline: none;
            box-shadow: 0 0 0 3px rgba(235, 0, 69, 0.12);
        }

        .btn-verificar {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #eb0045, #c4003a);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Montserrat', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-verificar:hover {
            background: linear-gradient(135deg, #c4003a, #a10030);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(235, 0, 69, 0.3);
        }

        .error-msg {
            background: #fef2f2;
            color: #c62828;
            padding: 10px 14px;
            border-radius: 8px;
            text-align: center;
            font-size: 0.78rem;
            border: 1px solid #fecaca;
        }

        .cancelar-link {
            text-align: center;
            margin-top: 4px;
        }

        .cancelar-link a {
            color: #999;
            text-decoration: none;
            font-size: 0.75rem;
            transition: color 0.2s;
        }

        .cancelar-link a:hover {
            color: #eb0045;
        }

        /* Responsive: una columna en móvil */
        @media (max-width: 640px) {
            .setup-card {
                width: 100%;
            }

            .setup-body {
                grid-template-columns: 1fr;
            }

            .setup-left {
                border-right: none;
                border-bottom: 1px solid #f0f0f0;
                padding: 18px 20px;
            }

            .setup-right {
                padding: 18px 20px;
            }

            .qr-wrap svg {
                width: 140px;
                height: 140px;
            }
        }
    </style>
</head>
<body>
    <div class="setup-card">
        <!-- Header -->
        <div class="setup-header">
            <i class="fas fa-shield-alt"></i>
            <div class="setup-header-text">
                <h2>Configurar Autenticación</h2>
                <p>Vincula tu cuenta con una app de autenticación para proteger tu acceso</p>
            </div>
        </div>

        <?php if ($es_admin): ?>
            <div class="admin-banner">
                <i class="fas fa-exclamation-circle"></i>
                <span><strong>La autenticación de dos factores es obligatoria</strong> para cuentas de administrador. Configura tu app para continuar.</span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="padding: 12px 28px 0;">
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Body: 2 columnas -->
        <div class="setup-body">
            <!-- Izquierda: QR -->
            <div class="setup-left">
                <span class="step-badge"><span class="num">1</span> Escanea con tu app</span>
                <div class="qr-wrap">
                    <?= $qr_svg ?>
                </div>
                <div class="clave-manual">
                    <div class="clave-manual-label">¿No puedes escanear? Clave manual:</div>
                    <div class="clave-manual-value" id="clave-manual"><?= htmlspecialchars($secret_formateado) ?></div>
                    <button type="button" class="btn-copiar" onclick="copiarClave()">
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                </div>
            </div>

            <!-- Derecha: App + Verificar -->
            <div class="setup-right">
                <div class="apps-section">
                    <span class="step-badge"><span class="num">2</span> Instala una de estas apps</span>
                    <div class="apps-row">
                        <span class="app-badge"><i class="fab fa-google"></i> Google Authenticator</span>
                        <span class="app-badge"><i class="fab fa-microsoft"></i> Microsoft Auth</span>
                    </div>
                </div>

                <hr class="setup-divider">

                <div class="verify-section">
                    <span class="step-badge"><span class="num">3</span> Ingresa el código de 6 dígitos</span>

                    <form method="POST" action="configurar_2fa.php" id="form-setup-2fa">
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

                        <button type="submit" class="btn-verificar" style="margin-top: 10px;">
                            <i class="fas fa-check-circle"></i> Verificar y Activar
                        </button>
                    </form>

                    <div class="cancelar-link">
                        <a href="cerrar_sesion.php">
                            <i class="fas fa-arrow-left"></i> Cancelar y volver al login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Solo permitir números en el input
        const codigoInput = document.querySelector('.codigo-input');
        codigoInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });

        // Pegar código
        codigoInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text');
            this.value = pasteData.replace(/\D/g, '').substring(0, 6);
        });

        // Copiar clave manual (se lee del DOM, no de variable JS expuesta)
        function copiarClave() {
            const clave = document.getElementById('clave-manual').textContent.replace(/\s/g, '');
            navigator.clipboard.writeText(clave).then(() => {
                const btn = document.querySelector('.btn-copiar');
                btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiada!';
                btn.style.background = '#059669';
                btn.style.color = 'white';
                btn.style.borderColor = '#059669';
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-copy"></i> Copiar clave';
                    btn.style.background = '';
                    btn.style.color = '';
                    btn.style.borderColor = '';
                }, 2000);
            });
        }
    </script>
</body>
</html>
