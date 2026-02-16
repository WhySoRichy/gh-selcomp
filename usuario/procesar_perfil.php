<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

// Verificar token CSRF
require_once __DIR__ . '/../administrador/csrf_protection.php';
if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

include_once "../conexion/conexion.php";

try {
    $usuario_id = $_SESSION['usuario_id'];
    
    // Validar que sea una petición POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Crear directorio de avatares si no existe
    $avatar_dir = $_SERVER['DOCUMENT_ROOT'] . '/gh/Img/Avatars';
    if (!is_dir($avatar_dir)) {
        mkdir($avatar_dir, 0755, true);
    }

    $avatar_path = $avatar_dir . '/user_' . $usuario_id . '.jpg';

    // Si se está eliminando el avatar
    if (isset($_POST['action']) && $_POST['action'] === 'remove') {
        if (file_exists($avatar_path)) {
            unlink($avatar_path);
        }
        // También limpiar el campo en la BD
        $stmt = $conexion->prepare("UPDATE usuarios SET avatar = NULL WHERE id = :id");
        $stmt->bindParam(':id', $usuario_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Avatar eliminado correctamente']);
        exit;
    }

    // Variable para saber si se subió avatar
    $avatar_subido = false;
    $avatar_name = 'user_' . $usuario_id . '.jpg';

    // Procesar subida de avatar si hay archivo
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = $_FILES['avatar'];
        
        // Validar tipo de archivo
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $uploaded_file['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception('Tipo de archivo no permitido. Solo JPEG, PNG, GIF y WebP');
        }
        
        // Validar tamaño (máximo 5MB)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($uploaded_file['size'] > $max_size) {
            throw new Exception('El archivo es muy grande. Máximo 5MB');
        }
        
        // Verificar que es una imagen real
        $image_info = getimagesize($uploaded_file['tmp_name']);
        if ($image_info === false) {
            throw new Exception('El archivo no es una imagen válida');
        }
        
        // Redimensionar y guardar imagen
        $resultado = redimensionarImagen($uploaded_file['tmp_name'], $avatar_path, 200, 200);
        
        if (!$resultado) {
            throw new Exception('Error al procesar la imagen');
        }
        
        $avatar_subido = true;
    }
    
    // Obtener y validar datos del perfil
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
    $estado_civil = $_POST['estado_civil'] ?? '';
    $emergencia_contacto = trim($_POST['emergencia_contacto'] ?? '');
    $emergencia_telefono = trim($_POST['emergencia_telefono'] ?? '');
    $acerca_de_mi = trim($_POST['acerca_de_mi'] ?? '');
    
    // Validar longitud de acerca_de_mi
    if (strlen($acerca_de_mi) > 500) {
        throw new Exception('El campo "Acerca de mí" no puede superar los 500 caracteres');
    }
    
    // Validaciones básicas
    if (empty($nombre) || empty($apellido)) {
        throw new Exception('El nombre y apellido son obligatorios');
    }
    
    if (strlen($nombre) < 2 || strlen($nombre) > 100) {
        throw new Exception('El nombre debe tener entre 2 y 100 caracteres');
    }
    
    if (strlen($apellido) < 2 || strlen($apellido) > 100) {
        throw new Exception('El apellido debe tener entre 2 y 100 caracteres');
    }
    
    if (!empty($telefono) && !preg_match('/^[0-9+\-\s()]{7,15}$/', $telefono)) {
        throw new Exception('El teléfono debe tener un formato válido');
    }
    
    if (!empty($emergencia_telefono) && !preg_match('/^[0-9+\-\s()]{7,15}$/', $emergencia_telefono)) {
        throw new Exception('El teléfono de emergencia debe tener un formato válido');
    }
    
    // Si fecha de nacimiento no está vacía, validar
    if (!empty($fecha_nacimiento)) {
        $fecha = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        if (!$fecha) {
            throw new Exception('La fecha de nacimiento no es válida');
        }
        
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha)->y;
        
        if ($edad < 16 || $edad > 100) {
            throw new Exception('La edad debe estar entre 16 y 100 años');
        }
    } else {
        $fecha_nacimiento = null;
    }
    
    // Preparar consulta de actualización
    // Incluir avatar, acerca_de_mi y sincronizar cumple con fecha_nacimiento
    $sql = "UPDATE usuarios SET 
            nombre = :nombre, 
            apellido = :apellido, 
            telefono = :telefono, 
            direccion = :direccion, 
            fecha_nacimiento = :fecha_nacimiento,
            cumple = :cumple,
            estado_civil = :estado_civil, 
            emergencia_contacto = :emergencia_contacto, 
            emergencia_telefono = :emergencia_telefono,
            acerca_de_mi = :acerca_de_mi,
            avatar = CASE WHEN :avatar_subido = 1 THEN :avatar_name ELSE avatar END,
            fecha_actualizacion = NOW()
            WHERE id = :id";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':apellido', $apellido);
    $stmt->bindParam(':telefono', $telefono);
    $stmt->bindParam(':direccion', $direccion);
    $stmt->bindParam(':fecha_nacimiento', $fecha_nacimiento);
    $stmt->bindParam(':cumple', $fecha_nacimiento); // Sincronizar con cumple
    $stmt->bindParam(':estado_civil', $estado_civil);
    $stmt->bindParam(':emergencia_contacto', $emergencia_contacto);
    $stmt->bindParam(':emergencia_telefono', $emergencia_telefono);
    $stmt->bindParam(':acerca_de_mi', $acerca_de_mi);
    $stmt->bindValue(':avatar_subido', $avatar_subido ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindParam(':avatar_name', $avatar_name);
    $stmt->bindParam(':id', $usuario_id);
    
    if ($stmt->execute()) {
        // Actualizar datos de la sesión
        $_SESSION['usuario_nombre'] = $nombre;
        
        $response = ['success' => true, 'message' => 'Perfil actualizado correctamente'];
        
        // Si se subió avatar, incluir la nueva URL
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $response['avatar_url'] = '/gh/Img/Avatars/user_' . $usuario_id . '.jpg?v=' . time();
        }
        
        echo json_encode($response);
    } else {
        throw new Exception('Error al actualizar el perfil en la base de datos');
    }
    
} catch (PDOException $e) {
    error_log("Error PDO en procesar_perfil.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos. Contacte al administrador.'
    ]);
    
} catch (Exception $e) {
    error_log("Error en procesar_perfil.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error al procesar la solicitud. Contacte al administrador.'
    ]);
}

