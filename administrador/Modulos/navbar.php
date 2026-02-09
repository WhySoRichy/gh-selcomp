<?php 
// Protección contra acceso directo
if (!isset($_SESSION)) {
    session_start();
}

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit;
}

// Verificar que este archivo no se esté accediendo directamente
if (basename($_SERVER['PHP_SELF']) === 'navbar.php') {
    header('Location: ../index.php');
    exit;
}

include_once __DIR__ . '/../navegacion.php'; 
?>
<link rel="stylesheet" href="/gh/Css/navbar.css">
<link rel="icon" href="/gh/Img/logo.png">

<div class="main-topbar">
  <div class="topbar-left">
    <img src="/gh/Img/Selcomp Logo.png" alt="Logo Selcomp" class="topbar-logo"></div>
    <div class="topbar-title">Bienvenido al Portal de Gestión Humana</div>
  <div class="topbar-user">
    <button class="dropdown-toggle">
      <img src="/gh/Img/user-registro.png" alt="Usuario">
    </button>
    <div class="dropdown-menu">
      <a href="/gh/administrador/mostrar_perfil.php">Mi perfil</a>
      <a href="/gh/administrador/cambiar_contraseña.php">Cambiar Contraseña</a>
      <a href="/gh/administrador/cerrar_sesion_admin.php">Cerrar sesión</a>
    </div>
  </div>
</div>

<div class="layout-admin">
    <!-- Sidebar lateral -->
    <aside class="sidebar-navbar">
<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/gh/conexion/conexion.php";
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$usuario_id = $_SESSION['usuario_id'] ?? null;

$nombre_completo = "Usuario";
$cargo = "";
$area = "";

if ($usuario_id) {
    $sql = "SELECT nombre, apellido, cargo, area FROM usuarios WHERE id = :id";
    if ($stmt = $conexion->prepare($sql)) {
        $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $nombre_completo = $row['nombre'] . ' ' . $row['apellido'];
            $cargo = $row['cargo'];
            $area = $row['area'];
        }
    }
}
?>
        <!-- MENÚ -->
        <nav class="sidebar-menu">
            <button class="inicio-btn" onclick="window.location.href='/gh/administrador/index.php'">Inicio</button>
            <button class="menu-btn" data-toggle="submenu-usuarios">Usuarios</button>
            <div class="submenu" id="submenu-usuarios">
                <button class="submenu-btn" onclick="window.location.href='/gh/administrador/Usuarios/ver_usuarios.php'">Ver Usuarios</button>
                <button class="submenu-btn" onclick="window.location.href='/gh/administrador/Usuarios/agregar_usuario.php'">Agregar Usuario</button>
                <button class="submenu-btn" onclick="window.location.href='/gh/administrador/Usuarios/eliminar_usuario.php'">Eliminar Usuario</button>
            </div>
            <button class="menu-btn" data-toggle="submenu-vacantes">Vacantes</button>
            <div class="submenu" id="submenu-vacantes">
                <button class="submenu-btn" onclick="window.location.href='/gh/administrador/Vacantes/ver_vacantes.php'">Ver Vacantes</button>
                <button class="submenu-btn" onclick="window.location.href='/gh/administrador/Vacantes/agregar_vacante.php'">Agregar Vacante</button>
                <button class="submenu-btn" onclick="window.location.href='/gh/administrador/Vacantes/eliminar_vacante.php'">Eliminar Vacante</button>
            </div>
            <button class="menu-btn" data-toggle="submenu-archivo">Archivo</button>
            <div class="submenu" id="submenu-archivo">
                <button class="submenu-btn" onclick="window.location.href='/gh/administrador/Archivos/archivo.php'">Ver Documentos</button>
            </div>
            <button class="menu-btn" onclick="window.location.href='/gh/notificaciones/'">Notificaciones</button>
            <button class="menu-btn" onclick="window.location.href='/gh/administrador/seguridad.php'">Seguridad</button>
            <div class="sidebar-user">
    <div class="user-details">
        <p>
            <?= htmlspecialchars($nombre_completo) ?><br>
            <span><?= htmlspecialchars($cargo) ?> | <?= htmlspecialchars($area) ?></span>
        </p>
        <button class="back-btn" onclick="navegarAtras()">
            <!-- SVG -->
            Atrás
        </button>
    </div>
</div>
        </nav>
    </aside>

<script>
function navegarAtras() {
    const currentPath = window.location.pathname;
    if (currentPath.includes('/administrador/index.php')) return;

    const referrer = document.referrer;
    if (referrer && referrer.includes('/gh/administrador') && !referrer.includes('index.php') && !referrer.includes('procesar_') && referrer !== window.location.href) {
        window.history.back();
        return;
    }

    fetch('/gh/administrador/obtener_pagina_anterior.php')
        .then(response => response.text())
        .then(url => {
            if (url && url.trim() !== '' && url.trim() !== currentPath) {
                window.location.href = url.trim();
            } else {
                window.location.href = '/gh/administrador/index.php';
            }
        })
        .catch(() => {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '/gh/administrador/index.php';
            }
        });
}

window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});
</script>
<script src="/gh/js/navigation.js" defer></script>
