<?php
session_start();
require_once 'config.php';
require_once 'conexion/conexion.php';
require_once 'seguridad/proteccion_fuerza_bruta.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inicializar la protección contra fuerza bruta
$proteccion = new ProteccionFuerzaBruta($conexion);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Obtener IP real
    $ip = $_SERVER['REMOTE_ADDR'];
    if ($ip === '::1') {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
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
        $stmt = $conexion->prepare('SELECT id, password_hash, nombre, rol, email, tiene_2fa FROM usuarios WHERE email = :email');
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            // Reiniciar contador de intentos fallidos
            $proteccion->reiniciarIntentos($email, $ip);

            // ========== VERIFICACIÓN 2FA ==========
            if (!empty($usuario['tiene_2fa']) && $usuario['tiene_2fa'] == 1) {
                // Usuario tiene 2FA activo - generar código y redirigir
                try {
                    // Invalidar códigos anteriores
                    $stmt = $conexion->prepare("UPDATE codigos_2fa SET usado = 1 WHERE usuario_id = :id AND usado = 0");
                    $stmt->execute(['id' => $usuario['id']]);

                    // Generar código de 6 dígitos
                    $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $codigo_hash = password_hash($codigo, PASSWORD_DEFAULT);

                    // Guardar código en BD (usando NOW() de MySQL para evitar problemas de zona horaria)
                    $stmt = $conexion->prepare("
                        INSERT INTO codigos_2fa (usuario_id, codigo, codigo_hash, expira_en, ip_solicitud)
                        VALUES (:usuario_id, '******', :codigo_hash, DATE_ADD(NOW(), INTERVAL 5 MINUTE), :ip)
                    ");
                    $stmt->execute([
                        'usuario_id' => $usuario['id'],
                        'codigo_hash' => $codigo_hash,
                        'ip' => $ip
                    ]);

                    // Enviar código por email
                    $mail = new PHPMailer(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USER;
                    $mail->Password = SMTP_PASS;
                    $mail->Port = SMTP_PORT;

                    $mail->setFrom(SMTP_USER, config('SMTP_FROM_NAME', 'Selcomp - Portal GH'));
                    $mail->addAddress($usuario['email']);
                    $mail->Subject = 'Código de Verificación - Portal Gestión Humana';
                    $mail->isHTML(true);

                    // Adjuntar imágenes de firma usando CID
                    $mail->addEmbeddedImage(__DIR__ . '/Img/OlvidoFirma1.png', 'firmaContacto');
                    $mail->addEmbeddedImage(__DIR__ . '/Img/OlvidoFirma2.png', 'firmaLegal');

                    $mail->Body = '
                    <p>Hola <strong>' . htmlspecialchars($usuario['nombre']) . '</strong>,</p>
                    <p>Tu código de verificación para acceder al Portal Gestión Humana es:</p>
                    <div style="background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #d1164c; border-radius: 8px; margin: 20px 0;">
                        ' . $codigo . '
                    </div>
                    <p style="color: #666;">Este código expira en <strong>5 minutos</strong>.</p>
                    <p style="color: #666;">Si no intentaste iniciar sesión, cambia tu contraseña inmediatamente.</p>
                    <p style="font-size: 12px; color: #999;">IP de la solicitud: ' . $ip . '</p>
                    <br>
                    <img src="cid:firmaContacto" alt="Firma contacto Selcomp" style="max-width:650px;width:100%;display:block;margin-left:0;"><br>
                    <img src="cid:firmaLegal" alt="Aviso legal Selcomp" style="max-width:650px;width:100%;display:block;margin-left:0;">
                    ';
                    $mail->AltBody = "Tu código de verificación es: {$codigo}. Expira en 5 minutos.";
                    $mail->send();

                    // Guardar datos en sesión para verificación
                    $_SESSION['2fa_pendiente'] = true;
                    $_SESSION['2fa_usuario_id'] = $usuario['id'];
                    $_SESSION['2fa_pendiente_time'] = time(); // Para expiración

                    // Ocultar parte del email para mostrar (más seguro)
                    $email_parts = explode('@', $usuario['email']);
                    $name = $email_parts[0];
                    if (strlen($name) > 2) {
                        $email_parcial = substr($name, 0, 1) . str_repeat('*', strlen($name)-2) . substr($name, -1) . '@' . $email_parts[1];
                    } else {
                        $email_parcial = '**@' . $email_parts[1];
                    }
                    $_SESSION['2fa_email_parcial'] = $email_parcial;

                    // Redirigir a verificación 2FA
                    header('Location: verificar_2fa.php');
                    exit;

                } catch (Exception $e) {
                    error_log("Error al enviar código 2FA: " . $e->getMessage());
                    // Si falla el envío 2FA, NO permitir login - es crítico para seguridad
                    $_SESSION['titulo'] = 'Error de verificación';
                    $_SESSION['mensaje'] = 'No se pudo enviar el código de verificación. Por favor intenta más tarde o contacta al administrador.';
                    $_SESSION['tipo_alerta'] = 'error';
                    header('Location: index.php');
                    exit;
                }
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
