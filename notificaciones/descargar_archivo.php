<?php
/**
 * Descarga de archivos adjuntos de notificaciones y respuestas
 * Accesible para usuarios y administradores que tengan acceso a la notificación
 */
session_start();
require_once "../conexion/conexion.php";

// Verificar autenticación básica
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    die('No autenticado');
}

$usuario_id = $_SESSION['usuario_id'];
$es_admin = isset($_SESSION['usuario_rol']) &&
            in_array(strtolower($_SESSION['usuario_rol']), ['admin', 'administrador']);

$proyecto_path = dirname(dirname(__FILE__));

// ====================================================================
// MODO 1: Descarga de archivos de RESPUESTAS (por ruta)
// ====================================================================
if (isset($_GET['tipo']) && $_GET['tipo'] === 'respuesta' && isset($_GET['ruta'])) {
    $ruta = $_GET['ruta'];
    $nombre_original = $_GET['nombre'] ?? basename($ruta);
    
    // Validación básica de la ruta
    if (empty($ruta) || strpos($ruta, '..') !== false) {
        http_response_code(400);
        die('Ruta inválida');
    }
    
    // Verificar que la ruta sea del directorio de respuestas
    if (strpos($ruta, 'Documentos/Respuestas/') !== 0) {
        http_response_code(403);
        die('Acceso no permitido a esta ruta');
    }
    
    // Construir ruta completa
    $ruta_completa = $proyecto_path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ruta);
    
    // Verificar que el archivo existe
    if (!file_exists($ruta_completa)) {
        http_response_code(404);
        die('Archivo no encontrado');
    }
    
    // Validación de seguridad contra path traversal
    $real_path = realpath($ruta_completa);
    $allowed_dir = realpath($proyecto_path . DIRECTORY_SEPARATOR . 'Documentos' . DIRECTORY_SEPARATOR . 'Respuestas');
    
    if ($real_path === false || strpos($real_path, $allowed_dir) !== 0) {
        error_log("⚠️ INTENTO DE PATH TRAVERSAL bloqueado (respuestas): Usuario {$usuario_id}");
        http_response_code(403);
        die('Ruta de archivo inválida');
    }
    
    // Validar extensión
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    $extensiones_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip', 'rar'];
    
    if (!in_array($extension, $extensiones_permitidas)) {
        http_response_code(403);
        die('Tipo de archivo no permitido');
    }
    
    // Determinar MIME type
    $mime_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed'
    ];
    $tipo_mime = $mime_types[$extension] ?? 'application/octet-stream';
    
    // Enviar archivo
    enviarArchivo($real_path, $nombre_original, $tipo_mime);
    exit;
}

// ====================================================================
// MODO 2: Descarga de archivos de NOTIFICACIONES (por ID - modo original)
// ====================================================================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('ID de archivo inválido');
}

$archivo_id = intval($_GET['id']);

// ====================================================================
// FUNCIÓN AUXILIAR: Enviar archivo al navegador
// ====================================================================
function enviarArchivo($ruta_real, $nombre_descarga, $tipo_mime) {
    // Limpiar caracteres peligrosos del nombre
    $caracteres_prohibidos = array('/', '\\', ':', '*', '?', '"', '<', '>', '|', "\0");
    $nombre_descarga = str_replace($caracteres_prohibidos, '_', $nombre_descarga);
    
    // Limitar longitud del nombre
    if (strlen($nombre_descarga) > 200) {
        $ext = pathinfo($nombre_descarga, PATHINFO_EXTENSION);
        $nombre_base = pathinfo($nombre_descarga, PATHINFO_FILENAME);
        $nombre_descarga = substr($nombre_base, 0, 200 - strlen($ext) - 1) . '.' . $ext;
    }
    
    // Limpiar cualquier output previo
    if (ob_get_level()) ob_end_clean();
    
    // Headers de seguridad y cache
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Headers de contenido
    header('Content-Type: ' . $tipo_mime);
    header('Content-Disposition: attachment; filename="' . $nombre_descarga . '"');
    header('Content-Length: ' . filesize($ruta_real));
    
    // Limpiar buffer antes de enviar
    if (ob_get_level()) ob_end_clean();
    
    // Leer y enviar el archivo en chunks
    $file_handle = fopen($ruta_real, 'rb');
    if ($file_handle === false) {
        http_response_code(500);
        die('Error al abrir el archivo');
    }
    
    while (!feof($file_handle)) {
        echo fread($file_handle, 8192);
        flush();
    }
    
    fclose($file_handle);
    exit;
}

try {
    // Obtener información del archivo y verificar acceso
    if ($es_admin) {
        // Los administradores pueden descargar cualquier archivo
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
    } else {
        // Los usuarios solo pueden descargar archivos de notificaciones a las que tienen acceso
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
            LEFT JOIN notificaciones_usuarios nu ON n.id = nu.notificacion_id AND nu.usuario_id = :usuario_id
            WHERE na.id = :archivo_id
            AND (
                n.destino = 'todos'
                OR (n.destino = 'regulares' AND EXISTS (
                    SELECT 1 FROM usuarios u WHERE u.id = :usuario_id2 AND LOWER(u.rol) NOT IN ('admin', 'administrador')
                ))
                OR (n.destino = 'administradores' AND EXISTS (
                    SELECT 1 FROM usuarios u WHERE u.id = :usuario_id3 AND LOWER(u.rol) IN ('admin', 'administrador')
                ))
                OR nu.usuario_id IS NOT NULL
            )
        ";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            'archivo_id' => $archivo_id,
            'usuario_id' => $usuario_id,
            'usuario_id2' => $usuario_id,
            'usuario_id3' => $usuario_id
        ]);
    }

    $archivo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$archivo) {
        http_response_code(404);
        die('Archivo no encontrado o no tienes acceso');
    }

    // Construir la ruta completa del archivo
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

    // Validación de seguridad contra path traversal
    $real_path = realpath($ruta_completa);
    $allowed_dir = realpath($proyecto_path . DIRECTORY_SEPARATOR . 'Documentos' . DIRECTORY_SEPARATOR . 'Notificaciones');
    $allowed_dir_alt = realpath($proyecto_path . DIRECTORY_SEPARATOR . 'Documentos');

    if ($real_path === false ||
        (strpos($real_path, $allowed_dir) !== 0 && strpos($real_path, $allowed_dir_alt) !== 0)) {
        error_log("⚠️ INTENTO DE PATH TRAVERSAL bloqueado: Usuario {$usuario_id}, Archivo ID {$archivo_id}");
        http_response_code(403);
        die('Ruta de archivo inválida');
    }

    // Validar extensión como capa adicional de seguridad
    $extension = strtolower(pathinfo($archivo['nombre_original'], PATHINFO_EXTENSION));
    $extensiones_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip', 'rar'];

    if (!in_array($extension, $extensiones_permitidas)) {
        http_response_code(403);
        die('Tipo de archivo no permitido');
    }

    // Enviar archivo usando la función auxiliar
    enviarArchivo($real_path, $archivo['nombre_original'], $archivo['tipo_mime']);

} catch (Exception $e) {
    error_log("Error al descargar archivo de notificación: " . $e->getMessage());
    http_response_code(500);
    die('Error interno del servidor');
}
