<?php
session_start();
include '../auth.php';
include '../csrf_protection.php';
require_once "../../conexion/conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $id = $_POST['id'] ?? null;
    if ($id) {
        try {
            // Evitar que el usuario se elimine a sí mismo
            if ($id == $_SESSION['usuario_id']) {
                $_SESSION['titulo'] = 'Error';
                $_SESSION['mensaje'] = 'No puedes eliminarte a ti mismo';
                $_SESSION['tipo_alerta'] = 'error';
            } else {
                $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['titulo'] = 'Éxito';
                    $_SESSION['mensaje'] = 'Usuario eliminado correctamente';
                    $_SESSION['tipo_alerta'] = 'success';
                } else {
                    $_SESSION['titulo'] = 'Error';
                    $_SESSION['mensaje'] = 'El usuario no existe o ya fue eliminado';
                    $_SESSION['tipo_alerta'] = 'error';
                }
            }
        } catch (PDOException $e) {
            error_log('Error al eliminar usuario: ' . $e->getMessage());
            $_SESSION['titulo'] = 'Error';
            $_SESSION['mensaje'] = 'Error interno al eliminar el usuario. Contacte al administrador.';
            $_SESSION['tipo_alerta'] = 'error';
        }
    }
}

try {
    $stmt = $conexion->query("SELECT id, nombre, email, rol FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error al obtener usuarios: ' . $e->getMessage());
    die('Error interno al cargar los datos.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Usuario</title>
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
        <!-- Header universal de peligro -->
        <div class="header-universal danger">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h1>Eliminar Usuario</h1>
                </div>
                <div class="header-info">
                    <span class="info-text">Selecciona un usuario para eliminarlo permanentemente</span>
                </div>
            </div>
        </div>

        <div class="danger-container-professional">
            <?php if (empty($usuarios)): ?>
                <div class="empty-users-professional">
                    <div class="empty-icon-professional">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>No hay usuarios disponibles</h3>
                    <p>No se encontraron usuarios en el sistema para eliminar.</p>
                    <a href="ver_usuarios.php" class="btn-primary">
                        <i class="fas fa-users"></i> Ver Usuarios
                    </a>
                </div>
            <?php else: ?>
                <div class="warning-professional">
                    <div class="warning-content-professional">
                        <i class="fas fa-exclamation-triangle warning-icon-professional"></i>
                        <div>
                            <strong>¡Atención!</strong>
                            <p>La eliminación de usuarios es una acción permanente e irreversible. Asegúrate de seleccionar correctamente.</p>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="usuarios-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr class="user-row-danger">
                                <td class="user-id">#<?= htmlspecialchars($usuario['id']) ?></td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php if ($usuario['rol'] === 'admin'): ?>
                                                <i class="fas fa-crown"></i>
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="user-email"><?= htmlspecialchars($usuario['email']) ?></td>
                                <td>
                                    <span class="role-badge <?= $usuario['rol'] === 'admin' ? 'admin' : 'user' ?>">
                                        <?= $usuario['rol'] === 'admin' ? 'Administrador' : 'Usuario' ?>
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <form method="post" class="delete-form-professional" onsubmit="return confirmarEliminacion('<?= htmlspecialchars($usuario['nombre']) ?>')">
                                        <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                        <?php echo campo_csrf_token(); ?>
                                        <button type="submit" class="btn-delete-professional">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php require_once '../../mensaje_alerta.php'; ?>
        </div>
    </main>
</div>

<script>
function confirmarEliminacion(nombre) {
    return confirm(`¿Estás seguro de que deseas eliminar al usuario "${nombre}"?\n\nEsta acción no se puede deshacer.`);
}
</script>
</body>
</html>
