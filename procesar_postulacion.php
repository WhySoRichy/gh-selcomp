<?php
session_start();
require_once 'conexion/conexion.php';
require_once 'administrador/csrf_protection.php';

date_default_timezone_set('America/Bogota');

// Validar token CSRF
if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
    $_SESSION["titulo"] = "Error de Seguridad";
    $_SESSION["mensaje"] = "Token de seguridad inválido. Por favor, recarga la página e intenta de nuevo.";
    $_SESSION["tipo_alerta"] = "error";
    header("Location: postulacion.php");
    exit;
}

// Configuración de validación por tipo de documento
$configDocumentos = [
    'CC' => ['min' => 6, 'max' => 10, 'soloNumeros' => true, 'nombre' => 'Cédula de ciudadanía'],
    'CE' => ['min' => 6, 'max' => 12, 'soloNumeros' => false, 'nombre' => 'Cédula de extranjería'],
    'TI' => ['min' => 10, 'max' => 11, 'soloNumeros' => true, 'nombre' => 'Tarjeta de identidad'],
    'Pasaporte' => ['min' => 5, 'max' => 15, 'soloNumeros' => false, 'nombre' => 'Pasaporte']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibe datos del formulario
    $nombre = mb_strtoupper(trim($_POST['nombre'] ?? ''), 'UTF-8');
    $tipoDocumento = trim($_POST['tipoDocumento'] ?? '');
    $numeroDocumento = strtoupper(trim($_POST['numeroDocumento'] ?? ''));
    $correo = mb_strtolower(trim($_POST['correo'] ?? ''));
    $vacanteId = (int)($_POST['vacante'] ?? 0);
    $archivo = $_FILES["archivo"] ?? null;

    // Validaciones básicas
    if (!$nombre || !$tipoDocumento || !$numeroDocumento || !$correo || !$vacanteId || !$archivo) {
        $_SESSION["titulo"] = "Error";
        $_SESSION["mensaje"] = "Faltan datos obligatorios en la postulación.";
        $_SESSION["tipo_alerta"] = "error";
        header("Location: postulacion.php");
        exit;
    }

    // Validar tipo de documento permitido
    if (!array_key_exists($tipoDocumento, $configDocumentos)) {
        $_SESSION["titulo"] = "Error";
        $_SESSION["mensaje"] = "Tipo de documento no válido.";
        $_SESSION["tipo_alerta"] = "error";
        header("Location: postulacion.php");
        exit;
    }

    $config = $configDocumentos[$tipoDocumento];

    // Validar longitud del número de documento
    $longitud = strlen($numeroDocumento);
    if ($longitud < $config['min'] || $longitud > $config['max']) {
        $_SESSION["titulo"] = "Error";
        $_SESSION["mensaje"] = "El {$config['nombre']} debe tener entre {$config['min']} y {$config['max']} caracteres.";
        $_SESSION["tipo_alerta"] = "error";
        header("Location: postulacion.php");
        exit;
    }

    // Validar formato del número de documento
    if ($config['soloNumeros']) {
        if (!preg_match('/^[0-9]+$/', $numeroDocumento)) {
            $_SESSION["titulo"] = "Error";
            $_SESSION["mensaje"] = "El {$config['nombre']} solo puede contener números.";
            $_SESSION["tipo_alerta"] = "error";
            header("Location: postulacion.php");
            exit;
        }
    } else {
        if (!preg_match('/^[A-Za-z0-9]+$/', $numeroDocumento)) {
            $_SESSION["titulo"] = "Error";
            $_SESSION["mensaje"] = "El {$config['nombre']} solo puede contener letras y números.";
            $_SESSION["tipo_alerta"] = "error";
            header("Location: postulacion.php");
            exit;
        }
    }

    // Validar nombre (solo letras, espacios y caracteres válidos)
    if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s\'-]+$/u', $nombre)) {
        $_SESSION["titulo"] = "Error";
        $_SESSION["mensaje"] = "El nombre contiene caracteres no permitidos.";
        $_SESSION["tipo_alerta"] = "error";
        header("Location: postulacion.php");
        exit;
    }

    if (strlen($nombre) < 3 || strlen($nombre) > 255) {
        $_SESSION["titulo"] = "Error";
        $_SESSION["mensaje"] = "El nombre debe tener entre 3 y 255 caracteres.";
        $_SESSION["tipo_alerta"] = "error";
        header("Location: postulacion.php");
        exit;
    }

    // Validar correo electrónico
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["titulo"] = "Error";
        $_SESSION["mensaje"] = "El correo electrónico no es válido.";
        $_SESSION["tipo_alerta"] = "error";
        header("Location: postulacion.php");
        exit;
    }

    // Validar longitud del correo
    if (strlen($correo) > 255) {
        $_SESSION["titulo"] = "Error";
        $_SESSION["mensaje"] = "El correo electrónico es demasiado largo.";
        $_SESSION["tipo_alerta"] = "error";
        header("Location: postulacion.php");
        exit;
    }

    // Validar que la vacante existe
    try {
        $stmtVacante = $conexion->prepare("SELECT id FROM vacantes WHERE id = :id");
        $stmtVacante->execute(['id' => $vacanteId]);
        if (!$stmtVacante->fetch()) {
            $_SESSION["titulo"] = "Error";
            $_SESSION["mensaje"] = "La vacante seleccionada no existe.";
            $_SESSION["tipo_alerta"] = "error";
            header("Location: postulacion.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION["titulo"] = "Error";
        $_SESSION["mensaje"] = "Error al validar la vacante.";
        $_SESSION["tipo_alerta"] = "error";
        header("Location: postulacion.php");
        exit;
    }

    // Validar el archivo PDF
    if ($archivo["error"] !== UPLOAD_ERR_OK) {
        $_SESSION["titulo"] = "Error";
        $_SESSION["mensaje"] = "No se ha seleccionado ningún archivo válido.";
        $_SESSION["tipo_alerta"] = "error";
        header("Location: postulacion.php");
        exit;
    }
    $extension = strtolower(pathinfo($archivo["name"], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $archivo["tmp_name"]);
    finfo_close($finfo);
    if ($extension !== 'pdf' || $mime !== 'application/pdf') {
        $_SESSION["titulo"] = "Error";
        $_SESSION["mensaje"] = "Solo se permiten archivos PDF.";
        $_SESSION["tipo_alerta"] = "error";
        header("Location: postulacion.php");
        exit;
    }
    if ($archivo["size"] > 10 * 1024 * 1024) {
        $_SESSION["titulo"] = "Archivo demasiado grande";
        $_SESSION["mensaje"] = "El archivo no debe superar los 10MB.";
        $_SESSION["tipo_alerta"] = "warning";
        header("Location: postulacion.php");
        exit;
    }

    // Guardar el archivo PDF en la carpeta "Documentos/Postulaciones"
    $baseDir = __DIR__; // Directorio base del proyecto
    $carpetaPostulaciones = $baseDir . DIRECTORY_SEPARATOR . "Documentos" . DIRECTORY_SEPARATOR . "Postulaciones";
    
    if (!is_dir($carpetaPostulaciones)) {
        if (!mkdir($carpetaPostulaciones, 0775, true)) {
            $_SESSION["titulo"] = "Error";
            $_SESSION["mensaje"] = "No se pudo crear la carpeta de destino.";
            $_SESSION["tipo_alerta"] = "error";
            header("Location: postulacion.php");
            exit;
        }
    }
    
    // Generar nombre de archivo con formato HV-PRIMERNOMBRE
    // Obtener solo el primer nombre (primera palabra)
    $palabrasNombre = explode(' ', $nombre);
    $primerNombre = $palabrasNombre[0];
    // Limpiar caracteres especiales del nombre para el archivo
    $primerNombreLimpio = preg_replace('/[^A-Za-z0-9]/', '', $primerNombre);
    $nombre_archivo = 'HV-' . $primerNombreLimpio . '.pdf';
    
    // Si ya existe un archivo con el mismo nombre, agregar un sufijo numérico
    $contador = 1;
    $nombre_base = 'HV-' . $primerNombreLimpio;
    while (file_exists($carpetaPostulaciones . DIRECTORY_SEPARATOR . $nombre_archivo)) {
        $nombre_archivo = $nombre_base . '_' . $contador . '.pdf';
        $contador++;
    }
    
    $ruta_completa = $carpetaPostulaciones . DIRECTORY_SEPARATOR . $nombre_archivo;
    $ruta_destino = "Documentos/Postulaciones/" . $nombre_archivo; // Ruta relativa para BD
    
    if (!move_uploaded_file($archivo["tmp_name"], $ruta_completa)) {
        $_SESSION["titulo"] = "Error";
        $_SESSION["mensaje"] = "Error al guardar el archivo en el servidor.";
        $_SESSION["tipo_alerta"] = "error";
        header("Location: postulacion.php");
        exit;
    }

    // Insertar en la base de datos
    try {
        $conexion->beginTransaction();
        
        // Insertar en tabla postulaciones
        $query = "INSERT INTO postulaciones (nombre, tipo_documento, numero_documento, correo, archivo, vacante_id)
                  VALUES (:nombre, :tipo_documento, :numero_documento, :correo, :archivo, :vacante_id)";
        $stmt = $conexion->prepare($query);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':tipo_documento', $tipoDocumento);
        $stmt->bindParam(':numero_documento', $numeroDocumento);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':archivo', $ruta_destino);
        $stmt->bindParam(':vacante_id', $vacanteId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Obtener título de la vacante para el Excel
        $stmtVacanteInfo = $conexion->prepare("SELECT titulo FROM vacantes WHERE id = :id");
        $stmtVacanteInfo->execute(['id' => $vacanteId]);
        $vacanteInfo = $stmtVacanteInfo->fetch(PDO::FETCH_ASSOC);
        $vacanteTitulo = $vacanteInfo['titulo'] ?? '';
        
        $conexion->commit();
        
        // ==========================================
        // PROCESAMIENTO AUTOMÁTICO CON GEMINI AI
        // Extraer datos de la HV y agregarlos al Excel
        // ==========================================
        try {
            require_once __DIR__ . '/Excel/procesar_hv_async.php';
            // Ejecutar en segundo plano para no hacer esperar al usuario
            procesarHojaDeVidaAsync($ruta_completa, $vacanteTitulo);
        } catch (Exception $e) {
            // Si falla el procesamiento de IA, no afecta la postulación
            error_log("Error al procesar HV con IA: " . $e->getMessage());
        }
        
        header("Location: gracias.php");
        exit;
        
    } catch (PDOException $e) {
        $conexion->rollBack();
        // Si la clave única falla (ya existe esa persona para esa vacante)
        if ($e->getCode() === '23000') {
            $_SESSION["titulo"] = "Atención";
            $_SESSION["mensaje"] = "Ya existe una postulación registrada con ese número de documento para la vacante seleccionada.";
            $_SESSION["tipo_alerta"] = "warning";
        } else {
            // Registrar error en log pero no exponer detalles al usuario
            error_log("Error en postulación: " . $e->getMessage());
            $_SESSION["titulo"] = "Error";
            $_SESSION["mensaje"] = "Ocurrió un error al procesar tu postulación. Por favor intenta nuevamente.";
            $_SESSION["tipo_alerta"] = "error";
        }
        header("Location: postulacion.php");
        exit;
    }
}
?>
