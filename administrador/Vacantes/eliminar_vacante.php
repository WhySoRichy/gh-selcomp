<?php
session_start();
require_once '../auth.php';
require_once '../csrf_protection.php';
require_once "../../conexion/conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['titulo'] = 'Error de Seguridad';
        $_SESSION['mensaje'] = 'Token de seguridad inválido';
        $_SESSION['tipo_alerta'] = 'error';
        header('Location: eliminar_vacante.php');
        exit();
    }

    $id = $_POST['id'] ?? null;
    if ($id) {
        try {
            $stmt = $conexion->prepare("DELETE FROM vacantes WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['titulo'] = 'Éxito';
                $_SESSION['mensaje'] = 'Vacante eliminada correctamente';
                $_SESSION['tipo_alerta'] = 'success';
            } else {
                $_SESSION['titulo'] = 'Error';
                $_SESSION['mensaje'] = 'La vacante no existe o ya fue eliminada';
                $_SESSION['tipo_alerta'] = 'error';
            }
        } catch (PDOException $e) {
            $_SESSION['titulo'] = 'Error';
            $_SESSION['mensaje'] = 'Error al eliminar la vacante: ' . $e->getMessage();
            $_SESSION['tipo_alerta'] = 'error';
        }
    }
}

try {
    $stmt = $conexion->query("SELECT id, titulo, ciudad FROM vacantes");
    $vacantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error al obtener vacantes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Vacante</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar.css">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/vacantes.css">
    <link rel="icon" href="/gh/Img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include("../Modulos/navbar.php"); ?>
<main class="contenido-principal">
    <!-- Header universal de peligro -->
    <div class="header-universal danger">
        <div class="header-content">
            <div class="header-title">
                <i class="fas fa-trash-alt"></i>
                <h1>Eliminar Vacante</h1>
            </div>
            <div class="header-info">
                <span class="info-text">Elimina vacantes que ya no estén disponibles</span>
            </div>
        </div>
    </div>

    <?php if (count($vacantes) > 0): ?>
        <div class="danger-warning">
            <div class="warning-content">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <h3>¡Atención!</h3>
                    <p>La eliminación de vacantes es permanente. Esta acción no se puede deshacer.</p>
                </div>
            </div>
        </div>

        <div class="vacantes-container eliminar-vacante">
            <div class="vacantes-grid">
                <?php foreach ($vacantes as $vacante): ?>
                    <div class="vacante-card danger-card">
                        <div class="vacante-header">
                            <div class="vacante-icon danger">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="vacante-info">
                                <h3 class="vacante-titulo"><?= htmlspecialchars($vacante['titulo']) ?></h3>
                                <p class="vacante-ciudad">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($vacante['ciudad']) ?>
                                </p>
                            </div>
                            <div class="vacante-actions">
                                <button class="btn-delete" onclick="confirmarEliminacion(<?= $vacante['id'] ?>, '<?= htmlspecialchars($vacante['titulo']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Formulario oculto para eliminación -->
                        <form id="form-eliminar-<?= $vacante['id'] ?>" method="post" style="display: none;">
                            <?= campo_csrf_token() ?>
                            <input type="hidden" name="id" value="<?= $vacante['id'] ?>">
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-check-circle" style="color: #48bb78;"></i>
            </div>
            <h3>¡Excelente!</h3>
            <p>No hay vacantes para eliminar en este momento.</p>
            <button class="btn-primary" onclick="location.href='ver_vacantes.php'">
                <i class="fas fa-briefcase"></i>
                Ver todas las vacantes
            </button>
        </div>
    <?php endif; ?>

    <?php require_once '../../mensaje_alerta.php'; ?>
    
    <script>
        function confirmarEliminacion(id, titulo) {
            if (confirm('¿Estás seguro de que deseas eliminar la vacante "' + titulo + '"?\n\nEsta acción no se puede deshacer.')) {
                document.getElementById('form-eliminar-' + id).submit();
            }
        }
    </script>
</main>
</body>
</html>
