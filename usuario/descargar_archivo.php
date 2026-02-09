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
$usuario_id = $_SESSION['usuario_id'];
$usuario_rol = $_SESSION['usuario_rol'] ?? 'usuario';

try {
    // Obtener información del archivo y verificar permisos
    $sql = "
        SELECT 
            na.nombre_original,
            na.nombre_archivo,
            na.ruta_archivo,
            na.tipo_mime,
            na.tamano,
            n.id as notificacion_id,
            n.destino
        FROM notificaciones_archivos na
        JOIN notificaciones n ON na.notificacion_id = n.id
        WHERE na.id = :archivo_id AND n.estado = 'activa'
    ";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute(['archivo_id' => $archivo_id]);
    $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$archivo) {
        http_response_code(404);
        die('Archivo no encontrado');
    }
    
    // Verificar si el usuario tiene permiso para descargar este archivo
    $tiene_permiso = false;
    
    if ($archivo['destino'] === 'todos') {
        $tiene_permiso = true;
    } elseif ($archivo['destino'] === 'administradores' && $usuario_rol === 'admin') {
        $tiene_permiso = true;
    } elseif ($archivo['destino'] === 'regulares' && $usuario_rol === 'usuario') {
        $tiene_permiso = true;
    } elseif ($archivo['destino'] === 'especificos') {
        // Verificar si el usuario está en la lista de destinatarios específicos
        $stmt = $conexion->prepare("
            SELECT 1 FROM notificaciones_usuarios 
            WHERE notificacion_id = :notificacion_id AND usuario_id = :usuario_id
        ");
        $stmt->execute([
            'notificacion_id' => $archivo['notificacion_id'],
            'usuario_id' => $usuario_id
        ]);
        $tiene_permiso = $stmt->fetch() !== false;
    }
    
    if (!$tiene_permiso) {
        http_response_code(403);
        die('No tienes permiso para descargar este archivo');
    }
    
    // ✅ FIX BUG S3: Protección mejorada contra path traversal
    // Sanitizar ruta de archivo eliminando secuencias peligrosas
    $ruta_archivo = $archivo['ruta_archivo'];
    
    // Rechazar cualquier intento de path traversal
    if (strpos($ruta_archivo, '..') !== false || 
        strpos($ruta_archivo, './') !== false || 
        strpos($ruta_archivo, '~') !== false) {
        http_response_code(403);
        die('Ruta de archivo inválida');
    }
    
    // Construir ruta base segura
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/gh/';
    
    // Si la ruta comienza con ../, rechazar (ya validado arriba, doble seguridad)
    if (strpos($ruta_archivo, '../') === 0) {
        $ruta_completa = $base_path . substr($ruta_archivo, 3);
    } else {
        $ruta_completa = $base_path . $ruta_archivo;
    }
    
    // Normalizar separadores de directorio para Windows
    $ruta_completa = str_replace('/', DIRECTORY_SEPARATOR, $ruta_completa);
    
    // Verificar que el archivo existe físicamente
    if (!file_exists($ruta_completa)) {
        // Intentar con ruta alternativa por si acaso
        $ruta_alternativa = $_SERVER['DOCUMENT_ROOT'] . '/gh/Documentos/Notificaciones/' . $archivo['nombre_archivo'];
        $ruta_alternativa = str_replace('/', DIRECTORY_SEPARATOR, $ruta_alternativa);
        
        if (file_exists($ruta_alternativa)) {
            $ruta_completa = $ruta_alternativa;
        } else {
            http_response_code(404);
            die('El archivo no existe en el servidor');
        }
    }
    
    // ✅ FIX BUG S3: Validación estricta de seguridad contra path traversal
    $ruta_normalizada = realpath($ruta_completa);
    $directorio_permitido = realpath($_SERVER['DOCUMENT_ROOT'] . '/gh/Documentos/');
    
    // Verificar que realpath() tuvo éxito (archivo existe)
    if ($ruta_normalizada === false) {
        http_response_code(404);
        die('Archivo no encontrado');
    }
    
    // CRÍTICO: Verificar que la ruta normalizada está dentro del directorio permitido
    if (strpos($ruta_normalizada, $directorio_permitido) !== 0) {
        error_log("⚠️ INTENTO DE PATH TRAVERSAL bloqueado: Usuario {$usuario_id}, Archivo ID {$archivo_id}, Ruta: {$ruta_archivo}");
        http_response_code(403);
        die('Acceso denegado: ruta no autorizada');
    }
    
    // Tipos MIME seguros permitidos
    $mimes_permitidos = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/gif',
        'text/plain'
    ];
    
    // Validar extensión del archivo como capa adicional
    $extension = strtolower(pathinfo($archivo['nombre_original'], PATHINFO_EXTENSION));
    $extensiones_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
    
    if (!in_array($extension, $extensiones_permitidas)) {
        http_response_code(403);
        die('Tipo de archivo no permitido');
    }
    
    // Verificar MIME type
    if (!in_array($archivo['tipo_mime'], $mimes_permitidos)) {
        http_response_code(403);
        die('Tipo MIME no permitido');
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
    
    // Preparar nombre de archivo para descarga (escapar caracteres especiales)
    $nombre_descarga = mb_convert_encoding($archivo['nombre_original'], 'UTF-8', 'UTF-8');
    $nombre_descarga = preg_replace('/[^\w\-_\.]/', '_', $nombre_descarga);
    
    // Headers de contenido
    header('Content-Type: ' . $archivo['tipo_mime']);
    header('Content-Disposition: attachment; filename="' . $nombre_descarga . '"');
    header('Content-Length: ' . filesize($ruta_completa));
    
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
    error_log("Error al descargar archivo: " . $e->getMessage());
    http_response_code(500);
    die('Error interno del servidor');
}
