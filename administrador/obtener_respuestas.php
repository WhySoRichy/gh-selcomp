<?php
/**
 * Backend para gestión de respuestas a notificaciones
 * Endpoint AJAX para obtener respuestas con filtros
 */

session_start();
require_once '../conexion/conexion.php';
require_once 'auth.php';
require_once 'csrf_protection.php';

header('Content-Type: application/json');

// Verificar que es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Verificar token CSRF
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validar_token_csrf($csrf_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

try {
    // Verificar si es solicitud para contar respuestas no leídas
    $action = $_POST['action'] ?? '';
    
    if ($action === 'contar_no_leidas') {
        // Contar respuestas de las últimas 24 horas (consideradas "nuevas")
        $sql_count = "SELECT COUNT(*) as count 
                      FROM notificaciones_respuestas r
                      WHERE r.fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt_count = $conexion->prepare($sql_count);
        $stmt_count->execute();
        $result = $stmt_count->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'count' => (int)$result['count']
            ]
        ]);
        exit;
    }
    
    // Obtener parámetros de filtro
    $busqueda = $_POST['busqueda'] ?? '';
    $ordenar = $_POST['ordenar'] ?? 'fecha_desc';
    $periodo = $_POST['periodo'] ?? 'todos';
    $prioridad = $_POST['prioridad'] ?? 'todas';
    $fecha_desde = $_POST['fecha_desde'] ?? '';
    $fecha_hasta = $_POST['fecha_hasta'] ?? '';
    $pagina = max(1, intval($_POST['pagina'] ?? 1));
    $por_pagina = max(10, min(100, intval($_POST['por_pagina'] ?? 25)));
    
    // Calcular offset
    $offset = ($pagina - 1) * $por_pagina;
    
    // Construir consulta base
    $sql = "SELECT r.*, n.nombre as notificacion_nombre, n.prioridad, u.nombre as usuario_nombre, u.email as usuario_email, n.fecha_creacion as notificacion_fecha
            FROM notificaciones_respuestas r
            INNER JOIN notificaciones n ON r.notificacion_id = n.id
            INNER JOIN usuarios u ON r.usuario_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    // Aplicar filtro de búsqueda
    if (!empty($busqueda)) {
        $sql .= " AND (u.nombre LIKE ? OR u.email LIKE ? OR n.nombre LIKE ? OR r.respuesta LIKE ?)";
        $busqueda_param = "%{$busqueda}%";
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
    }
    
    // Aplicar filtro de prioridad
    if ($prioridad !== 'todas') {
        $sql .= " AND n.prioridad = ?";
        $params[] = $prioridad;
    }
    
    // Aplicar filtro de período
    switch ($periodo) {
        case 'hoy':
            $sql .= " AND DATE(r.fecha_respuesta) = CURDATE()";
            break;
        case 'semana':
            $sql .= " AND r.fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'mes':
            $sql .= " AND r.fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'personalizado':
            if (!empty($fecha_desde)) {
                $sql .= " AND DATE(r.fecha_respuesta) >= ?";
                $params[] = $fecha_desde;
            }
            if (!empty($fecha_hasta)) {
                $sql .= " AND DATE(r.fecha_respuesta) <= ?";
                $params[] = $fecha_hasta;
            }
            break;
        default:
            // 'todos' - no agregar filtro de fecha
            break;
    }
    
    // Aplicar ordenamiento
    switch ($ordenar) {
        case 'fecha_desc':
            $sql .= " ORDER BY r.fecha_respuesta DESC";
            break;
        case 'fecha_asc':
            $sql .= " ORDER BY r.fecha_respuesta ASC";
            break;
        case 'notificacion':
            $sql .= " ORDER BY n.nombre ASC";
            break;
        case 'usuario':
            $sql .= " ORDER BY u.nombre ASC";
            break;
        case 'prioridad':
            $sql .= " ORDER BY FIELD(n.prioridad, 'alta', 'media', 'baja')";
            break;
        default:
            $sql .= " ORDER BY r.fecha_respuesta DESC";
    }
    
    // Consulta para contar total de resultados (crear desde cero para evitar problemas con str_replace)
    $sql_count = "SELECT COUNT(*) as total
                  FROM notificaciones_respuestas r
                  INNER JOIN notificaciones n ON r.notificacion_id = n.id
                  INNER JOIN usuarios u ON r.usuario_id = u.id
                  WHERE 1=1";
    
    // Aplicar los mismos filtros que en la consulta principal
    if (!empty($busqueda)) {
        $sql_count .= " AND (u.nombre LIKE ? OR u.email LIKE ? OR n.nombre LIKE ? OR r.respuesta LIKE ?)";
    }
    
    if ($prioridad !== 'todas') {
        $sql_count .= " AND n.prioridad = ?";
    }
    
    // Aplicar filtro de período
    switch ($periodo) {
        case 'hoy':
            $sql_count .= " AND DATE(r.fecha_respuesta) = CURDATE()";
            break;
        case 'semana':
            $sql_count .= " AND r.fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'mes':
            $sql_count .= " AND r.fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'personalizado':
            if (!empty($fecha_desde)) {
                $sql_count .= " AND DATE(r.fecha_respuesta) >= ?";
            }
            if (!empty($fecha_hasta)) {
                $sql_count .= " AND DATE(r.fecha_respuesta) <= ?";
            }
            break;
        default:
            // 'todos' - no agregar filtro de fecha
            break;
    }
    
    $stmt_count = $conexion->prepare($sql_count);
    $stmt_count->execute($params);
    $total_resultados = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Añadir límite y offset (no se pueden usar placeholders para LIMIT/OFFSET)
    $sql .= " LIMIT " . intval($por_pagina) . " OFFSET " . intval($offset);
    
    // Ejecutar consulta principal (sin agregar limit/offset a params)
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estadísticas adicionales
    $stats_sql = "SELECT 
                    COUNT(*) as total_respuestas,
                    COUNT(DISTINCT r.usuario_id) as usuarios_activos,
                    COUNT(CASE WHEN DATE(r.fecha_respuesta) = CURDATE() THEN 1 END) as respuestas_hoy,
                    AVG(TIMESTAMPDIFF(HOUR, n.fecha_creacion, r.fecha_respuesta)) as tiempo_promedio_horas
                  FROM notificaciones_respuestas r
                  INNER JOIN notificaciones n ON r.notificacion_id = n.id";
    
    $stmt_stats = $conexion->prepare($stats_sql);
    $stmt_stats->execute();
    $estadisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    // Formatear respuestas para el frontend
    $respuestas_formateadas = array_map(function($respuesta) {
        return [
            'id' => $respuesta['id'],
            'contenido' => htmlspecialchars($respuesta['respuesta']),
            'fecha_respuesta' => $respuesta['fecha_respuesta'],
            'usuario' => [
                'id' => $respuesta['usuario_id'],
                'nombre' => htmlspecialchars($respuesta['usuario_nombre']),
                'email' => htmlspecialchars($respuesta['usuario_email']),
                'iniciales' => strtoupper(substr($respuesta['usuario_nombre'], 0, 2))
            ],
            'notificacion' => [
                'id' => $respuesta['notificacion_id'],
                'nombre' => htmlspecialchars($respuesta['notificacion_nombre']),
                'prioridad' => $respuesta['prioridad'],
                'fecha_creacion' => $respuesta['notificacion_fecha']
            ]
        ];
    }, $respuestas);
    
    // Calcular información de paginación
    $total_paginas = ceil($total_resultados / $por_pagina);
    
    echo json_encode([
        'success' => true,
        'respuestas' => $respuestas_formateadas,
        'paginacion' => [
            'pagina_actual' => $pagina,
            'total_paginas' => $total_paginas,
            'total_resultados' => $total_resultados,
            'por_pagina' => $por_pagina,
            'desde' => $offset + 1,
            'hasta' => min($offset + $por_pagina, $total_resultados)
        ],
        'estadisticas' => [
            'total_respuestas' => intval($estadisticas['total_respuestas']),
            'usuarios_activos' => intval($estadisticas['usuarios_activos']),
            'respuestas_hoy' => intval($estadisticas['respuestas_hoy']),
            'tiempo_promedio' => round($estadisticas['tiempo_promedio_horas'] ?? 0, 1)
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en obtener_respuestas.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => 'No se pudieron cargar las respuestas'
    ]);
}
?>
