<?php
session_start();
include '../auth.php';
include '../csrf_protection.php';
require_once(__DIR__ . '/../../conexion/conexion.php');

verificar_csrf();

$id = $_POST['id'];
$nombre = $_POST['nombre'];
$apellido = $_POST['apellido'];
$email = $_POST['email'];
$cargo = $_POST['cargo'];
$area = $_POST['area'];
$rol = $_POST['rol'];
// Nuevos campos opcionales
$telefono = $_POST['telefono'] ?? null;
$direccion = $_POST['direccion'] ?? null;
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
$estado_civil = $_POST['estado_civil'] ?? null;
$emergencia_contacto = $_POST['emergencia_contacto'] ?? null;
$emergencia_telefono = $_POST['emergencia_telefono'] ?? null;

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
    // Manejar error de base de datos
    $_SESSION['error_usuario'] = "Error al actualizar el usuario: " . $e->getMessage();
    header("Location: ver_usuarios.php");
    exit;
}
