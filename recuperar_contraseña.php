<?php
session_start();
require 'conexion/conexion.php';
require_once 'administrador/csrf_protection.php';

// Headers de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

$alerta = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF
    verificar_csrf();

    $token = $_POST['token'] ?? '';
    $pass_nueva = $_POST['pass_nueva'] ?? '';
    $confirmar_pass = $_POST['confirmar_pass'] ?? '';

    if ($pass_nueva !== $confirmar_pass) {
        $alerta = ['title' => 'Error', 'text' => 'Las contraseñas no coinciden.', 'icon' => 'error'];
    } elseif (strlen($pass_nueva) < 8) {
        $alerta = ['title' => 'Error', 'text' => 'La contraseña debe tener al menos 8 caracteres.', 'icon' => 'error'];
    } else {
        // Validar complejidad de contraseña
        $tiene_mayuscula = preg_match('/[A-Z]/', $pass_nueva);
        $tiene_minuscula = preg_match('/[a-z]/', $pass_nueva);
        $tiene_numero = preg_match('/[0-9]/', $pass_nueva);
        
        if (!$tiene_mayuscula || !$tiene_minuscula || !$tiene_numero) {
            $alerta = ['title' => 'Contraseña débil', 'text' => 'La contraseña debe incluir mayúsculas, minúsculas y números.', 'icon' => 'error'];
        } else {
            $token_hash = hash('sha256', $token);
            $stmt = $conexion->prepare("SELECT user_id, expires_at FROM password_resets WHERE token_hash = :token_hash");
            $stmt->bindParam(':token_hash', $token_hash, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                if (strtotime($result['expires_at']) < time()) {
                    $alerta = ['title' => 'Error', 'text' => 'El token ha expirado.', 'icon' => 'error'];
                } else {
                    // Actualizar la contraseña
                    $password_hash = password_hash($pass_nueva, PASSWORD_BCRYPT);
                    $update = $conexion->prepare("UPDATE usuarios SET password_hash = :password_hash WHERE id = :id");
                    $update->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
                    $update->bindParam(':id', $result['user_id'], PDO::PARAM_INT);
                    $update->execute();

                    // Eliminar TODOS los tokens del usuario (limpiar)
                    $delete = $conexion->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
                    $delete->bindParam(':user_id', $result['user_id'], PDO::PARAM_INT);
                    $delete->execute();

                    // Registrar en historial
                    try {
                        $stmt_log = $conexion->prepare("INSERT INTO historial_accesos 
                            (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles) 
                            VALUES (?, NOW(), ?, ?, ?, 1, 'Contraseña restablecida vía token')");
                        $stmt_log->execute([
                            $result['user_id'], 
                            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 
                            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido', 
                            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'
                        ]);
                    } catch (PDOException $e) {
                        error_log("Error al registrar reset password en historial: " . $e->getMessage());
                    }

                    $alerta = ['title' => 'Éxito', 'text' => 'Contraseña cambiada correctamente.', 'icon' => 'success', 'redirect' => 'index.php'];
                }
            } else {
                $alerta = ['title' => 'Error', 'text' => 'Token no válido o ya utilizado.', 'icon' => 'error'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Recuperar contraseña</title>
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
        <h2>Restablecer contraseña</h2>
        <form action="recuperar_contraseña.php" method="post" autocomplete="off">
            <?php echo campo_csrf_token(); ?>
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? $_POST['token'] ?? ''); ?>">
            <input type="password" name="pass_nueva" placeholder="Nueva contraseña" required>
            <input type="password" name="confirmar_pass" placeholder="Confirmar contraseña" required>
            <button type="submit" class="btn">Restablecer</button>
        </form>
    </div>

    <script>
    <?php if ($alerta): ?>
        Swal.fire({
            title: <?php echo json_encode($alerta['title']); ?>,
            text: <?php echo json_encode($alerta['text']); ?>,
            icon: <?php echo json_encode($alerta['icon']); ?>
        }).then(() => {
            <?php if (isset($alerta['redirect'])): ?>
            window.location.href = <?php echo json_encode($alerta['redirect']); ?>;
            <?php endif; ?>
        });
    <?php endif; ?>
    </script>
</body>
</html>
