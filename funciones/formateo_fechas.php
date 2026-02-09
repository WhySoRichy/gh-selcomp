<?php
/**
 * Funciones de utilidad para formateo de fechas en español
 */

/**
 * Formatea una fecha específicamente para el historial de accesos
 * @param string|DateTime $fecha La fecha a formatear
 * @param string $formato El formato deseado (simple, completo, o personalizado)
 * @return string La fecha formateada
 */
function formatear_fecha_historial($fecha, $formato = 'completo') {
    // Verificar si la fecha está vacía o es inválida
    if (empty($fecha) || $fecha == '0000-00-00 00:00:00' || $fecha == '0000-00-00') {
        return '<span class="fecha-no-disponible">Fecha no disponible</span>';
    }
    
    try {
        // Manejar diferentes formatos de entrada
        if (is_string($fecha)) {
            // Verificar si la fecha está en formato -0001 o es inválida
            if (substr($fecha, 0, 5) == '-0001' || substr($fecha, 0, 4) == '0000') {
                return '<span class="fecha-no-disponible">Fecha no disponible</span>';
            }
            
            // Intentar parsear la fecha
            $fecha_obj = new DateTime($fecha);
        } elseif ($fecha instanceof DateTime) {
            $fecha_obj = $fecha;
        } else {
            return '<span class="fecha-no-disponible">Formato inválido</span>';
        }
        
        // Verificar año razonable (entre 2020 y 2030 para el historial)
        $year = (int)$fecha_obj->format('Y');
        if ($year < 2020 || $year > 2030) {
            return '<span class="fecha-no-disponible">Fecha fuera de rango</span>';
        }
        
        // Formatear la fecha
        return formatear_fecha($fecha, $formato);
        
    } catch (Exception $e) {
        error_log("Error al formatear fecha del historial: " . $e->getMessage() . " - Fecha: $fecha");
        return '<span class="fecha-no-disponible">Fecha inválida</span>';
    }
}

/**
 * Formatea una fecha en español
 * @param string|DateTime $fecha La fecha a formatear
 * @param string $formato El formato deseado (simple, completo, o personalizado)
 * @return string La fecha formateada
 */
function formatear_fecha($fecha, $formato = 'completo') {
    // Verificar si la fecha está vacía o es 0000-00-00
    if (empty($fecha) || $fecha == '0000-00-00 00:00:00' || $fecha == '0000-00-00') {
        return 'N/A';
    }
    
    try {
        // Manejar diferentes formatos de entrada
        if (is_string($fecha)) {
            // Verificar si la fecha está en formato -0001
            if (substr($fecha, 0, 5) == '-0001' || substr($fecha, 0, 4) == '0000') {
                return 'N/A';
            }
            
            // Convertir valor de timestamp si es numérico
            if (is_numeric($fecha)) {
                $fecha = date('Y-m-d H:i:s', (int)$fecha);
            }
            
            // Intentar parsear la fecha con strtotime
            $timestamp = strtotime($fecha);
            if ($timestamp === false) {
                return 'Fecha inválida';
            }
            
            // Crear objeto DateTime
            $fecha_obj = new DateTime();
            $fecha_obj->setTimestamp($timestamp);
        } elseif ($fecha instanceof DateTime) {
            $fecha_obj = $fecha;
        } elseif (is_numeric($fecha)) {
            // Si es un timestamp numérico
            $fecha_obj = new DateTime();
            $fecha_obj->setTimestamp((int)$fecha);
        } else {
            return 'Formato inválido';
        }
        
        // Verificar año razonable (entre 1970 y 2100)
        $year = (int)$fecha_obj->format('Y');
        if ($year < 1970 || $year > 2100) {
            return 'Fecha fuera de rango';
        }
        
        $meses = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];
        
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        
        switch ($formato) {
            case 'simple':
                return $fecha_obj->format('d/m/Y');
            case 'hora':
                return $fecha_obj->format('H:i:s');
            case 'completo':
                $dia_semana = $dias[$fecha_obj->format('w')];
                $dia = $fecha_obj->format('j');
                $mes = $meses[$fecha_obj->format('n') - 1];
                $anio = $fecha_obj->format('Y');
                $hora = $fecha_obj->format('H:i:s');
                
                return "$dia_semana $dia de $mes de $anio, $hora";
        case 'corto':
            $dia = $fecha_obj->format('j');
            $mes = $meses[$fecha_obj->format('n') - 1];
            $anio = $fecha_obj->format('Y');
            
            return "$dia de $mes de $anio";
        case 'dia_hora':
            $dia = $fecha_obj->format('j');
            $mes = $meses[$fecha_obj->format('n') - 1];
            $hora = $fecha_obj->format('H:i');
            
            return "$dia de $mes, $hora";
        default:
            return $fecha_obj->format($formato);
    }
    } catch (Exception $e) {
        error_log("Error al formatear fecha: " . $e->getMessage() . " - Fecha: $fecha");
        return 'Fecha inválida';
    }
}