/**
 * Función para redimensionar imagen
 */
function redimensionarImagen($origen, $destino, $ancho_max, $alto_max) {
    $info = getimagesize($origen);
    if (!$info) return false;
    
    $ancho_orig = $info[0];
    $alto_orig = $info[1];
    $tipo = $info[2];
    
    // Crear imagen desde el archivo original
    switch ($tipo) {
        case IMAGETYPE_JPEG:
            $imagen_orig = imagecreatefromjpeg($origen);
            break;
        case IMAGETYPE_PNG:
            $imagen_orig = imagecreatefrompng($origen);
            break;
        case IMAGETYPE_GIF:
            $imagen_orig = imagecreatefromgif($origen);
            break;
        case IMAGETYPE_WEBP:
            $imagen_orig = imagecreatefromwebp($origen);
            break;
        default:
            return false;
    }
    
    if (!$imagen_orig) return false;
    
    // Calcular proporciones para mantener aspecto
    $ratio = min($ancho_max / $ancho_orig, $alto_max / $alto_orig);
    $ancho_nuevo = round($ancho_orig * $ratio);
    $alto_nuevo = round($alto_orig * $ratio);
    
    // Crear nueva imagen
    $imagen_nueva = imagecreatetruecolor($ancho_max, $alto_max);
    
    // Fondo blanco
    $blanco = imagecolorallocate($imagen_nueva, 255, 255, 255);
    imagefill($imagen_nueva, 0, 0, $blanco);
    
    // Centrar imagen
    $x = ($ancho_max - $ancho_nuevo) / 2;
    $y = ($alto_max - $alto_nuevo) / 2;
    
    // Redimensionar
    imagecopyresampled(
        $imagen_nueva, $imagen_orig,
        $x, $y, 0, 0,
        $ancho_nuevo, $alto_nuevo,
        $ancho_orig, $alto_orig
    );
    
    // Guardar como JPEG
    $resultado = imagejpeg($imagen_nueva, $destino, 85);
    
    // Limpiar memoria
    imagedestroy($imagen_orig);
    imagedestroy($imagen_nueva);
    
    return $resultado;
}
?>
