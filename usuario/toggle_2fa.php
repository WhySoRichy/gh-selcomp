<?php
/**
 * API para activar/desactivar autenticación 2FA
 * Endpoint: POST /usuario/toggle_2fa.php
 */
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configurar respuesta JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../administrador/csrf_protection.php';

// Verificar CSRF con token dedicado para 2FA
$token_enviado = $_POST['csrf_token'] ?? '';
$token_guardado = $_SESSION['csrf_token_2fa'] ?? '';
$token_tiempo = $_SESSION['csrf_token_2fa_time'] ?? 0;

if (empty($token_enviado) || empty($token_guardado) || $token_enviado !== $token_guardado) {
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página.']);
    exit;
}

if (time() - $token_tiempo > 1800) {
    echo json_encode(['success' => false, 'message' => 'El token ha expirado. Recarga la página.']);
    exit;
}

// Invalidar token CSRF después de validación exitosa (prevenir replay)
unset($_SESSION['csrf_token_2fa']);
unset($_SESSION['csrf_token_2fa_time']);

$usuario_id = $_SESSION['usuario_id'];
$activar = isset($_POST['activar']) && $_POST['activar'] === 'true';

// Defensa en profundidad: bloquear desactivación si el usuario es administrador
if (!$activar && isset($_SESSION['usuario_rol']) &&
    ($_SESSION['usuario_rol'] === 'admin' || $_SESSION['usuario_rol'] === 'administrador')) {
    echo json_encode(['success' => false, 'message' => 'La verificación 2FA es obligatoria para administradores y no puede ser desactivada.']);
    exit;
}

try {
    // Verificar que el usuario exista
    $stmt = $conexion->prepare("SELECT id, email, nombre FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }

    // Actualizar estado 2FA
    $nuevo_estado = $activar ? 1 : 0;
    $stmt = $conexion->prepare("UPDATE usuarios SET tiene_2fa = :estado WHERE id = :id");
    $stmt->bindParam(':estado', $nuevo_estado, PDO::PARAM_INT);
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();

    // Registrar cambio en historial de accesos
    try {
        $detalles_log = $activar ? 'Verificación 2FA activada' : 'Verificación 2FA desactivada';
        $stmt_log = $conexion->prepare("INSERT INTO historial_accesos
            (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles)
            VALUES (?, NOW(), ?, ?, ?, 1, ?)");
        $stmt_log->execute([
            $usuario_id,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido',
            $detalles_log
        ]);
    } catch (PDOException $e) {
        error_log("Error al registrar cambio 2FA en historial: " . $e->getMessage());
    }

    // Si se está activando, enviar email de confirmación
    if ($activar) {
        try {
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
            $mail->Subject = 'Verificación en 2 Pasos Activada - Portal Gestión Humana';
            $mail->isHTML(true);

            // Adjuntar imágenes de firma usando CID
            $mail->addEmbeddedImage(__DIR__ . '/../Img/OlvidoFirma1.png', 'firmaContacto');
            $mail->addEmbeddedImage(__DIR__ . '/../Img/OlvidoFirma2.png', 'firmaLegal');

            $mail->Body = '
            <p>Hola <strong>' . htmlspecialchars($usuario['nombre']) . '</strong>,</p>
            <p>La verificación en 2 pasos ha sido <strong style="color: #eb0045;">activada</strong> en tu cuenta del Portal Gestión Humana.</p>
            <p>A partir de ahora, cada vez que inicies sesión, recibirás un código de 6 dígitos en este correo que deberás ingresar para acceder.</p>
            <div style="background: #fce4ec; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #eb0045;">
                <strong>¿Por qué es importante?</strong>
                <p style="margin: 10px 0 0 0;">La verificación en 2 pasos protege tu cuenta incluso si alguien conoce tu contraseña.</p>
            </div>
            <p style="color: #666;">Si no realizaste este cambio, contacta inmediatamente al administrador.</p>
            <br>
            <img src="cid:firmaContacto" alt="Firma contacto Selcomp" style="max-width:650px;width:100%;display:block;margin-left:0;"><br>
            <img src="cid:firmaLegal" alt="Aviso legal Selcomp" style="max-width:650px;width:100%;display:block;margin-left:0;">
            ';
            $mail->AltBody = "La verificación en 2 pasos ha sido activada en tu cuenta.";
            $mail->send();
        } catch (Exception $e) {
            // No es crítico si falla el email de confirmación
            error_log("Error al enviar confirmación 2FA: " . $e->getMessage());
        }
    }

    $mensaje = $activar
        ? 'Verificación en 2 pasos activada correctamente. Recibirás un código por email cada vez que inicies sesión.'
        : 'Verificación en 2 pasos desactivada.';

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'estado_2fa' => $activar
    ]);

} catch (PDOException $e) {
    error_log("Error al toggle 2FA: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar configuración']);
}
