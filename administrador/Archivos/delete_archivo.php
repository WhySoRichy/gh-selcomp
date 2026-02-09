<?php
/**
 * Eliminar archivo de recursos o postulaciones
 */

// Establecer header JSON primero
header('Content-Type: application/json; charset=utf-8');

session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Verificar que sea administrador
if (!isset($_SESSION['usuario_rol']) ||
    ($_SESSION['usuario_rol'] !== 'admin' && $_SESSION['usuario_rol'] !== 'administrador')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once "../../conexion/conexion.php";
require_once "../csrf_protection.php";

// Leer datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validar token CSRF
if (!isset($data['csrf_token']) || !validar_token_csrf($data['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

if (!$data || !isset($data['nombre_archivo']) || !isset($data['tipo_seccion'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros requeridos faltantes']);
    exit;
}

$nombreArchivo = $data['nombre_archivo'];
$tipoSeccion = $data['tipo_seccion'];

// Validar tipo de sección
if (!in_array($tipoSeccion, ['archivo', 'recursos'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de sección no válido']);
    exit;
}

try {
    $mensajeExito = 'Archivo eliminado exitosamente';
    $rutaArchivo = '';

    if ($tipoSeccion === 'archivo') {
        // Buscar en postulaciones
        $stmt = $conexion->prepare("SELECT archivo FROM postulaciones WHERE archivo LIKE ?");
        $stmt->execute(['%' . $nombreArchivo]);
        $postulacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($postulacion) {
            $rutaArchivo = $_SERVER['DOCUMENT_ROOT'] . '/gh/' . $postulacion['archivo'];
            $stmtDelete = $conexion->prepare("DELETE FROM postulaciones WHERE archivo LIKE ?");
            $stmtDelete->execute(['%' . $nombreArchivo]);
            $mensajeExito = 'Postulación eliminada exitosamente';
        } else {
            $rutaArchivo = $_SERVER['DOCUMENT_ROOT'] . '/gh/Documentos/Procesados/' . $nombreArchivo;
        }
    } elseif ($tipoSeccion === 'recursos') {
        $rutaArchivo = $_SERVER['DOCUMENT_ROOT'] . '/gh/Documentos/Recursos/' . $nombreArchivo;
    }

    // Eliminar archivo físico si existe
    if (file_exists($rutaArchivo) && is_writable($rutaArchivo)) {
        @unlink($rutaArchivo);
    }

    // Eliminar registro de la base de datos
    $stmt = $conexion->prepare("DELETE FROM documentos WHERE nombre_archivo = ? AND tipo_seccion = ?");
    $stmt->execute([$nombreArchivo, $tipoSeccion]);

    echo json_encode([
        'success' => true,
        'message' => $mensajeExito
    ]);

} catch (PDOException $e) {
    error_log('Error BD delete_archivo: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el archivo']);
} catch (Exception $e) {
    error_log('Error delete_archivo: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
}
