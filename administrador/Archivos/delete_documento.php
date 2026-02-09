<?php
/**
 * Eliminar documento de usuario registrado (tabla documentos_usuarios)
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

if (!$data || !isset($data['id']) || empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID del documento no proporcionado']);
    exit;
}

$documento_id = intval($data['id']);

try {
    // Buscar el documento
    $stmt = $conexion->prepare("
        SELECT du.*, u.nombre, u.apellido
        FROM documentos_usuarios du
        LEFT JOIN usuarios u ON du.usuario_id = u.id
        WHERE du.id = :id
    ");
    $stmt->bindParam(':id', $documento_id, PDO::PARAM_INT);
    $stmt->execute();
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$documento) {
        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
        exit;
    }

    // Construir la ruta física del archivo
    $ruta_archivo = $documento['ruta_archivo'];
    if (strpos($ruta_archivo, '/gh/') === 0) {
        $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_archivo;
    } else {
        $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . '/gh/' . ltrim($ruta_archivo, '/');
    }

    // Eliminar el archivo físico si existe
    if (file_exists($ruta_fisica) && is_writable($ruta_fisica)) {
        @unlink($ruta_fisica);
    }

    // Eliminar el registro de la base de datos
    $stmt = $conexion->prepare("DELETE FROM documentos_usuarios WHERE id = :id");
    $stmt->bindParam(':id', $documento_id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Documento eliminado exitosamente'
    ]);

} catch (PDOException $e) {
    error_log('Error BD delete_documento: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el documento']);
} catch (Exception $e) {
    error_log('Error delete_documento: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
}