/**
 * Tiempo transcurrido desde una fecha dada hasta ahora, en formato legible
 * @param string|DateTime $fecha La fecha desde la cual calcular
 * @return string El tiempo transcurrido en formato legible
 */
function tiempo_transcurrido($fecha) {
    // Verificar si la fecha está vacía o es 0000-00-00
    if (empty($fecha) || $fecha == '0000-00-00 00:00:00' || $fecha == '0000-00-00') {
        return 'N/A';
    }
    
    try {
        // Manejar diferentes formatos de entrada
        if (is_string($fecha)) {
            // Verificar si la fecha está en formato -0001
            if (substr($fecha, 0, 5) == '-0001' || substr($fecha, 0, 4) == '0000') {
                return 'N/A';
            }
            
            // Convertir valor de timestamp si es numérico
            if (is_numeric($fecha)) {
                $fecha = date('Y-m-d H:i:s', (int)$fecha);
            }
            
            // Intentar parsear la fecha con strtotime
            $timestamp = strtotime($fecha);
            if ($timestamp === false) {
                return 'Fecha inválida';
            }
            
            // Crear objeto DateTime
            $fecha_obj = new DateTime();
            $fecha_obj->setTimestamp($timestamp);
        } elseif ($fecha instanceof DateTime) {
            $fecha_obj = $fecha;
        } elseif (is_numeric($fecha)) {
            // Si es un timestamp numérico
            $fecha_obj = new DateTime();
            $fecha_obj->setTimestamp((int)$fecha);
        } else {
            return 'Formato inválido';
        }
        
        // Verificar año razonable (entre 1970 y 2100)
        $year = (int)$fecha_obj->format('Y');
        if ($year < 1970 || $year > 2100) {
            return 'Fecha fuera de rango';
        }
        
        $ahora = new DateTime();
        $diff = $ahora->diff($fecha_obj);
        
        if ($diff->y > 0) {
            return $diff->y == 1 ? "hace 1 año" : "hace {$diff->y} años";
        }
        
        if ($diff->m > 0) {
            return $diff->m == 1 ? "hace 1 mes" : "hace {$diff->m} meses";
        }
        
        if ($diff->d > 0) {
            return $diff->d == 1 ? "hace 1 día" : "hace {$diff->d} días";
        }
        
        if ($diff->h > 0) {
            return $diff->h == 1 ? "hace 1 hora" : "hace {$diff->h} horas";
        }
        
        if ($diff->i > 0) {
            return $diff->i == 1 ? "hace 1 minuto" : "hace {$diff->i} minutos";
        }
        
        return "hace unos segundos";
    } catch (Exception $e) {
        error_log("Error al calcular tiempo transcurrido: " . $e->getMessage() . " - Fecha: $fecha");
        return 'Fecha inválida';
    }
}
?>
