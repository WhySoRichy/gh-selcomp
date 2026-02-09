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
if (basename($_SERVER['PHP_SELF']) === 'navbar_usuario.php') {
    header('Location: ../index.php');
    exit;
}
?>

<div class="main-topbar">
  <div class="topbar-center">
    <img src="/gh/Img/Selcomp Logo.png" alt="Logo Selcomp" class="topbar-logo">
    <div class="topbar-title">Portal de Usuario - Gestión Humana</div>
  </div>
  <div class="topbar-user">
    <button class="dropdown-toggle">
      <img src="/gh/Img/user-registro.png" alt="Usuario">
    </button>
    <div class="dropdown-menu">
      <a href="/gh/usuario/perfil.php">Mi perfil</a>
      <a href="/gh/usuario/seguridad.php">Seguridad</a>
      <a href="/gh/cerrar_sesion.php">Cerrar sesión</a>
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
            <button class="inicio-btn" onclick="window.location.href='/gh/usuario/index.php'">Inicio</button>
            <button class="menu-btn" onclick="window.location.href='/gh/usuario/perfil.php'">Mi Perfil</button>
            <button class="menu-btn" onclick="window.location.href='/gh/usuario/vacantes.php'">Vacantes</button>
            <button class="menu-btn" onclick="window.location.href='/gh/usuario/documentos.php'">Mis Documentos</button>
            <button class="menu-btn" onclick="window.location.href='/gh/usuario/notificaciones.php'">Notificaciones</button>
            <button class="menu-btn" onclick="window.location.href='/gh/usuario/seguridad.php'">Seguridad</button>
        </nav>
        
        <!-- FOOTER DEL SIDEBAR -->
        <div class="sidebar-footer">
            <button class="back-btn" onclick="navegarAtras()">
                Atrás
            </button>
            
            <div class="sidebar-user">
                <div class="user-details">
                    <p>
                        <?= htmlspecialchars($nombre_completo) ?><br>
                        <span><?= htmlspecialchars($cargo) ?> | <?= htmlspecialchars($area) ?></span>
                    </p>
                </div>
            </div>
        </div>
    </aside>

<script>
function navegarAtras() {
    const currentPath = window.location.pathname;
    if (currentPath.includes('/usuario/index.php')) return;

    const referrer = document.referrer;
    if (referrer && referrer.includes('/gh/usuario') && !referrer.includes('index.php') && referrer !== window.location.href) {
        window.history.back();
        return;
    }

    window.location.href = '/gh/usuario/index.php';
}

window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});
</script>
<script src="/gh/js/navigation.js" defer></script>
