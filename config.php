<?php
/**
 * Configuración principal del sistema
 * Carga variables de entorno y configuración
 */

// Función para cargar variables de entorno
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception("Archivo .env no encontrado: $path");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Omitir comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parsear variable
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remover comillas si existen (solo si el valor no está vacío)
            if (strlen($value) >= 2) {
                if (($value[0] === '"' && $value[-1] === '"') ||
                    ($value[0] === "'" && $value[-1] === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Cargar variables de entorno
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    loadEnv($envPath);
}

// Configuración de la aplicación
define('BASE_URL', $_ENV['BASE_URL'] ?? '/gh/');
define('ENVIRONMENT', $_ENV['ENVIRONMENT'] ?? 'production');

// Configuración de base de datos
if (empty($_ENV['DB_HOST']) || empty($_ENV['DB_NAME']) || empty($_ENV['DB_USER'])) {
    throw new Exception('Variables de entorno de base de datos no configuradas (DB_HOST, DB_NAME, DB_USER). Verifique el archivo .env');
}
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Configuración de seguridad
if (empty($_ENV['CSRF_SECRET']) || $_ENV['CSRF_SECRET'] === 'default_secret_key') {
    throw new Exception('CSRF_SECRET no configurado en .env');
}
define('CSRF_SECRET', $_ENV['CSRF_SECRET']);
define('SESSION_SECURE', ($_ENV['SESSION_SECURE'] ?? 'false') === 'true');

// Clave de cifrado para secretos TOTP (AES-256-CBC, 64 hex chars = 32 bytes)
define('APP_KEY', $_ENV['APP_KEY'] ?? '');

// Configuración de email
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? 'tls');

// Configuración de errores según el entorno
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configuración segura de cookies de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
if (SESSION_SECURE || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
    ini_set('session.cookie_secure', 1);
}

// Función helper para generar URLs
function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

// Función helper para obtener configuración
function config($key, $default = null) {
    return $_ENV[$key] ?? $default;
}
