<?php
/**
 * PROCESADOR DE NOTIFICACIONES - ADMIN
 * Maneja las operaciones CRUD para notificaciones
 */

// Iniciar buffer de salida para evitar warnings antes del JSON
ob_start();

session_start();
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../administrador/csrf_protection.php';

// Limpiar cualquier output accidental de los requires
ob_clean();

// Establecer cabeceras JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

/**
 * Función auxiliar para enviar respuestas JSON
 */
function enviarRespuestaJSON($data, $httpCode = 200) {
    // Limpiar cualquier output accidental
    if (ob_get_length()) ob_clean();
    
    if ($httpCode !== 200) {
        http_response_code($httpCode);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Función auxiliar para errores
 */
function enviarError($mensaje, $codigo = 400) {
    enviarRespuestaJSON(['success' => false, 'error' => $mensaje], $codigo);
}

/**
 * Función auxiliar para éxito
 */
function enviarExito($data = [], $mensaje = null) {
    $respuesta = ['success' => true];
    if ($mensaje) {
        $respuesta['message'] = $mensaje;
    }
    if (!empty($data)) {
        $respuesta = array_merge($respuesta, $data);
    }
    enviarRespuestaJSON($respuesta);
}

/**
 * FIX #7: Función auxiliar para resolver rutas de archivos
 * Soporta formato legacy (../Documentos/...) y nuevo (Documentos/...)
 */
function resolverRutaArchivo($ruta_archivo) {
    if (strpos($ruta_archivo, '../') === 0) {
        // Formato legacy: ../Documentos/...
        return __DIR__ . '/' . $ruta_archivo;
    }
    // Formato nuevo: Documentos/...
    return __DIR__ . '/../' . $ruta_archivo;
}

// Función de verificación de autenticación para AJAX
function verificar_auth_ajax() {
    // Verificar que el usuario esté autenticado
    if (!isset($_SESSION['usuario_id'])) {
        enviarError('Usuario no autenticado', 401);
    }
}

// Función de verificación específica para administradores
function verificar_admin() {
    verificar_auth_ajax();
    
    // Verificar que sea administrador (case-insensitive)
    if (!isset($_SESSION['usuario_rol'])) {
        enviarError('Rol de usuario no definido', 403);
    }
    
    $rol_lower = strtolower(trim($_SESSION['usuario_rol']));
    $roles_permitidos = ['admin', 'administrador'];
    
    if (!in_array($rol_lower, $roles_permitidos)) {
        enviarError('Solo administradores pueden realizar esta acción', 403);
    }
    
    // Para operaciones POST, verificar CSRF token
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validar_token_csrf($token)) {
            enviarError('Token CSRF inválido o expirado', 403);
        }
    }
}

// Obtener acción
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

try {
    switch ($accion) {
        case 'listar':
            verificar_auth_ajax();
            listarNotificaciones($conexion);
            break;
            
        case 'obtener_estadisticas':
            verificar_auth_ajax();
            obtenerEstadisticas($conexion);
            break;
            
        case 'obtener_token_csrf':
            verificar_auth_ajax();
            obtenerTokenCSRF();
            break;
            
        case 'obtener_usuarios':
            verificar_auth_ajax();
            obtenerUsuarios($conexion);
            break;
            
        case 'obtener':
            verificar_auth_ajax();
            obtenerNotificacion($conexion);
            break;
            
        case 'crear':
            verificar_admin();
            crearNotificacion($conexion);
            break;
            
        case 'actualizar':
            verificar_admin();
            actualizarNotificacion($conexion);
            break;
            
        case 'eliminar':
            verificar_admin();
            eliminarNotificacion($conexion);
            break;
            
        case 'cambiar_estado':
            verificar_admin();
            cambiarEstadoNotificacion($conexion);
            break;
            
        case 'duplicar':
            verificar_admin();
            duplicarNotificacion($conexion);
            break;
            
        case 'responder_notificacion':
            verificar_auth_ajax();
            responderNotificacion($conexion);
            break;
            
        case 'obtener_respuestas':
            verificar_auth_ajax();
            obtenerRespuestas($conexion);
            break;
            
        case 'eliminar_respuesta':
            verificar_admin();
            eliminarRespuesta($conexion);
            break;
            
        case 'descargar_respuesta':
            verificar_auth_ajax();
            descargarRespuesta($conexion);
            break;
            
        case 'descargar_archivo':
            verificar_auth_ajax();
            descargarArchivo($conexion);
            break;
            
        default:
            throw new InvalidArgumentException('Acción no válida');
    }
} catch (Exception $e) {
    error_log("Error en procesar_notificacion.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar la solicitud. Contacte al administrador.'
    ]);
}

/**
 * Obtiene un token CSRF para peticiones AJAX
 * Incluye información de expiración para el cliente
 */
function obtenerTokenCSRF() {
    $token = generar_token_csrf();
    $timestamp = $_SESSION['csrf_token_timestamp'] ?? time();
    $expira_en = CSRF_TOKEN_LIFETIME - (time() - $timestamp);
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'expira_en_segundos' => max(0, $expira_en),
        'timestamp' => $timestamp
    ]);
}

/**
 * Lista todas las notificaciones con filtros opcionales
 */
function construirClausulasWHERE() {
    $where = [];
    $params = [];
    
    // Determinar si el usuario es admin
    $es_admin = false;
    if (isset($_SESSION['usuario_rol'])) {
        $rol_lower = strtolower(trim($_SESSION['usuario_rol']));
        $es_admin = in_array($rol_lower, ['admin', 'administrador']);
    }
    
    // Si NO es admin, filtrar notificaciones por destino
    if (!$es_admin && isset($_SESSION['usuario_id'])) {
        $usuario_id = $_SESSION['usuario_id'];
        
        // Usuarios normales solo ven:
        // - destino = 'todos' (para todos)
        // - destino = 'regulares' (solo usuarios regulares)
        // - destino = 'especificos' Y están en la tabla notificaciones_usuarios
        $where[] = "(
            n.destino = 'todos' 
            OR n.destino = 'regulares'
            OR (n.destino = 'especificos' AND EXISTS (
                SELECT 1 FROM notificaciones_usuarios nu 
                WHERE nu.notificacion_id = n.id AND nu.usuario_id = :usuario_destino
            ))
        )";
        $params['usuario_destino'] = $usuario_id;
        
        // Usuarios normales solo ven notificaciones activas
        $where[] = "n.estado = 'activa'";
    }
    
    $filtros = [
        'estado' => ['activa', 'archivada'],
        'prioridad' => ['baja', 'media', 'alta'],
        'destino' => ['todos', 'administradores', 'regulares', 'especificos']
    ];
    
    // Solo aplicar filtros de estado/destino si es admin
    foreach ($filtros as $campo => $valoresValidos) {
        if (!empty($_GET[$campo]) && in_array($_GET[$campo], $valoresValidos)) {
            // Si no es admin, ignorar filtros de estado (ya forzamos 'activa')
            if (!$es_admin && $campo === 'estado') {
                continue;
            }
            $where[] = "n.$campo = :$campo";
            $params[$campo] = $_GET[$campo];
        }
    }
    
    // Filtros de fecha
    if (!empty($_GET['fecha_desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha_desde'])) {
        $where[] = "DATE(n.fecha_creacion) >= :fecha_desde";
        $params['fecha_desde'] = $_GET['fecha_desde'];
    }
    
    if (!empty($_GET['fecha_hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha_hasta'])) {
        $where[] = "DATE(n.fecha_creacion) <= :fecha_hasta";
        $params['fecha_hasta'] = $_GET['fecha_hasta'];
    }
    
    // Búsqueda por texto (escapar wildcards para prevenir búsquedas maliciosas)
    if (!empty($_GET['busqueda'])) {
        $where[] = "(n.nombre LIKE :busqueda OR n.cuerpo LIKE :busqueda)";
        $busqueda_escapada = str_replace(['%', '_'], ['\\%', '\\_'], $_GET['busqueda']);
        $params['busqueda'] = '%' . $busqueda_escapada . '%';
    }
    
    return [$where, $params];
}

