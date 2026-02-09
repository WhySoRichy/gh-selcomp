<?php
// Proteccion contra acceso directo
if (basename($_SERVER['PHP_SELF']) === 'csrf_protection.php') {
    header('HTTP/1.0 403 Forbidden');
    exit('Acceso directo no permitido');
}

/**
 * Sistema de proteccion CSRF con expiracion
 * Genera y valida tokens CSRF con tiempo de vida limitado
 * 
 * Configuracion:
 * - Tokens expiran despues de 30 minutos
 * - Se regeneran automaticamente al expirar
 * - Proteccion contra timing attacks con hash_equals()
 */

// Tiempo de vida del token en segundos (30 minutos)
define('CSRF_TOKEN_LIFETIME', 1800);

function generar_token_csrf($forzar_regeneracion = false) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $ahora = time();
    $token_expirado = false;
    
    // Verificar si el token existe y si ha expirado
    if (isset($_SESSION['csrf_token_timestamp'])) {
        $tiempo_transcurrido = $ahora - $_SESSION['csrf_token_timestamp'];
        $token_expirado = $tiempo_transcurrido > CSRF_TOKEN_LIFETIME;
    }
    
    // Generar nuevo token si no existe, expiro o se fuerza regeneracion
    if (!isset($_SESSION['csrf_token']) || $token_expirado || $forzar_regeneracion) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_timestamp'] = $ahora;
    }
    
    return $_SESSION['csrf_token'];
}

function validar_token_csrf($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar que existan el token
    if (!isset($_SESSION['csrf_token']) || !$token) {
        return false;
    }
    
    // Si no existe timestamp, generarlo ahora
    if (!isset($_SESSION['csrf_token_timestamp'])) {
        $_SESSION['csrf_token_timestamp'] = time();
    }
    
    // Verificar expiracion
    $ahora = time();
    $tiempo_transcurrido = $ahora - $_SESSION['csrf_token_timestamp'];
    
    if ($tiempo_transcurrido > CSRF_TOKEN_LIFETIME) {
        generar_token_csrf(true);
        return false;
    }
    
    // Validar token con proteccion contra timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

function campo_csrf_token() {
    $token = generar_token_csrf();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function verificar_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        
        if (!validar_token_csrf($token)) {
            http_response_code(403);
            die('Token CSRF invalido. Accion no permitida.');
        }
    }
}