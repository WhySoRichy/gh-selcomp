<?php
session_start();
include 'auth.php';
require_once __DIR__ . '/../administrador/csrf_protection.php';
require_once "../conexion/conexion.php";

$usuario_id = $_SESSION['usuario_id'];

// Generar token CSRF
$csrf_token = generar_token_csrf();

// Obtener documentos del usuario organizados por tipo
try {
    $stmt = $conexion->prepare("
        SELECT id, tipo_documento, nombre_original, nombre_archivo, 
               ruta_archivo, tamano_archivo, fecha_subida, descripcion
        FROM documentos_usuarios 
        WHERE usuario_id = :usuario_id AND estado = 'activo'
        ORDER BY tipo_documento, fecha_subida DESC
    ");
    $stmt->bindParam(':usuario_id', $usuario_id);
    $stmt->execute();
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar documentos por tipo
    $documentos_por_tipo = [
        'hoja_vida' => [],
        'certificado' => [],
        'certificacion' => [],
        'experiencia_laboral' => [],
        'otros' => []
    ];
    
    foreach ($documentos as $doc) {
        $documentos_por_tipo[$doc['tipo_documento']][] = $doc;
    }
    
} catch (Exception $e) {
    $documentos_por_tipo = [
        'hoja_vida' => [],
        'certificado' => [],
        'certificacion' => [],
        'experiencia_laboral' => [],
        'otros' => []
    ];
}

// Configuración de tipos de documentos
$tipos_documentos = [
    'hoja_vida' => [
        'nombre' => 'Hojas de Vida',
        'icono' => 'fas fa-user-tie',
        'color' => '#404e62',
        'descripcion' => 'Currículum vitae y perfiles profesionales'
    ],
    'certificado' => [
        'nombre' => 'Certificados Académicos',
        'icono' => 'fas fa-graduation-cap',
        'color' => '#404e62',
        'descripcion' => 'Diplomas, títulos y certificados de estudio'
    ],
    'certificacion' => [
        'nombre' => 'Formación Adicional',
        'icono' => 'fas fa-award',
        'color' => '#404e62',
        'descripcion' => 'Certificaciones técnicas y profesionales'
    ],
    'experiencia_laboral' => [
        'nombre' => 'Experiencia Laboral',
        'icono' => 'fas fa-briefcase',
        'color' => '#404e62',
        'descripcion' => 'Cartas de recomendación y certificados laborales'
    ],
    'otros' => [
        'nombre' => 'Otros Documentos',
        'icono' => 'fas fa-file-alt',
        'color' => '#404e62',
        'descripcion' => 'Documentos adicionales e información complementaria'
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Documentos - Portal Usuario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/dashboard_usuario.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/gh/Css/documentos_usuario.css?v=<?= time() ?>">
    <link rel="icon" href="/gh/Img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include __DIR__ . "/Modulos/navbar_usuario.php"; ?>
<main class="contenido-principal">
    <!-- Header universal -->
    <div class="header-universal">
        <div class="header-content">
            <div class="header-title">
                <i class="fas fa-folder-open"></i>
                <h1>Mis Documentos</h1>
            </div>
            <div class="header-stats">
                <div class="stat-item">
                    <span class="stat-number"><?= count($documentos) ?></span>
                    <span class="stat-label">Documentos</span>
                </div>
            </div>
        </div>
    </div>

    <div class="documentos-container">
        <!-- Grid de tipos de documentos -->
        <div class="documentos-grid">
            <?php foreach ($tipos_documentos as $tipo => $config): ?>
                <div class="documento-categoria" data-tipo="<?= $tipo ?>">
                    <div class="categoria-header">
                        <div class="categoria-icon" style="background: <?= $config['color'] ?>;">
                            <i class="<?= $config['icono'] ?>"></i>
                        </div>
                        <div class="categoria-info">
                            <h3><?= $config['nombre'] ?></h3>
                            <p><?= $config['descripcion'] ?></p>
                            <span class="documento-count"><?= count($documentos_por_tipo[$tipo]) ?> documento(s)</span>
                        </div>
                        <div class="categoria-actions">
                            <button class="btn-subir" onclick="abrirModalSubida('<?= $tipo ?>')">
                                <i class="fas fa-plus"></i>
                                Subir
                            </button>
                            <?php if (count($documentos_por_tipo[$tipo]) > 0): ?>
                                <button class="btn-ver" onclick="toggleDocumentos('<?= $tipo ?>')">
                                    <i class="fas fa-eye"></i>
                                    Ver
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (count($documentos_por_tipo[$tipo]) > 0): ?>
                        <div class="documentos-lista" id="lista-<?= $tipo ?>" style="display: none;">
                            <?php foreach ($documentos_por_tipo[$tipo] as $doc): ?>
                                <div class="documento-item">
                                    <div class="documento-info">
                                        <i class="fas fa-file-pdf"></i>
                                        <div class="documento-detalles">
                                            <h4><?= htmlspecialchars($doc['nombre_original']) ?></h4>
                                            <span class="documento-fecha">Subido: <?= date('d/m/Y H:i', strtotime($doc['fecha_subida'])) ?></span>
                                            <span class="documento-tamano"><?= number_format($doc['tamano_archivo'] / 1024, 0) ?> KB</span>
                                            <?php if ($doc['descripcion']): ?>
                                                <p class="documento-descripcion"><?= htmlspecialchars($doc['descripcion']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="documento-acciones">
                                        <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" target="_blank" class="btn-accion btn-ver">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" download class="btn-accion btn-descargar">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button class="btn-accion btn-eliminar" onclick="eliminarDocumento(<?= $doc['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Información importante -->
        <div class="info-documentos">
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="info-content">
                    <h3>Información Importante</h3>
                    <ul>
                        <li><strong>Formatos aceptados:</strong> PDF únicamente</li>
                        <li><strong>Tamaño máximo:</strong> 10 MB por archivo</li>
                        <li><strong>Documentos requeridos:</strong> La hoja de vida es obligatoria para postulaciones internas</li>
                        <li><strong>Seguridad:</strong> Tus documentos están protegidos y solo los ve RR.HH.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para subir documentos -->
    <div class="modal-overlay" id="modal-subir-documento">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-titulo">Subir Documento</h2>
                <button class="btn-cerrar" onclick="cerrarModalSubida()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="form-subir-documento" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="tipo-documento" name="tipo_documento">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <div class="form-group">
                        <label for="archivo-documento">Seleccionar archivo (PDF):</label>
                        <input type="file" id="archivo-documento" name="archivo" accept=".pdf" required>
                        <small>Máximo 10 MB - Solo archivos PDF</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion-documento">Descripción (opcional):</label>
                        <textarea id="descripcion-documento" name="descripcion" rows="3" 
                                placeholder="Describe brevemente el documento..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" onclick="cerrarModalSubida()">Cancelar</button>
                    <button type="submit" class="btn-subir-documento">
                        <i class="fas fa-upload"></i>
                        Subir Documento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php require_once '../mensaje_alerta.php'; ?>
    
    <script>
        // Configuración de tipos de documentos
        const tiposDocumentos = <?= json_encode($tipos_documentos) ?>;
        
        function abrirModalSubida(tipo) {
            const modal = document.getElementById('modal-subir-documento');
            const titulo = document.getElementById('modal-titulo');
            const tipoInput = document.getElementById('tipo-documento');
            
            titulo.textContent = `Subir ${tiposDocumentos[tipo].nombre}`;
            tipoInput.value = tipo;
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function cerrarModalSubida() {
            const modal = document.getElementById('modal-subir-documento');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            
            // Limpiar formulario
            document.getElementById('form-subir-documento').reset();
            
            // Rehabilitar botón si está deshabilitado
            const submitButton = document.querySelector('#form-subir-documento button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-upload"></i> Subir Documento';
            }
        }
        
        function toggleDocumentos(tipo) {
            const lista = document.getElementById(`lista-${tipo}`);
            const isVisible = lista.style.display !== 'none';
            
            if (isVisible) {
                lista.style.display = 'none';
            } else {
                lista.style.display = 'block';
            }
        }
        
        function eliminarDocumento(documentoId) {
            Swal.fire({
                title: '¿Eliminar documento?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#eb0045',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Eliminando...',
                        icon: 'info',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    fetch('procesar_documentos.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=eliminar&documento_id=${documentoId}&csrf_token=${document.getElementById('csrf_token').value}`
                    })
                    .then(response => {
                        return response.text().then(text => {
                            try {
                                const data = JSON.parse(text);
                                return { ok: response.ok, status: response.status, data };
                            } catch (e) {
                                return { ok: response.ok, status: response.status, data: { success: false, message: text || 'Error en el servidor' } };
                            }
                        });
                    })
                    .then(result => {
                        Swal.close();
                        
                        if (result.ok && result.data.success) {
                            Swal.fire({
                                title: '¡Eliminado!',
                                text: result.data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            
                            // Recargar después de que se cierre la alerta
                            setTimeout(() => {
                                location.reload();
                            }, 2500);
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: result.data.message || 'Error al eliminar el documento',
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        Swal.fire({
                            title: 'Error!',
                            text: 'Error de conexión al eliminar',
                            icon: 'error'
                        });
                    });
                }
            });
        }
        
        // Manejar envío del formulario
        document.getElementById('form-subir-documento').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const archivo = document.getElementById('archivo-documento').files[0];
            const submitButton = this.querySelector('button[type="submit"]');
            
            // Evitar doble envío
            if (submitButton.disabled) {
                return;
            }
            
            // Agregar action al FormData
            formData.append('action', 'subir');
            
            // Validaciones
            if (!archivo) {
                Swal.fire('Error!', 'Debes seleccionar un archivo', 'error');
                return;
            }
            
            if (archivo.type !== 'application/pdf') {
                Swal.fire('Error!', 'Solo se aceptan archivos PDF', 'error');
                return;
            }
            
            if (archivo.size > 10 * 1024 * 1024) { // 10 MB
                Swal.fire('Error!', 'El archivo no debe superar los 10 MB', 'error');
                return;
            }
            
            // Deshabilitar botón para evitar doble envío
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            
            // Mostrar loading
            Swal.fire({
                title: 'Subiendo documento...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Enviar archivo
            fetch('procesar_documentos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Intentar parsear JSON aunque haya error
                return response.text().then(text => {
                    try {
                        const data = JSON.parse(text);
                        return { ok: response.ok, status: response.status, data };
                    } catch (e) {
                        // Si no es JSON, devolver el texto crudo
                        return { ok: response.ok, status: response.status, data: { success: false, message: text || 'Error en el servidor' } };
                    }
                });
            })
            .then(result => {
                Swal.close(); // Cerrar loading
                
                if (result.ok && result.data.success) {
                    // Cerrar modal INMEDIATAMENTE
                    cerrarModalSubida();
                    
                    // Mostrar alerta de éxito sin recargar
                    Swal.fire({
                        title: '¡Éxito!',
                        text: result.data.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Recargar la lista de documentos sin recargar la página completa
                    setTimeout(() => {
                        location.reload();
                    }, 2500); // Esperar a que se cierre la alerta antes de recargar
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: result.data.message || 'Error al procesar la petición',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'Error al procesar la petición: ' + error.message, 'error');
            })
            .finally(() => {
                // Rehabilitar botón
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fas fa-upload"></i> Subir Documento';
            });
        });
        
        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalSubida();
            }
        });
        
        // Cerrar modal al hacer clic en el overlay
        document.getElementById('modal-subir-documento').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalSubida();
            }
        });
    </script>
</main>
</body>
</html>
