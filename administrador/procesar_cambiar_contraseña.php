<?php
session_start();
include 'auth.php';
include 'csrf_protection.php';
require_once __DIR__ . "/../conexion/conexion.php";

// Detectar página de origen para redirect correcto
$pagina_origen = $_SERVER['HTTP_REFERER'] ?? '';
$redirect_page = 'seguridad.php'; // Por defecto

if (strpos($pagina_origen, 'cambiar_contrase') !== false) {
    $redirect_page = 'cambiar_contraseña.php';
}

// Verificar token CSRF
verificar_csrf();

$usuario_id = $_SESSION['usuario_id'];
$actual_contrasena = trim($_POST['actual_contrasena'] ?? '');
$nueva_contrasena = trim($_POST['nueva_contrasena'] ?? '');
$confirmar_contrasena = trim($_POST['confirmar_contrasena'] ?? '');

// Validaciones
if (empty($actual_contrasena) || empty($nueva_contrasena) || empty($confirmar_contrasena)) {
    $_SESSION['titulo'] = 'Campos incompletos';
    $_SESSION['mensaje'] = 'Todos los campos son obligatorios';
    $_SESSION['tipo_alerta'] = 'error';
    header("Location: $redirect_page");
    exit;
}

if ($nueva_contrasena !== $confirmar_contrasena) {
    $_SESSION['titulo'] = 'Error de validación';
    $_SESSION['mensaje'] = 'Las contraseñas no coinciden';
    $_SESSION['tipo_alerta'] = 'error';
    header("Location: $redirect_page");
    exit;
}

if (strlen($nueva_contrasena) < 8) {
    $_SESSION['titulo'] = 'Contraseña muy corta';
    $_SESSION['mensaje'] = 'La nueva contraseña debe tener al menos 8 caracteres';
    $_SESSION['tipo_alerta'] = 'error';
    header("Location: $redirect_page");
    exit;
}

// Validar complejidad de contraseña
$tiene_mayuscula = preg_match('/[A-Z]/', $nueva_contrasena);
$tiene_minuscula = preg_match('/[a-z]/', $nueva_contrasena);
$tiene_numero = preg_match('/[0-9]/', $nueva_contrasena);
$tiene_especial = preg_match('/[^A-Za-z0-9]/', $nueva_contrasena);

if (!$tiene_mayuscula || !$tiene_minuscula || !$tiene_numero) {
    $_SESSION['titulo'] = 'Contraseña débil';
    $_SESSION['mensaje'] = 'La contraseña debe incluir mayúsculas, minúsculas y números';
    $_SESSION['tipo_alerta'] = 'error';
    header("Location: $redirect_page");
    exit;
}

// Validar que la nueva contraseña no sea igual a la actual
if ($actual_contrasena === $nueva_contrasena) {
    $_SESSION['titulo'] = 'Error de validación';
    $_SESSION['mensaje'] = 'La nueva contraseña debe ser diferente a la actual';
    $_SESSION['tipo_alerta'] = 'error';
    header("Location: $redirect_page");
    exit;
}

// Verificar contraseña actual
try {
    $stmt = $conexion->prepare("SELECT password_hash FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || !password_verify($actual_contrasena, $usuario['password_hash'])) {
        $_SESSION['titulo'] = 'Error de autenticación';
        $_SESSION['mensaje'] = 'La contraseña actual es incorrecta';
        $_SESSION['tipo_alerta'] = 'error';
        header("Location: $redirect_page");
        exit;
    }

    // Generar nuevo hash y actualizar
    $nuevo_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
    $stmt = $conexion->prepare("UPDATE usuarios SET password_hash = :hash WHERE id = :id");
    $stmt->bindParam(':hash', $nuevo_hash);
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();

    // Registrar cambio en historial
    try {
        $stmt_log = $conexion->prepare("INSERT INTO historial_accesos 
            (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles) 
            VALUES (?, NOW(), ?, ?, ?, 1, 'Cambio de contraseña exitoso (Admin)')");
        $stmt_log->execute([
            $usuario_id, 
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido', 
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'
        ]);
    } catch (PDOException $e) {
        error_log("Error al registrar cambio de contraseña en historial: " . $e->getMessage());
    }

    $_SESSION['titulo'] = 'Contraseña actualizada';
    $_SESSION['mensaje'] = 'Tu contraseña ha sido cambiada exitosamente';
    $_SESSION['tipo_alerta'] = 'success';
    header("Location: $redirect_page");
    exit;

} catch (Exception $e) {
    error_log("Error al cambiar contraseña admin: " . $e->getMessage());
    $_SESSION['titulo'] = 'Error del sistema';
    $_SESSION['mensaje'] = 'Ocurrió un error al cambiar la contraseña. Inténtalo nuevamente.';
    $_SESSION['tipo_alerta'] = 'error';
    header("Location: $redirect_page");
    exit;
}
?>
