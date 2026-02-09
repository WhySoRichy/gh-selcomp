<?php
/**
 * Archivo para descargar documentos
 * Requiere autenticación para acceder
 */
session_start();

// ========== VALIDAR AUTENTICACIÓN ==========
// Solo usuarios logueados o administradores pueden descargar documentos
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['admin_id'])) {
    header('HTTP/1.0 401 Unauthorized');
    echo "Acceso no autorizado. Debe iniciar sesión.";
    exit;
}

// Validar que se haya proporcionado un archivo
if (!isset($_GET['archivo']) || empty($_GET['archivo'])) {
    header('HTTP/1.0 400 Bad Request');
    echo "No se proporcionó un archivo para descargar";
    exit;
}

// Obtener la ruta del archivo desde el parámetro GET
$ruta_relativa = $_GET['archivo'];

// ========== VALIDACIÓN DE EXTENSIONES PERMITIDAS ==========
$extension = strtolower(pathinfo($ruta_relativa, PATHINFO_EXTENSION));
$extensiones_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
if (!in_array($extension, $extensiones_permitidas)) {
    header('HTTP/1.0 403 Forbidden');
    echo "Tipo de archivo no permitido";
    exit;
}

// Eliminar cualquier "/gh/" inicial para construir correctamente la ruta física
$ruta_relativa = preg_replace('/^\/gh\//', '', $ruta_relativa);

// Construir la ruta absoluta al archivo
$ruta_absoluta = __DIR__ . '/' . $ruta_relativa;

// Validar que el archivo existe
if (!file_exists($ruta_absoluta)) {
    header('HTTP/1.0 404 Not Found');
    echo "El archivo solicitado no existe";
    exit;
}

// Verificar que el archivo no esté fuera del directorio de documentos (seguridad)
$path_real = realpath($ruta_absoluta);
$directorio_base = realpath(__DIR__);
if (strpos($path_real, $directorio_base) !== 0) {
    header('HTTP/1.0 403 Forbidden');
    echo "Acceso denegado";
    exit;
}

// Determinar el tipo de contenido basado en la extensión del archivo
$extension = strtolower(pathinfo($ruta_absoluta, PATHINFO_EXTENSION));
$content_type = 'application/octet-stream'; // Por defecto

// Enviar cabeceras para forzar la descarga
header('Content-Description: File Transfer');
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . basename($ruta_absoluta) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($ruta_absoluta));

// Leer y enviar el archivo
readfile($ruta_absoluta);
exit;
