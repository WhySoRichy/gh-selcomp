<?php
session_start();
include 'auth.php';
require_once "../conexion/conexion.php";

// Obtener todas las vacantes activas
try {
    $stmt = $conexion->query("SELECT id, titulo, descripcion, ciudad FROM vacantes ORDER BY id DESC");
    $vacantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error al obtener vacantes: " . $e->getMessage());
    die("Error al cargar las vacantes. Por favor, intente más tarde.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vacantes - Portal Usuario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/header-universal.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/dashboard_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/vacantes_usuario.css?v=<?= time() ?>">
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
                <i class="fas fa-briefcase"></i>
                <h1>Vacantes Disponibles</h1>
            </div>
            <div class="header-stats">
                <div class="stat-item">
                    <span class="stat-number"><?= count($vacantes) ?></span>
                    <span class="stat-label">Disponibles</span>
                </div>
            </div>
        </div>
    </div>

    <?php if (count($vacantes) > 0): ?>
        <div class="vacantes-container">
            <div class="vacantes-grid">
                <?php foreach ($vacantes as $vacante): ?>
                    <div class="vacante-card" data-id="<?= $vacante['id'] ?>">
                        <div class="vacante-header">
                            <div class="vacante-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="vacante-info">
                                <h3 class="vacante-titulo"><?= htmlspecialchars($vacante['titulo']) ?></h3>
                                <p class="vacante-ciudad">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($vacante['ciudad']) ?>
                                </p>
                            </div>
                        </div>
                        <div class="vacante-content">
                            <div class="vacante-descripcion">
                                <h4><i class="fas fa-align-left"></i> Descripción del puesto:</h4>
                                <p><?= nl2br(htmlspecialchars($vacante['descripcion'])) ?></p>
                            </div>
                        </div>
                        <div class="vacante-actions">
                            <button class="btn-ver-mas" onclick="verMasDetalles(<?= $vacante['id'] ?>)">
                                <i class="fas fa-eye"></i>
                                Ver más
                            </button>
                            <a href="/gh/postulacion.php?vacante=<?= $vacante['id'] ?>" class="btn-postular">
                                <i class="fas fa-paper-plane"></i>
                                Postularme
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-briefcase"></i>
            </div>
            <h3>No hay vacantes disponibles</h3>
            <p>En este momento no hay vacantes publicadas. Te invitamos a revisar próximamente.</p>
        </div>
    <?php endif; ?>

    <!-- Modal para ver descripción completa -->
    <div class="modal-overlay" id="modal-vacante">
        <div class="modal-content">
            <div class="modal-header">
                <div class="vacante-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="modal-title">
                    <h2 id="modal-titulo"></h2>
                    <p class="modal-ciudad" id="modal-ciudad"></p>
                </div>
                <button class="btn-cerrar" onclick="cerrarModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-descripcion">
                    <h3><i class="fas fa-align-left"></i> Descripción completa:</h3>
                    <p id="modal-descripcion"></p>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn-postular" id="modal-postular">
                    <i class="fas fa-paper-plane"></i>
                    Postularme a esta vacante
                </a>
            </div>
        </div>
    </div>

    <?php require_once '../mensaje_alerta.php'; ?>
    
    <script>
        // Datos de vacantes para JavaScript
        const vacantesData = <?= json_encode($vacantes) ?>;
        
        function verMasDetalles(id) {
            const vacante = vacantesData.find(v => v.id == id);
            if (vacante) {
                document.getElementById('modal-titulo').textContent = vacante.titulo;
                document.getElementById('modal-ciudad').innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + vacante.ciudad;
                document.getElementById('modal-descripcion').textContent = vacante.descripcion;
                document.getElementById('modal-postular').href = '/gh/postulacion.php?vacante=' + id;
                
                const modal = document.getElementById('modal-vacante');
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
        
        function cerrarModal() {
            const modal = document.getElementById('modal-vacante');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Cerrar modal al hacer clic en el overlay
        document.getElementById('modal-vacante').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
        
        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
            }
        });
    </script>
</main>
</body>
</html>