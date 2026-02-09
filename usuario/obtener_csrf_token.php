<?php
/**
 * ENDPOINT SEGURO PARA OBTENER TOKEN CSRF - USUARIO
 * Portal de Gestión Humana
 */

session_start();
require_once "../conexion/conexion.php";
include 'auth.php';

// Verificar que es una petición AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    exit('Acceso denegado');
}

// Verificar referrer para mayor seguridad
$allowed_domains = ['localhost', '127.0.0.1'];
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$valid_referrer = false;

foreach ($allowed_domains as $domain) {
    if (strpos($referrer, $domain) !== false) {
        $valid_referrer = true;
        break;
    }
}

if (!$valid_referrer && !empty($referrer)) {
    http_response_code(403);
    exit('Referrer no válido');
}

try {
    // Generar o renovar token CSRF
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Respuesta JSON segura
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo json_encode([
        'success' => true,
        'token' => $_SESSION['csrf_token'],
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ], JSON_UNESCAPED_UNICODE);
}
?>