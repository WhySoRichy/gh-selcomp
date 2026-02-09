<?php
session_start();
require_once __DIR__ . "/conexion/conexion.php";

// Registrar el cierre de sesión en el historial antes de destruir la sesión
if (isset($_SESSION['usuario_id'])) {
    try {
        $usuario_id = $_SESSION['usuario_id'];
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Si es IPv6 local, intentar obtener la IP real
        if ($ip === '::1') {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } else {
                $ip = '127.0.0.1';
            }
        }
        
        $dispositivo = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
        $navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
        $detalles = "Cierre de sesión manual - Usuario";
        $exito = true;
        
        $stmt = $conexion->prepare("INSERT INTO historial_accesos 
            (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles) 
            VALUES (?, NOW(), ?, ?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $ip, $dispositivo, $navegador, $exito, $detalles]);
    } catch (Exception $e) {
        // Solo registramos el error, no interrumpimos el flujo
        error_log("Error al registrar cierre de sesión: " . $e->getMessage());
    }
}

// Elimina todas las variables de sesión
$_SESSION = [];

// Destruye la sesión
session_destroy();

// Opcional: elimina la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirige al inicio (o login)
header("Location: index.php");
exit;
?>
