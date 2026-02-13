<?php
/**
 * Restablecer MFA de Usuarios
 * Permite al administrador ver el estado 2FA de todos los usuarios
 * y restablecer (desactivar) la verificación cuando sea necesario.
 * Para administradores: el 2FA se reactivará automáticamente en su próximo login.
 */
session_start();
include_once 'auth.php';
include_once 'csrf_protection.php';
require_once __DIR__ . '/../conexion/conexion.php';

// Solo administradores
if ($_SESSION['usuario_rol'] !== 'admin' && $_SESSION['usuario_rol'] !== 'administrador') {
    header('Location: ../index.php');
    exit;
}

// Procesar restablecimiento si se envía por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restablecer_usuario_id'])) {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['titulo'] = 'Error de seguridad';
        $_SESSION['mensaje'] = 'Token de seguridad inválido. Recarga la página.';
        $_SESSION['tipo_alerta'] = 'error';
        header('Location: restablecer_mfa.php');
        exit;
    }

    $usuario_objetivo_id = (int) $_POST['restablecer_usuario_id'];

    try {
        // Verificar que el usuario objetivo exista
        $stmt = $conexion->prepare("SELECT id, nombre, apellido, email, rol FROM usuarios WHERE id = :id");
        $stmt->bindParam(':id', $usuario_objetivo_id, PDO::PARAM_INT);
        $stmt->execute();
        $usuario_objetivo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario_objetivo) {
            $_SESSION['titulo'] = 'Error';
            $_SESSION['mensaje'] = 'El usuario no fue encontrado.';
            $_SESSION['tipo_alerta'] = 'error';
            header('Location: restablecer_mfa.php');
            exit;
        }

        // Desactivar 2FA del usuario
        $stmt = $conexion->prepare("UPDATE usuarios SET tiene_2fa = 0 WHERE id = :id");
        $stmt->bindParam(':id', $usuario_objetivo_id, PDO::PARAM_INT);
        $stmt->execute();

        // Invalidar todos los códigos 2FA pendientes del usuario
        $stmt = $conexion->prepare("UPDATE codigos_2fa SET usado = 1 WHERE usuario_id = :id AND usado = 0");
        $stmt->execute(['id' => $usuario_objetivo_id]);

        // Registrar en historial de accesos
        $detalles = 'MFA restablecido por administrador ' . htmlspecialchars($_SESSION['usuario_nombre']) .
                     ' para el usuario ' . htmlspecialchars($usuario_objetivo['nombre'] . ' ' . $usuario_objetivo['apellido']) .
                     ' (' . htmlspecialchars($usuario_objetivo['email']) . ')';
        $stmt_log = $conexion->prepare("INSERT INTO historial_accesos
            (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles)
            VALUES (?, NOW(), ?, ?, ?, 1, ?)");
        $stmt_log->execute([
            $_SESSION['usuario_id'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido',
            $detalles
        ]);

        $es_admin = ($usuario_objetivo['rol'] === 'admin' || $usuario_objetivo['rol'] === 'administrador');
        $nota_extra = $es_admin ? ' Como es administrador, el 2FA se reactivará automáticamente en su próximo inicio de sesión.' : '';

        $_SESSION['titulo'] = 'MFA Restablecido';
        $_SESSION['mensaje'] = 'Se ha restablecido el MFA de ' . $usuario_objetivo['nombre'] . ' ' . $usuario_objetivo['apellido'] . '.' . $nota_extra;
        $_SESSION['tipo_alerta'] = 'success';

    } catch (PDOException $e) {
        error_log("Error al restablecer MFA: " . $e->getMessage());
        $_SESSION['titulo'] = 'Error';
        $_SESSION['mensaje'] = 'No se pudo restablecer el MFA. Intenta nuevamente.';
        $_SESSION['tipo_alerta'] = 'error';
    }

    header('Location: restablecer_mfa.php');
    exit;
}

// Obtener todos los usuarios con su estado 2FA
try {
    $sql = "SELECT id, nombre, apellido, email, rol, tiene_2fa FROM usuarios ORDER BY rol DESC, nombre ASC";
    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($usuarios);
    $con_2fa = count(array_filter($usuarios, function($u) { return $u['tiene_2fa'] == 1; }));
    $sin_2fa = $total - $con_2fa;
} catch (Exception $e) {
    die("Error al obtener usuarios: " . $e->getMessage());
}

