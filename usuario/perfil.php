<?php
session_start();
include 'auth.php';
include_once $_SERVER['DOCUMENT_ROOT'] . "/gh/conexion/conexion.php";

// Generar token CSRF para este formulario
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener datos del usuario
$usuario_id = $_SESSION['usuario_id'];

// Obtener datos actuales del usuario
$sql = "SELECT id, nombre, apellido, cargo, area, email, telefono, direccion, fecha_nacimiento, estado_civil, emergencia_contacto, emergencia_telefono, acerca_de_mi, avatar, fecha_creacion FROM usuarios WHERE id = :id";
$stmt = $conexion->prepare($sql);
$stmt->bindParam(':id', $usuario_id);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: /gh/cerrar_sesion.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Portal Usuario</title>
    <link rel="icon" href="/gh/Img/logo.png" type="image/png">
    <link rel="shortcut icon" href="/gh/Img/logo.png" type="image/png">
    <link rel="apple-touch-icon" href="/gh/Img/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/usuarios.css">
    <link rel="stylesheet" href="/gh/Css/dashboard_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/perfil_usuario.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/gh/js/perfil-usuario.js?v=<?= time() ?>" defer></script>
</head>
<body>
<?php include __DIR__ . "/Modulos/navbar_usuario.php"; ?>
    <main class="contenido-principal">
        <!-- Header universal -->
        <div class="header-universal">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-user-circle"></i>
                    <h1>Mi Perfil</h1>
                </div>
                <div class="header-info">
                    <span class="info-text">Gestiona tu información personal</span>
                </div>
            </div>
        </div>

        <!-- Contenido del perfil -->
        <div class="perfil-container">
            <!-- Información básica -->
            <div class="perfil-card">
                <div class="perfil-header">
                    <div class="perfil-avatar">
                        <?php 
                        $avatar_path = "/gh/Img/Avatars/user_" . $usuario_id . ".jpg";
                        $avatar_file = $_SERVER['DOCUMENT_ROOT'] . $avatar_path;
                        if (file_exists($avatar_file)): 
                        ?>
                            <img src="<?= $avatar_path ?>?v=<?= time() ?>" alt="Avatar de <?= htmlspecialchars($usuario['nombre']) ?>">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </div>
                    <div class="perfil-info">
                        <h2><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></h2>
                        <p class="perfil-cargo"><?= htmlspecialchars($usuario['cargo']) ?></p>
                        <p class="perfil-area"><?= htmlspecialchars($usuario['area']) ?></p>
                        <p class="perfil-email"><?= htmlspecialchars($usuario['email']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Formulario de perfil -->
            <div class="perfil-card">
                <div class="card-header">
                    <h3><i class="fas fa-edit"></i> Editar Información Personal</h3>
                </div>
                <div class="card-content">
                    <form class="perfil-form" id="perfil-form" enctype="multipart/form-data">
                        <!-- Token CSRF -->
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <!-- Sección de Avatar -->
                        <div class="form-section avatar-section">
                            <h4><i class="fas fa-camera"></i> Foto de Perfil</h4>
                            <div class="avatar-upload">
                                <div class="avatar-preview">
                                    <?php 
                                    $avatar_path = "/gh/Img/Avatars/user_" . $usuario_id . ".jpg";
                                    $avatar_file = $_SERVER['DOCUMENT_ROOT'] . $avatar_path;
                                    if (file_exists($avatar_file)): 
                                    ?>
                                        <img src="<?= $avatar_path ?>?v=<?= time() ?>" alt="Avatar" id="avatar-img">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle" id="avatar-placeholder"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="avatar-controls">
                                    <label for="avatar-upload" class="btn-upload">
                                        <i class="fas fa-camera"></i> Cambiar Foto
                                    </label>
                                    <input type="file" id="avatar-upload" name="avatar" accept="image/*" style="display: none;">
                                    <?php if (file_exists($avatar_file)): ?>
                                        <button type="button" class="btn-remove" id="remove-avatar">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <!-- Información básica -->
                            <div class="form-section">
                                <h4><i class="fas fa-user"></i> Información Básica</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="nombre">Nombre *</label>
                                        <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="apellido">Apellido *</label>
                                        <input type="text" id="apellido" name="apellido" value="<?= htmlspecialchars($usuario['apellido']) ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="telefono">Celular</label>
                                        <input type="tel" id="telefono" name="telefono" value="<?= htmlspecialchars($usuario['telefono']) ?>" placeholder="Ej: +57 300 123 4567">
                                    </div>
                                    <div class="form-group">
                                        <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= $usuario['fecha_nacimiento'] ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="direccion">Dirección</label>
                                    <input type="text" id="direccion" name="direccion" value="<?= htmlspecialchars($usuario['direccion']) ?>" placeholder="Dirección completa">
                                </div>
                                <div class="form-group">
                                    <label for="estado_civil">Estado Civil</label>
                                    <select id="estado_civil" name="estado_civil">
                                        <option value="">Seleccionar</option>
                                        <option value="Soltero" <?= $usuario['estado_civil'] === 'Soltero' ? 'selected' : '' ?>>Soltero</option>
                                        <option value="Casado" <?= $usuario['estado_civil'] === 'Casado' ? 'selected' : '' ?>>Casado</option>
                                        <option value="Divorciado" <?= $usuario['estado_civil'] === 'Divorciado' ? 'selected' : '' ?>>Divorciado</option>
                                        <option value="Viudo" <?= $usuario['estado_civil'] === 'Viudo' ? 'selected' : '' ?>>Viudo</option>
                                        <option value="Unión Libre" <?= $usuario['estado_civil'] === 'Unión Libre' ? 'selected' : '' ?>>Unión Libre</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Contacto de emergencia -->
                            <div class="form-section">
                                <h4><i class="fas fa-exclamation-triangle"></i> Contacto de Emergencia</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="emergencia_contacto">Nombre de Contacto</label>
                                        <input type="text" id="emergencia_contacto" name="emergencia_contacto" value="<?= htmlspecialchars($usuario['emergencia_contacto'] ?? '') ?>" placeholder="Nombre completo">
                                    </div>
                                    <div class="form-group">
                                        <label for="emergencia_telefono">Celular de Contacto</label>
                                        <input type="tel" id="emergencia_telefono" name="emergencia_telefono" value="<?= htmlspecialchars($usuario['emergencia_telefono'] ?? '') ?>" placeholder="Celular de emergencia">
                                    </div>
                                </div>
                            </div>

                            <!-- Acerca de mí -->
                            <div class="form-section">
                                <h4><i class="fas fa-user-edit"></i> Acerca de Mí</h4>
                                <div class="form-group">
                                    <label for="acerca_de_mi">Cuéntanos sobre ti</label>
                                    <textarea id="acerca_de_mi" name="acerca_de_mi" rows="4" maxlength="500" placeholder="Escribe algo sobre ti, tus intereses, experiencia, hobbies..."><?= htmlspecialchars($usuario['acerca_de_mi'] ?? '') ?></textarea>
                                    <small class="char-counter"><span id="acerca-count"><?= strlen($usuario['acerca_de_mi'] ?? '') ?></span>/500 caracteres</small>
                                </div>
                            </div>

                            <!-- Información de la empresa (solo lectura) -->
                            <div class="form-section readonly">
                                <h4><i class="fas fa-building"></i> Información de la Empresa</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Email Corporativo</label>
                                        <input type="email" value="<?= htmlspecialchars($usuario['email']) ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Cargo</label>
                                        <input type="text" value="<?= htmlspecialchars($usuario['cargo']) ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Área</label>
                                        <input type="text" value="<?= htmlspecialchars($usuario['area']) ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Fecha de Ingreso</label>
                                        <input type="text" value="<?= $usuario['fecha_creacion'] ? date('d/m/Y', strtotime($usuario['fecha_creacion'])) : 'N/A' ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                            <button type="button" class="btn-secondary" onclick="window.location.reload()">
                                <i class="fas fa-undo"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Solo mantener funciones necesarias para el formulario AJAX
        // Perfil cargado - Solo modo AJAX
    </script>

    <style>
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    </style>
</body>
</html>
