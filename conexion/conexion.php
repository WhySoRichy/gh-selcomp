<?php
// Protección contra acceso directo
if (basename($_SERVER['PHP_SELF']) === 'conexion.php') {
    header('HTTP/1.0 403 Forbidden');
    exit('Acceso directo no permitido');
}

// Cargar configuración
require_once __DIR__ . '/../config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $conexion = new PDO($dsn, DB_USER, DB_PASS);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    if (ENVIRONMENT === 'development') {
        die("Error de conexión: " . $e->getMessage());
    } else {
        error_log("Error de conexión DB: " . $e->getMessage());
        die("Error de conexión a la base de datos. Contacte al administrador.");
    }
}