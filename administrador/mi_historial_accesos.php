<?php
/**
 * Historial de accesos personales - Panel de Administrador
 * Muestra todos los accesos del administrador actual al sistema
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
$titulo_pagina = "Mi Historial de Accesos";

// Parámetros de paginación y filtrado
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$registros_por_pagina = isset($_GET['registros']) ? intval($_GET['registros']) : 20;
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

// Construir la consulta base - solo para el usuario actual
$sql_count = "SELECT COUNT(*) FROM historial_accesos h WHERE h.usuario_id = :usuario_id";
$sql_select = "SELECT h.id, h.usuario_id, h.fecha_acceso, h.ip_acceso, h.dispositivo, h.navegador, 
                      h.exito, h.detalles, u.nombre, u.apellido, u.email 
               FROM historial_accesos h
               JOIN usuarios u ON h.usuario_id = u.id
               WHERE h.usuario_id = :usuario_id";

// Construir la cláusula WHERE para filtros adicionales
$where_clauses = [];
$params = [':usuario_id' => $_SESSION['usuario_id']];

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

// Aplicar filtros adicionales a las consultas
if (!empty($where_clauses)) {
    $where_clause = " AND " . implode(" AND ", $where_clauses);
    $sql_count .= $where_clause;
    $sql_select .= $where_clause;
}

// Ejecutar consulta para contar registros
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

// Ordenar y paginar
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

// Eliminamos la depuración que estaba visible anteriormente
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
<?php include 'Modulos/navbar.php'; ?>    <main class="contenido-principal contenido-con-navbar">
        <div class="header-universal">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-history"></i>
                    <h1>Mi Historial de Accesos</h1>
                </div>
                <div class="header-info">
                    <span class="info-text">Registro de tus accesos al sistema</span>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filtros-container">
            <form method="GET" action="" class="form-filtros">
                <div class="filtro-grupo">
                    <label for="estado">Estado:</label>
                    <select name="estado" id="estado">
                        <option value="">Todos</option>
                        <option value="exitoso" <?php echo $estado_filtro === 'exitoso' ? 'selected' : ''; ?>>Exitosos</option>
                        <option value="fallido" <?php echo $estado_filtro === 'fallido' ? 'selected' : ''; ?>>Fallidos</option>
                    </select>
                </div>
                
                <div class="filtro-grupo">
                    <label for="desde">Desde:</label>
                    <input type="date" name="desde" id="desde" value="<?php echo $desde_fecha; ?>">
                </div>
                
                <div class="filtro-grupo">
                    <label for="hasta">Hasta:</label>
                    <input type="date" name="hasta" id="hasta" value="<?php echo $hasta_fecha; ?>">
                </div>
                
                <div class="filtro-grupo filtro-mostrar">
                    <label for="registros">Mostrar:</label>
                    <div class="select-with-label">
                        <select name="registros" id="registros">
                            <option value="10" <?php echo $registros_por_pagina === 10 ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo $registros_por_pagina === 20 ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo $registros_por_pagina === 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $registros_por_pagina === 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                        <span class="label-suffix">registros</span>
                    </div>
                </div>
                
                <div class="filtro-botones">
                    <button type="submit" class="btn-filtrar">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    
                    <a href="mi_historial_accesos.php" class="btn-limpiar">
                        <i class="fas fa-broom"></i> Limpiar filtros
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
            <?php if ($estado_filtro || $desde_fecha || $hasta_fecha): ?>
                <div class="filtros-activos">
                    <strong>Filtros activos:</strong>
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
        
        <!-- Tabla de resultados -->
        <div class="tabla-container">
        <?php if (count($accesos) === 0) { ?>
            <div class="no-resultados">
                <i class="fas fa-exclamation-circle"></i>
                <h3>No se encontraron registros</h3>
                <p>Intenta modificar los filtros de búsqueda o intenta más tarde.</p>
            </div>
        <?php } else { ?>
            <div class="resumen-resultados">
                <span>Mostrando <?= count($accesos) ?> de <?= $total_registros ?> registros (página <?= $pagina ?> de <?= $total_paginas ?>)</span>
            </div>
            <table class="tabla-historial">
                <thead>
                    <tr>
                        <th><i class="far fa-calendar-alt"></i> Fecha y Hora</th>
                        <th><i class="fas fa-network-wired"></i> IP</th>
                        <th><i class="fas fa-laptop"></i> Dispositivo/Navegador</th>
                        <th><i class="fas fa-check-circle"></i> Estado</th>
                        <th><i class="fas fa-info-circle"></i> Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accesos as $acceso): ?>
                    <tr>
                                <td><?php 
                                    $fecha = $acceso['fecha_acceso'] ?? null;
                                    echo formatear_fecha_historial($fecha, 'completo');
                                ?></td>
                                <td><?php echo htmlspecialchars($acceso['ip_acceso'] ?? 'Desconocida'); ?></td>
                            <td>
                                <div class="dispositivo-info">
                                    <?php
                                    $ua = $acceso['dispositivo'];
                                    $iconClass = 'fa-question'; // Icono predeterminado
                                    $deviceClass = '';
                                    $deviceName = 'Desconocido';
                                    
                                    if (strpos($ua, 'Windows') !== false) {
                                        $iconClass = 'fa-windows';
                                        $deviceClass = 'windows';
                                        $deviceName = 'Windows';
                                    } elseif (strpos($ua, 'Macintosh') !== false || strpos($ua, 'Mac OS') !== false) {
                                        $iconClass = 'fa-apple';
                                        $deviceClass = 'apple';
                                        $deviceName = 'Mac OS';
                                    } elseif (strpos($ua, 'Android') !== false) {
                                        $iconClass = 'fa-android';
                                        $deviceClass = 'android';
                                        $deviceName = 'Android';
                                    } elseif (strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false) {
                                        $iconClass = 'fa-apple';
                                        $deviceClass = 'apple';
                                        $deviceName = strpos($ua, 'iPhone') !== false ? 'iPhone' : 'iPad';
                                    } elseif (strpos($ua, 'Linux') !== false) {
                                        $iconClass = 'fa-linux';
                                        $deviceClass = 'linux';
                                        $deviceName = 'Linux';
                                    }
                                    
                                    // Detectar navegador
                                    $browser = 'Navegador';
                                    if (strpos($ua, 'Chrome') !== false) {
                                        $browser = 'Chrome';
                                    } elseif (strpos($ua, 'Firefox') !== false) {
                                        $browser = 'Firefox';
                                    } elseif (strpos($ua, 'Safari') !== false) {
                                        $browser = 'Safari';
                                    } elseif (strpos($ua, 'Edge') !== false) {
                                        $browser = 'Edge';
                                    } elseif (strpos($ua, 'Opera') !== false) {
                                        $browser = 'Opera';
                                    }
                                    ?>
                                    <div class="dispositivo-icon <?php echo $deviceClass; ?>">
                                        <i class="fab <?php echo $iconClass; ?>"></i>
                                    </div>
                                    <div class="dispositivo-details">
                                        <span class="dispositivo-name"><?php echo $deviceName; ?></span>
                                        <span class="dispositivo-browser"><?php echo $browser; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (isset($acceso['exito']) && $acceso['exito']): ?>
                                    <span class="estado-exitoso">Exitoso</span>
                                <?php else: ?>
                                    <span class="estado-fallido">Fallido</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($acceso['detalles']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php } ?>
        </div>
        
        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="paginacion">
            <?php if ($pagina > 1): ?>
                <a href="?pagina=1<?php echo isset($_GET['estado']) ? '&estado='.htmlspecialchars($_GET['estado']) : ''; ?><?php echo isset($_GET['desde']) ? '&desde='.htmlspecialchars($_GET['desde']) : ''; ?><?php echo isset($_GET['hasta']) ? '&hasta='.htmlspecialchars($_GET['hasta']) : ''; ?><?php echo isset($_GET['registros']) ? '&registros='.intval($_GET['registros']) : ''; ?>" class="btn-paginacion">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?pagina=<?php echo $pagina - 1; ?><?php echo isset($_GET['estado']) ? '&estado='.htmlspecialchars($_GET['estado']) : ''; ?><?php echo isset($_GET['desde']) ? '&desde='.htmlspecialchars($_GET['desde']) : ''; ?><?php echo isset($_GET['hasta']) ? '&hasta='.htmlspecialchars($_GET['hasta']) : ''; ?><?php echo isset($_GET['registros']) ? '&registros='.intval($_GET['registros']) : ''; ?>" class="btn-paginacion">
                    <i class="fas fa-angle-left"></i>
                </a>
            <?php endif; ?>
            
            <?php
            $rango = 2;
            $inicio = max(1, $pagina - $rango);
            $fin = min($total_paginas, $pagina + $rango);
            
            if ($inicio > 1) {
                echo '<span class="ellipsis">...</span>';
            }
            
            for ($i = $inicio; $i <= $fin; $i++): ?>
                <a href="?pagina=<?php echo $i; ?><?php echo isset($_GET['estado']) ? '&estado='.htmlspecialchars($_GET['estado']) : ''; ?><?php echo isset($_GET['desde']) ? '&desde='.htmlspecialchars($_GET['desde']) : ''; ?><?php echo isset($_GET['hasta']) ? '&hasta='.htmlspecialchars($_GET['hasta']) : ''; ?><?php echo isset($_GET['registros']) ? '&registros='.intval($_GET['registros']) : ''; ?>" class="btn-paginacion <?php echo $i === $pagina ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; 
            
            if ($fin < $total_paginas) {
                echo '<span class="ellipsis">...</span>';
            }
            ?>
            
            <?php if ($pagina < $total_paginas): ?>
                <a href="?pagina=<?php echo $pagina + 1; ?><?php echo isset($_GET['estado']) ? '&estado='.htmlspecialchars($_GET['estado']) : ''; ?><?php echo isset($_GET['desde']) ? '&desde='.htmlspecialchars($_GET['desde']) : ''; ?><?php echo isset($_GET['hasta']) ? '&hasta='.htmlspecialchars($_GET['hasta']) : ''; ?><?php echo isset($_GET['registros']) ? '&registros='.intval($_GET['registros']) : ''; ?>" class="btn-paginacion">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?pagina=<?php echo $total_paginas; ?><?php echo isset($_GET['estado']) ? '&estado='.htmlspecialchars($_GET['estado']) : ''; ?><?php echo isset($_GET['desde']) ? '&desde='.htmlspecialchars($_GET['desde']) : ''; ?><?php echo isset($_GET['hasta']) ? '&hasta='.htmlspecialchars($_GET['hasta']) : ''; ?><?php echo isset($_GET['registros']) ? '&registros='.intval($_GET['registros']) : ''; ?>" class="btn-paginacion">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="resumen">
            <p>Mostrando <?php echo count($accesos); ?> de <?php echo $total_registros; ?> registros</p>
        </div>
    </main>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mejora para alternancia de colores en filas para mejor legibilidad
        const filas = document.querySelectorAll('.tabla-historial tbody tr');
        filas.forEach((fila, index) => {
            if (index % 2 === 1) {
                fila.classList.add('fila-alternada');
            }

            // Añadir efecto hover más suave
            fila.addEventListener('mouseenter', function() {
                this.style.transition = 'background-color 0.2s';
                this.style.backgroundColor = '#f1f5f9';
            });
            
            fila.addEventListener('mouseleave', function() {
                if (index % 2 === 1) {
                    this.style.backgroundColor = '#f9fafc';
                } else {
                    this.style.backgroundColor = '';
                }
            });
        });
        
        // Añadir comportamiento para mostrar más información al pasar el mouse
        const dispositivosCeldas = document.querySelectorAll('td .dispositivo');
        dispositivosCeldas.forEach(celda => {
            const textoCompleto = celda.querySelector('span').textContent;
            
            if (textoCompleto.length > 50) {
                const textoCorto = textoCompleto.substring(0, 47) + '...';
                const span = celda.querySelector('span');
                span.textContent = textoCorto;
                span.title = textoCompleto;
                
                // Mostrar tooltip al pasar el mouse
                celda.addEventListener('mouseover', function() {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = textoCompleto;
                    document.body.appendChild(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.top = (rect.bottom + window.scrollY) + 'px';
                    tooltip.style.left = rect.left + 'px';
                    
                    // Eliminar tooltip al quitar el mouse
                    celda.addEventListener('mouseout', function() {
                        tooltip.remove();
                    });
                });
            }
        });
        
        // Añadir validación a los campos de fecha
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
