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
        header('Location: agregar_vacante.php');
        exit();
    }

    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');

    if ($titulo !== '' && $descripcion !== '') {
        try {
            $stmt = $conexion->prepare("INSERT INTO vacantes (titulo, descripcion, ciudad) VALUES (:titulo, :descripcion, :ciudad)");
            $stmt->bindParam(':titulo', $titulo);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':ciudad', $ciudad);
            $stmt->execute();
            $_SESSION['titulo'] = 'Éxito';
            $_SESSION['mensaje'] = 'Vacante agregada correctamente';
            $_SESSION['tipo_alerta'] = 'success';
            header('Location: ver_vacantes.php');
            exit;
        } catch (PDOException $e) {
            error_log('Error al agregar vacante: ' . $e->getMessage());
            $_SESSION['titulo'] = 'Error';
            $_SESSION['mensaje'] = 'Error interno al agregar la vacante. Contacte al administrador.';
            $_SESSION['tipo_alerta'] = 'error';
        }
    } else {
        $_SESSION['titulo'] = 'Error';
        $_SESSION['mensaje'] = 'El título y la descripción son obligatorios';
        $_SESSION['tipo_alerta'] = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Vacante - Portal Gestión Humana</title>
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
                <i class="fas fa-plus-circle"></i>
                <h1>Agregar Vacante</h1>
            </div>
            <div class="header-info">
                <span class="info-text">Crea una nueva oportunidad laboral</span>
            </div>
        </div>
    </div>

    <div class="form-container">
        <div class="form-card">
            <form method="post" class="modern-form">
                <?= campo_csrf_token() ?>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="titulo">
                            <i class="fas fa-briefcase"></i>
                            Título del puesto
                        </label>
                        <input type="text" 
                               id="titulo" 
                               name="titulo" 
                               placeholder="Ej: Desarrollador Full Stack" 
                               required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ciudad">
                            <i class="fas fa-map-marker-alt"></i>
                            Ciudad
                        </label>
                        <input type="text" 
                               id="ciudad" 
                               name="ciudad" 
                               placeholder="Ej: Bogotá">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="descripcion">
                            <i class="fas fa-align-left"></i>
                            Descripción del puesto
                        </label>
                        <textarea id="descripcion" 
                                  name="descripcion" 
                                  rows="6" 
                                  placeholder="Describe las responsabilidades, requisitos y beneficios del puesto..."
                                  required></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i>
                        Crear Vacante
                    </button>
                    <a href="ver_vacantes.php" class="btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php require_once '../../mensaje_alerta.php'; ?>
</main>
</body>
</html>
