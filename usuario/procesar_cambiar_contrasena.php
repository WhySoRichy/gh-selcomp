<?php
/**
 * Procesa el cambio de contraseña del usuario
 * Realiza todas las validaciones necesarias y actualiza la contraseña en la base de datos
 */

session_start();
include 'auth.php';
require_once __DIR__ . '/../administrador/csrf_protection.php';
require_once __DIR__ . "/../conexion/conexion.php";

// Verificar token CSRF
verificar_csrf();

$usuario_id = $_SESSION['usuario_id'];
$actual_contrasena = $_POST['actual_contrasena'] ?? '';
$nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
$confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';

// Validaciones básicas
if (empty($actual_contrasena) || empty($nueva_contrasena) || empty($confirmar_contrasena)) {
    $_SESSION['titulo'] = 'Campos incompletos';
    $_SESSION['mensaje'] = 'Todos los campos son obligatorios para cambiar la contraseña';
    $_SESSION['tipo_alerta'] = 'error';
    header("Location: seguridad.php");
    exit;
}

if ($nueva_contrasena !== $confirmar_contrasena) {
    $_SESSION['titulo'] = 'Error de validación';
    $_SESSION['mensaje'] = 'Las contraseñas nuevas no coinciden';
    $_SESSION['tipo_alerta'] = 'error';
    header("Location: seguridad.php");
    exit;
}

// Validaciones de seguridad en la contraseña
if (strlen($nueva_contrasena) < 8) {
    $_SESSION['titulo'] = 'Contraseña insegura';
    $_SESSION['mensaje'] = 'La nueva contraseña debe tener al menos 8 caracteres';
    $_SESSION['tipo_alerta'] = 'error';
    header("Location: seguridad.php");
    exit;
}

// Validación de complejidad de contraseña
$tiene_mayuscula = preg_match('/[A-Z]/', $nueva_contrasena);
$tiene_minuscula = preg_match('/[a-z]/', $nueva_contrasena);
$tiene_numero = preg_match('/[0-9]/', $nueva_contrasena);
$tiene_especial = preg_match('/[^A-Za-z0-9]/', $nueva_contrasena);

if (!$tiene_mayuscula || !$tiene_minuscula || !$tiene_numero || !$tiene_especial) {
    $_SESSION['titulo'] = 'Contraseña insegura';
    $_SESSION['mensaje'] = 'La contraseña debe incluir al menos una mayúscula, una minúscula, un número y un carácter especial';
    $_SESSION['tipo_alerta'] = 'error';
    header("Location: seguridad.php");
    exit;
}

// Validar que la nueva contraseña no sea igual a la anterior
if ($actual_contrasena === $nueva_contrasena) {
    $_SESSION['titulo'] = 'Error de validación';
    $_SESSION['mensaje'] = 'La nueva contraseña debe ser diferente a la actual';
    $_SESSION['tipo_alerta'] = 'error';
    header("Location: seguridad.php");
    exit;
}

try {
    // Verificar la contraseña actual del usuario
    $stmt = $conexion->prepare("SELECT password_hash FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || !password_verify($actual_contrasena, $usuario['password_hash'])) {
        $_SESSION['titulo'] = 'Error de autenticación';
        $_SESSION['mensaje'] = 'La contraseña actual es incorrecta';
        $_SESSION['tipo_alerta'] = 'error';
        header("Location: seguridad.php");
        exit;
    }

    // Actualizar la contraseña
    $nueva_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
    
    $stmt = $conexion->prepare("UPDATE usuarios SET password_hash = :password_hash, fecha_actualizacion = NOW() WHERE id = :id");
    $stmt->bindParam(':password_hash', $nueva_hash);
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $resultado = $stmt->execute();

    if ($resultado) {
        // Registrar el cambio de contraseña en el historial de accesos
        $ip = $_SERVER['REMOTE_ADDR'];
        $dispositivo = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
        $navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
        $detalles = "Cambio de contraseña exitoso";
        $exito = true;
        
        try {
            // Insertar directamente en la tabla con fecha explícita
            $stmt = $conexion->prepare("INSERT INTO historial_accesos 
                (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles) 
                VALUES (?, NOW(), ?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_id, $ip, $dispositivo, $navegador, $exito, $detalles]);
        } catch (PDOException $accesErr) {
            // Si falla el registro del historial, solo lo registramos pero no interrumpimos el proceso
            error_log("Error al registrar historial de acceso: " . $accesErr->getMessage());
            // El cambio de contraseña ya fue exitoso, así que continuamos
        }

        // Mensaje de éxito
        $_SESSION['titulo'] = '¡Contraseña actualizada!';
        $_SESSION['mensaje'] = 'Tu contraseña ha sido cambiada exitosamente.';
        $_SESSION['tipo_alerta'] = 'success';
    } else {
        $_SESSION['titulo'] = 'Error al actualizar';
        $_SESSION['mensaje'] = 'No se pudo actualizar la contraseña. Inténtalo nuevamente.';
        $_SESSION['tipo_alerta'] = 'error';
    }

} catch (PDOException $e) {
    $_SESSION['titulo'] = 'Error en la base de datos';
    $_SESSION['mensaje'] = 'Ha ocurrido un error al procesar tu solicitud. Inténtalo más tarde.';
    $_SESSION['tipo_alerta'] = 'error';
    // Log del error (no mostrado al usuario)
    error_log("Error en cambio de contraseña: " . $e->getMessage());
}

header("Location: seguridad.php");
exit;
