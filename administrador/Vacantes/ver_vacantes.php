<?php
session_start();
include '../auth.php';
include '../csrf_protection.php';
require_once "../../conexion/conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['titulo'] = 'Error de Seguridad';
        $_SESSION['mensaje'] = 'Token de seguridad inválido';
        $_SESSION['tipo_alerta'] = 'error';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $id = $_POST['id'] ?? null;
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    if ($id && $titulo !== '' && $descripcion !== '') {
        try {
            $stmt = $conexion->prepare("UPDATE vacantes SET titulo=:titulo, descripcion=:descripcion, ciudad=:ciudad WHERE id=:id");
            $stmt->bindParam(':titulo', $titulo);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':ciudad', $ciudad);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $_SESSION['titulo'] = 'Éxito';
            $_SESSION['mensaje'] = 'Vacante actualizada correctamente';
            $_SESSION['tipo_alerta'] = 'success';
        } catch (PDOException $e) {
            error_log('Error al actualizar vacante: ' . $e->getMessage());
            $_SESSION['titulo'] = 'Error';
            $_SESSION['mensaje'] = 'Error interno al actualizar la vacante. Contacte al administrador.';
            $_SESSION['tipo_alerta'] = 'error';
        }
    } else {
        $_SESSION['titulo'] = 'Error';
        $_SESSION['mensaje'] = 'Todos los campos son obligatorios';
        $_SESSION['tipo_alerta'] = 'error';
    }
    // Redirigir para evitar reenvío de formulario
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

