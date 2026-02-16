<?php
/**
 * Protección contra acceso directo a componentes del sistema
 * Este archivo debe ser incluido en todos los módulos, includes y componentes
 */

// Verificar que el usuario esté autenticado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('HTTP/1.0 403 Forbidden');
    header('Location: ../../index.php');
    exit('Acceso no autorizado');
}

// Lista de archivos que no deben ser accedidos directamente
$archivos_protegidos = [
    'navbar.php',
    'auth.php',
    'navegacion.php',
    'csrf_protection.php',
    'conexion.php'
];

$archivo_actual = basename($_SERVER['PHP_SELF']);

// Si es un archivo protegido y se está accediendo directamente, redirigir
if (in_array($archivo_actual, $archivos_protegidos)) {
    header('HTTP/1.0 403 Forbidden');
    header('Location: ../index.php');
    exit('Acceso directo no permitido');
}

// Verificar que la petición viene de una página válida del sistema
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';

if (!empty($referer)) {
    $referer_host = parse_url($referer, PHP_URL_HOST);
    if ($referer_host !== $host) {
        header('HTTP/1.0 403 Forbidden');
        exit('Acceso no autorizado desde origen externo');
    }
}
?>