function listarNotificaciones($conexion) {
    try {
        // Obtener ID del usuario actual para excluir sus propias respuestas del badge
        $usuario_actual = $_SESSION['usuario_id'] ?? 0;
        
        // ✅ FIX P1: Optimizar query N+1 con LEFT JOIN (archivos + respuestas)
        // ✅ FIX: No contar respuestas propias del usuario en el badge
        $sql = "SELECT n.id, n.nombre, n.cuerpo, n.destino, n.prioridad, n.estado,
                       n.fecha_creacion, n.fecha_actualizacion, n.permitir_respuesta,
                       (SELECT COUNT(*) FROM notificaciones_archivos na WHERE na.notificacion_id = n.id) as archivos_count,
                       (SELECT COUNT(*) FROM notificaciones_respuestas nr WHERE nr.notificacion_id = n.id AND nr.usuario_id != :usuario_actual) as respuestas_count
                FROM notificaciones n";
        
        [$where, $params] = construirClausulasWHERE();
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY n.fecha_creacion DESC";
        
        $limite = isset($_GET['limite']) ? min((int)$_GET['limite'], 100) : 50;
        $sql .= " LIMIT :limite";
        $params['limite'] = $limite;
        $params['usuario_actual'] = $usuario_actual;
        
        $stmt = $conexion->prepare($sql);
        
        foreach ($params as $key => $value) {
            $type = ($key === 'limite' || $key === 'usuario_actual') ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $type);
        }
        
        $stmt->execute();
        $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        enviarExito([
            'data' => $notificaciones,
            'total' => count($notificaciones),
            'filtros_aplicados' => array_keys($where)
        ]);
        
    } catch (Exception $e) {
        error_log("Error en listarNotificaciones: " . $e->getMessage());
        enviarError('Error al cargar las notificaciones');
    }
}

/**
 * Obtiene estadísticas de notificaciones
 */
function obtenerEstadisticas($conexion) {
    try {
        // Determinar si el usuario es admin
        $es_admin = false;
        if (isset($_SESSION['usuario_rol'])) {
            $rol_lower = strtolower(trim($_SESSION['usuario_rol']));
            $es_admin = in_array($rol_lower, ['admin', 'administrador']);
        }
        
        if ($es_admin) {
            // Admin ve todas las estadísticas
            $stmt = $conexion->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'activa' THEN 1 ELSE 0 END) as activas,
                    SUM(CASE WHEN estado = 'archivada' THEN 1 ELSE 0 END) as archivadas
                FROM notificaciones
            ");
        } else {
            // Usuario normal solo ve las que le corresponden
            $usuario_id = $_SESSION['usuario_id'] ?? 0;
            $stmt = $conexion->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN n.estado = 'activa' THEN 1 ELSE 0 END) as activas,
                    0 as archivadas
                FROM notificaciones n
                WHERE n.estado = 'activa'
                AND (
                    n.destino = 'todos' 
                    OR n.destino = 'regulares'
                    OR (n.destino = 'especificos' AND EXISTS (
                        SELECT 1 FROM notificaciones_usuarios nu 
                        WHERE nu.notificacion_id = n.id AND nu.usuario_id = :usuario_id
                    ))
                )
            ");
            $stmt->execute(['usuario_id' => $usuario_id]);
        }
        
        $estadisticas = $es_admin ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Total de respuestas (query separada porque es otra tabla)
        $stmt = $conexion->query("SELECT COUNT(*) as total FROM notificaciones_respuestas");
        $estadisticas['total_respuestas'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Convertir a enteros
        $estadisticas['total'] = (int)($estadisticas['total'] ?? 0);
        $estadisticas['activas'] = (int)($estadisticas['activas'] ?? 0);
        $estadisticas['archivadas'] = (int)($estadisticas['archivadas'] ?? 0);
        
        echo json_encode([
            'success' => true,
            'data' => $estadisticas
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error al obtener estadísticas: ' . $e->getMessage());
    }
}

/**
 * Obtiene lista de usuarios para notificaciones específicas
 */
function obtenerUsuarios($conexion) {
    try {
        $sql = "SELECT id, nombre, apellido, email, cargo, area, rol 
                FROM usuarios 
                WHERE rol IN ('admin', 'administrador', 'usuario')
                ORDER BY nombre ASC, apellido ASC";        $stmt = $conexion->prepare($sql);
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        enviarExito(['usuarios' => $usuarios]);
        
    } catch (Exception $e) {
        error_log("Error en obtenerUsuarios: " . $e->getMessage());
        enviarError('Error al cargar usuarios');
    }
}

/**
 * Obtiene una notificación específica
 */
function obtenerNotificacion($conexion) {
    try {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception('ID de notificación no válido');
        }
        
        $sql = "SELECT n.*, u.nombre as autor_nombre
                FROM notificaciones n
                LEFT JOIN usuarios u ON n.autor_id = u.id
                WHERE n.id = :id";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute(['id' => $id]);
        $notificacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notificacion) {
            throw new Exception('Notificación no encontrada');
        }
        
        // Obtener archivos adjuntos con validación de integridad
        $stmt = $conexion->prepare("SELECT * FROM notificaciones_archivos WHERE notificacion_id = :id ORDER BY fecha_subida ASC");
        $stmt->execute(['id' => $id]);
        $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Validar integridad de archivos físicos
        $archivos_validos = [];
        $archivos_faltantes = [];
        
        foreach ($archivos as $archivo) {
            // Resolver ruta del archivo (soporta formato legacy ../ y nuevo)
            $ruta_archivo = $archivo['ruta_archivo'];
            $ruta_completa = resolverRutaArchivo($ruta_archivo);
            
            if (file_exists($ruta_completa)) {
                $archivos_validos[] = $archivo;
            } else {
                $archivos_faltantes[] = $archivo;
            }
        }
        
        $notificacion['archivos'] = $archivos_validos;
        
        echo json_encode([
            'success' => true,
            'data' => $notificacion
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error al obtener notificación: ' . $e->getMessage());
    }
}

/**
 * Crea una nueva notificación
 */
function crearNotificacion($conexion) {
    try {
        // Validar datos requeridos
        $nombre = trim($_POST['nombre'] ?? '');
        $cuerpo = trim($_POST['cuerpo'] ?? '');
        $destino = $_POST['destino'] ?? '';
        $prioridad = $_POST['prioridad'] ?? '';
        
        if (empty($nombre) || empty($cuerpo) || empty($destino) || empty($prioridad)) {
            throw new Exception('Todos los campos requeridos deben estar completos');
        }
        
        // Validar usuarios específicos si el destino lo requiere
        if ($destino === 'especificos') {
            $usuarios_ids = $_POST['usuarios_ids'] ?? '';
            if (empty($usuarios_ids)) {
                throw new Exception('Debe seleccionar al menos un usuario para notificaciones específicas');
            }
            
            // Validar que los IDs de usuarios sean válidos
            $ids_array = array_filter(array_map('intval', explode(',', $usuarios_ids)));
            if (empty($ids_array)) {
                throw new Exception('Los usuarios seleccionados no son válidos');
            }
        }
        
        $permitir_respuesta = isset($_POST['permitir_respuesta']) ? 1 : 0;
        $autor_id = $_SESSION['usuario_id'];
        
        $sql = "INSERT INTO notificaciones (nombre, cuerpo, destino, prioridad, permitir_respuesta, autor_id, fecha_creacion)
                VALUES (:nombre, :cuerpo, :destino, :prioridad, :permitir_respuesta, :autor_id, NOW())";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            'nombre' => $nombre,
            'cuerpo' => $cuerpo,
            'destino' => $destino,
            'prioridad' => $prioridad,
            'permitir_respuesta' => $permitir_respuesta,
            'autor_id' => $autor_id
        ]);
        
        $notificacion_id = $conexion->lastInsertId();
        
        // Insertar usuarios específicos si corresponde
        if ($destino === 'especificos' && !empty($ids_array)) {
            $stmt_usuarios = $conexion->prepare("INSERT INTO notificaciones_usuarios (notificacion_id, usuario_id) VALUES (?, ?)");
            foreach ($ids_array as $usuario_id) {
                $stmt_usuarios->execute([$notificacion_id, $usuario_id]);
            }
        }
        
        // Manejar archivos adjuntos si existen (hasta 10)
        $archivos_guardados = 0;
        $errores_archivos = [];
        
        if (isset($_FILES['archivos']) && is_array($_FILES['archivos']['name'])) {
            $total_archivos = count($_FILES['archivos']['name']);
            
            // Limitar a 10 archivos
            if ($total_archivos > 10) {
                throw new Exception('Máximo 10 archivos permitidos por notificación');
            }
            
            // Procesar cada archivo
            for ($i = 0; $i < $total_archivos; $i++) {
                if ($_FILES['archivos']['error'][$i] === UPLOAD_ERR_OK) {
                    $archivo = [
                        'name' => $_FILES['archivos']['name'][$i],
                        'type' => $_FILES['archivos']['type'][$i],
                        'tmp_name' => $_FILES['archivos']['tmp_name'][$i],
                        'error' => $_FILES['archivos']['error'][$i],
                        'size' => $_FILES['archivos']['size'][$i]
                    ];
                    
                    try {
                        if (procesarArchivoAdjunto($archivo, $notificacion_id, $conexion)) {
                            $archivos_guardados++;
                        }
                    } catch (Exception $e) {
                        $error_msg = "Error al procesar archivo {$archivo['name']}: " . $e->getMessage();
                        $errores_archivos[] = $error_msg;
                    }
                }
            }
        }
        
        $response = [
            'success' => true,
            'message' => 'Notificación creada exitosamente',
            'id' => $notificacion_id
        ];
        
        if ($archivos_guardados > 0) {
            $response['mensaje_archivo'] = "$archivos_guardados archivo(s) adjunto(s) guardado(s) correctamente";
        }
        
        if (!empty($errores_archivos)) {
            $response['errores_archivos'] = $errores_archivos;
            $response['mensaje_archivo'] = ($response['mensaje_archivo'] ?? '') . '. Algunos archivos fallaron: ' . implode(', ', $errores_archivos);
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Error al crear notificación: ' . $e->getMessage());
    }
}

