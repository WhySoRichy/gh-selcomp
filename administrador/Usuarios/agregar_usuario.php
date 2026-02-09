<?php
session_start();
include '../auth.php';
include '../csrf_protection.php';
require_once "../../conexion/conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $rol = trim($_POST['rol'] ?? 'usuario');

    if ($nombre && $apellido && $email && $contrasena) {
        try {
            // Verificar si el email ya existe
            $stmtCheck = $conexion->prepare("SELECT id FROM usuarios WHERE email = :email");
            $stmtCheck->bindParam(':email', $email);
            $stmtCheck->execute();
            
            if ($stmtCheck->rowCount() > 0) {
                $_SESSION['titulo'] = 'Error';
                $_SESSION['mensaje'] = "El correo electrónico '$email' ya está registrado.";
                $_SESSION['tipo_alerta'] = 'error';
            } else {
                $hash = password_hash($contrasena, PASSWORD_DEFAULT);
                $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, apellido, email, password_hash, cargo, area, rol, fecha_creacion, fecha_actualizacion) 
                                            VALUES (:nombre, :apellido, :email, :password_hash, :cargo, :area, :rol, NOW(), NOW())");
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':apellido', $apellido);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password_hash', $hash);
                $stmt->bindParam(':cargo', $cargo);
                $stmt->bindParam(':area', $area);
                $stmt->bindParam(':rol', $rol);
                $stmt->execute();

                $_SESSION['titulo'] = 'Éxito';
                $_SESSION['mensaje'] = 'Usuario agregado correctamente';
                $_SESSION['tipo_alerta'] = 'success';
                header("Location: ver_usuarios.php");
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['titulo'] = 'Error';
            $_SESSION['mensaje'] = 'Error al crear el usuario: ' . $e->getMessage();
            $_SESSION['tipo_alerta'] = 'error';
        }
    } else {
        $_SESSION['titulo'] = 'Error';
        $_SESSION['mensaje'] = 'Todos los campos obligatorios deben estar completos';
        $_SESSION['tipo_alerta'] = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Usuario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar.css">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/usuarios.css">
    <link rel="icon" href="/gh/Img/logo.png">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="layout-admin">
    <?php include("../Modulos/navbar.php"); ?>
    <main class="contenido-principal">
        <!-- Header universal -->
        <div class="header-universal">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-user-plus"></i>
                    <h1>Agregar Usuario</h1>
                </div>
                <div class="header-info">
                    <span class="info-text">Crear un nuevo usuario en el sistema</span>
                </div>
            </div>
        </div>

        <div class="form-container-professional">
            <form method="post" class="form-usuario-professional">
                <?php echo campo_csrf_token(); ?>
                
                <div class="form-section-professional">
                    <h3 class="section-title-professional">
                        <i class="fas fa-user"></i> Información Personal
                    </h3>
                    <div class="form-grid-professional">
                        <div class="input-group-professional">
                            <label>Nombre</label>
                            <input type="text" name="nombre" placeholder="Ingresa el nombre" required>
                        </div>
                        <div class="input-group-professional">
                            <label>Apellido</label>
                            <input type="text" name="apellido" placeholder="Ingresa el apellido" required>
                        </div>
                    </div>
                </div>

                <div class="form-section-professional">
                    <h3 class="section-title-professional">
                        <i class="fas fa-key"></i> Información de Acceso
                    </h3>
                    <div class="input-group-professional">
                        <label>Correo Electrónico</label>
                        <input type="email" name="email" placeholder="ejemplo@empresa.com" required>
                    </div>
                    <div class="input-group-professional">
                        <label>Contraseña</label>
                        <input type="password" name="contrasena" placeholder="Mínimo 8 caracteres" required>
                    </div>
                </div>

                <div class="form-section-professional">
                    <h3 class="section-title-professional">
                        <i class="fas fa-briefcase"></i> Información Laboral
                    </h3>
                    <div class="form-grid-professional">
                        <div class="input-group-professional">
                            <label>Cargo</label>
                            <input type="text" name="cargo" placeholder="Ej: Desarrollador, Analista">
                        </div>
                        <div class="input-group-professional">
                            <label>Área</label>
                            <input type="text" name="area" placeholder="Ej: Tecnología, Ventas">
                        </div>
                    </div>
                    <div class="input-group-professional">
                        <label>Rol del Usuario</label>
                        <select name="rol">
                            <option value="usuario">Usuario Regular</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>

                <div class="form-buttons-professional">
                    <button type="submit" class="btn-save-professional">
                        <i class="fas fa-save"></i> Guardar Usuario
                    </button>
                    <a href="ver_usuarios.php" class="btn-cancel-professional">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
            <?php require_once '../../mensaje_alerta.php'; ?>
        </div>
    </main>
</div>
</body>
</html>
