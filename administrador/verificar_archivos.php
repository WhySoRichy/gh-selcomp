<?php
session_start();
require_once "../conexion/conexion.php";

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

header('Content-Type: application/json');

try {
    // Obtener todos los archivos de notificaciones
    $stmt = $conexion->query("
        SELECT 
            na.id,
            na.notificacion_id,
            na.nombre_original,
            na.nombre_archivo,
            na.ruta_archivo,
            na.tamano,
            na.tipo_mime,
            na.fecha_subida,
            n.nombre as notificacion_nombre
        FROM notificaciones_archivos na
        LEFT JOIN notificaciones n ON na.notificacion_id = n.id
        ORDER BY na.id DESC
    ");
    
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar existencia física de cada archivo
    foreach ($archivos as &$archivo) {
        $rutaCompleta = "../" . $archivo['ruta_archivo'];
        $archivo['existe_fisicamente'] = file_exists($rutaCompleta);
        $archivo['ruta_completa'] = $rutaCompleta;
        
        // Información adicional del archivo si existe
        if ($archivo['existe_fisicamente']) {
            $archivo['tamano_real'] = filesize($rutaCompleta);
            $archivo['fecha_modificacion'] = date('Y-m-d H:i:s', filemtime($rutaCompleta));
        } else {
            $archivo['tamano_real'] = 0;
            $archivo['fecha_modificacion'] = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_archivos' => count($archivos),
        'archivos' => $archivos,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error al consultar archivos: ' . $e->getMessage()
    ]);
}
?>
