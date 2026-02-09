<?php
/**
 * Protección contra ataques de fuerza bruta
 * Este archivo implementa bloqueos temporales después de múltiples intentos fallidos
 */

class ProteccionFuerzaBruta {
    private $conexion;
    public $max_intentos = 5; // Número máximo de intentos fallidos permitidos
    public $tiempo_bloqueo = 900; // Tiempo de bloqueo en segundos (15 minutos)
    private $tabla_bloqueos = 'bloqueos_acceso';
    
    public function __construct($conexion) {
        $this->conexion = $conexion;
        $this->verificarTabla();
    }
    
    /**
     * Verifica si existe la tabla de bloqueos y la crea si no existe
     */
    private function verificarTabla() {
        try {
            // Verificar si la tabla existe
            $stmt = $this->conexion->query("SHOW TABLES LIKE '{$this->tabla_bloqueos}'");
            
            if ($stmt->rowCount() == 0) {
                // La tabla no existe, crearla
                $sql = "CREATE TABLE {$this->tabla_bloqueos} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip VARCHAR(45) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    intentos INT NOT NULL DEFAULT 1,
                    ultimo_intento DATETIME NOT NULL,
                    bloqueado_hasta DATETIME NULL,
                    INDEX (ip),
                    INDEX (email),
                    UNIQUE KEY ip_email (ip, email)
                )";
                
                $this->conexion->exec($sql);
            }
        } catch (PDOException $e) {
            // Registrar el error pero continuar
            error_log("Error al verificar/crear tabla de bloqueos: " . $e->getMessage());
        }
    }
    
    /**
     * Registra un intento fallido y aplica bloqueo si es necesario
     */
    public function registrarIntentoFallido($email, $ip) {
        try {
            // Comprobar si ya existe un registro para esta IP y email
            $stmt = $this->conexion->prepare("SELECT * FROM {$this->tabla_bloqueos} WHERE ip = ? AND email = ?");
            $stmt->execute([$ip, $email]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($registro) {
                // Ya existe un registro, actualizar intentos
                $intentos = $registro['intentos'] + 1;
                
                // Determinar si debemos bloquear
                $bloqueado_hasta = null;
                if ($intentos >= $this->max_intentos) {
                    $bloqueado_hasta = date('Y-m-d H:i:s', time() + $this->tiempo_bloqueo);
                }
                
                $stmt = $this->conexion->prepare("UPDATE {$this->tabla_bloqueos} 
                    SET intentos = ?, ultimo_intento = NOW(), bloqueado_hasta = ? 
                    WHERE ip = ? AND email = ?");
                $stmt->execute([$intentos, $bloqueado_hasta, $ip, $email]);
            } else {
                // No existe registro, crear uno nuevo
                $stmt = $this->conexion->prepare("INSERT INTO {$this->tabla_bloqueos} 
                    (ip, email, intentos, ultimo_intento) 
                    VALUES (?, ?, 1, NOW())");
                $stmt->execute([$ip, $email]);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error al registrar intento fallido: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reinicia los intentos fallidos después de un inicio de sesión exitoso
     */
    public function reiniciarIntentos($email, $ip) {
        try {
            $stmt = $this->conexion->prepare("DELETE FROM {$this->tabla_bloqueos} 
                WHERE ip = ? AND email = ?");
            $stmt->execute([$ip, $email]);
            return true;
        } catch (PDOException $e) {
            error_log("Error al reiniciar intentos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica si un usuario está bloqueado
     * @return array ['bloqueado' => bool, 'tiempo_restante' => int]
     */
    public function verificarBloqueo($email, $ip) {
        try {
            $stmt = $this->conexion->prepare("SELECT bloqueado_hasta, intentos FROM {$this->tabla_bloqueos} 
                WHERE ip = ? AND email = ?");
            $stmt->execute([$ip, $email]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $resultado = ['bloqueado' => false, 'tiempo_restante' => 0, 'intentos' => 0];
            
            if ($registro) {
                $resultado['intentos'] = $registro['intentos'];
                
                if ($registro['bloqueado_hasta']) {
                    $ahora = time();
                    $bloqueado_hasta = strtotime($registro['bloqueado_hasta']);
                    
                    if ($ahora < $bloqueado_hasta) {
                        $resultado['bloqueado'] = true;
                        $resultado['tiempo_restante'] = $bloqueado_hasta - $ahora;
                    } else {
                        // El bloqueo ha expirado, pero mantenemos el contador de intentos
                        $stmt = $this->conexion->prepare("UPDATE {$this->tabla_bloqueos} 
                            SET bloqueado_hasta = NULL 
                            WHERE ip = ? AND email = ?");
                        $stmt->execute([$ip, $email]);
                    }
                }
            }
            
            return $resultado;
        } catch (PDOException $e) {
            error_log("Error al verificar bloqueo: " . $e->getMessage());
            return ['bloqueado' => false, 'tiempo_restante' => 0, 'intentos' => 0];
        }
    }
    
    /**
     * Formatea el tiempo restante para mostrar al usuario
     */
    public function formatearTiempoRestante($segundos) {
        if ($segundos < 60) {
            return "$segundos segundos";
        } elseif ($segundos < 3600) {
            $minutos = floor($segundos / 60);
            $segundos_restantes = $segundos % 60;
            return "$minutos minutos y $segundos_restantes segundos";
        } else {
            $horas = floor($segundos / 3600);
            $minutos = floor(($segundos % 3600) / 60);
            return "$horas horas y $minutos minutos";
        }
    }
}
?>