/**
 * Procesa y guarda un archivo adjunto usando el manejador centralizado
 */
function procesarArchivoAdjunto($archivo, $notificacion_id, $conexion) {
    try {
        // Validar el archivo
        if (!$archivo || !is_array($archivo)) {
            throw new Exception('Archivo no válido');
        }
        
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir archivo: ' . $archivo['error']);
        }
        
        $nombre = $archivo['name'];
        $tmp_name = $archivo['tmp_name'];
        $size = $archivo['size'];
        $tipo = $archivo['type'];
        
        // Validaciones
        if (empty($nombre) || empty($tmp_name)) {
            throw new Exception('Datos de archivo incompletos');
        }
        
        // Límite de tamaño (10MB)
        $max_size = 10 * 1024 * 1024;
        if ($size > $max_size) {
            throw new Exception('El archivo es demasiado grande. Máximo 10MB.');
        }
        
        // Extensiones permitidas (sincronizado con JS)
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $extensiones_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
        
        if (!in_array($extension, $extensiones_permitidas)) {
            throw new Exception('Tipo de archivo no permitido: ' . $extension);
        }
        
        // ✅ FIX BUG S2: Validar MIME type real del archivo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_real = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);
        
        // MIME types permitidos por extensión
        $mimes_permitidos = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'txt' => ['text/plain']
        ];
        
        if (!isset($mimes_permitidos[$extension]) || !in_array($mime_real, $mimes_permitidos[$extension])) {
            throw new Exception('Archivo rechazado: el contenido no coincide con la extensión. MIME detectado: ' . $mime_real);
        }
        
        // Generar nombre único
        $nombre_unico = time() . '_' . uniqid() . '_' . $nombre;
        
        // Rutas
        $directorio_fisico = __DIR__ . '/../Documentos/Notificaciones';
        $archivo_fisico = $directorio_fisico . '/' . $nombre_unico;
        $ruta_bd = 'Documentos/Notificaciones/' . $nombre_unico;  // 🔧 SIN ../ para evitar path traversal
        
        // Crear directorio si no existe
        if (!is_dir($directorio_fisico)) {
            if (!mkdir($directorio_fisico, 0755, true)) {
                throw new Exception('No se pudo crear el directorio: ' . $directorio_fisico);
            }
        }
        
        // Mover archivo
        if (!move_uploaded_file($tmp_name, $archivo_fisico)) {
            throw new Exception('Error al mover archivo a: ' . $archivo_fisico);
        }
        
        // Guardar en base de datos (con ruta relativa)
        $sql = "INSERT INTO notificaciones_archivos (notificacion_id, nombre_original, nombre_archivo, ruta_archivo, tamano, tipo_mime, fecha_subida) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conexion->prepare($sql);
        $result = $stmt->execute([
            $notificacion_id,
            $nombre,
            $nombre_unico,
            $ruta_bd,
            $size,
            $tipo
        ]);
        
        return $result;
        
    } catch (Exception $e) {
        throw new Exception('Error al procesar archivo: ' . $e->getMessage());
    }
}

