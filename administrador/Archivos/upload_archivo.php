<?php
/**
 * Subir archivo a recursos o archivo
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once "../../conexion/conexion.php";
require_once "../csrf_protection.php";

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'No autenticado']));
}

// Verificar que sea administrador
if (!isset($_SESSION['usuario_rol']) ||
    ($_SESSION['usuario_rol'] !== 'admin' && $_SESSION['usuario_rol'] !== 'administrador')) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Acceso denegado']));
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Método no permitido']));
}

// Validar token CSRF
if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Token de seguridad inválido']));
}

try {
    // Verificar que se recibió un archivo
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $errorCodes = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el límite permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el límite permitido por el formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor',
            UPLOAD_ERR_CANT_WRITE => 'Error de escritura en el servidor',
            UPLOAD_ERR_EXTENSION => 'Extensión de PHP detuvo la subida'
        ];
        $errorCode = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
        $errorMsg = $errorCodes[$errorCode] ?? 'Error desconocido al subir archivo';
        throw new Exception($errorMsg);
    }

    // Verificar tipo de sección
    if (!isset($_POST['tipo_seccion']) || !in_array($_POST['tipo_seccion'], ['archivo', 'recursos'])) {
        throw new Exception('Tipo de sección no válido');
    }

    $tipoSeccion = $_POST['tipo_seccion'];
    $archivo = $_FILES['archivo'];

    // Validar tipo de archivo
    $tiposPermitidos = [
        'application/pdf',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $tiposPermitidos)) {
        throw new Exception('Tipo de archivo no permitido. Solo se aceptan PDF, Excel y Word');
    }

    // Validar tamaño (máximo 10MB)
    if ($archivo['size'] > 10 * 1024 * 1024) {
        throw new Exception('El archivo no puede superar los 10MB');
    }

    // Generar nombre único para el archivo
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombreOriginal = pathinfo($archivo['name'], PATHINFO_FILENAME);
    $nombreUnico = $nombreOriginal . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;

    // Determinar tipo de documento basado en la extensión
    switch (strtolower($extension)) {
        case 'pdf':
            $tipoDocumento = 'pdf';
            break;
        case 'xlsx':
        case 'xls':
            $tipoDocumento = 'excel';
            break;
        case 'docx':
        case 'doc':
            $tipoDocumento = 'word';
            break;
        default:
            $tipoDocumento = 'otro';
    }

    // Usar rutas relativas basadas en la ubicación del script actual
    // __DIR__ = ruta absoluta de la carpeta donde está este script (administrador/Archivos)
    $baseDir = realpath(__DIR__ . '/../../'); // Ir a la raíz del proyecto (gh)
    
    // Determinar carpeta de destino según el tipo de sección
    if ($tipoSeccion === 'archivo') {
        $carpetaDestino = $baseDir . '/Documentos/Procesados/';
        $rutaRelativa = '/gh/Documentos/Procesados/';
        $mensajeExito = 'Archivo subido exitosamente';
    } else {
        $carpetaDestino = $baseDir . '/Documentos/Recursos/';
        $rutaRelativa = '/gh/Documentos/Recursos/';
        $mensajeExito = 'Recurso subido exitosamente';
    }

    // Crear carpeta si no existe
    if (!is_dir($carpetaDestino)) {
        if (!mkdir($carpetaDestino, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de destino. Verifique los permisos.');
        }
    }

    // Verificar permisos de escritura
    if (!is_writable($carpetaDestino)) {
        throw new Exception('No hay permisos de escritura en el directorio de destino');
    }

    // Mover archivo a destino final
    $rutaCompleta = $carpetaDestino . $nombreUnico;
    if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        // Obtener más información del error
        $lastError = error_get_last();
        $errorDetail = $lastError ? $lastError['message'] : 'Sin detalles adicionales';
        error_log("Error al mover archivo - Destino: $rutaCompleta - Detalle: $errorDetail");
        throw new Exception('Error al mover el archivo. Verifique los permisos del servidor.');
    }

    // Guardar información en la base de datos
    $rutaArchivo = $rutaRelativa . $nombreUnico;
    $stmt = $conexion->prepare("INSERT INTO documentos (nombre_archivo, nombre_original, ruta_archivo, tipo_seccion, tipo_documento, tamano_archivo, subido_por) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nombreUnico, $archivo['name'], $rutaArchivo, $tipoSeccion, $tipoDocumento, $archivo['size'], 'admin']);

    echo json_encode([
        'success' => true,
        'message' => $mensajeExito
    ]);

} catch (Exception $e) {
    error_log("Error en upload_archivo.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
