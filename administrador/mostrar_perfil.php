<?php
session_start();
include 'auth.php';
include 'csrf_protection.php';
require_once(__DIR__ . '/../conexion/conexion.php');

// Obtener datos del usuario
$user_id = $_SESSION['usuario_id'];
$stmt = $conexion->prepare("SELECT nombre, apellido, cargo, area, rol, avatar, cumple, acerca_de_mi, telefono, direccion, fecha_nacimiento, estado_civil, emergencia_contacto, emergencia_telefono FROM usuarios WHERE id = :id");
$stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    $_SESSION['titulo'] = 'Error';
    $_SESSION['mensaje'] = 'Usuario no encontrado';
    $_SESSION['tipo_alerta'] = 'error';
    header('Location: index.php');
    exit;
}

// --- AJAX para actualizar "Acerca de mí" ---
if (isset($_POST['actualizar_acerca'])) {
    verificar_csrf();
    $acerca = trim($_POST['acerca_de_mi']);
    
    try {
        $updateAcerca = $conexion->prepare("UPDATE usuarios SET acerca_de_mi = :acerca WHERE id = :id");
        $updateAcerca->bindParam(':acerca', $acerca);
        $updateAcerca->bindParam(':id', $user_id, PDO::PARAM_INT);
        $updateAcerca->execute();

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'texto' => nl2br(htmlspecialchars($acerca))
            ]);
            exit;
        } else {
            $_SESSION['titulo'] = 'Información actualizada';
            $_SESSION['mensaje'] = 'Tu información "Acerca de mí" ha sido actualizada correctamente';
            $_SESSION['tipo_alerta'] = 'success';
        }
    } catch (Exception $e) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
            exit;
        } else {
            $_SESSION['titulo'] = 'Error';
            $_SESSION['mensaje'] = 'No se pudo actualizar la información';
            $_SESSION['tipo_alerta'] = 'error';
        }
    }
}

// --- Actualizar cumpleaños ---
if (isset($_POST['nuevo_cumple'])) {
    verificar_csrf();
    $cumple = $_POST['nuevo_cumple'];
    
    try {
        // Sincronizar cumple con fecha_nacimiento
        $updateCumple = $conexion->prepare("UPDATE usuarios SET cumple = :cumple, fecha_nacimiento = :fecha_nacimiento WHERE id = :id");
        $updateCumple->bindParam(':cumple', $cumple);
        $updateCumple->bindParam(':fecha_nacimiento', $cumple);
        $updateCumple->bindParam(':id', $user_id, PDO::PARAM_INT);
        $updateCumple->execute();
        
        $_SESSION['titulo'] = 'Fecha actualizada';
        $_SESSION['mensaje'] = 'Tu fecha de cumpleaños ha sido actualizada correctamente';
        $_SESSION['tipo_alerta'] = 'success';
        header('Location: mostrar_perfil.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['titulo'] = 'Error';
        $_SESSION['mensaje'] = 'No se pudo actualizar la fecha de cumpleaños';
        $_SESSION['tipo_alerta'] = 'error';
    }
}

// --- Cambiar avatar ---
if (isset($_POST['cambiar_avatar']) && isset($_FILES['avatar'])) {
    verificar_csrf();
    $file = $_FILES['avatar'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $maxSize = 5 * 1024 * 1024; // 5MB (unificado con usuario)

        // Validar MIME type real del archivo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_real = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $mimes_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) && $file['size'] <= $maxSize && in_array($mime_real, $mimes_permitidos)) {
            // Formato unificado: user_{id}.jpg (siempre JPG)
            $avatarName = 'user_' . $user_id . '.jpg';
            $destino = '../Img/Avatars/' . $avatarName;
            
            // Crear directorio si no existe
            if (!is_dir('../Img/Avatars/')) {
                mkdir('../Img/Avatars/', 0755, true);
            }
            
            // Eliminar avatar anterior con formato antiguo si existe
            $oldAvatar = '../Img/Avatars/avatar_' . $user_id . '.*';
            foreach (glob($oldAvatar) as $oldFile) {
                unlink($oldFile);
            }
            
            // Redimensionar y convertir a JPG
            $resultado = redimensionarAvatar($file['tmp_name'], $destino, 200, 200);
            
            if ($resultado) {
                try {
                    // Guardar nombre en BD para compatibilidad
                    $update = $conexion->prepare("UPDATE usuarios SET avatar = :avatar WHERE id = :id");
                    $update->bindParam(':avatar', $avatarName);
                    $update->bindParam(':id', $user_id, PDO::PARAM_INT);
                    $update->execute();

                    $_SESSION['titulo'] = 'Avatar actualizado';
                    $_SESSION['mensaje'] = 'Tu avatar se ha cambiado correctamente';
                    $_SESSION['tipo_alerta'] = 'success';
                    header('Location: mostrar_perfil.php');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['titulo'] = 'Error';
                    $_SESSION['mensaje'] = 'No se pudo actualizar el avatar en la base de datos';
                    $_SESSION['tipo_alerta'] = 'error';
                }
            } else {
                $_SESSION['titulo'] = 'Error';
                $_SESSION['mensaje'] = 'No se pudo procesar la imagen';
                $_SESSION['tipo_alerta'] = 'error';
            }
        } else {
            // Mensaje informativo si el MIME real no coincide con la extensión
            $detalle = '';
            if (!in_array($mime_real, $mimes_permitidos) && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $detalle = " El archivo parece ser de tipo {$mime_real}, no un .{$ext} real.";
            }
            $_SESSION['titulo'] = 'Archivo no válido';
            $_SESSION['mensaje'] = "Formato no permitido o archivo muy grande (máx 5MB). Use JPG, JPEG, PNG, GIF o WebP.{$detalle}";
            $_SESSION['tipo_alerta'] = 'error';
        }
    } else {
        $_SESSION['titulo'] = 'Error de carga';
        $_SESSION['mensaje'] = 'Hubo un problema al subir el archivo';
        $_SESSION['tipo_alerta'] = 'error';
    }
}

