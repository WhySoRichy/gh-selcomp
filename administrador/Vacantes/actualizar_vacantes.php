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
        header('Location: actualizar_vacantes.php');
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
            $_SESSION['titulo'] = '¡Éxito!';
            $_SESSION['mensaje'] = 'Vacante actualizada correctamente';
            $_SESSION['tipo_alerta'] = 'success';
        } catch (Exception $e) {
            $_SESSION['titulo'] = 'Error';
            $_SESSION['mensaje'] = 'Error al actualizar la vacante: ' . $e->getMessage();
            $_SESSION['tipo_alerta'] = 'error';
        }
    } else {
        $_SESSION['titulo'] = 'Error';
        $_SESSION['mensaje'] = 'Todos los campos son obligatorios';
        $_SESSION['tipo_alerta'] = 'error';
    }
    
    // Redirigir para evitar reenvío del formulario
    header('Location: actualizar_vacantes.php');
    exit();
}

try {
    $stmt = $conexion->query("SELECT id, titulo, descripcion, ciudad FROM vacantes ORDER BY id DESC");
    $vacantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalVacantes = count($vacantes);
} catch (Exception $e) {
    die("Error al obtener vacantes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizar Vacantes - Sistema de Gestión</title>
    <link rel="stylesheet" href="/gh/Css/navbar.css">
    <link rel="stylesheet" href="/gh/Css/vacantes.css">
    <link rel="icon" href="/gh/Img/logo.png">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .actualizar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .actualizar-form {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .actualizar-form:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        
        .vacante-info {
            display: grid;
            grid-template-columns: 80px 1fr 1fr 2fr auto;
            gap: 15px;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .vacante-info label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-input {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
            background: white;
        }
        
        .form-textarea {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            min-height: 80px;
            resize: vertical;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
            background: white;
        }
        
        .btn-actualizar {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-actualizar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40,167,69,0.3);
        }
        
        .vacante-id {
            background: #007bff;
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-align: center;
            min-width: 50px;
        }
        
        .no-vacantes {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .no-vacantes i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .vacante-info {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .vacante-info label {
                font-size: 12px;
                margin-bottom: 5px;
            }
            
            .actualizar-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<?php include("../Modulos/navbar.php"); ?>

<main class="content">
    <!-- Header con estadísticas -->
    <div class="vacantes-header">
        <div class="header-content">
            <div class="header-text">
                <h1>✏️ Actualizar Vacantes</h1>
                <p>Modifica la información de las vacantes existentes</p>
            </div>
            <div class="header-stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $totalVacantes ?></div>
                    <div class="stat-label">Vacantes Totales</div>
                </div>
            </div>
        </div>
    </div>

    <div class="actualizar-container">
        <?php if (empty($vacantes)): ?>
            <div class="no-vacantes">
                <i>📝</i>
                <h3>No hay vacantes disponibles</h3>
                <p>Agrega nuevas vacantes para comenzar a editarlas</p>
                <a href="agregar_vacante.php" class="btn-primary">Agregar Vacante</a>
            </div>
        <?php else: ?>
            <?php foreach ($vacantes as $vacante): ?>
                <div class="actualizar-form">
                    <form method="post">
                        <?= campo_csrf_token() ?>
                        <input type="hidden" name="id" value="<?= htmlspecialchars($vacante['id']) ?>">
                        
                        <div class="vacante-info">
                            <div class="vacante-id">
                                ID: <?= htmlspecialchars($vacante['id']) ?>
                            </div>
                            
                            <div>
                                <label>Título de la Vacante</label>
                                <input type="text" name="titulo" 
                                       value="<?= htmlspecialchars($vacante['titulo']) ?>" 
                                       class="form-input" 
                                       required 
                                       maxlength="100">
                            </div>
                            
                            <div>
                                <label>Ciudad</label>
                                <input type="text" name="ciudad" 
                                       value="<?= htmlspecialchars($vacante['ciudad']) ?>" 
                                       class="form-input" 
                                       maxlength="50">
                            </div>
                            
                            <div>
                                <label>Descripción</label>
                                <textarea name="descripcion" 
                                          class="form-textarea" 
                                          required 
                                          maxlength="500"><?= htmlspecialchars($vacante['descripcion']) ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn-actualizar">
                                💾 Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php require_once '../../mensaje_alerta.php'; ?>
</main>
</body>
</html>
