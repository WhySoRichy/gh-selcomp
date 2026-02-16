<?php
session_start();
require 'conexion/conexion.php';
require_once 'config.php';
require_once 'administrador/csrf_protection.php';
require_once 'seguridad/proteccion_fuerza_bruta.php';

// Inicializar protección contra fuerza bruta
$proteccion_reset = new ProteccionFuerzaBruta($conexion);

// Headers de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$alerta = null;  // Variable para el mensaje de alerta

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF
    verificar_csrf();

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alerta = ['title' => 'Error', 'text' => 'El correo ingresado no es válido.', 'icon' => 'error'];
    } else {
        // Rate limiting: verificar bloqueo por IP/email
        $ip_reset = $_SERVER['REMOTE_ADDR'];
        $bloqueo_reset = $proteccion_reset->verificarBloqueo($email, $ip_reset);
        if ($bloqueo_reset['bloqueado']) {
            $alerta = ['title' => 'Demasiados intentos', 'text' => 'Ha realizado demasiadas solicitudes. Intente nuevamente en ' . $proteccion_reset->formatearTiempoRestante($bloqueo_reset['tiempo_restante']) . '.', 'icon' => 'error'];
        } else {
            // Registrar intento (para rate limiting)
            $proteccion_reset->registrarIntentoFallido($email, $ip_reset);
        $stmt = $conexion->prepare("SELECT id, nombre, apellido FROM usuarios WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Siempre mostrar el mismo mensaje (no revelar si el email existe)
        $alerta = ['title' => 'Solicitud recibida', 'text' => 'Si el correo existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.', 'icon' => 'success'];

        if ($user) {
            $user_id = $user['id'];
            
            // Limpiar tokens anteriores de este usuario
            $deleteOld = $conexion->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
            $deleteOld->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $deleteOld->execute();
            
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $insertStmt = $conexion->prepare("
                INSERT INTO password_resets (user_id, token_hash, expires_at) 
                VALUES (:user_id, :token_hash, :expires_at)
            ");
            $insertStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $insertStmt->bindParam(':token_hash', $token_hash, PDO::PARAM_STR);
            $insertStmt->bindParam(':expires_at', $expires_at, PDO::PARAM_STR);
            $insertStmt->execute();

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $link = $protocol . $_SERVER['HTTP_HOST'] . BASE_URL . "recuperar_contraseña.php?token=$token";

            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';

            try {
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->Port       = SMTP_PORT;

                $mail->setFrom(SMTP_USER, config('SMTP_FROM_NAME', 'Selcomp Ingeniería S.A.S'));
                $mail->addAddress($email);
                $mail->Subject = 'Recuperación de contraseña - Portal Gestión Humana';
                $mail->isHTML(true);

                // Adjuntar imágenes de firma usando CID
                $mail->addEmbeddedImage(__DIR__ . '/Img/OlvidoFirma1.png', 'firmaContacto');
                $mail->addEmbeddedImage(__DIR__ . '/Img/OlvidoFirma2.png', 'firmaLegal');

                $nombreCompleto = trim(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? ''));
                $nombreCompleto = $nombreCompleto ?: 'Usuario';

                $mail->Body = '
                <p>Buen día ' . htmlspecialchars($nombreCompleto) . ',</p>
                <p>Para recuperar la contraseña de tu cuenta del Portal Gestión Humana haz clic <a href="' . $link . '" style="color: #d1164c; font-weight: bold;">aquí</a>.</p>
                <p style="color: #666; font-size: 12px;">Este enlace es válido por 1 hora. Si no solicitaste este cambio, puedes ignorar este mensaje.</p>
                <br>
                <img src="cid:firmaContacto" alt="Firma contacto Selcomp" style="max-width:650px;width:100%;display:block;margin-left:0;"><br>
                <img src="cid:firmaLegal" alt="Aviso legal Selcomp" style="max-width:650px;width:100%;display:block;margin-left:0;">
                ';

                $mail->AltBody = "Buen día $nombreCompleto, para recuperar tu contraseña visita: $link";

                $mail->send();
                // No cambiar el mensaje de éxito genérico ya establecido
            } catch (Exception $e) {
                // Log del error pero mostrar mensaje genérico
                error_log("Error al enviar email de recuperación: " . $e->getMessage());
                // Mantener mensaje genérico para no revelar info
            }
        }
        // Si el usuario no existe, simplemente no hacemos nada pero mostramos el mismo mensaje
        } // cierre rate limiting
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Olvido de contraseña</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Css/index.css">
    <link rel="icon" href="Img/Favicon.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.14/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.14/dist/sweetalert2.min.js"></script>
</head>
<body>
    <div class="login-card">
        <img src="Img/Selcomp Logo.png" alt="Selcomp Logo" class="logo">
        <h1>Portal Gestión Humana</h1>
        <h2>Olvido de contraseña</h2>
        <form action="olvido_contraseña.php" method="post" autocomplete="off">
            <?php echo campo_csrf_token(); ?>
            <input type="email" name="email" placeholder="Correo electrónico" required>
            <button type="submit" class="btn">Enviar</button>
        </form>
        <div class="postulacion-section">
            <p class="postulacion-text"><a href="index.php" class="postulacion-link">Volver al inicio</a></p>
        </div>
    </div>
    <script>
<?php if ($alerta): ?>
Swal.fire({
    title: <?php echo json_encode($alerta['title']); ?>,
    text: <?php echo json_encode($alerta['text']); ?>,
    icon: <?php echo json_encode($alerta['icon']); ?>
});
<?php endif; ?>
</script>
</body>
</html>
