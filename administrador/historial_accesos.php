<?php
/**
 * Historial de accesos - Panel de Administrador
 * Muestra todos los accesos de todos los usuarios al sistema
 */

// Configuración de encabezados de seguridad
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

session_start();
include 'auth.php';
include 'csrf_protection.php';
require_once __DIR__ . "/../conexion/conexion.php";
require_once __DIR__ . "/../funciones/formateo_fechas.php";

// Verificar que el usuario tenga rol de administrador
if ($_SESSION['usuario_rol'] !== 'admin' && $_SESSION['usuario_rol'] !== 'administrador') {
    header('Location: ../index.php');
    exit;
}

// Título de la página
$titulo_pagina = "Historial Global de Accesos";

// Parámetros de paginación y filtrado
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$registros_por_pagina = isset($_GET['registros']) ? intval($_GET['registros']) : 20;
$usuario_filtro = isset($_GET['usuario']) && !empty($_GET['usuario']) ? intval($_GET['usuario']) : null;
$estado_filtro = isset($_GET['estado']) && !empty($_GET['estado']) ? trim($_GET['estado']) : null;
$desde_fecha = isset($_GET['desde']) && !empty($_GET['desde']) ? trim($_GET['desde']) : null;
$hasta_fecha = isset($_GET['hasta']) && !empty($_GET['hasta']) ? trim($_GET['hasta']) : null;

// Validar registros por página
if (!in_array($registros_por_pagina, [10, 20, 50, 100])) {
    $registros_por_pagina = 20;
}

// Validar estado
if ($estado_filtro && !in_array($estado_filtro, ['exitoso', 'fallido'])) {
    $estado_filtro = null;
}

// Validar fechas
if ($desde_fecha && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde_fecha)) {
    $desde_fecha = null;
}
if ($hasta_fecha && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta_fecha)) {
    $hasta_fecha = null;
}

// Construir la consulta base
$sql_count = "SELECT COUNT(*) FROM historial_accesos h";
$sql_select = "SELECT h.id, h.usuario_id, h.fecha_acceso, h.ip_acceso, h.dispositivo, h.navegador, 
                      h.exito, h.detalles, u.nombre, u.apellido, u.email 
               FROM historial_accesos h
               JOIN usuarios u ON h.usuario_id = u.id";

// Construir la cláusula WHERE para filtros
$where_clauses = [];
$params = [];

if ($usuario_filtro !== null) {
    $where_clauses[] = "h.usuario_id = :usuario_id";
    $params[':usuario_id'] = $usuario_filtro;
}

if ($estado_filtro !== null) {
    $exitoso = ($estado_filtro === 'exitoso') ? 1 : 0;
    $where_clauses[] = "h.exito = :exitoso";
    $params[':exitoso'] = $exitoso;
}

if ($desde_fecha !== null) {
    $where_clauses[] = "h.fecha_acceso >= :desde_fecha";
    $params[':desde_fecha'] = $desde_fecha . ' 00:00:00';
}

if ($hasta_fecha !== null) {
    $where_clauses[] = "h.fecha_acceso <= :hasta_fecha";
    $params[':hasta_fecha'] = $hasta_fecha . ' 23:59:59';
}

// Aplicar filtros a las consultas
if (!empty($where_clauses)) {
    $where_clause = " WHERE " . implode(" AND ", $where_clauses);
    $sql_count .= $where_clause;
    $sql_select .= $where_clause;
} else {
    $where_clause = "";
}

// Ejecutar consulta de conteo
try {
    $stmt = $conexion->prepare($sql_count);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $total_registros = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error en consulta de conteo: " . $e->getMessage());
    $total_registros = 0;
}

// Calcular paginación
$total_paginas = $total_registros > 0 ? ceil($total_registros / $registros_por_pagina) : 1;
$pagina = min($pagina, $total_paginas); // Asegurar que no exceda las páginas disponibles
$offset = ($pagina - 1) * $registros_por_pagina;

// Ajustar consulta principal con paginación
$sql_select .= " ORDER BY h.fecha_acceso DESC LIMIT :offset, :limit";

// Ejecutar consulta principal
try {
    $stmt = $conexion->prepare($sql_select);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->execute();
    $accesos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error en consulta principal: " . $e->getMessage());
    $accesos = [];
}

