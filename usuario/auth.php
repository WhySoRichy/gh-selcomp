<?php
// Headers de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

// PROTECCIÓN ANTI-BYPASS 2FA: Si hay un 2FA pendiente, no permitir acceso
if (isset($_SESSION['2fa_pendiente']) && $_SESSION['2fa_pendiente'] === true) {
    // Limpiar sesión sospechosa y redirigir a verificación 2FA
    unset($_SESSION['usuario_id']);
    unset($_SESSION['usuario_nombre']);
    unset($_SESSION['usuario_rol']);
    header('Location: ../verificar_2fa.php');
    exit;
}

// Verificar tiempo de actividad (expirar después de 30 minutos de inactividad)
$tiempo_maximo_inactividad = 1800; // 30 minutos
$hora_actual = time();

if (isset($_SESSION['ultima_actividad']) && 
    ($hora_actual - $_SESSION['ultima_actividad']) > $tiempo_maximo_inactividad) {
    
    // Registrar cierre de sesión por inactividad
    if (isset($_SESSION['usuario_id'])) {
        try {
            require_once __DIR__ . "/../conexion/conexion.php";
            $usuario_id = $_SESSION['usuario_id'];
            $ip = $_SERVER['REMOTE_ADDR'];
            $dispositivo = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
            $navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
            $detalles = "Cierre de sesión automático por inactividad";
            $exito = true;
            
            $stmt = $conexion->prepare("INSERT INTO historial_accesos 
                (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles) 
                VALUES (?, NOW(), ?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_id, $ip, $dispositivo, $navegador, $exito, $detalles]);
        } catch (Exception $e) {
            // Solo registramos el error, no interrumpimos el flujo
            error_log("Error al registrar cierre por inactividad: " . $e->getMessage());
        }
    }
    
    // Destruir la sesión
    session_unset();
    session_destroy();
    
    // Redirigir a la página de login
    header('Location: ../index.php?sesion=expirada');
    exit;
}

// Actualizar el tiempo de última actividad
$_SESSION['ultima_actividad'] = $hora_actual;

// Regenerar el ID de sesión periódicamente para prevenir fijación de sesión
if (!isset($_SESSION['hora_creacion_sesion'])) {
    $_SESSION['hora_creacion_sesion'] = $hora_actual;
} else if ($hora_actual - $_SESSION['hora_creacion_sesion'] > 300) { // Cada 5 minutos
    // Regenerar ID de sesión
    session_regenerate_id(true);
    $_SESSION['hora_creacion_sesion'] = $hora_actual;
}

// Verificar que sea un usuario normal (no administrador)
if (isset($_SESSION['usuario_rol']) && ($_SESSION['usuario_rol'] === 'admin' || $_SESSION['usuario_rol'] === 'administrador')) {
    header('Location: ../administrador/index.php');
    exit;
}
?>
