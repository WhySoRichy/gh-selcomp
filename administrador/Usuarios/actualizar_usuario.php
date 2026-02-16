<?php
session_start();
include '../auth.php';
include '../csrf_protection.php';
require_once(__DIR__ . '/../../conexion/conexion.php');

verificar_csrf();

$id = (int)($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$cargo = trim($_POST['cargo'] ?? '');
$area = trim($_POST['area'] ?? '');

// Validar que el rol sea un valor permitido
$roles_validos = ['usuario', 'admin'];
$rol = in_array($_POST['rol'] ?? '', $roles_validos) ? $_POST['rol'] : 'usuario';
// Nuevos campos opcionales
$telefono = trim($_POST['telefono'] ?? '') ?: null;
$direccion = trim($_POST['direccion'] ?? '') ?: null;
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
$estado_civil = trim($_POST['estado_civil'] ?? '') ?: null;
$emergencia_contacto = trim($_POST['emergencia_contacto'] ?? '') ?: null;
$emergencia_telefono = trim($_POST['emergencia_telefono'] ?? '') ?: null;

// Validaciones básicas
if (!$id || !$nombre || !$apellido || !$email) {
    $_SESSION['error_usuario'] = 'Todos los campos obligatorios deben ser válidos.';
    header('Location: ver_usuarios.php');
    exit;
}

try {
    // Verificar si el email ya existe para otro usuario
    $sqlCheck = "SELECT id FROM usuarios WHERE email = :email AND id != :id";
    $stmtCheck = $conexion->prepare($sqlCheck);
    $stmtCheck->bindParam(':email', $email);
    $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
    $stmtCheck->execute();
    
    if ($stmtCheck->rowCount() > 0) {
        // El email ya existe para otro usuario
        $_SESSION['error_usuario'] = "El correo electrónico '$email' ya está registrado para otro usuario.";
        header("Location: ver_usuarios.php");
        exit;
    }

    $sql = "UPDATE usuarios SET 
            nombre = :nombre,
            apellido = :apellido,
            email = :email,
            cargo = :cargo,
            area = :area,
            rol = :rol,
            telefono = :telefono,
            direccion = :direccion,
            fecha_nacimiento = :fecha_nacimiento,
            estado_civil = :estado_civil,
            emergencia_contacto = :emergencia_contacto,
            emergencia_telefono = :emergencia_telefono,
            fecha_actualizacion = NOW()
            WHERE id = :id";
    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':apellido', $apellido);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':cargo', $cargo);
    $stmt->bindParam(':area', $area);
    $stmt->bindParam(':rol', $rol);
    $stmt->bindParam(':telefono', $telefono);
    $stmt->bindParam(':direccion', $direccion);
    $stmt->bindParam(':fecha_nacimiento', $fecha_nacimiento);
    $stmt->bindParam(':estado_civil', $estado_civil);
    $stmt->bindParam(':emergencia_contacto', $emergencia_contacto);
    $stmt->bindParam(':emergencia_telefono', $emergencia_telefono);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['exito_usuario'] = "Usuario actualizado correctamente.";
    header("Location: ver_usuarios.php");
    exit;
    
} catch (PDOException $e) {
    error_log("Error al actualizar usuario ID {$id}: " . $e->getMessage());
    $_SESSION['error_usuario'] = 'Error interno al actualizar el usuario. Contacte al administrador.';
    header("Location: ver_usuarios.php");
    exit;
}
