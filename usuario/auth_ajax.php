<?php
// Verificar que el usuario esté autenticado (para peticiones AJAX)
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

// Verificar que sea un usuario normal (no administrador)
if (isset($_SESSION['usuario_rol']) && ($_SESSION['usuario_rol'] === 'admin' || $_SESSION['usuario_rol'] === 'administrador')) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}
// No cerrar PHP para evitar espacios en blanco que rompan el JSON
