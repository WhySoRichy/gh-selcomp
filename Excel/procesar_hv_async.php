<?php
/**
 * Procesador asíncrono de Hojas de Vida con Groq AI
 * Este script se ejecuta en segundo plano después de cada postulación
 * para extraer datos de la HV y agregarlos al Excel de Prospectos
 */

// Evitar timeout
set_time_limit(120);
ignore_user_abort(true);

// Configuración
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config.php';

// Ruta de Python: primero .env, luego buscar en el sistema
$pythonPath = config('PYTHON_PATH', '');
if (empty($pythonPath) || !file_exists($pythonPath)) {
    // Intentar encontrar Python automáticamente
    $pythonCheck = trim(shell_exec('where python 2>&1') ?? '');
    $firstLine = strtok($pythonCheck, "\n");
    if ($firstLine && file_exists($firstLine)) {
        $pythonPath = $firstLine;
    } else {
        $pythonPath = 'python'; // Último recurso: confiar en PATH
    }
}
$scriptPath = __DIR__ . '\\extractor_hv.py';
$logFile = __DIR__ . '\\procesar_hv.log';

/**
 * Registra un mensaje en el log
 */
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Procesa una hoja de vida de forma asíncrona
 * @param string $rutaPdf Ruta completa al archivo PDF
 * @param string $vacanteTitulo Título de la vacante
 * @return array Resultado del procesamiento
 */
function procesarHojaDeVida($rutaPdf, $vacanteTitulo = '') {
    global $pythonPath, $scriptPath;
    
    // Verificar que Python está disponible
    $pythonCheck = shell_exec('"' . $pythonPath . '" --version 2>&1');
    if (strpos($pythonCheck, 'Python') === false) {
        logMessage("ERROR: Python no encontrado en: $pythonPath");
        return ['success' => false, 'message' => 'Python no configurado en el servidor'];
    }
    logMessage("Python encontrado: " . trim($pythonCheck) . " en: $pythonPath");
    
    // Verificar que el script existe
    if (!file_exists($scriptPath)) {
        logMessage("ERROR: Script no encontrado en: $scriptPath");
        return ['success' => false, 'message' => 'Script de extracción no encontrado'];
    }
    
    // Verificar que el PDF existe
    if (!file_exists($rutaPdf)) {
        logMessage("ERROR: PDF no encontrado: $rutaPdf");
        return ['success' => false, 'message' => 'Archivo PDF no encontrado'];
    }
    
    logMessage("Iniciando procesamiento: $rutaPdf");
    
    // Construir comando
    $comando = sprintf(
        '"%s" "%s" --auto "%s" "%s" 2>&1',
        $pythonPath,
        $scriptPath,
        $rutaPdf,
        $vacanteTitulo
    );
    
    // Ejecutar
    $output = [];
    $returnCode = 0;
    exec($comando, $output, $returnCode);
    
    $outputStr = implode("\n", $output);
    
    if ($returnCode === 0) {
        logMessage("✅ Procesado exitosamente: $rutaPdf");
        // Intentar parsear la respuesta JSON
        $resultado = json_decode($outputStr, true);
        if ($resultado) {
            return $resultado;
        }
        return ['success' => true, 'message' => 'Procesado correctamente', 'output' => $outputStr];
    } else {
        logMessage("❌ Error procesando: $rutaPdf - Código: $returnCode - Output: $outputStr");
        return ['success' => false, 'message' => "Error en el procesamiento (código $returnCode)", 'output' => $outputStr];
    }
}

/**
 * Ejecuta el procesamiento en segundo plano (no bloqueante)
 * @param string $rutaPdf Ruta completa al archivo PDF
 * @param string $vacanteTitulo Título de la vacante
 */
function procesarHojaDeVidaAsync($rutaPdf, $vacanteTitulo = '') {
    global $pythonPath, $scriptPath, $logFile;
    
    // Escapar argumentos para Windows
    $rutaPdfEscaped = escapeshellarg($rutaPdf);
    $vacanteTituloEscaped = escapeshellarg($vacanteTitulo);
    $scriptPathEscaped = escapeshellarg($scriptPath);
    
    // Usar WMI para ejecutar proceso completamente independiente (no hereda handles)
    // Esto es la forma más confiable en Windows/IIS
    $comando = sprintf(
        '%s %s --auto %s %s',
        $pythonPath,
        $scriptPathEscaped,
        $rutaPdfEscaped,
        $vacanteTituloEscaped
    );
    
    logMessage("Lanzando proceso asíncrono: $rutaPdf");
    
    // Método 1: WScript.Shell (más confiable en IIS)
    if (class_exists('COM')) {
        try {
            $WshShell = new COM("WScript.Shell");
            $WshShell->Run($comando, 0, false); // 0 = hidden, false = no esperar
            logMessage("Proceso lanzado via COM");
            return true;
        } catch (Exception $e) {
            logMessage("COM falló: " . $e->getMessage() . " - intentando método alternativo");
        }
    }
    
    // Método 2: start /B con redirección a nul
    $comandoBackground = sprintf(
        'start /B cmd /C "%s > nul 2>&1"',
        $comando
    );
    
    // Ejecutar sin esperar - usando exec con output a null
    exec($comandoBackground . ' > nul 2>&1 &');
    
    logMessage("Proceso lanzado via exec");
    return true;
}

// Si se llama directamente desde CLI (para pruebas)
if (php_sapi_name() === 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'procesar_hv_async.php') {
    // Modo de prueba CLI
    if (isset($argv[1])) {
        $rutaPdf = $argv[1];
        $vacante = $argv[2] ?? 'Prueba CLI';
        
        echo "Procesando: $rutaPdf\n";
        echo "Vacante: $vacante\n";
        
        $resultado = procesarHojaDeVida($rutaPdf, $vacante);
        
        echo "\nResultado:\n";
        print_r($resultado);
    } else {
        echo "Uso: php procesar_hv_async.php <ruta_pdf> [vacante]\n";
        exit(1);
    }
}
