<?php
// Protección contra acceso directo
if (basename($_SERVER['PHP_SELF']) === 'navegacion.php') {
    header('HTTP/1.0 403 Forbidden');
    exit('Acceso directo no permitido');
}

/**
 * Sistema de navegación inteligente mejorado
 * Maneja el historial de navegación del usuario de forma correcta
 */

function registrar_pagina_actual() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $pagina_actual = $_SERVER['REQUEST_URI'];
    
    // No registrar páginas de procesamiento
    $paginas_excluidas = [
        'procesar_login.php',
        'cerrar_sesion.php',
        'actualizar_usuario.php',
        'eliminar_usuario.php',
        'agregar_usuario.php',
        'actualizar_vacantes.php',
        'eliminar_vacante.php',
        'agregar_vacante.php',
        'obtener_pagina_anterior.php'
    ];
    
    $es_pagina_excluida = false;
    foreach ($paginas_excluidas as $excluida) {
        if (strpos($pagina_actual, $excluida) !== false) {
            $es_pagina_excluida = true;
            break;
        }
    }
    
    // Solo registrar si no es una página excluida
    if (!$es_pagina_excluida) {
        // Guardar la página actual como anterior (para la próxima navegación)
        if (isset($_SESSION['pagina_actual']) && $_SESSION['pagina_actual'] !== $pagina_actual) {
            $_SESSION['pagina_anterior'] = $_SESSION['pagina_actual'];
        }
        $_SESSION['pagina_actual'] = $pagina_actual;
    }
}

function obtener_pagina_anterior_segura() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $pagina_anterior = $_SESSION['pagina_anterior'] ?? null;
    $pagina_actual = $_SESSION['pagina_actual'] ?? null;
    $pagina_default = '/gh/administrador/index.php';
    
    // Si estamos en el dashboard, no hacer nada (no hay atrás)
    if ($pagina_actual && strpos($pagina_actual, '/gh/administrador/index.php') !== false) {
        return '';
    }
    
    // Si no hay página anterior válida, ir al dashboard
    if (!$pagina_anterior) {
        return $pagina_default;
    }
    
    // Verificar que la página anterior sea del área administrativa
    if (strpos($pagina_anterior, '/gh/administrador') === false) {
        return $pagina_default;
    }
    
    // Evitar bucles (no volver a la misma página)
    if ($pagina_anterior === $pagina_actual) {
        return $pagina_default;
    }
    
    return $pagina_anterior;
}

function debug_navegacion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return [
        'actual' => $_SESSION['pagina_actual'] ?? 'null',
        'anterior' => $_SESSION['pagina_anterior'] ?? 'null',
        'sugerida' => obtener_pagina_anterior_segura()
    ];
}

// Registrar la página actual automáticamente
registrar_pagina_actual();
?>
