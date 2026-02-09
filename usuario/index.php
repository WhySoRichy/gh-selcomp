<?php
session_start();
include 'auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Usuario - Gestión Humana</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/usuarios.css">
    <link rel="stylesheet" href="/gh/Css/dashboard_usuario.css?v=<?= time() ?>">
    <link rel="icon" href="/gh/Img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include __DIR__ . "/Modulos/navbar_usuario.php"; ?>
    <main class="contenido-principal">
        <!-- Header universal -->
        <div class="header-universal">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-home"></i>
                    <h1>Dashboard Usuario</h1>
                </div>
                <div class="header-info">
                    <span class="info-text">Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></span>
                </div>
            </div>
        </div>

        <!-- Contenido principal del dashboard -->
        <div class="dashboard-container">
            <div class="dashboard-grid">
                <!-- Card de Perfil -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h3>Mi Perfil</h3>
                    </div>
                    <div class="card-content">
                        <p>Consulta y actualiza tu información personal, avatar y datos de contacto.</p>
                        <a href="perfil.php" class="btn-card">
                            <i class="fas fa-edit"></i>
                            Ver Mi Perfil
                        </a>
                    </div>
                </div>

                <!-- Card de Vacantes -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h3>Vacantes Disponibles</h3>
                    </div>
                    <div class="card-content">
                        <p>Explora las oportunidades laborales disponibles en la empresa.</p>
                        <a href="vacantes.php" class="btn-card">
                            <i class="fas fa-search"></i>
                            Ver Vacantes
                        </a>
                    </div>
                </div>

                <!-- Card de Documentos -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3>Mis Documentos</h3>
                    </div>
                    <div class="card-content">
                        <p>Accede a tus documentos personales, certificados y archivos importantes.</p>
                        <a href="documentos.php" class="btn-card">
                            <i class="fas fa-folder-open"></i>
                            Ver Documentos
                        </a>
                    </div>
                </div>

                <!-- Card de Notificaciones -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3>Notificaciones</h3>
                    </div>
                    <div class="card-content">
                        <p>Mantente al día con las notificaciones importantes del sistema.</p>
                        <a href="notificaciones.php" class="btn-card">
                            <i class="fas fa-bell"></i>
                            Ver Notificaciones
                        </a>
                    </div>
                </div>

                <!-- Card de Seguridad -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Seguridad</h3>
                    </div>
                    <div class="card-content">
                        <p>Administra la seguridad de tu cuenta y cambia tu contraseña.</p>
                        <a href="seguridad.php" class="btn-card">
                            <i class="fas fa-key"></i>
                            Configurar
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once '../mensaje_alerta.php'; ?>
    </main>
</body>
</html>