try {
    $stmt = $conexion->query("SELECT id, titulo, descripcion, ciudad FROM vacantes");
    $vacantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error al obtener vacantes: ' . $e->getMessage());
    die('Error interno al cargar los datos.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vacantes - Portal Gestión Humana</title>
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
    <!-- Header universal -->
    <div class="header-universal">
        <div class="header-content">
            <div class="header-title">
                <i class="fas fa-briefcase"></i>
                <h1>Vacantes</h1>
            </div>
            <div class="header-stats">
                <div class="stat-item">
                    <span class="stat-number"><?= count($vacantes) ?></span>
                    <span class="stat-label">Activas</span>
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
                                <h3 class="vacante-titulo" 
                                    onclick="toggleTitulo(<?= $vacante['id'] ?>)"
                                    data-full-title="<?= htmlspecialchars($vacante['titulo']) ?>"
                                    title="<?= htmlspecialchars($vacante['titulo']) ?>"
                                    id="titulo-<?= $vacante['id'] ?>">
                                    <?= htmlspecialchars($vacante['titulo']) ?>
                                </h3>
                                <p class="vacante-ciudad" 
                                   title="<?= htmlspecialchars($vacante['ciudad']) ?>">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= htmlspecialchars($vacante['ciudad']) ?></span>
                                </p>
                            </div>
                            <div class="vacante-actions">
                                <button class="btn-editar" onclick="editarVacante(<?= $vacante['id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="vacante-content">
                            <div class="vacante-descripcion">
                                <h4>Descripción del puesto:</h4>
                                <p><?= nl2br(htmlspecialchars($vacante['descripcion'])) ?></p>
                            </div>
                        </div>
                        
                        <!-- Formulario de edición (oculto inicialmente) -->
                        <div class="vacante-form" id="form-<?= $vacante['id'] ?>" style="display: none;">
                            <form method="post" class="form-edicion">
                                <?= campo_csrf_token() ?>
                                <input type="hidden" name="id" value="<?= $vacante['id'] ?>">
                                
                                <div class="form-group">
                                    <label for="titulo-<?= $vacante['id'] ?>">
                                        <i class="fas fa-briefcase"></i>
                                        Título del puesto
                                    </label>
                                    <input type="text" 
                                           id="titulo-<?= $vacante['id'] ?>" 
                                           name="titulo" 
                                           value="<?= htmlspecialchars($vacante['titulo']) ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="ciudad-<?= $vacante['id'] ?>">
                                        <i class="fas fa-map-marker-alt"></i>
                                        Ciudad
                                    </label>
                                    <input type="text" 
                                           id="ciudad-<?= $vacante['id'] ?>" 
                                           name="ciudad" 
                                           value="<?= htmlspecialchars($vacante['ciudad']) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="descripcion-<?= $vacante['id'] ?>">
                                        <i class="fas fa-align-left"></i>
                                        Descripción
                                    </label>
                                    <textarea id="descripcion-<?= $vacante['id'] ?>" 
                                              name="descripcion" 
                                              rows="4" 
                                              required><?= htmlspecialchars($vacante['descripcion']) ?></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn-guardar">
                                        <i class="fas fa-save"></i>
                                        Guardar cambios
                                    </button>
                                    <button type="button" class="btn-cancelar" onclick="cancelarEdicion(<?= $vacante['id'] ?>)">
                                        <i class="fas fa-times"></i>
                                        Cancelar
                                    </button>
                                </div>
                            </form>
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
            <p>Aún no se han registrado vacantes en el sistema.</p>
            <button class="btn-primary" onclick="location.href='agregar_vacante.php'">
                <i class="fas fa-plus"></i>
                Agregar primera vacante
            </button>
        </div>
    <?php endif; ?>

    <?php require_once '../../mensaje_alerta.php'; ?>
    
    <script>
        // Función para expandir/contraer tarjetas horizontalmente
        function toggleTitulo(id) {
            const card = document.querySelector('[data-id="' + id + '"]');
            const titulo = document.getElementById('titulo-' + id);
            
            // Toggle de la expansión horizontal de toda la tarjeta
            card.classList.toggle('expanded');
            titulo.classList.toggle('expanded');
            
            // Remover tooltip si está expandido
            if (card.classList.contains('expanded')) {
                removeTooltip();
            }
        }
        
        // Función para detectar títulos largos y aplicar comportamiento
        function initTituloTruncation() {
            const titulos = document.querySelectorAll('.vacante-titulo');
            
            titulos.forEach((titulo) => {
                const fullText = titulo.getAttribute('data-full-title');
                const displayText = titulo.textContent.trim();
                
                // Si el texto es largo, marcar como truncado
                if (fullText && fullText.length > 60) {
                    titulo.setAttribute('data-truncated', 'true');
                    titulo.style.cursor = 'pointer';
                    
                    // Tooltip solo cuando NO está expandido
                    titulo.addEventListener('mouseenter', function(e) {
                        const card = titulo.closest('.vacante-card');
                        if (!card.classList.contains('expanded')) {
                            createTooltip(e.target, fullText);
                        }
                    });
                    
                    titulo.addEventListener('mouseleave', function() {
                        removeTooltip();
                    });
                } else {
                    // Para títulos cortos, no necesitan funcionalidad de expansión
                    titulo.style.cursor = 'default';
                    titulo.removeAttribute('onclick');
                }
            });
        }
        
        // Función para crear tooltip dinámico (mejorado)
        function createTooltip(element, text) {
            removeTooltip(); // Limpiar tooltips existentes
            
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = text;
            
            const rect = element.getBoundingClientRect();
            tooltip.style.cssText = `
                position: fixed;
                top: ${rect.top - 60}px;
                left: ${rect.left}px;
                background: rgba(45, 55, 72, 0.95);
                color: white;
                padding: 12px 16px;
                border-radius: 8px;
                font-size: 0.9rem;
                font-weight: 500;
                white-space: normal;
                word-break: break-word;
                z-index: 9999;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                pointer-events: none;
                min-width: 200px;
                max-width: 450px;
                line-height: 1.4;
                opacity: 0;
                transition: opacity 0.3s ease;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
            `;
            
            document.body.appendChild(tooltip);
            
            // Animar entrada
            requestAnimationFrame(() => {
                tooltip.style.opacity = '1';
            });
        }
        
        // Función para remover tooltip
        function removeTooltip() {
            const existingTooltip = document.querySelector('.custom-tooltip');
            if (existingTooltip) {
                existingTooltip.style.opacity = '0';
                setTimeout(() => {
                    if (existingTooltip.parentNode) {
                        existingTooltip.parentNode.removeChild(existingTooltip);
                    }
                }, 300);
            }
        }
        
        // Función para colapsar todas las tarjetas excepto la actual
        function collapseOtherCards(currentId) {
            const allCards = document.querySelectorAll('.vacante-card');
            allCards.forEach(card => {
                if (card.getAttribute('data-id') !== currentId.toString()) {
                    card.classList.remove('expanded');
                    const titulo = card.querySelector('.vacante-titulo');
                    if (titulo) {
                        titulo.classList.remove('expanded');
                    }
                }
            });
        }
        
        // Actualizar la función toggleTitulo para colapsar otras tarjetas
        function toggleTituloWithCollapse(id) {
            const card = document.querySelector('[data-id="' + id + '"]');
            
            // Si no está expandida, colapsar las demás primero
            if (!card.classList.contains('expanded')) {
                collapseOtherCards(id);
            }
            
            // Luego hacer el toggle normal
            toggleTitulo(id);
        }
        
        function editarVacante(id) {
            const card = document.querySelector('[data-id="' + id + '"]');
            const content = card.querySelector('.vacante-content');
            const form = card.querySelector('.vacante-form');
            const editBtn = card.querySelector('.btn-editar');
            
            // Colapsar la tarjeta si está expandida
            card.classList.remove('expanded');
            const titulo = card.querySelector('.vacante-titulo');
            if (titulo) titulo.classList.remove('expanded');
            
            content.style.display = 'none';
            form.style.display = 'block';
            editBtn.innerHTML = '<i class="fas fa-eye"></i>';
            editBtn.setAttribute('onclick', 'verVacante(' + id + ')');
        }
        
        function verVacante(id) {
            cancelarEdicion(id);
        }
        
        function cancelarEdicion(id) {
            const card = document.querySelector('[data-id="' + id + '"]');
            const content = card.querySelector('.vacante-content');
            const form = card.querySelector('.vacante-form');
            const editBtn = card.querySelector('.btn-editar');
            
            content.style.display = 'block';
            form.style.display = 'none';
            editBtn.innerHTML = '<i class="fas fa-edit"></i>';
            editBtn.setAttribute('onclick', 'editarVacante(' + id + ')');
        }
        
        // Inicializar cuando cargue la página
        document.addEventListener('DOMContentLoaded', function() {
            initTituloTruncation();
            
            // Actualizar los onclick para usar la nueva función
            const titulos = document.querySelectorAll('.vacante-titulo[onclick]');
            titulos.forEach(titulo => {
                const onclickAttr = titulo.getAttribute('onclick');
                const match = onclickAttr.match(/toggleTitulo\((\d+)\)/);
                if (match) {
                    const id = match[1];
                    titulo.setAttribute('onclick', 'toggleTituloWithCollapse(' + id + ')');
                }
            });
        });
    </script>
</main>
</body>
</html>