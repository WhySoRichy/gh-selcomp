<?php
/**
 * Procesar documentos del usuario
 * Maneja subida y eliminación de documentos
 */

// Desactivar mostrar errores en la salida (para respuestas JSON limpias)
ini_set('display_errors', 0);
error_reporting(0);

// Iniciar sesión PRIMERO
session_start();

// Limpiar cualquier output previo y establecer headers JSON
ob_start();
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

require_once "../conexion/conexion.php";

// Función para responder JSON y terminar
function responderJSON($success, $message, $data = []) {
    $response = array_merge(['success' => $success, 'message' => $message], $data);
    echo json_encode($response);
    exit;
}

// Validar autenticación
if (!isset($_SESSION['usuario_id'])) {
    responderJSON(false, 'Usuario no autenticado');
}

// Verificar que sea un usuario normal
if (isset($_SESSION['usuario_rol']) && in_array($_SESSION['usuario_rol'], ['admin', 'administrador'])) {
    responderJSON(false, 'Acceso denegado');
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJSON(false, 'Método no permitido');
}

// Verificar token CSRF
require_once __DIR__ . '/../administrador/csrf_protection.php';
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validar_token_csrf($csrf_token)) {
    responderJSON(false, 'Token de seguridad inválido. Recarga la página.');
}

$usuario_id = $_SESSION['usuario_id'];
$action = $_POST['action'] ?? 'subir';

try {
    // ========== ELIMINAR DOCUMENTO ==========
    if ($action === 'eliminar') {
        $documento_id = $_POST['documento_id'] ?? null;
        
        if (!$documento_id) {
            responderJSON(false, 'ID de documento no válido');
        }
        
        // Buscar documento
        $stmt = $conexion->prepare("SELECT ruta_archivo FROM documentos_usuarios WHERE id = :id AND usuario_id = :usuario_id");
        $stmt->bindParam(':id', $documento_id);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$documento) {
            responderJSON(false, 'Documento no encontrado');
        }
        
        // Eliminar archivo físico
        $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $documento['ruta_archivo'];
        if (file_exists($ruta_fisica)) {
            @unlink($ruta_fisica);
        }
        
        // Eliminar de la BD
        $stmt = $conexion->prepare("DELETE FROM documentos_usuarios WHERE id = :id AND usuario_id = :usuario_id");
        $stmt->bindParam(':id', $documento_id);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();
        
        responderJSON(true, 'Documento eliminado exitosamente');
    }
    
    // ========== SUBIR DOCUMENTO ==========
    if (!isset($_FILES['archivo'])) {
        responderJSON(false, 'No se recibió ningún archivo');
    }
    
    if ($_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errores = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Error del servidor: carpeta temporal no disponible',
            UPLOAD_ERR_CANT_WRITE => 'Error del servidor: no se pudo escribir el archivo',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
        ];
        $error_msg = $errores[$_FILES['archivo']['error']] ?? 'Error desconocido al subir el archivo';
        responderJSON(false, $error_msg);
    }
    
    $archivo = $_FILES['archivo'];
    $tipo_documento = $_POST['tipo_documento'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    // Validar tipo de documento
    $tipos_validos = ['hoja_vida', 'certificado', 'certificacion', 'experiencia_laboral', 'otros'];
    if (!in_array($tipo_documento, $tipos_validos)) {
        responderJSON(false, 'Tipo de documento no válido');
    }
    
    // Validar MIME type (debe ser PDF)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    if ($mime_type !== 'application/pdf') {
        responderJSON(false, 'Solo se aceptan archivos PDF');
    }
    
    // Validar tamaño (máximo 10 MB)
    if ($archivo['size'] > 10 * 1024 * 1024) {
        responderJSON(false, 'El archivo no debe superar los 10 MB');
    }
    
    // Mapeo de carpetas
    $carpetas = [
        'hoja_vida' => 'HojasDeVida',
        'certificado' => 'Certificados',
        'certificacion' => 'Certificaciones',
        'experiencia_laboral' => 'ExperienciaLaboral',
        'otros' => 'Otros'
    ];
    
    $carpeta_destino = $carpetas[$tipo_documento];
    $base_dir = dirname(__DIR__);
    $directorio_destino = $base_dir . DIRECTORY_SEPARATOR . 'Documentos' . DIRECTORY_SEPARATOR . $carpeta_destino;
    
    // Crear directorio si no existe
    if (!is_dir($directorio_destino)) {
        if (!@mkdir($directorio_destino, 0755, true)) {
            responderJSON(false, 'Error al preparar la carpeta de destino');
        }
    }
    
    // Generar nombre único para el archivo
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $nombre_archivo = $usuario_id . '_' . $tipo_documento . '_' . uniqid() . '.' . $extension;
    $ruta_completa = $directorio_destino . DIRECTORY_SEPARATOR . $nombre_archivo;
    $ruta_web = "/gh/Documentos/{$carpeta_destino}/{$nombre_archivo}";
    
    // Mover archivo
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
        responderJSON(false, 'Error al guardar el archivo en el servidor');
    }
    
    // Guardar en base de datos
    $stmt = $conexion->prepare("
        INSERT INTO documentos_usuarios 
        (usuario_id, tipo_documento, nombre_original, nombre_archivo, ruta_archivo, tamano_archivo, descripcion) 
        VALUES (:usuario_id, :tipo_documento, :nombre_original, :nombre_archivo, :ruta_archivo, :tamano_archivo, :descripcion)
    ");
    
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->bindParam(':tipo_documento', $tipo_documento);
    $stmt->bindParam(':nombre_original', $archivo['name']);
    $stmt->bindParam(':nombre_archivo', $nombre_archivo);
    $stmt->bindParam(':ruta_archivo', $ruta_web);
    $stmt->bindParam(':tamano_archivo', $archivo['size']);
    $stmt->bindParam(':descripcion', $descripcion);
    
    if ($stmt->execute()) {
        $nuevo_id = $conexion->lastInsertId();
        responderJSON(true, 'Documento subido exitosamente', [
            'documento_id' => $nuevo_id,
            'nombre' => $archivo['name'],
            'ruta' => $ruta_web
        ]);
    } else {
        // Si falla la BD, eliminar el archivo
        if (file_exists($ruta_completa)) {
            @unlink($ruta_completa);
        }
        responderJSON(false, 'Error al registrar el documento en la base de datos');
    }
    
} catch (Exception $e) {
    // En caso de excepción, limpiar archivo si existe
    if (isset($ruta_completa) && file_exists($ruta_completa)) {
        @unlink($ruta_completa);
    }
    responderJSON(false, 'Error interno del servidor');
}
