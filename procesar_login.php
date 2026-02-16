<?php
session_start();
require_once 'config.php';
require_once 'conexion/conexion.php';
require_once 'seguridad/proteccion_fuerza_bruta.php';
require_once 'administrador/csrf_protection.php';
require_once 'vendor/autoload.php';
require_once 'funciones/totp_helpers.php';

// Inicializar la protección contra fuerza bruta
$proteccion = new ProteccionFuerzaBruta($conexion);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF
    verificar_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Obtener IP real
    $ip = $_SERVER['REMOTE_ADDR'];
    if ($ip === '::1') {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
            $ip = filter_var(trim($forwarded), FILTER_VALIDATE_IP) ?: '127.0.0.1';
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP) ?: '127.0.0.1';
        } else {
            $ip = '127.0.0.1';
        }
    }

    // Verificar si el usuario está bloqueado
    if (!empty($email)) {
        $bloqueo = $proteccion->verificarBloqueo($email, $ip);

        if ($bloqueo['bloqueado']) {
            $_SESSION['titulo'] = 'Acceso bloqueado';
            $_SESSION['mensaje'] = 'Demasiados intentos fallidos. Por favor, intente nuevamente después de ' .
                                $proteccion->formatearTiempoRestante($bloqueo['tiempo_restante']);
            $_SESSION['tipo_alerta'] = 'error';
            header('Location: index.php');
            exit;
        }
    }

    if ($email === '' || $password === '') {
        $_SESSION['titulo'] = 'Error';
        $_SESSION['mensaje'] = 'Debe ingresar correo y contraseña';
        $_SESSION['tipo_alerta'] = 'error';
        header('Location: index.php');
        exit;
    }

    try {
        $stmt = $conexion->prepare('SELECT id, password_hash, nombre, rol, email, tiene_2fa, secreto_2fa FROM usuarios WHERE email = :email');
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            // Reiniciar contador de intentos fallidos
            $proteccion->reiniciarIntentos($email, $ip);

            $es_admin = ($usuario['rol'] === 'admin' || $usuario['rol'] === 'administrador');

            // ========== 2FA OBLIGATORIO PARA ADMINISTRADORES ==========
            // Admin sin secreto TOTP configurado → redirigir a configurar_2fa.php
            if ($es_admin && empty($usuario['secreto_2fa'])) {
                // Preparar sesión de configuración 2FA
                $_SESSION['2fa_setup_pendiente'] = true;
                $_SESSION['2fa_setup_usuario_id'] = $usuario['id'];
                $_SESSION['2fa_setup_email'] = $usuario['email'];
                $_SESSION['2fa_setup_es_admin'] = true;
                $_SESSION['2fa_setup_time'] = time();
                header('Location: configurar_2fa.php');
                exit;
            }
            // ========== FIN 2FA OBLIGATORIO PARA ADMINISTRADORES ==========

            // ========== VERIFICACIÓN 2FA (TOTP) ==========
            // Si tiene secreto_2fa configurado → pedir código TOTP
            if (!empty($usuario['secreto_2fa']) && !empty($usuario['tiene_2fa']) && $usuario['tiene_2fa'] == 1) {
                $_SESSION['2fa_pendiente'] = true;
                $_SESSION['2fa_usuario_id'] = $usuario['id'];
                $_SESSION['2fa_pendiente_time'] = time();
                header('Location: verificar_2fa.php');
                exit;
            }
            // ========== FIN VERIFICACIÓN 2FA ==========

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_rol'] = $usuario['rol'];

            // Actualizar la hora de creación de sesión para el control de tiempo
            $_SESSION['hora_creacion_sesion'] = time();
            $_SESSION['ultima_actividad'] = time();

            // Generar un nuevo token de sesión para prevenir ataques
            session_regenerate_id(true);
            $dispositivo = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
            $navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
            $detalles = 'Inicio de sesión exitoso';

            try {
                // Insertar directamente en la tabla con fecha explícita
                $stmt = $conexion->prepare("INSERT INTO historial_accesos (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles) VALUES (?, NOW(), ?, ?, ?, ?, ?)");
                $exito = 1; // Usar entero explícito para éxito
                $stmt->execute([$usuario['id'], $ip, $dispositivo, $navegador, $exito, $detalles]);
            } catch (PDOException $e) {
                // Solo registrar el error, no interrumpir el login
                error_log("Error al registrar acceso: " . $e->getMessage());
            }

            // Redireccionar según el rol del usuario
            if ($usuario['rol'] === 'admin' || $usuario['rol'] === 'administrador') {
                header('Location: administrador/index.php');
            } else {
                header('Location: usuario/index.php');
            }
            exit;
        } else {
            // Buscar si el usuario existe para registrar el intento fallido
            $stmt = $conexion->prepare('SELECT id FROM usuarios WHERE email = :email');
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $usuario_id = $stmt->fetchColumn();

            // Si el usuario existe, registrar el intento fallido
            if ($usuario_id) {
                $dispositivo = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
                $navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
                $detalles = 'Intento de inicio de sesión fallido - Contraseña incorrecta';

                try {
                    // Insertar directamente en la tabla con fecha explícita
                    $stmt = $conexion->prepare("INSERT INTO historial_accesos (usuario_id, fecha_acceso, ip_acceso, dispositivo, navegador, exito, detalles) VALUES (?, NOW(), ?, ?, ?, ?, ?)");
                    $exito = 0; // Usar entero explícito para fallo
                    $stmt->execute([$usuario_id, $ip, $dispositivo, $navegador, $exito, $detalles]);

                    // Registrar el intento fallido para la protección contra fuerza bruta
                    $proteccion->registrarIntentoFallido($email, $ip);

                    // Obtener información sobre el bloqueo
                    $bloqueo = $proteccion->verificarBloqueo($email, $ip);
                    $intentos_restantes = $proteccion->max_intentos - $bloqueo['intentos'];

                    if ($intentos_restantes > 0) {
                        $_SESSION['mensaje'] = 'Credenciales incorrectas. Le quedan ' . $intentos_restantes . ' intentos antes del bloqueo temporal.';
                    } else {
                        $_SESSION['mensaje'] = 'Credenciales incorrectas';
                    }
                } catch (PDOException $e) {
                    // Solo registrar el error, no interrumpir el login
                    error_log("Error al registrar acceso fallido: " . $e->getMessage());
                }
            } else {
                // Si el email no existe, también registramos el intento para evitar enumeración de usuarios
                $proteccion->registrarIntentoFallido($email, $ip);
            }

            $_SESSION['titulo'] = 'Error de autenticación';
            if (!isset($_SESSION['mensaje'])) {
                $_SESSION['mensaje'] = 'Credenciales incorrectas';
            }
            $_SESSION['tipo_alerta'] = 'error';
            header('Location: index.php');
            exit;
        }
    } catch (Exception $e) {
        error_log("Error en procesar_login: " . $e->getMessage());
        $_SESSION['titulo'] = 'Error';
        $_SESSION['mensaje'] = 'Error al procesar el inicio de sesión. Intente nuevamente.';
        $_SESSION['tipo_alerta'] = 'error';
        header('Location: index.php');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
