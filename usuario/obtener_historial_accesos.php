<?php
/**
 * Obtiene y muestra el historial de accesos del usuario
 * Este archivo se carga mediante una petición AJAX
 */

session_start();
include 'auth.php';
require_once __DIR__ . "/../conexion/conexion.php";

$usuario_id = $_SESSION['usuario_id'];

try {
    // Obtener los últimos 15 accesos del usuario
    $stmt = $conexion->prepare("
        SELECT 
            id, 
            fecha_acceso, 
            ip_acceso, 
            dispositivo, 
            navegador, 
            exito, 
            detalles 
        FROM historial_accesos 
        WHERE usuario_id = :usuario_id 
        ORDER BY fecha_acceso DESC 
        LIMIT 15
    ");
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $accesos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($accesos) === 0) {
        echo '<div class="no-historial">
                <i class="fas fa-info-circle"></i>
                <p>No se encontraron registros de accesos a tu cuenta.</p>
                <p class="no-historial-sub">Los nuevos accesos comenzarán a registrarse desde ahora.</p>
              </div>';
    } else {
        // Mostrar la tabla de historial
        ?>
        <button onclick="exportarHistorialCSV()" class="exportar-btn">
            <i class="fas fa-file-export"></i> Exportar Historial
        </button>
        
        <table class="historial-table">
            <thead>
                <tr>
                    <th><i class="fas fa-calendar-alt"></i> Fecha y Hora</th>
                    <th><i class="fas fa-globe"></i> Dirección IP</th>
                    <th><i class="fas fa-desktop"></i> Dispositivo</th>
                    <th><i class="fas fa-check-circle"></i> Estado</th>
                    <th><i class="fas fa-info-circle"></i> Detalles</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accesos as $acceso): ?>
                    <tr class="<?= $acceso['exito'] ? 'acceso-exito' : 'acceso-fallido' ?>">
                        <td>
                            <strong><?= date('d/m/Y', strtotime($acceso['fecha_acceso'])) ?></strong><br>
                            <small><?= date('H:i:s', strtotime($acceso['fecha_acceso'])) ?></small>
                        </td>
                        <td>
                            <?php if ($acceso['ip_acceso'] === '::1' || $acceso['ip_acceso'] === '127.0.0.1'): ?>
                                <span title="Conexión desde localhost">Local (Mismo equipo)</span>
                            <?php else: ?>
                                <?= htmlspecialchars($acceso['ip_acceso']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            // Simplificar información del dispositivo/navegador
                            $userAgent = htmlspecialchars($acceso['dispositivo']);
                            // Detectar sistema operativo
                            $os = "Desconocido";
                            $osIcon = "fas fa-question-circle";
                            
                            if (strpos($userAgent, 'Windows') !== false) {
                                $os = "Windows";
                                $osIcon = "fab fa-windows";
                            }
                            elseif (strpos($userAgent, 'Mac') !== false) {
                                $os = "Mac";
                                $osIcon = "fab fa-apple";
                            }
                            elseif (strpos($userAgent, 'Linux') !== false) {
                                $os = "Linux";
                                $osIcon = "fab fa-linux";
                            }
                            elseif (strpos($userAgent, 'Android') !== false) {
                                $os = "Android";
                                $osIcon = "fab fa-android";
                            }
                            elseif (strpos($userAgent, 'iOS') !== false) {
                                $os = "iOS";
                                $osIcon = "fab fa-apple";
                            }
                            
                            // Detectar navegador
                            $browser = "Otro";
                            $browserIcon = "fas fa-globe";
                            
                            if (strpos($userAgent, 'Chrome') !== false && strpos($userAgent, 'Edg') === false) {
                                $browser = "Chrome";
                                $browserIcon = "fab fa-chrome";
                            }
                            elseif (strpos($userAgent, 'Firefox') !== false) {
                                $browser = "Firefox";
                                $browserIcon = "fab fa-firefox";
                            }
                            elseif (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
                                $browser = "Safari";
                                $browserIcon = "fab fa-safari";
                            }
                            elseif (strpos($userAgent, 'Edg') !== false) {
                                $browser = "Edge";
                                $browserIcon = "fab fa-edge";
                            }
                            elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
                                $browser = "Internet Explorer";
                                $browserIcon = "fab fa-internet-explorer";
                            }
                            ?>
                            
                            <div title="<?= htmlspecialchars($acceso['dispositivo']) ?>">
                                <i class="<?= $osIcon ?>"></i> <?= $os ?><br>
                                <small><i class="<?= $browserIcon ?>"></i> <?= $browser ?></small>
                            </div>
                        </td>
                        <td>
                            <?php if($acceso['exito']): ?>
                                <span class="acceso-badge success">
                                    <i class="fas fa-check"></i> Exitoso
                                </span>
                            <?php else: ?>
                                <span class="acceso-badge error">
                                    <i class="fas fa-times"></i> Fallido
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($acceso['detalles'] ?? 'Inicio de sesión') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="historial-footer">
            <p><i class="fas fa-shield-alt"></i> Se muestran los últimos 15 accesos a tu cuenta</p>
            <p class="seguridad-tip">Si detectas un acceso no reconocido, te recomendamos cambiar tu contraseña inmediatamente.</p>
        </div>
        <?php
    }
    
} catch (PDOException $e) {
    echo '<div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Error al obtener el historial de accesos</p>
            <p class="error-details">Por favor, inténtalo nuevamente más tarde</p>
          </div>';
    
    // Log del error (no mostrado al usuario)
    error_log("Error al obtener historial de accesos: " . $e->getMessage());
}