/**
 * Actualiza una notificación existente
 */
function actualizarNotificacion($conexion) {
    try {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception('ID de notificación no válido');
        }
        
        $nombre = trim($_POST['nombre'] ?? '');
        $cuerpo = trim($_POST['cuerpo'] ?? '');
        $destino = $_POST['destino'] ?? '';
        $prioridad = $_POST['prioridad'] ?? '';
        
        if (empty($nombre) || empty($cuerpo) || empty($destino) || empty($prioridad)) {
            throw new Exception('Todos los campos requeridos deben estar completos');
        }
        
        // Validar y procesar usuarios específicos
        if ($destino === 'especificos') {
            $usuarios_ids = $_POST['usuarios_ids'] ?? '';
            if (empty($usuarios_ids)) {
                throw new Exception('Debe seleccionar al menos un usuario para notificaciones específicas');
            }
            
            $ids_array = array_filter(array_map('intval', explode(',', $usuarios_ids)));
            if (empty($ids_array)) {
                throw new Exception('Los usuarios seleccionados no son válidos');
            }
            
            // Eliminar usuarios anteriores
            $stmt_delete = $conexion->prepare("DELETE FROM notificaciones_usuarios WHERE notificacion_id = ?");
            $stmt_delete->execute([$id]);
            
            // Insertar nuevos usuarios
            $stmt_usuarios = $conexion->prepare("INSERT INTO notificaciones_usuarios (notificacion_id, usuario_id) VALUES (?, ?)");
            foreach ($ids_array as $usuario_id) {
                $stmt_usuarios->execute([$id, $usuario_id]);
            }
        }
        
        $permitir_respuesta = isset($_POST['permitir_respuesta']) ? 1 : 0;
        
        $sql = "UPDATE notificaciones
                SET nombre = :nombre, cuerpo = :cuerpo, destino = :destino,
                    prioridad = :prioridad, permitir_respuesta = :permitir_respuesta,
                    fecha_actualizacion = NOW()
                WHERE id = :id";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            'nombre' => $nombre,
            'cuerpo' => $cuerpo,
            'destino' => $destino,
            'prioridad' => $prioridad,
            'permitir_respuesta' => $permitir_respuesta,
            'id' => $id
        ]);
        
        // Manejar archivos adjuntos nuevos si existen (hasta 10)
        $archivos_guardados = 0;
        $errores_archivos = [];
        if (isset($_FILES['archivos']) && is_array($_FILES['archivos']['name'])) {
            $total_archivos = count($_FILES['archivos']['name']);
            
            // Limitar a 10 archivos
            if ($total_archivos > 10) {
                throw new Exception('Máximo 10 archivos permitidos por notificación');
            }
            
            // Procesar cada archivo
            for ($i = 0; $i < $total_archivos; $i++) {
                if ($_FILES['archivos']['error'][$i] === UPLOAD_ERR_OK) {
                    $archivo = [
                        'name' => $_FILES['archivos']['name'][$i],
                        'type' => $_FILES['archivos']['type'][$i],
                        'tmp_name' => $_FILES['archivos']['tmp_name'][$i],
                        'error' => $_FILES['archivos']['error'][$i],
                        'size' => $_FILES['archivos']['size'][$i]
                    ];
                    
                    try {
                        if (procesarArchivoAdjunto($archivo, $id, $conexion)) {
                            $archivos_guardados++;
                        }
                    } catch (Exception $e) {
                        error_log("Error al procesar archivo {$archivo['name']}: " . $e->getMessage());
                        $errores_archivos[] = $archivo['name'] . ': ' . $e->getMessage();
                    }
                }
            }
        }
        
        $response = [
            'success' => true,
            'message' => 'Notificación actualizada exitosamente'
        ];
        
        if ($archivos_guardados > 0) {
            $response['mensaje_archivo'] = "$archivos_guardados archivo(s) adjunto(s) guardado(s) correctamente";
        }
        
        if (!empty($errores_archivos)) {
            $response['errores_archivos'] = $errores_archivos;
            $response['mensaje_archivo'] = ($response['mensaje_archivo'] ?? '') . '. Algunos archivos fallaron: ' . implode(', ', $errores_archivos);
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        throw new Exception('Error al actualizar notificación: ' . $e->getMessage());
    }
}

/**
 * Elimina una notificación
 */
function eliminarNotificacion($conexion) {
    try {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception('ID de notificación no válido');
        }
        
        // Iniciar transacción para atomicidad
        $conexion->beginTransaction();
        
        try {
            // 1. Obtener archivos para eliminar físicamente
            $stmt = $conexion->prepare("SELECT ruta_archivo FROM notificaciones_archivos WHERE notificacion_id = :id");
            $stmt->execute(['id' => $id]);
            $archivos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // 2. Eliminar registros relacionados
            // Eliminar respuestas
            $stmt = $conexion->prepare("DELETE FROM notificaciones_respuestas WHERE notificacion_id = :id");
            $stmt->execute(['id' => $id]);
            
            // Eliminar lecturas
            $stmt = $conexion->prepare("DELETE FROM notificaciones_leidas WHERE notificacion_id = :id");
            $stmt->execute(['id' => $id]);
            
            // Eliminar usuarios específicos
            $stmt = $conexion->prepare("DELETE FROM notificaciones_usuarios WHERE notificacion_id = :id");
            $stmt->execute(['id' => $id]);
            
            // Eliminar archivos de BD
            $stmt = $conexion->prepare("DELETE FROM notificaciones_archivos WHERE notificacion_id = :id");
            $stmt->execute(['id' => $id]);
            
            // 3. Eliminar la notificación principal
            $stmt = $conexion->prepare("DELETE FROM notificaciones WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Notificación no encontrada');
            }
            
            // 4. Confirmar transacción
            $conexion->commit();
            
            // 5. Eliminar archivos físicos (fuera de transacción)
            foreach ($archivos as $ruta_archivo) {
                $ruta_completa = __DIR__ . '/../' . $ruta_archivo;
                if (file_exists($ruta_completa)) {
                    unlink($ruta_completa);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Notificación eliminada exitosamente',
                'archivos_eliminados' => count($archivos)
            ]);
            
        } catch (Exception $e) {
            // Rollback en caso de error
            $conexion->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        throw new Exception('Error al eliminar notificación: ' . $e->getMessage());
    }
}

/**
 * Cambia el estado de una notificación (activa/archivada)
 */
