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
define('ENVIRONMENT', $_ENV['ENVIRONMENT'] ?? 'development');

// Configuración de base de datos
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'gestionhumana');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Configuración de seguridad
define('CSRF_SECRET', $_ENV['CSRF_SECRET'] ?? 'default_secret_key');
define('SESSION_SECURE', $_ENV['SESSION_SECURE'] === 'true');

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

// Función helper para generar URLs
function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

// Función helper para obtener configuración
function config($key, $default = null) {
    return $_ENV[$key] ?? $default;
}
