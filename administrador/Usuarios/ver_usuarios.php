<?php
session_start();
include '../auth.php';
include '../csrf_protection.php';
require_once(__DIR__ . '/../../conexion/conexion.php');

// Obtener usuarios y estadísticas
try {
    $sql = "SELECT id, nombre, apellido, email, rol, cargo, area FROM usuarios ORDER BY id DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas
    $totalUsuarios = count($usuarios);
    $totalAdmins = count(array_filter($usuarios, function($u) { return $u['rol'] === 'admin'; }));
    $totalUsuariosRegulares = $totalUsuarios - $totalAdmins;
    
} catch (Exception $e) {
    die("Error al obtener usuarios: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Sistema de Gestión</title>
    <link rel="icon" href="/gh/Img/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar.css">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/usuarios.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="layout-admin">
    <?php include(__DIR__ . '/../Modulos/navbar.php'); ?>

    <main class="contenido-principal">
        <!-- Header universal -->
        <div class="header-universal">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-users"></i>
                    <h1>Usuarios del Sistema</h1>
                </div>
                <div class="header-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?= $totalUsuarios ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $totalAdmins ?></span>
                        <span class="stat-label">Admins</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $totalUsuariosRegulares ?></span>
                        <span class="stat-label">Usuarios</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra de acciones -->
        <div class="actions-bar">
            <div class="actions-left">
                <h2 class="section-title">Gestión de Usuarios</h2>
            </div>
            <div class="actions-right">
                <a href="agregar_usuario.php" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Agregar Usuario
                </a>
                <a href="eliminar_usuario.php" class="btn-secondary">
                    <i class="fas fa-trash"></i> Eliminar Usuarios
                </a>
            </div>
        </div>

        <!-- Tabla de usuarios -->
        <div class="usuarios-container">
            <?php if (empty($usuarios)): ?>
                <div class="no-usuarios">
                    <div class="no-content-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>No hay usuarios registrados</h3>
                    <p>Agrega nuevos usuarios para comenzar a gestionarlos</p>
                    <a href="agregar_usuario.php" class="btn-primary">Agregar Usuario</a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="usuarios-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Cargo</th>
                                <th>Área</th>
                                <th>Rol</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr id="usuario-<?= $usuario['id'] ?>">
                                    <td class="user-id">#<?= $usuario['id'] ?></td>
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
                                                <div class="user-name"><?= htmlspecialchars($usuario['nombre']) ?> <?= htmlspecialchars($usuario['apellido']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="user-email"><?= htmlspecialchars($usuario['email']) ?></td>
                                    <td class="user-cargo"><?= htmlspecialchars($usuario['cargo'] ?? '-') ?></td>
                                    <td class="user-area"><?= htmlspecialchars($usuario['area'] ?? '-') ?></td>
                                    <td>
                                        <span class="role-badge <?= $usuario['rol'] === 'admin' ? 'admin' : 'user' ?>">
                                            <?= $usuario['rol'] === 'admin' ? 'Administrador' : 'Usuario' ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="btn-edit" onclick="toggleFormulario(<?= $usuario['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr id="form-row-<?= $usuario['id'] ?>" class="edit-form-row" style="display: none;">
                                    <td colspan="7">
                                        <div class="edit-form-container">
                                            <form method="POST" action="actualizar_usuario.php" class="form-actualizar">
                                                <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                                <?= campo_csrf_token() ?>
                                                
                                                <div class="form-grid">
                                                    <div class="form-group">
                                                        <label>Nombre</label>
                                                        <input type="text" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Apellido</label>
                                                        <input type="text" name="apellido" value="<?= htmlspecialchars($usuario['apellido']) ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Email</label>
                                                        <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Cargo</label>
                                                        <input type="text" name="cargo" value="<?= htmlspecialchars($usuario['cargo'] ?? '') ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Área</label>
                                                        <input type="text" name="area" value="<?= htmlspecialchars($usuario['area'] ?? '') ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label>Rol</label>
                                                        <select name="rol">
                                                            <option value="usuario" <?= $usuario['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                                                            <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div class="form-actions">
                                                    <button type="submit" class="btn-save">
                                                        <i class="fas fa-save"></i> Guardar
                                                    </button>
                                                    <button type="button" class="btn-cancel" onclick="toggleFormulario(<?= $usuario['id'] ?>)">
                                                        <i class="fas fa-times"></i> Cancelar
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function toggleFormulario(id) {
    const formRow = document.getElementById('form-row-' + id);
    const isVisible = formRow.style.display !== 'none';
    
    // Ocultar todas las filas de formulario
    document.querySelectorAll('.edit-form-row').forEach(row => {
        row.style.display = 'none';
    });
    
    // Mostrar/ocultar la fila del formulario actual
    if (!isVisible) {
        formRow.style.display = 'table-row';
        formRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

<?php if (isset($_SESSION['error_usuario'])): ?>
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: <?= json_encode($_SESSION['error_usuario']) ?>,
    confirmButtonColor: '#eb0045'
});
<?php unset($_SESSION['error_usuario']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['exito_usuario'])): ?>
Swal.fire({
    icon: 'success',
    title: '¡Éxito!',
    text: <?= json_encode($_SESSION['exito_usuario']) ?>,
    confirmButtonColor: '#eb0045'
});
<?php unset($_SESSION['exito_usuario']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['mensaje'])): ?>
Swal.fire({
    icon: <?= json_encode($_SESSION['tipo_alerta'] ?? 'info') ?>,
    title: <?= json_encode($_SESSION['titulo'] ?? 'Notificación') ?>,
    text: <?= json_encode($_SESSION['mensaje']) ?>,
    confirmButtonColor: '#eb0045'
});
<?php 
    unset($_SESSION['titulo']); 
    unset($_SESSION['mensaje']); 
    unset($_SESSION['tipo_alerta']); 
?>
<?php endif; ?>
</script>
</body>
</html>