// Obtener lista de usuarios para el filtro
try {
    $stmt = $conexion->query("SELECT id, nombre, apellido, email FROM usuarios ORDER BY nombre ASC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error obteniendo usuarios: " . $e->getMessage());
    $usuarios = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> - Panel de Administración</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/navbar.css">
    <link rel="stylesheet" href="/gh/Css/usuarios.css">
    <link rel="stylesheet" href="/gh/Css/seguridad.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/historial_admin.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/dispositivos.css?v=<?= time() ?>">
    <link rel="icon" href="/gh/Img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include 'Modulos/navbar.php'; ?>

<main class="contenido-principal contenido-con-navbar">
    <div class="header-universal">
        <div class="header-content">
            <div class="header-title">
                <i class="fas fa-shield-alt"></i>
                <h1>Historial de Accesos</h1>
            </div>
            <div class="header-info">
                <span class="info-text">Registro de todos los accesos al sistema</span>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-container">
        <form action="" method="GET" class="form-filtros">
            <div class="filtro-grupo">
                <label for="usuario">Usuario:</label>
                <select name="usuario" id="usuario">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $usuario_filtro === $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-grupo">
                <label for="estado">Estado:</label>
                <select name="estado" id="estado">
                    <option value="">Todos</option>
                    <option value="exitoso" <?= $estado_filtro === 'exitoso' ? 'selected' : '' ?>>Exitosos</option>
                    <option value="fallido" <?= $estado_filtro === 'fallido' ? 'selected' : '' ?>>Fallidos</option>
                </select>
            </div>
            <div class="filtro-grupo">
                <label for="desde">Desde:</label>
                <input type="date" name="desde" id="desde" value="<?= $desde_fecha ?>">
            </div>
            <div class="filtro-grupo">
                <label for="hasta">Hasta:</label>
                <input type="date" name="hasta" id="hasta" value="<?= $hasta_fecha ?>">
            </div>
            <div class="filtro-grupo filtro-mostrar">
                <label for="registros">Mostrar:</label>
                <div class="select-with-label">
                    <select name="registros" id="registros">
                        <option value="10" <?= $registros_por_pagina === 10 ? 'selected' : '' ?>>10</option>
                        <option value="20" <?= $registros_por_pagina === 20 ? 'selected' : '' ?>>20</option>
                        <option value="50" <?= $registros_por_pagina === 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $registros_por_pagina === 100 ? 'selected' : '' ?>>100</option>
                    </select>
                    <span class="label-suffix">registros</span>
                </div>
            </div>
            <div class="filtro-botones">
                <button type="submit" class="btn-filtrar">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <a href="historial_accesos.php" class="btn-reset">
                    <i class="fas fa-broom"></i> Limpiar
                </a>
            </div>
        </form>
    </div>
    
    <!-- Información de resultados -->
    <div class="resultados-info">
        <div class="info-registros">
            <i class="fas fa-info-circle"></i>
            Mostrando <?= count($accesos) ?> de <?= $total_registros ?> registros
            <?php if ($pagina > 1): ?>
                (Página <?= $pagina ?> de <?= $total_paginas ?>)
            <?php endif; ?>
        </div>
        <?php if ($usuario_filtro || $estado_filtro || $desde_fecha || $hasta_fecha): ?>
            <div class="filtros-activos">
                <strong>Filtros activos:</strong>
                <?php if ($usuario_filtro): ?>
                    <?php 
                    $nombre_usuario = 'ID '.$usuario_filtro;
                    foreach ($usuarios as $user) {
                        if ($user['id'] == $usuario_filtro) {
                            $nombre_usuario = $user['nombre'] . ' ' . $user['apellido'];
                            break;
                        }
                    }
                    ?>
                    <span class="filtro-activo">Usuario: <?= htmlspecialchars($nombre_usuario) ?></span>
                <?php endif; ?>
                <?php if ($estado_filtro): ?>
                    <span class="filtro-activo">Estado: <?= $estado_filtro === 'exitoso' ? 'Exitosos' : 'Fallidos' ?></span>
                <?php endif; ?>
                <?php if ($desde_fecha): ?>
                    <span class="filtro-activo">Desde: <?= date('d/m/Y', strtotime($desde_fecha)) ?></span>
                <?php endif; ?>
                <?php if ($hasta_fecha): ?>
                    <span class="filtro-activo">Hasta: <?= date('d/m/Y', strtotime($hasta_fecha)) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Resultados -->
    <div class="resultados-container">
        <?php if ($total_registros === 0): ?>
            <div class="no-resultados">
                <i class="fas fa-exclamation-circle"></i>
                <h3>No se encontraron registros</h3>
                <p>Intenta modificar los filtros de búsqueda.</p>
            </div>
        <?php else: ?>
            <div class="resumen-resultados">
                <span>Mostrando <?= min($registros_por_pagina, count($accesos)) ?> de <?= $total_registros ?> registros (página <?= $pagina ?> de <?= $total_paginas ?>)</span>
            </div>
            <div class="table-responsive">
                <table class="historial-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Fecha y Hora</th>
                            <th>IP</th>
                            <th>Dispositivo</th>
                            <th>Estado</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accesos as $acceso): ?>
                            <tr>
                                <td><?= $acceso['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($acceso['nombre']) ?></strong><br>
                                    <small><?= htmlspecialchars($acceso['email']) ?></small>
                                </td>
                                <td><?php 
                                    $fecha = $acceso['fecha_acceso'] ?? null;
                                    echo formatear_fecha_historial($fecha, 'completo');
                                ?></td>
                                <td><?= htmlspecialchars($acceso['ip_acceso']) ?></td>
                                <td>
                                    <span title="<?= htmlspecialchars($acceso['navegador']) ?>">
                                        <?= htmlspecialchars(substr($acceso['dispositivo'], 0, 30)) ?>...
                                    </span>
                                </td>
                                <td>
                                    <span class="estado-<?= isset($acceso['exito']) && $acceso['exito'] ? 'success' : 'error' ?>">
                                        <?= isset($acceso['exito']) && $acceso['exito'] ? 'Exitoso' : 'Fallido' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($acceso['detalles']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
                <div class="paginacion">
                    <?php if ($pagina > 1): ?>
                        <a href="?pagina=1<?= isset($_GET['usuario']) ? '&usuario='.htmlspecialchars($_GET['usuario']) : '' ?><?= isset($_GET['estado']) ? '&estado='.htmlspecialchars($_GET['estado']) : '' ?><?= isset($_GET['desde']) ? '&desde='.htmlspecialchars($_GET['desde']) : '' ?><?= isset($_GET['hasta']) ? '&hasta='.htmlspecialchars($_GET['hasta']) : '' ?><?= isset($_GET['registros']) ? '&registros='.intval($_GET['registros']) : '' ?>" class="btn-pag">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?pagina=<?= $pagina - 1 ?><?= isset($_GET['usuario']) ? '&usuario='.htmlspecialchars($_GET['usuario']) : '' ?><?= isset($_GET['estado']) ? '&estado='.htmlspecialchars($_GET['estado']) : '' ?><?= isset($_GET['desde']) ? '&desde='.htmlspecialchars($_GET['desde']) : '' ?><?= isset($_GET['hasta']) ? '&hasta='.htmlspecialchars($_GET['hasta']) : '' ?><?= isset($_GET['registros']) ? '&registros='.intval($_GET['registros']) : '' ?>" class="btn-pag">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $inicio = max(1, $pagina - 2);
                    $fin = min($total_paginas, $inicio + 4);
                    $inicio = max(1, $fin - 4);
                    
                    for ($i = $inicio; $i <= $fin; $i++):
                    ?>
                        <a href="?pagina=<?= $i ?><?= isset($_GET['usuario']) ? '&usuario='.htmlspecialchars($_GET['usuario']) : '' ?><?= isset($_GET['estado']) ? '&estado='.htmlspecialchars($_GET['estado']) : '' ?><?= isset($_GET['desde']) ? '&desde='.htmlspecialchars($_GET['desde']) : '' ?><?= isset($_GET['hasta']) ? '&hasta='.htmlspecialchars($_GET['hasta']) : '' ?><?= isset($_GET['registros']) ? '&registros='.intval($_GET['registros']) : '' ?>" class="btn-pag <?= ($i == $pagina) ? 'active' : '' ?>" aria-label="Página <?= $i ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($pagina < $total_paginas): ?>
                        <a href="?pagina=<?= $pagina + 1 ?><?= isset($_GET['usuario']) ? '&usuario='.htmlspecialchars($_GET['usuario']) : '' ?><?= isset($_GET['estado']) ? '&estado='.htmlspecialchars($_GET['estado']) : '' ?><?= isset($_GET['desde']) ? '&desde='.htmlspecialchars($_GET['desde']) : '' ?><?= isset($_GET['hasta']) ? '&hasta='.htmlspecialchars($_GET['hasta']) : '' ?><?= isset($_GET['registros']) ? '&registros='.intval($_GET['registros']) : '' ?>" class="btn-pag">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?pagina=<?= $total_paginas ?><?= isset($_GET['usuario']) ? '&usuario='.htmlspecialchars($_GET['usuario']) : '' ?><?= isset($_GET['estado']) ? '&estado='.htmlspecialchars($_GET['estado']) : '' ?><?= isset($_GET['desde']) ? '&desde='.htmlspecialchars($_GET['desde']) : '' ?><?= isset($_GET['hasta']) ? '&hasta='.htmlspecialchars($_GET['hasta']) : '' ?><?= isset($_GET['registros']) ? '&registros='.intval($_GET['registros']) : '' ?>" class="btn-pag">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<script>
    // Funcionalidad para filtros y mejoras de interacción
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar la visualización del tooltip al pasar sobre el UA
        const dispositivos = document.querySelectorAll('.historial-table td span[title]');
        dispositivos.forEach(dispositivo => {
            dispositivo.addEventListener('mouseover', function() {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.innerText = this.getAttribute('title');
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.top = rect.bottom + window.scrollY + 'px';
                tooltip.style.left = rect.left + window.scrollX + 'px';
                
                // Remover el tooltip al mover el mouse fuera
                this.addEventListener('mouseout', function() {
                    tooltip.remove();
                });
            });
        });
        
        // Mejora visual de filas al pasar el ratón
        const filas = document.querySelectorAll('.historial-table tbody tr');
        filas.forEach(fila => {
            fila.addEventListener('mouseenter', function() {
                this.style.transition = 'background-color 0.2s';
                this.style.backgroundColor = '#f1f5f9';
            });
            
            fila.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
        
        // Añadir validación básica a los campos de fecha
        const desdeInput = document.getElementById('desde');
        const hastaInput = document.getElementById('hasta');
        
        if (desdeInput && hastaInput) {
            hastaInput.addEventListener('change', function() {
                if (desdeInput.value && this.value && this.value < desdeInput.value) {
                    alert('La fecha "Hasta" debe ser posterior a la fecha "Desde"');
                    this.value = '';
                }
            });
        }
    });
    
    // Mejorar funcionalidad de filtros
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('.form-filtros');
        const filtros = form.querySelectorAll('select, input[type="date"]');
        
        // Auto-submit en cambios de filtros (opcional)
        filtros.forEach(filtro => {
            filtro.addEventListener('change', function() {
                // Opcional: auto-submit cuando cambian los filtros
                // form.submit();
            });
        });
        
        // Validación de fechas
        const desdeInput = document.getElementById('desde');
        const hastaInput = document.getElementById('hasta');
        
        if (desdeInput && hastaInput) {
            hastaInput.addEventListener('change', function() {
                if (desdeInput.value && this.value && this.value < desdeInput.value) {
                    alert('La fecha "Hasta" debe ser posterior a la fecha "Desde"');
                    this.value = '';
                }
            });
            
            desdeInput.addEventListener('change', function() {
                if (hastaInput.value && this.value && this.value > hastaInput.value) {
                    alert('La fecha "Desde" debe ser anterior a la fecha "Hasta"');
                    this.value = '';
                }
            });
        }
        
        // Limpiar filtros individuales
        document.querySelectorAll('.filtro-activo').forEach(filtro => {
            filtro.addEventListener('click', function() {
                const tipo = this.textContent.split(':')[0].trim();
                const url = new URL(window.location.href);
                
                switch(tipo) {
                    case 'Usuario':
                        url.searchParams.delete('usuario');
                        break;
                    case 'Estado':
                        url.searchParams.delete('estado');
                        break;
                    case 'Desde':
                        url.searchParams.delete('desde');
                        break;
                    case 'Hasta':
                        url.searchParams.delete('hasta');
                        break;
                }
                
                window.location.href = url.toString();
            });
        });
    });
</script>
</body>
</html>
