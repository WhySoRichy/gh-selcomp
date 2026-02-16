<?php
/**
 * API para activar/desactivar autenticación 2FA (TOTP) - Administrador
 * Endpoint: POST /administrador/toggle_2fa.php
 * Al activar: devuelve URL de redirección a configurar_2fa.php
 * Al desactivar: bloqueado para administradores (2FA obligatoria)
 */
session_start();

require_once __DIR__ . '/../config.php';

// Configurar respuesta JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Verificar rol de administrador
if ($_SESSION['usuario_rol'] !== 'admin' && $_SESSION['usuario_rol'] !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../conexion/conexion.php';
require_once 'csrf_protection.php';

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

// Los administradores no pueden desactivar 2FA (es obligatoria)
if (!$activar) {
    echo json_encode(['success' => false, 'message' => 'La verificación 2FA es obligatoria para administradores y no puede ser desactivada.']);
    exit;
}

try {
    // Verificar que el usuario exista
    $stmt = $conexion->prepare("SELECT id, email, nombre, secreto_2fa FROM usuarios WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }

    // Si ya tiene secreto configurado, no necesita re-configurar
    if (!empty($usuario['secreto_2fa'])) {
        echo json_encode(['success' => false, 'message' => 'La autenticación 2FA ya está configurada.']);
        exit;
    }
    
    // Registrar intento en historial
    try {
        $stmt_log = $conexion->prepare("INSERT INTO historial_accesos 
            (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles) 
            VALUES (?, NOW(), ?, ?, ?, 1, ?)");
        $stmt_log->execute([
            $usuario_id, 
            $_SERVER['REMOTE_ADDR'], 
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido', 
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido', 
            'Iniciando configuración 2FA por App de Autenticación (Admin)'
        ]);
    } catch (PDOException $e) {
        error_log("Error al registrar cambio 2FA admin en historial: " . $e->getMessage());
    }
    
    // Preparar sesión para configurar_2fa.php
    $_SESSION['2fa_setup_pendiente'] = true;
    $_SESSION['2fa_setup_usuario_id'] = $usuario['id'];
    $_SESSION['2fa_setup_email'] = $usuario['email'];
    $_SESSION['2fa_setup_es_admin'] = true;
    $_SESSION['2fa_setup_time'] = time();
    $_SESSION['2fa_redirect_after_setup'] = '/gh/administrador/seguridad.php';
    
    echo json_encode([
        'success' => true, 
        'message' => 'Redirigiendo a configuración de autenticación...',
        'redirect' => '/gh/configurar_2fa.php'
    ]);
    
} catch (PDOException $e) {
    error_log("Error al toggle 2FA admin: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar configuración']);
}
