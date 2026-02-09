<?php
session_start();

// Headers de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Si el usuario ya está autenticado, redirigir según su rol
if (isset($_SESSION['usuario_id'])) {
    if (isset($_SESSION['usuario_rol']) && ($_SESSION['usuario_rol'] === 'admin' || $_SESSION['usuario_rol'] === 'administrador')) {
        header('Location: administrador/index.php');
    } else {
        header('Location: usuario/index.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Portal Gestión Humana</title>
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
        <h2>Iniciar Sesión</h2>
        <form action="procesar_login.php" method="post" autocomplete="off">
            <label for="email" class="sr-only">Correo electrónico</label>
            <input type="email" id="email" name="email" placeholder="Correo electrónico" required>
            <label for="password" class="sr-only">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="Contraseña" required>
            <a href="olvido_contraseña.php" class="forgot">¿Olvidaste tu contraseña?</a>
            <button type="submit" class="btn">Ingresar</button>
        </form>
    </div>
    <?php require_once 'mensaje_alerta.php'; ?>
</body>
</html>