// Generar token CSRF
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer MFA - Panel Administrador</title>
    <link rel="icon" href="/gh/Img/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar.css">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/usuarios.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ===== Tabla MFA ===== */
        .mfa-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.04);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .mfa-table {
            width: 100%;
            border-collapse: collapse;
        }

        .mfa-table thead {
            background: linear-gradient(135deg, #404e62 0%, #2d3748 100%);
        }

        .mfa-table thead th {
            padding: 14px 20px;
            text-align: left;
            font-weight: 600;
            color: white;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border: none;
        }

        .mfa-table thead th:first-child {
            padding-left: 24px;
        }

        .mfa-table thead th:last-child {
            text-align: center;
        }

        .mfa-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s ease;
        }

        .mfa-table tbody tr:last-child {
            border-bottom: none;
        }

        .mfa-table tbody tr:hover {
            background: #f8fafc;
        }

        .mfa-table tbody td {
            padding: 16px 20px;
            vertical-align: middle;
            font-size: 0.875rem;
            color: #374151;
        }

        .mfa-table tbody td:first-child {
            padding-left: 24px;
        }

        .mfa-table tbody td:last-child {
            text-align: center;
        }

        /* Columna ID */
        .mfa-id {
            font-weight: 700;
            color: #9ca3af;
            font-size: 0.8rem;
        }

        /* Columna Usuario */
        .mfa-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .mfa-user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .mfa-user-avatar.admin-avatar {
            background: linear-gradient(135deg, #fce4ec, #ffeef3);
            color: #eb0045;
        }

        .mfa-user-avatar.user-avatar {
            background: linear-gradient(135deg, #f0f4f8, #e2e8f0);
            color: #607d8b;
        }

        .mfa-user-name {
            font-weight: 600;
            color: #1e293b;
        }

        /* Columna Email */
        .mfa-email {
            color: #64748b;
            font-family: 'Courier New', monospace;
            font-size: 0.82rem;
        }

        /* Badges de Rol */
        .mfa-role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .mfa-role-badge.role-admin {
            background: linear-gradient(135deg, #fce4ec, #ffeef3);
            color: #eb0045;
        }

        .mfa-role-badge.role-user {
            background: #f3f4f6;
            color: #6b7280;
        }

        /* Contenedor estado 2FA */
        .mfa-estado-container {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-start;
        }

        /* Badge principal de estado */
        .badge-2fa {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-2fa.activo {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .badge-2fa.inactivo {
            background: #eef1f5;
            color: #404e62;
            border: 1px solid #cdd5e0;
        }

        /* Sub-etiqueta de nota admin */
        .badge-nota-admin {
            color: #404e62;
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding-left: 4px;
            white-space: nowrap;
            opacity: 0.8;
        }

        .badge-nota-admin i {
            font-size: 0.6rem;
        }

        /* Botón Restablecer */
        .btn-restablecer {
            background: linear-gradient(135deg, #eb0045, #c4003a);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 4px rgba(235, 0, 69, 0.15);
        }

        .btn-restablecer:hover {
            background: linear-gradient(135deg, #c4003a, #a10030);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(235, 0, 69, 0.3);
        }

        .btn-restablecer:active {
            transform: translateY(0);
        }

        .btn-restablecer:disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Info & Warning boxes */
        .info-box {
            background: #f0f4f8;
            border-left: 4px solid #404e62;
            padding: 16px 22px;
            border-radius: 0 10px 10px 0;
            margin-bottom: 16px;
            font-size: 0.875rem;
            color: #404e62;
            line-height: 1.6;
        }

        .info-box i {
            margin-right: 8px;
        }

        .warning-box {
            background: #fef2f2;
            border-left: 4px solid #eb0045;
            padding: 16px 22px;
            border-radius: 0 10px 10px 0;
            margin-bottom: 24px;
            font-size: 0.875rem;
            color: #404e62;
            line-height: 1.6;
        }

        .warning-box i {
            margin-right: 8px;
            color: #eb0045;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .mfa-table-container {
                overflow-x: auto;
            }
            .mfa-table thead th,
            .mfa-table tbody td {
                padding: 10px 12px;
                font-size: 0.8rem;
            }
            .mfa-user {
                gap: 8px;
            }
            .mfa-user-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
<div class="layout-admin">
    <?php include_once __DIR__ . '/Modulos/navbar.php'; ?>

    <main class="contenido-principal">
        <!-- Header -->
        <div class="header-universal">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-user-shield"></i>
                    <h1>Restablecer MFA de Usuarios</h1>
                </div>
                <div class="header-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?= $total ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $con_2fa ?></span>
                        <span class="stat-label">Con 2FA</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $sin_2fa ?></span>
                        <span class="stat-label">Sin 2FA</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información -->
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Nota para administradores:</strong> El 2FA es obligatorio para cuentas de administrador. Si se restablece el MFA de un administrador, este se reactivará automáticamente en su próximo inicio de sesión y recibirá un nuevo código de verificación.
        </div>

        <!-- Tabla de usuarios -->
        <div class="mfa-table-container">
            <table class="mfa-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado 2FA</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario):
                        $es_admin = ($usuario['rol'] === 'admin' || $usuario['rol'] === 'administrador');
                        $tiene_2fa = $usuario['tiene_2fa'] == 1;
                    ?>
                    <tr>
                        <td><span class="mfa-id">#<?= $usuario['id'] ?></span></td>
                        <td>
                            <div class="mfa-user">
                                <div class="mfa-user-avatar <?= $es_admin ? 'admin-avatar' : 'user-avatar' ?>">
                                    <?php if ($es_admin): ?>
                                        <i class="fas fa-crown"></i>
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <span class="mfa-user-name"><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></span>
                            </div>
                        </td>
                        <td><span class="mfa-email"><?= htmlspecialchars($usuario['email']) ?></span></td>
                        <td>
                            <?php if ($es_admin): ?>
                                <span class="mfa-role-badge role-admin"><i class="fas fa-shield-alt"></i> Admin</span>
                            <?php else: ?>
                                <span class="mfa-role-badge role-user"><i class="fas fa-user"></i> Usuario</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="mfa-estado-container">
                                <?php if ($tiene_2fa): ?>
                                    <span class="badge-2fa activo">
                                        <i class="fas fa-check-circle"></i> Activo
                                    </span>
                                    <?php if ($es_admin): ?>
                                        <span class="badge-nota-admin"><i class="fas fa-lock"></i> Obligatorio</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge-2fa inactivo">
                                        <i class="fas fa-minus-circle"></i> Inactivo
                                    </span>
                                    <?php if ($es_admin): ?>
                                        <span class="badge-nota-admin"><i class="fas fa-sync-alt"></i> Se activa al iniciar sesión</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($tiene_2fa): ?>
                                <button type="button" class="btn-restablecer"
                                        onclick="confirmarRestablecimiento(<?= $usuario['id'] ?>, '<?= htmlspecialchars(addslashes($usuario['nombre'] . ' ' . $usuario['apellido'])) ?>', <?= $es_admin ? 'true' : 'false' ?>)">
                                    <i class="fas fa-undo-alt"></i> Restablecer
                                </button>
                            <?php else: ?>
                                <button class="btn-restablecer" disabled>
                                    <i class="fas fa-minus-circle"></i> Sin 2FA
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Formulario oculto para enviar el restablecimiento -->
        <form id="form-restablecer" method="POST" action="restablecer_mfa.php" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="restablecer_usuario_id" id="restablecer_usuario_id">
        </form>

        <?php require_once '../mensaje_alerta.php'; ?>
    </main>
</div>

<script>
    // Variables para las notificaciones (SweetAlert desde sesión)
    <?php if (isset($_SESSION['titulo']) && isset($_SESSION['mensaje']) && isset($_SESSION['tipo_alerta'])): ?>
    const mensajeTitulo = "<?php echo addslashes($_SESSION['titulo']); ?>";
    const mensajeTexto = "<?php echo addslashes($_SESSION['mensaje']); ?>";
    const mensajeTipo = "<?php echo $_SESSION['tipo_alerta']; ?>";
    <?php
        unset($_SESSION['titulo']);
        unset($_SESSION['mensaje']);
        unset($_SESSION['tipo_alerta']);
    ?>
    
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof mensajeTitulo !== 'undefined') {
            Swal.fire({
                title: mensajeTitulo,
                text: mensajeTexto,
                icon: mensajeTipo || 'info',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#eb0045'
            });
        }
    });
    <?php endif; ?>

    async function confirmarRestablecimiento(usuarioId, nombreUsuario, esAdmin) {
        let textoExtra = '';
        if (esAdmin) {
            textoExtra = '\n\nNota: Como es administrador, el 2FA se reactivará automáticamente en su próximo inicio de sesión.';
        }

        const confirmacion = await Swal.fire({
            title: '¿Restablecer MFA?',
            html: `¿Estás seguro de restablecer la verificación en dos pasos de <strong>${nombreUsuario}</strong>?<br><br>Se desactivará su 2FA y se invalidarán los códigos pendientes.${esAdmin ? '<br><br><span style="color: #eb0045;"><i class="fas fa-exclamation-triangle"></i> Como es administrador, el 2FA se reactivará automáticamente en su próximo inicio de sesión.</span>' : ''}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#eb0045',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, restablecer',
            cancelButtonText: 'Cancelar'
        });

        if (confirmacion.isConfirmed) {
            document.getElementById('restablecer_usuario_id').value = usuarioId;
            document.getElementById('form-restablecer').submit();
        }
    }
</script>
</body>
</html>
