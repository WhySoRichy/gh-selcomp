<?php
/**
 * API para obtener el historial de accesos del usuario actual
 * Devuelve en formato JSON para ser procesado por JavaScript
 */

session_start();
include 'auth.php';
require_once __DIR__ . "/../conexion/conexion.php";

header('Content-Type: application/json');

try {
    // Verificar si existe la variable de sesión
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception("No hay usuario autenticado");
    }
    
    $usuario_id = $_SESSION['usuario_id'];
    
    // Verificar si la tabla existe
    $tableCheck = $conexion->query("SHOW TABLES LIKE 'historial_accesos'");
    if ($tableCheck->rowCount() === 0) {
        throw new Exception("La tabla historial_accesos no existe en la base de datos. Contacta al administrador para instalar el módulo.");
    }
    
    // Ya no verificamos el procedimiento almacenado porque ahora hacemos insert directo
    // Y en caso de que exista, igualmente funcionará
    
    // Parámetros de paginación
    $pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 10;
    $offset = ($pagina - 1) * $limite;
    
    // Consultar el historial de accesos - adaptado a la estructura real de la tabla
    $stmt = $conexion->prepare("
        SELECT 
            id,
            fecha_acceso AS fecha_hora,
            ip_acceso,
            dispositivo,
            navegador,
            exito AS exitoso,
            detalles
        FROM 
            historial_accesos
        WHERE 
            usuario_id = :usuario_id
        ORDER BY 
            fecha_acceso DESC
        LIMIT :limite OFFSET :offset
    ");
    
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar el total de registros para la paginación
    $stmt = $conexion->prepare("
        SELECT COUNT(*) as total
        FROM historial_accesos
        WHERE usuario_id = :usuario_id
    ");
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Formatear fechas y preparar datos para el frontend
    foreach ($historial as &$acceso) {
        // Convertir la fecha a formato legible
        $fecha = new DateTime($acceso['fecha_hora']); // fecha_hora es un alias de fecha_acceso
        $fecha->setTimezone(new DateTimeZone('America/Bogota')); // Ajustar a zona horaria local
        
        // Formato más legible: día/mes/año hora:minutos
        $acceso['fecha_formateada'] = $fecha->format('d/m/Y H:i:s');
        
        // Agregar información de hace cuánto tiempo fue el acceso
        $ahora = new DateTime();
        $intervalo = $ahora->diff($fecha);
        
        if ($intervalo->days > 0) {
            $acceso['tiempo_transcurrido'] = $intervalo->days . ' día(s)';
        } elseif ($intervalo->h > 0) {
            $acceso['tiempo_transcurrido'] = $intervalo->h . ' hora(s)';
        } elseif ($intervalo->i > 0) {
            $acceso['tiempo_transcurrido'] = $intervalo->i . ' minuto(s)';
        } else {
            $acceso['tiempo_transcurrido'] = 'Hace unos segundos';
        }
        
        // Formatear el estado (la columna en la BD se llama exito pero en el código se usa exitoso)
        // Por eso necesitamos verificar cuál existe
        if (isset($acceso['exitoso'])) {
            $exito = $acceso['exitoso'];
        } elseif (isset($acceso['exito'])) {
            $exito = $acceso['exito'];
        } else {
            $exito = true; // valor por defecto
        }
        
        $acceso['estado'] = $exito ? 'success' : 'error';
        $acceso['estado_texto'] = $exito ? 'Exitoso' : 'Fallido';
    }
    
    // Preparar la respuesta
    $respuesta = [
        'exito' => true,
        'historial' => $historial,
        'paginacion' => [
            'total' => $total,
            'pagina_actual' => $pagina,
            'por_pagina' => $limite,
            'total_paginas' => ceil($total / $limite)
        ]
    ];
    
    echo json_encode($respuesta);
    
} catch (PDOException $e) {
    error_log("Error PDO en api_historial_accesos: " . $e->getMessage());
    $respuesta = [
        'exito' => false,
        'mensaje' => 'Error al obtener el historial de accesos. Contacte al administrador.',
        'error_tipo' => 'PDOException',
        'necesita_instalacion' => true
    ];
    
    echo json_encode($respuesta);
} catch (Exception $e) {
    error_log("Error en api_historial_accesos: " . $e->getMessage());
    $respuesta = [
        'exito' => false,
        'mensaje' => 'Error al procesar la solicitud.',
        'error_tipo' => 'Exception',
        'necesita_instalacion' => false
    ];
    
    echo json_encode($respuesta);
}
?>