/**
 * Función para redimensionar y convertir avatar a JPG
 */
function redimensionarAvatar($origen, $destino, $ancho_max, $alto_max) {
    $info = getimagesize($origen);
    if (!$info) return false;
    
    $ancho_orig = $info[0];
    $alto_orig = $info[1];
    $tipo = $info[2];
    
    // Crear imagen desde el archivo original
    switch ($tipo) {
        case IMAGETYPE_JPEG:
            $imagen_orig = imagecreatefromjpeg($origen);
            break;
        case IMAGETYPE_PNG:
            $imagen_orig = imagecreatefrompng($origen);
            break;
        case IMAGETYPE_GIF:
            $imagen_orig = imagecreatefromgif($origen);
            break;
        case IMAGETYPE_WEBP:
            $imagen_orig = imagecreatefromwebp($origen);
            break;
        default:
            return false;
    }
    
    if (!$imagen_orig) return false;
    
    // Calcular proporciones para recorte cuadrado centrado
    $lado = min($ancho_orig, $alto_orig);
    $x_offset = ($ancho_orig - $lado) / 2;
    $y_offset = ($alto_orig - $lado) / 2;
    
    // Crear nueva imagen cuadrada
    $imagen_nueva = imagecreatetruecolor($ancho_max, $alto_max);
    
    // Fondo blanco
    $blanco = imagecolorallocate($imagen_nueva, 255, 255, 255);
    imagefill($imagen_nueva, 0, 0, $blanco);
    
    // Redimensionar con recorte centrado
    imagecopyresampled(
        $imagen_nueva, $imagen_orig,
        0, 0, $x_offset, $y_offset,
        $ancho_max, $alto_max,
        $lado, $lado
    );
    
    // Guardar como JPEG
    $resultado = imagejpeg($imagen_nueva, $destino, 90);
    
    // Limpiar memoria
    imagedestroy($imagen_orig);
    imagedestroy($imagen_nueva);
    
    return $resultado;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Portal Gestión Humana</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar.css">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/usuarios.css">
    <link rel="stylesheet" href="/gh/Css/mostrar_perfil.css">
    <link rel="icon" href="/gh/Img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="layout-admin">
    <?php include __DIR__ . "/Modulos/navbar.php"; ?>
    <main class="contenido-principal">
        <!-- Header universal -->
        <div class="header-universal">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-user-circle"></i>
                    <h1>Mi Perfil</h1>
                </div>
                <div class="header-info">
                    <span class="info-text">Gestiona tu información personal y configuración de cuenta</span>
                </div>
            </div>
        </div>

        <div class="perfil-container-modern">
            <!-- Sección de Avatar -->
            <div class="profile-section">
                <div class="section-header">
                    <h3 class="section-title-professional">
                        <i class="fas fa-camera"></i> Foto de Perfil
                    </h3>
                </div>
                <div class="avatar-section">
                    <div class="avatar-wrapper-modern">
                        <?php 
                        // Buscar avatar con formato unificado user_{id}.jpg
                        $avatar_path = "/gh/Img/Avatars/user_" . $user_id . ".jpg";
                        $avatar_file = $_SERVER['DOCUMENT_ROOT'] . $avatar_path;
                        
                        // También verificar formato antiguo por compatibilidad
                        $old_avatar_path = $usuario['avatar'] ? "/gh/Img/Avatars/" . $usuario['avatar'] : null;
                        $old_avatar_file = $old_avatar_path ? $_SERVER['DOCUMENT_ROOT'] . $old_avatar_path : null;
                        
                        if (file_exists($avatar_file)): 
                        ?>
                            <img src="<?php echo $avatar_path; ?>?v=<?php echo time(); ?>" alt="Avatar" id="avatar-preview">
                        <?php elseif ($old_avatar_file && file_exists($old_avatar_file)): ?>
                            <img src="<?php echo $old_avatar_path; ?>?v=<?php echo time(); ?>" alt="Avatar" id="avatar-preview">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <div class="avatar-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <form action="mostrar_perfil.php" method="post" enctype="multipart/form-data" class="avatar-form">
                        <?php echo campo_csrf_token(); ?>
                        <div class="file-input-group">
                            <label for="avatar-input" class="file-input-label">
                                <i class="fas fa-upload"></i> Seleccionar Imagen
                            </label>
                            <input type="file" 
                                   id="avatar-input" 
                                   name="avatar" 
                                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" 
                                   class="file-input">
                            <span class="file-info">JPG, JPEG, PNG, GIF, WebP - Máx. 5MB</span>
                        </div>
                        <button type="submit" name="cambiar_avatar" class="btn-primary-modern">
                            <i class="fas fa-save"></i> Actualizar Avatar
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sección de Información Personal -->
            <div class="profile-section">
                <div class="section-header">
                    <h3 class="section-title-professional">
                        <i class="fas fa-id-card"></i> Información Personal
                    </h3>
                </div>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Nombre Completo</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Cargo</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($usuario['cargo'] ?: 'No especificado'); ?>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Área</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($usuario['area'] ?: 'No especificada'); ?>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Rol en el Sistema</div>
                        <div class="info-value">
                            <span class="role-badge <?php echo $usuario['rol'] === 'admin' ? 'admin' : 'user'; ?>">
                                <?php echo $usuario['rol'] === 'admin' ? 'Administrador' : 'Usuario'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección de Cumpleaños -->
            <div class="profile-section">
                <div class="section-header">
                    <h3 class="section-title-professional">
                        <i class="fas fa-birthday-cake"></i> Fecha de Cumpleaños
                    </h3>
                </div>
                <div class="birthday-section">
                    <div class="birthday-display" id="birthday-display">
                        <span class="birthday-value">
                            <?php echo $usuario['cumple'] ? date('d/m/Y', strtotime($usuario['cumple'])) : 'No registrado'; ?>
                        </span>
                        <button type="button" id="edit-birthday-btn" class="edit-btn">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                    <form id="birthday-form" method="post" class="birthday-form" style="display: none;">
                        <?php echo campo_csrf_token(); ?>
                        <div class="input-group-modern">
                            <input type="date" 
                                   name="nuevo_cumple" 
                                   value="<?php echo htmlspecialchars($usuario['cumple']); ?>"
                                   class="date-input">
                            <div class="form-buttons-inline">
                                <button type="submit" class="btn-save-small">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button type="button" id="cancel-birthday-btn" class="btn-cancel-small">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sección Acerca de Mí -->
            <div class="profile-section">
                <div class="section-header">
                    <h3 class="section-title-professional">
                        <i class="fas fa-user-edit"></i> Acerca de Mí
                    </h3>
                </div>
                <div class="about-section">
                    <div class="about-display" id="about-display">
                        <div class="about-text" id="about-text">
                            <?php echo $usuario['acerca_de_mi'] ? nl2br(htmlspecialchars($usuario['acerca_de_mi'])) : '<em class="placeholder-text">Cuéntanos algo sobre ti...</em>'; ?>
                        </div>
                        <button type="button" id="edit-about-btn" class="edit-btn">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                    <form id="about-form" method="post" class="about-form" style="display: none;">
                        <?php echo campo_csrf_token(); ?>
                        <div class="textarea-group">
                            <textarea name="acerca_de_mi" 
                                      id="about-textarea"
                                      rows="4" 
                                      placeholder="Escribe algo sobre ti, tus intereses, experiencia, etc."
                                      maxlength="500"><?php echo htmlspecialchars($usuario['acerca_de_mi']); ?></textarea>
                            <div class="char-counter">
                                <span id="char-count">0</span>/500 caracteres
                            </div>
                        </div>
                        <div class="form-buttons-inline">
                            <button type="submit" class="btn-save-modern">
                                <i class="fas fa-save"></i> Guardar
                            </button>
                            <button type="button" id="cancel-about-btn" class="btn-cancel-modern">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sección de Acciones Rápidas -->
            <div class="profile-section">
                <div class="section-header">
                    <h3 class="section-title-professional">
                        <i class="fas fa-tools"></i> Acciones Rápidas
                    </h3>
                </div>
                <div class="quick-actions">
                    <a href="cambiar_contraseña.php" class="quick-action-btn">
                        <i class="fas fa-key"></i>
                        <span>Cambiar Contraseña</span>
                    </a>
                    <a href="index.php" class="quick-action-btn">
                        <i class="fas fa-home"></i>
                        <span>Ir al Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
        <?php require_once '../mensaje_alerta.php'; ?>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preview de avatar
    const avatarInput = document.getElementById('avatar-input');
    const avatarPreview = document.getElementById('avatar-preview');
    
    if (avatarInput) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validar tipo de archivo (MIME + extensión como fallback)
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                const validExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                const fileExt = file.name.split('.').pop().toLowerCase();
                const mimeOk = validTypes.includes(file.type);
                const extOk = validExts.includes(fileExt);
                
                if (!mimeOk && !extOk) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Archivo no válido',
                        text: 'Solo se permiten archivos JPG, JPEG, PNG, GIF y WebP (detectado: ' + (file.type || 'desconocido') + ')'
                    });
                    e.target.value = '';
                    return;
                }
                
                // Validar tamaño
                if (file.size > 5 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Archivo muy grande',
                        text: 'El archivo debe ser menor a 5MB'
                    });
                    e.target.value = '';
                    return;
                }
                
                // Preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (avatarPreview) {
                        avatarPreview.src = e.target.result;
                    } else {
                        // Si no hay imagen, crear una
                        const avatarWrapper = document.querySelector('.avatar-wrapper-modern');
                        avatarWrapper.innerHTML = `<img src="${e.target.result}" alt="Avatar" id="avatar-preview">`;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Editar cumpleaños
    const editBirthdayBtn = document.getElementById('edit-birthday-btn');
    const birthdayForm = document.getElementById('birthday-form');
    const birthdayDisplay = document.getElementById('birthday-display');
    const cancelBirthdayBtn = document.getElementById('cancel-birthday-btn');

    if (editBirthdayBtn) {
        editBirthdayBtn.addEventListener('click', function() {
            birthdayDisplay.style.display = 'none';
            birthdayForm.style.display = 'block';
        });
    }

    if (cancelBirthdayBtn) {
        cancelBirthdayBtn.addEventListener('click', function() {
            birthdayForm.style.display = 'none';
            birthdayDisplay.style.display = 'flex';
        });
    }

    // Editar "Acerca de mí"
    const editAboutBtn = document.getElementById('edit-about-btn');
    const aboutForm = document.getElementById('about-form');
    const aboutDisplay = document.getElementById('about-display');
    const cancelAboutBtn = document.getElementById('cancel-about-btn');
    const aboutTextarea = document.getElementById('about-textarea');
    const charCount = document.getElementById('char-count');

    if (editAboutBtn) {
        editAboutBtn.addEventListener('click', function() {
            aboutDisplay.style.display = 'none';
            aboutForm.style.display = 'block';
            aboutTextarea.focus();
            updateCharCount();
        });
    }

    if (cancelAboutBtn) {
        cancelAboutBtn.addEventListener('click', function() {
            aboutForm.style.display = 'none';
            aboutDisplay.style.display = 'flex';
        });
    }

    // Contador de caracteres
    function updateCharCount() {
        if (aboutTextarea && charCount) {
            const count = aboutTextarea.value.length;
            charCount.textContent = count;
            
            if (count > 450) {
                charCount.style.color = '#ef4444';
            } else if (count > 400) {
                charCount.style.color = '#f59e0b';
            } else {
                charCount.style.color = '#6b7280';
            }
        }
    }

    if (aboutTextarea) {
        aboutTextarea.addEventListener('input', updateCharCount);
        updateCharCount(); // Inicializar contador
    }

    // AJAX para "Acerca de mí"
    if (aboutForm) {
        aboutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('actualizar_acerca', '1');
            
            fetch('mostrar_perfil.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Información actualizada',
                        text: 'Tu información ha sido actualizada correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Actualizar el texto mostrado
                    const aboutText = document.getElementById('about-text');
                    if (data.texto.trim()) {
                        aboutText.innerHTML = data.texto;
                    } else {
                        aboutText.innerHTML = '<em class="placeholder-text">Cuéntanos algo sobre ti...</em>';
                    }
                    
                    // Volver a la vista de display
                    aboutForm.style.display = 'none';
                    aboutDisplay.style.display = 'flex';
                    aboutTextarea.value = '';
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'No se pudo actualizar la información'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Ocurrió un error al procesar la solicitud'
                });
            });
        });
    }
});
</script>
</body>
</html>