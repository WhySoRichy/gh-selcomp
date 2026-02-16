<?php
session_start();
require_once "../conexion/conexion.php";
include 'auth.php';

// Verificar que se proporcionó un ID de archivo
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('ID de archivo inválido');
}

$archivo_id = intval($_GET['id']);

// Verificar permisos adicionales para administradores
if (!isset($_SESSION['usuario_rol']) || 
    ($_SESSION['usuario_rol'] !== 'admin' && $_SESSION['usuario_rol'] !== 'administrador')) {
    http_response_code(403);
    die('Acceso denegado: Permisos insuficientes');
}

try {
    // Obtener información del archivo
    $sql = "
        SELECT 
            na.nombre_original,
            na.nombre_archivo,
            na.ruta_archivo,
            na.tipo_mime,
            na.tamano,
            n.id as notificacion_id,
            n.nombre as titulo
        FROM notificaciones_archivos na
        JOIN notificaciones n ON na.notificacion_id = n.id
        WHERE na.id = :archivo_id
    ";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute(['archivo_id' => $archivo_id]);
    $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$archivo) {
        http_response_code(404);
        die('Archivo no encontrado en la base de datos');
    }
    
    // Construir la ruta completa del archivo
    $proyecto_path = dirname(dirname(__FILE__));
    $ruta_archivo = $archivo['ruta_archivo'];
    
    // Si la ruta comienza con ../, la resolvemos desde el directorio actual
    if (strpos($ruta_archivo, '../') === 0) {
        $ruta_completa = $proyecto_path . DIRECTORY_SEPARATOR . substr($ruta_archivo, 3);
    } else {
        $ruta_completa = $proyecto_path . DIRECTORY_SEPARATOR . $ruta_archivo;
    }
    
    // Normalizar separadores de directorio para el SO actual
    $ruta_completa = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta_completa);
    
    // Verificar que el archivo existe físicamente
    if (!file_exists($ruta_completa)) {
        // Intentar con ruta alternativa dinámica
        $ruta_alternativa = $proyecto_path . DIRECTORY_SEPARATOR . 'Documentos' . DIRECTORY_SEPARATOR . 'Notificaciones' . DIRECTORY_SEPARATOR . $archivo['nombre_archivo'];
        $ruta_alternativa = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta_alternativa);
        
        if (file_exists($ruta_alternativa)) {
            $ruta_completa = $ruta_alternativa;
        } else {
            http_response_code(404);
            die('El archivo no existe en el servidor');
        }
    }
    
    // ✅ FIX BUG S3: Validación estricta de seguridad contra path traversal
    // Verificar que la ruta normalizada está dentro del directorio permitido
    $real_path = realpath($ruta_completa);
    $allowed_dir = realpath($proyecto_path . DIRECTORY_SEPARATOR . 'Documentos' . DIRECTORY_SEPARATOR . 'Notificaciones');
    
    if ($real_path === false || strpos($real_path, $allowed_dir) !== 0) {
        error_log("⚠️ INTENTO DE PATH TRAVERSAL bloqueado en admin: " . $archivo['ruta_archivo'] . " -> " . $ruta_completa);
        http_response_code(403);
        die('Ruta de archivo inválida');
    }
    
    $ruta_normalizada = realpath($ruta_completa);
    $directorio_permitido = realpath($proyecto_path . DIRECTORY_SEPARATOR . 'Documentos' . DIRECTORY_SEPARATOR . 'Notificaciones');
    $directorio_permitido_alt = realpath($proyecto_path . DIRECTORY_SEPARATOR . 'Documentos');
    
    if (!$ruta_normalizada) {
        http_response_code(404);
        die('Archivo no encontrado');
    }
    
    // Verificar que está dentro de directorios permitidos
    if (strpos($ruta_normalizada, $directorio_permitido) !== 0 && 
        strpos($ruta_normalizada, $directorio_permitido_alt) !== 0) {
        error_log("⚠️ INTENTO DE PATH TRAVERSAL bloqueado en admin: Usuario {$_SESSION['usuario_id']}, Archivo ID {$archivo_id}");
        http_response_code(403);
        die('Acceso denegado: ruta no autorizada');
    }
    
    // Validar extensión como capa adicional de seguridad
    $extension = strtolower(pathinfo($archivo['nombre_original'], PATHINFO_EXTENSION));
    $extensiones_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
    
    if (!in_array($extension, $extensiones_permitidas)) {
        http_response_code(403);
        die('Tipo de archivo no permitido');
    }
    
    // Configurar headers para la descarga
    // Limpiar cualquier output previo
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Headers de seguridad y cache
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Preparar nombre de archivo para descarga (mejorado para caracteres especiales)
    $nombre_descarga = $archivo['nombre_original'];
    
    // 🔧 FIX: Limpiar caracteres peligrosos de forma simple
    $caracteres_prohibidos = array('/', '\\', ':', '*', '?', '"', '<', '>', '|', "\0");
    $nombre_descarga = str_replace($caracteres_prohibidos, '_', $nombre_descarga);
    
    // 🔧 FIX: Limitar longitud del nombre
    if (strlen($nombre_descarga) > 200) {
        $extension = pathinfo($nombre_descarga, PATHINFO_EXTENSION);
        $nombre_base = pathinfo($nombre_descarga, PATHINFO_FILENAME);
        $nombre_descarga = substr($nombre_base, 0, 200 - strlen($extension) - 1) . '.' . $extension;
    }
    
    // Headers de contenido
    header('Content-Type: ' . $archivo['tipo_mime']);
    header('Content-Disposition: attachment; filename="' . $nombre_descarga . '"');
    header('Content-Length: ' . filesize($ruta_completa));
    
    // 🔇 Log de auditoría deshabilitado (causaba output antes de descarga)
    // $usuario_id = $_SESSION['usuario_id'] ?? 'N/A';
    // $usuario_email = $_SESSION['usuario_email'] ?? 'N/A';
    // error_log("Descarga de archivo - Usuario: {$usuario_id} ({$usuario_email}), Archivo: {$archivo['nombre_original']}, Notificación: {$archivo['notificacion_id']}");
    
    // Limpiar buffer de salida antes de enviar archivo
    if (ob_get_level()) ob_end_clean();
    
    // Leer y enviar el archivo en chunks para archivos grandes
    $file_handle = fopen($ruta_completa, 'rb');
    if ($file_handle === false) {
        http_response_code(500);
        die('Error al abrir el archivo');
    }
    
    while (!feof($file_handle)) {
        echo fread($file_handle, 8192); // Leer en chunks de 8KB
        flush();
    }
    
    fclose($file_handle);
    exit;
    
} catch (Exception $e) {
    error_log("Error al descargar archivo desde admin: " . $e->getMessage());
    http_response_code(500);
    die('Error interno del servidor');
}
