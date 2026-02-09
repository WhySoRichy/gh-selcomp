<?php
/**
 * Archivo para actualizar el nombre de un archivo
 * Maneja documentos de usuarios registrados, recursos y archivos del sistema
 */

// Establecer header JSON primero
header('Content-Type: application/json; charset=utf-8');

session_start();

// Verificar autenticación manualmente para APIs JSON
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

require_once "../../conexion/conexion.php";
require_once "../csrf_protection.php";

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener y decodificar los datos JSON de la petición
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validar token CSRF
if (!isset($data['csrf_token']) || !validar_token_csrf($data['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

// Verificar que los datos son válidos
if (!isset($data['id']) || !isset($data['nuevo_nombre']) || !isset($data['tipo'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$id = $data['id'];
$nuevo_nombre = trim($data['nuevo_nombre']);
$tipo = $data['tipo'];

// Validar que el nombre no esté vacío
if (empty($nuevo_nombre)) {
    echo json_encode(['success' => false, 'message' => 'El nombre no puede estar vacío']);
    exit;
}

try {
    // Determinar la tabla y consulta según el tipo
    switch ($tipo) {
        case 'documentos_usuarios':
            $stmt = $conexion->prepare("
                UPDATE documentos_usuarios
                SET nombre_original = :nuevo_nombre
                WHERE id = :id
            ");
            $stmt->bindParam(':nuevo_nombre', $nuevo_nombre);
            $stmt->bindParam(':id', $id);
            break;
        case 'recursos':
        case 'archivo':
            $stmt = $conexion->prepare("
                UPDATE documentos
                SET nombre_original = :nuevo_nombre
                WHERE id = :id AND tipo_seccion = :tipo_seccion
            ");
            $stmt->bindParam(':nuevo_nombre', $nuevo_nombre);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':tipo_seccion', $tipo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Tipo de documento no válido']);
            exit;
    }

    // Ejecutar la consulta
    $stmt->execute();

    // Verificar si se actualizó algún registro
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Nombre actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró el archivo o no se pudo actualizar']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