function cambiarEstadoNotificacion($conexion) {
    try {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $nuevo_estado = $_POST['estado'] ?? $_GET['estado'] ?? '';
        
        if ($id <= 0) {
            throw new Exception('ID de notificación no válido');
        }
        
        if (!in_array($nuevo_estado, ['activa', 'archivada'])) {
            throw new Exception('Estado no válido. Debe ser "activa" o "archivada"');
        }
        
        // Verificar que la notificación existe
        $stmt = $conexion->prepare("SELECT id, estado FROM notificaciones WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $notificacion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notificacion) {
            throw new Exception('Notificación no encontrada');
        }
        
        // Si ya tiene el estado solicitado, no hacer nada
        if ($notificacion['estado'] === $nuevo_estado) {
            echo json_encode([
                'success' => true,
                'message' => 'La notificación ya tiene el estado solicitado',
                'estado_actual' => $nuevo_estado
            ]);
            return;
        }
        
        // Actualizar estado
        $sql = "UPDATE notificaciones 
                SET estado = :estado, fecha_actualizacion = NOW() 
                WHERE id = :id";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            'estado' => $nuevo_estado,
            'id' => $id
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('No se pudo actualizar el estado');
        }
        
        $accion_texto = ($nuevo_estado === 'activa') ? 'activada' : 'archivada';
        
        echo json_encode([
            'success' => true,
            'message' => "Notificación {$accion_texto} correctamente",
            'nuevo_estado' => $nuevo_estado,
            'estado_anterior' => $notificacion['estado']
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error al cambiar estado: ' . $e->getMessage());
    }
}

/**
 * Duplica una notificación existente
 */
function duplicarNotificacion($conexion) {
    try {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        
        if ($id <= 0) {
            throw new Exception('ID de notificación no válido');
        }
        
        // Obtener la notificación original
        $stmt = $conexion->prepare("SELECT * FROM notificaciones WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $original = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$original) {
            throw new Exception('Notificación no encontrada');
        }
        
        // Crear copia con nuevo nombre
        $nuevo_nombre = "Copia de " . $original['nombre'];
        
        // Verificar si ya existe una copia y agregar número
        $stmt = $conexion->prepare("SELECT COUNT(*) as count FROM notificaciones WHERE nombre LIKE :patron");
        $stmt->execute(['patron' => "Copia de " . $original['nombre'] . "%"]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count > 0) {
            $nuevo_nombre .= " (" . ($count + 1) . ")";
        }
        
        // Insertar nueva notificación con campos básicos conocidos
        $sql = "INSERT INTO notificaciones (nombre, cuerpo, destino, prioridad, permitir_respuesta, autor_id, estado, fecha_creacion) 
                VALUES (:nombre, :cuerpo, :destino, :prioridad, :permitir_respuesta, :autor_id, :estado, NOW())";
        
        $stmt = $conexion->prepare($sql);
        
        $datos = [
            'nombre' => $nuevo_nombre,
            'cuerpo' => $original['cuerpo'] ?? '',
            'destino' => $original['destino'] ?? 'todos',
            'prioridad' => $original['prioridad'] ?? 'media',
            'permitir_respuesta' => $original['permitir_respuesta'] ?? 1,
            'autor_id' => $_SESSION['usuario_id'],
            'estado' => 'activa'
        ];
        
        $resultado = $stmt->execute($datos);
        
        if (!$resultado) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Error al crear la duplicación: ' . $errorInfo[2]);
        }
        
        $nueva_id = $conexion->lastInsertId();
        
        // Copiar archivos adjuntos si los hay
        $stmt = $conexion->prepare("SELECT * FROM notificaciones_archivos WHERE notificacion_id = :id_original");
        $stmt->execute(['id_original' => $id]);
        $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $archivos_copiados = 0;
        foreach ($archivos as $archivo) {
            try {
                // Generar nuevo nombre único para el archivo
                $extension = pathinfo($archivo['nombre_archivo'], PATHINFO_EXTENSION);
                $nuevo_nombre_archivo = 'notif_' . $nueva_id . '_' . uniqid() . '.' . $extension;
                $nueva_ruta = 'Documentos/Notificaciones/' . $nuevo_nombre_archivo;
                
                // Copiar archivo físico si existe
                $ruta_original = __DIR__ . '/../' . $archivo['ruta_archivo'];
                $ruta_nueva = __DIR__ . '/../' . $nueva_ruta;
                
                if (file_exists($ruta_original) && copy($ruta_original, $ruta_nueva)) {
                    // Insertar registro del archivo copiado
                    $stmt_archivo = $conexion->prepare("
                        INSERT INTO notificaciones_archivos 
                        (notificacion_id, nombre_original, nombre_archivo, ruta_archivo, tamano, tipo_mime, fecha_subida)
                        VALUES (:notificacion_id, :nombre_original, :nombre_archivo, :ruta_archivo, :tamano, :tipo_mime, NOW())
                    ");
                    
                    $stmt_archivo->execute([
                        'notificacion_id' => $nueva_id,
                        'nombre_original' => $archivo['nombre_original'],
                        'nombre_archivo' => $nuevo_nombre_archivo,
                        'ruta_archivo' => $nueva_ruta,
                        'tamano' => $archivo['tamano'],
                        'tipo_mime' => $archivo['tipo_mime']
                    ]);
                    
                    $archivos_copiados++;
                }
            } catch (Exception $e) {
                // Continuar con otros archivos
            }
        }
        
        // ✅ FIX BUG #7: Copiar usuarios específicos si la notificación es para ellos
        if ($original['destino'] === 'especificos') {
            $stmt_usuarios = $conexion->prepare("
                SELECT usuario_id FROM notificaciones_usuarios 
                WHERE notificacion_id = :id_original
            ");
            $stmt_usuarios->execute(['id_original' => $id]);
            $usuarios_originales = $stmt_usuarios->fetchAll(PDO::FETCH_COLUMN);
            
            $usuarios_copiados = 0;
            foreach ($usuarios_originales as $usuario_id) {
                try {
                    $stmt_copiar_usuario = $conexion->prepare("
                        INSERT INTO notificaciones_usuarios (notificacion_id, usuario_id)
                        VALUES (:notificacion_id, :usuario_id)
                    ");
                    $stmt_copiar_usuario->execute([
                        'notificacion_id' => $nueva_id,
                        'usuario_id' => $usuario_id
                    ]);
                    $usuarios_copiados++;
                } catch (Exception $e) {
                    // Error al copiar usuario, continuar
                }
            }
        }
        
        // Copiar respuestas de la notificación original
        $stmt_respuestas = $conexion->prepare("
            SELECT usuario_id, respuesta, archivo_nombre, archivo_ruta, fecha_respuesta 
            FROM notificaciones_respuestas 
            WHERE notificacion_id = :id_original
        ");
        $stmt_respuestas->execute(['id_original' => $id]);
        $respuestas = $stmt_respuestas->fetchAll(PDO::FETCH_ASSOC);
        
        $respuestas_copiadas = 0;
        foreach ($respuestas as $respuesta_original) {
            try {
                $nueva_ruta_archivo = null;
                $nuevo_nombre_archivo = $respuesta_original['archivo_nombre'];
                
                // Copiar archivo físico si existe
                if (!empty($respuesta_original['archivo_ruta'])) {
                    $ruta_original = __DIR__ . '/../' . $respuesta_original['archivo_ruta'];
                    
                    if (file_exists($ruta_original)) {
                        $extension = pathinfo($respuesta_original['archivo_ruta'], PATHINFO_EXTENSION);
                        $nombre_unico = date('YmdHis') . '_' . $respuesta_original['usuario_id'] . '_' . uniqid() . '.' . $extension;
                        $nueva_ruta_archivo = 'Documentos/Respuestas/' . $nombre_unico;
                        $ruta_completa = __DIR__ . '/../' . $nueva_ruta_archivo;
                        
                        copy($ruta_original, $ruta_completa);
                    }
                }
                
                // Insertar respuesta copiada
                $stmt_copiar_respuesta = $conexion->prepare("
                    INSERT INTO notificaciones_respuestas 
                    (notificacion_id, usuario_id, respuesta, archivo_nombre, archivo_ruta, fecha_respuesta)
                    VALUES (:notificacion_id, :usuario_id, :respuesta, :archivo_nombre, :archivo_ruta, :fecha_respuesta)
                ");
                
                $stmt_copiar_respuesta->execute([
                    'notificacion_id' => $nueva_id,
                    'usuario_id' => $respuesta_original['usuario_id'],
                    'respuesta' => $respuesta_original['respuesta'],
                    'archivo_nombre' => $nuevo_nombre_archivo,
                    'archivo_ruta' => $nueva_ruta_archivo,
                    'fecha_respuesta' => $respuesta_original['fecha_respuesta']
                ]);
                
                $respuestas_copiadas++;
            } catch (Exception $e) {
                // Error al copiar respuesta, continuar
            }
        }
        
        enviarExito([
            'nueva_id' => $nueva_id,
            'nuevo_nombre' => $nuevo_nombre,
            'archivos_copiados' => $archivos_copiados,
            'total_archivos' => count($archivos),
            'respuestas_copiadas' => $respuestas_copiadas
        ], 'Notificación duplicada correctamente');
        
    } catch (Exception $e) {
        throw new Exception('Error al duplicar notificación: ' . $e->getMessage());
    }
}

// ==================== FUNCIONES DE RESPUESTAS ====================

/**
 * Responder a una notificación
 */
function responderNotificacion($conexion) {
    try {
        // Verificar CSRF (aceptar tanto POST como header)
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validar_token_csrf($token)) {
            enviarError('Token CSRF inválido o expirado', 403);
        }
        
        // Obtener datos
        $notificacion_id = $_POST['notificacion_id'] ?? '';
        $respuesta = trim($_POST['respuesta'] ?? '');
        $usuario_id = $_SESSION['usuario_id'];
        
        // Validaciones
        if (empty($notificacion_id)) {
            enviarError('ID de notificación requerido');
        }
        
        if (empty($respuesta)) {
            enviarError('La respuesta no puede estar vacía');
        }
        
        if (strlen($respuesta) > 2000) {
            enviarError('La respuesta no puede exceder 2000 caracteres');
        }
        
        // Determinar si es admin
        $es_admin = false;
        if (isset($_SESSION['usuario_rol'])) {
            $rol_lower = strtolower(trim($_SESSION['usuario_rol']));
            $es_admin = in_array($rol_lower, ['admin', 'administrador']);
        }
        
        // Verificar que la notificación existe, está activa, permite respuestas y el usuario tiene acceso
        if ($es_admin) {
            // Admin puede responder a cualquier notificación (incluyendo destino 'administradores')
            $stmt_verificar = $conexion->prepare("
                SELECT id, estado, permitir_respuesta FROM notificaciones WHERE id = :notificacion_id LIMIT 1
            ");
            $stmt_verificar->execute(['notificacion_id' => $notificacion_id]);
        } else {
            // Usuario normal: verificar acceso según destino
            $stmt_verificar = $conexion->prepare("
                SELECT n.id, n.estado, n.permitir_respuesta 
                FROM notificaciones n
                LEFT JOIN notificaciones_usuarios nu ON n.id = nu.notificacion_id
                WHERE n.id = :notificacion_id 
                AND (
                    n.destino = 'todos' 
                    OR n.destino = 'regulares'
                    OR (n.destino = 'especificos' AND nu.usuario_id = :usuario_id)
                )
                LIMIT 1
            ");
            $stmt_verificar->execute([
                'notificacion_id' => $notificacion_id,
                'usuario_id' => $usuario_id
            ]);
        }
        
        $notificacion = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
        
        if (!$notificacion) {
            enviarError('No tienes acceso a esta notificación', 403);
        }
        
        // Validar que la notificación esté activa
        if ($notificacion['estado'] === 'archivada') {
            enviarError('No se puede responder a una notificación archivada', 403);
        }
        
        // FIX Bug #2: Validar que la notificación permita respuestas
        if (!$notificacion['permitir_respuesta']) {
            enviarError('Esta notificación no permite respuestas', 403);
        }
        
        // Manejar múltiples archivos adjuntos si existen
        $archivos = []; // Array para guardar información de archivos
        
        if (isset($_FILES['archivos']) && is_array($_FILES['archivos']['name'])) {
            // Crear directorio de respuestas si no existe
            $directorio = __DIR__ . '/../Documentos/Respuestas';
            if (!is_dir($directorio)) {
                mkdir($directorio, 0755, true);
            }
            
            $extensiones_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt', 'zip', 'rar'];
            
            // Procesar cada archivo
            for ($i = 0; $i < count($_FILES['archivos']['name']); $i++) {
                // Saltar archivos con error UPLOAD_ERR_NO_FILE
                if ($_FILES['archivos']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                
                if ($_FILES['archivos']['error'][$i] !== UPLOAD_ERR_OK) {
                    enviarError('Error al subir uno de los archivos');
                }
                
                // Validar tamaño (10MB)
                if ($_FILES['archivos']['size'][$i] > 10485760) {
                    enviarError('Uno de los archivos pesa más de 10MB');
                }
                
                // Validar extensión
                $extension = strtolower(pathinfo($_FILES['archivos']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($extension, $extensiones_permitidas)) {
                    enviarError('Uno de los archivos tiene un formato no permitido');
                }
                
                // Validar MIME type real del archivo
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_real = finfo_file($finfo, $_FILES['archivos']['tmp_name'][$i]);
                finfo_close($finfo);
                
                $mimes_por_extension = [
                    'pdf' => ['application/pdf'],
                    'doc' => ['application/msword'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                    'xls' => ['application/vnd.ms-excel'],
                    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
                    'jpg' => ['image/jpeg'],
                    'jpeg' => ['image/jpeg'],
                    'png' => ['image/png'],
                    'txt' => ['text/plain'],
                    'zip' => ['application/zip', 'application/x-zip-compressed'],
                    'rar' => ['application/x-rar-compressed', 'application/vnd.rar', 'application/octet-stream']
                ];
                
                if (isset($mimes_por_extension[$extension]) && !in_array($mime_real, $mimes_por_extension[$extension])) {
                    enviarError('El tipo real del archivo no coincide con su extensión');
                }
                
                // Generar nombre único seguro
                $nombre_original = $_FILES['archivos']['name'][$i];
                $nombre_unico = date('YmdHis') . '_' . $usuario_id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                $archivo_ruta = 'Documentos/Respuestas/' . $nombre_unico;
                $ruta_completa = __DIR__ . '/../' . $archivo_ruta;
                
                // Mover archivo
                if (!move_uploaded_file($_FILES['archivos']['tmp_name'][$i], $ruta_completa)) {
                    enviarError('Error al guardar uno de los archivos');
                }
                
                // Guardar información del archivo
                $archivos[] = [
                    'nombre' => $nombre_original,
                    'ruta' => $archivo_ruta,
                    'archivo_id' => uniqid()
                ];
            }
        }
        
        // Guardar archivos como JSON
        $archivos_json = !empty($archivos) ? json_encode($archivos, JSON_UNESCAPED_UNICODE) : null;
        
        // Insertar respuesta con archivos como JSON
        $stmt = $conexion->prepare("
            INSERT INTO notificaciones_respuestas 
            (notificacion_id, usuario_id, respuesta, archivo_nombre, archivo_ruta, fecha_respuesta)
            VALUES (:notificacion_id, :usuario_id, :respuesta, :archivo_nombre, :archivo_ruta, NOW())
        ");
        
        $stmt->execute([
            'notificacion_id' => $notificacion_id,
            'usuario_id' => $usuario_id,
            'respuesta' => $respuesta,
            'archivo_nombre' => 'archivos', // Indica que hay archivos JSON
            'archivo_ruta' => $archivos_json // Guardar como JSON
        ]);
        
        enviarExito([
            'respuesta_id' => $conexion->lastInsertId()
        ], 'Respuesta enviada correctamente');
        
    } catch (Exception $e) {
        throw new Exception('Error al responder: ' . $e->getMessage());
    }
}

/**
 * Obtener respuestas de una notificación
 */
function obtenerRespuestas($conexion) {
    try {
        $notificacion_id = $_GET['notificacion_id'] ?? '';
        $usuario_id = $_SESSION['usuario_id'];
        
        if (empty($notificacion_id)) {
            enviarError('ID de notificación requerido');
        }
        
        // Determinar si es admin
        $es_admin = false;
        if (isset($_SESSION['usuario_rol'])) {
            $rol_lower = strtolower(trim($_SESSION['usuario_rol']));
            $es_admin = in_array($rol_lower, ['admin', 'administrador']);
        }
        
        // Verificar que el usuario tiene acceso a la notificación
        if ($es_admin) {
            // Admin puede ver respuestas de cualquier notificación
            $stmt_verificar = $conexion->prepare("
                SELECT id FROM notificaciones WHERE id = :notificacion_id LIMIT 1
            ");
            $stmt_verificar->execute(['notificacion_id' => $notificacion_id]);
        } else {
            // Usuario normal: verificar acceso según destino
            $stmt_verificar = $conexion->prepare("
                SELECT n.id 
                FROM notificaciones n
                LEFT JOIN notificaciones_usuarios nu ON n.id = nu.notificacion_id
                WHERE n.id = :notificacion_id 
                AND (
                    n.destino = 'todos' 
                    OR n.destino = 'regulares'
                    OR (n.destino = 'especificos' AND nu.usuario_id = :usuario_id)
                )
                LIMIT 1
            ");
            $stmt_verificar->execute([
                'notificacion_id' => $notificacion_id,
                'usuario_id' => $usuario_id
            ]);
        }
        
        if (!$stmt_verificar->fetch()) {
            enviarError('No tienes acceso a esta notificación', 403);
        }
        
        // Obtener respuestas
        $stmt = $conexion->prepare("
            SELECT 
                r.id,
                r.usuario_id,
                u.nombre AS usuario_nombre,
                r.respuesta,
                r.archivo_nombre,
                r.archivo_ruta,
                r.fecha_respuesta
            FROM notificaciones_respuestas r
            INNER JOIN usuarios u ON r.usuario_id = u.id
            WHERE r.notificacion_id = :notificacion_id
            ORDER BY r.fecha_respuesta ASC
        ");
        
        $stmt->execute(['notificacion_id' => $notificacion_id]);
        $respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Procesar archivos JSON para cada respuesta
        foreach ($respuestas as &$respuesta) {
            if ($respuesta['archivo_nombre'] === 'archivos' && !empty($respuesta['archivo_ruta'])) {
                $archivos = json_decode($respuesta['archivo_ruta'], true);
                $respuesta['archivos'] = is_array($archivos) ? $archivos : [];
            } else {
                $respuesta['archivos'] = [];
            }
        }
        
        enviarExito(['respuestas' => $respuestas]);
        
    } catch (Exception $e) {
        throw new Exception('Error al obtener respuestas: ' . $e->getMessage());
    }
}

/**
 * Eliminar respuesta (solo admin)
 */
function eliminarRespuesta($conexion) {
    try {
        // CSRF ya verificado en verificar_admin()
        
        $respuesta_id = $_POST['respuesta_id'] ?? '';
        
        if (empty($respuesta_id)) {
            enviarError('ID de respuesta requerido');
        }
        
        // Obtener info de la respuesta antes de eliminar
        $stmt_info = $conexion->prepare("
            SELECT archivo_ruta, archivo_nombre
            FROM notificaciones_respuestas 
            WHERE id = :respuesta_id
        ");
        $stmt_info->execute(['respuesta_id' => $respuesta_id]);
        $respuesta = $stmt_info->fetch(PDO::FETCH_ASSOC);
        
        if (!$respuesta) {
            enviarError('Respuesta no encontrada', 404);
        }
        
        // Eliminar archivos si existen
        if (!empty($respuesta['archivo_ruta'])) {
            // Verificar si es JSON (múltiples archivos) o ruta simple
            $archivos_json = json_decode($respuesta['archivo_ruta'], true);
            
            if (is_array($archivos_json)) {
                // Múltiples archivos en JSON
                foreach ($archivos_json as $archivo) {
                    if (isset($archivo['ruta'])) {
                        $ruta_completa = __DIR__ . '/../' . $archivo['ruta'];
                        if (file_exists($ruta_completa)) {
                            @unlink($ruta_completa);
                        }
                    }
                }
            } else {
                // Ruta simple (formato antiguo)
                $ruta_completa = __DIR__ . '/../' . $respuesta['archivo_ruta'];
                if (file_exists($ruta_completa)) {
                    @unlink($ruta_completa);
                }
            }
        }
        
        // Eliminar de BD
        $stmt = $conexion->prepare("DELETE FROM notificaciones_respuestas WHERE id = :respuesta_id");
        $stmt->execute(['respuesta_id' => $respuesta_id]);
        
        enviarExito([], 'Respuesta eliminada correctamente');
        
    } catch (Exception $e) {
        throw new Exception('Error al eliminar respuesta: ' . $e->getMessage());
    }
}

/**
 * Descargar archivo de respuesta
 */
function descargarRespuesta($conexion) {
    try {
        $respuesta_id = $_GET['respuesta_id'] ?? '';
        $usuario_id = $_SESSION['usuario_id'];
        
        if (empty($respuesta_id)) {
            enviarError('ID de respuesta requerido');
        }
        
        // Determinar si es admin
        $es_admin = false;
        if (isset($_SESSION['usuario_rol'])) {
            $rol_lower = strtolower(trim($_SESSION['usuario_rol']));
            $es_admin = in_array($rol_lower, ['admin', 'administrador']);
        }
        
        // Obtener info de la respuesta y verificar acceso
        if ($es_admin) {
            // Admin puede descargar cualquier respuesta
            $stmt = $conexion->prepare("
                SELECT 
                    r.archivo_nombre,
                    r.archivo_ruta,
                    r.notificacion_id,
                    r.usuario_id
                FROM notificaciones_respuestas r
                WHERE r.id = :respuesta_id
                LIMIT 1
            ");
            $stmt->execute(['respuesta_id' => $respuesta_id]);
        } else {
            // Usuario normal: verificar acceso
            $stmt = $conexion->prepare("
                SELECT 
                    r.archivo_nombre,
                    r.archivo_ruta,
                    r.notificacion_id,
                    r.usuario_id
                FROM notificaciones_respuestas r
                INNER JOIN notificaciones n ON r.notificacion_id = n.id
                LEFT JOIN notificaciones_usuarios nu ON n.id = nu.notificacion_id AND nu.usuario_id = :usuario_id
                WHERE r.id = :respuesta_id 
                AND (
                    r.usuario_id = :usuario_id_autor
                    OR n.destino = 'todos' 
                    OR n.destino = 'regulares'
                    OR (n.destino = 'especificos' AND nu.usuario_id IS NOT NULL)
                )
                LIMIT 1
            ");
            $stmt->execute([
                'respuesta_id' => $respuesta_id,
                'usuario_id' => $usuario_id,
                'usuario_id_autor' => $usuario_id
            ]);
        }
        
        $respuesta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$respuesta) {
            enviarError('Respuesta no encontrada o sin acceso', 404);
        }
        
        if (empty($respuesta['archivo_ruta'])) {
            enviarError('Esta respuesta no tiene archivo adjunto', 404);
        }
        
        $ruta_completa = __DIR__ . '/../' . $respuesta['archivo_ruta'];
        
        // Validar que la ruta real esté dentro del directorio permitido (previene path traversal)
        $real_path = realpath($ruta_completa);
        $allowed_dir = realpath(__DIR__ . '/../Documentos/Respuestas');
        
        if ($real_path === false || $allowed_dir === false || strpos($real_path, $allowed_dir) !== 0) {
            enviarError('Acceso denegado al archivo', 403);
        }
        
        if (!file_exists($real_path)) {
            enviarError('Archivo no encontrado en el servidor', 404);
        }
        
        // Limpiar buffer
        if (ob_get_length()) ob_clean();
        
        // Sanitizar nombre para Content-Disposition (previene header injection)
        $nombre_seguro = str_replace(["\r", "\n", '"', "\0"], '', $respuesta['archivo_nombre']);
        
        // Cabeceras de descarga
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nombre_seguro . '"');
        header('Content-Length: ' . filesize($real_path));
        header('Cache-Control: private');
        header('Pragma: private');
        
        // Enviar archivo
        readfile($real_path);
        exit;
        
    } catch (Exception $e) {
        throw new Exception('Error al descargar archivo: ' . $e->getMessage());
    }
}

/**
 * Descarga un archivo de respuesta por ruta
 */
function descargarArchivo($conexion) {
    try {
        $archivo_ruta = $_GET['archivo_ruta'] ?? '';
        $usuario_id = $_SESSION['usuario_id'];
        
        if (empty($archivo_ruta)) {
            enviarError('Ruta de archivo requerida');
        }
        
        // Determinar si es admin
        $es_admin = false;
        if (isset($_SESSION['usuario_rol'])) {
            $rol_lower = strtolower(trim($_SESSION['usuario_rol']));
            $es_admin = in_array($rol_lower, ['admin', 'administrador']);
        }
        
        // Buscar la respuesta que contiene este archivo
        // El archivo_ruta en la BD es JSON con array de archivos
        $nombre_archivo = basename($archivo_ruta);
        
        if ($es_admin) {
            // Admin puede descargar cualquier archivo de respuestas
            $stmt = $conexion->prepare("
                SELECT r.id, r.archivo_ruta, r.archivo_nombre, r.usuario_id
                FROM notificaciones_respuestas r
                WHERE r.archivo_ruta LIKE :archivo_busqueda
                LIMIT 1
            ");
            $stmt->execute([
                'archivo_busqueda' => '%' . $nombre_archivo . '%'
            ]);
        } else {
            // Usuario normal: verificar acceso según destino de la notificación
            // o si es el autor de la respuesta
            $stmt = $conexion->prepare("
                SELECT r.id, r.archivo_ruta, r.archivo_nombre, r.usuario_id
                FROM notificaciones_respuestas r
                INNER JOIN notificaciones n ON r.notificacion_id = n.id
                LEFT JOIN notificaciones_usuarios nu ON n.id = nu.notificacion_id AND nu.usuario_id = :usuario_id
                WHERE r.archivo_ruta LIKE :archivo_busqueda
                AND (
                    r.usuario_id = :usuario_id_autor
                    OR n.destino = 'todos' 
                    OR n.destino = 'regulares'
                    OR (n.destino = 'especificos' AND nu.usuario_id IS NOT NULL)
                )
                LIMIT 1
            ");
            $stmt->execute([
                'archivo_busqueda' => '%' . $nombre_archivo . '%',
                'usuario_id' => $usuario_id,
                'usuario_id_autor' => $usuario_id
            ]);
        }
        
        $respuesta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$respuesta) {
            enviarError('No tienes acceso a este archivo', 403);
        }
        
        // Verificar que el archivo solicitado está en el JSON de archivos
        $archivos_json = json_decode($respuesta['archivo_ruta'], true);
        $archivo_encontrado = false;
        $nombre_original = $nombre_archivo;
        
        if (is_array($archivos_json)) {
            foreach ($archivos_json as $archivo) {
                if (isset($archivo['ruta']) && strpos($archivo['ruta'], $nombre_archivo) !== false) {
                    $archivo_encontrado = true;
                    $nombre_original = $archivo['nombre'] ?? $nombre_archivo;
                    break;
                }
            }
        }
        
        if (!$archivo_encontrado) {
            enviarError('Archivo no encontrado en la respuesta', 404);
        }
        
        // Construir ruta completa y validar
        $ruta_completa = __DIR__ . '/../' . $archivo_ruta;
        
        // Validar que el archivo existe y está dentro del directorio permitido
        if (!file_exists($ruta_completa) || !is_file($ruta_completa)) {
            enviarError('Archivo no encontrado', 404);
        }
        
        // Validar que el archivo está en el directorio de respuestas
        $directorio_permitido = realpath(__DIR__ . '/../Documentos/Respuestas');
        if ($directorio_permitido && strpos(realpath($ruta_completa), $directorio_permitido) !== 0) {
            enviarError('Acceso denegado', 403);
        }
        
        // Limpiar buffer
        if (ob_get_length()) ob_clean();
        
        // Cabeceras de descarga - usar nombre original
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nombre_original . '"');
        header('Content-Length: ' . filesize($ruta_completa));
        header('Cache-Control: private');
        header('Pragma: private');
        
        // Enviar archivo
        readfile($ruta_completa);
        exit;
        
    } catch (Exception $e) {
        throw new Exception('Error al descargar archivo: ' . $e->getMessage());
    }
}