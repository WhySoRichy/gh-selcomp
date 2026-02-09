<?php
// Incluye el archivo de autenticación e inicia la sesión
session_start();
include '../auth.php';
include '../csrf_protection.php';
require_once "../../conexion/conexion.php";

// Generar token CSRF para las llamadas AJAX
$csrf_token = generar_token_csrf();

// Obtiene todos los documentos de los usuarios mediante la conexión a la base de datos
try {
    // Utilizamos GROUP BY para obtener solo un documento de cada tipo por usuario
    $stmt = $conexion->prepare("
        SELECT du.id, du.usuario_id, du.tipo_documento, du.nombre_archivo, 
               du.ruta_archivo, du.nombre_original, du.fecha_subida, 
               du.tamano_archivo, du.descripcion, du.estado,
               u.nombre, u.apellido 
        FROM documentos_usuarios du
        INNER JOIN usuarios u ON du.usuario_id = u.id
        WHERE du.estado = 'activo'
        GROUP BY du.id, du.usuario_id, du.tipo_documento, du.nombre_archivo, 
                 du.ruta_archivo, du.nombre_original, du.fecha_subida, 
                 du.tamano_archivo, du.descripcion, du.estado,
                 u.nombre, u.apellido
        ORDER BY du.fecha_subida DESC
    ");
    $stmt->execute();
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Inicializar array para archivos procesados
    $archivos_procesados = [];
    
    // Obtener archivos de postulaciones desde la tabla postulaciones
    // y asegurarnos de NO incluir los que también están en la tabla documentos
    $stmt = $conexion->prepare("
        SELECT p.*, v.titulo as vacante_titulo 
        FROM postulaciones p 
        LEFT JOIN vacantes v ON p.vacante_id = v.id 
        ORDER BY p.fecha_postulacion DESC
    ");
    $stmt->execute();
    $postulaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar postulaciones al array de archivos procesados
    foreach ($postulaciones as $postulacion) {
        $archivos_procesados[] = [
            'nombre_archivo' => basename($postulacion['archivo']),
            'ruta_archivo' => "/gh/{$postulacion['archivo']}",
            'tipo_documento' => 'postulacion',
            'nombre_original' => basename($postulacion['archivo']),
            'fecha_subida' => $postulacion['fecha_postulacion'],
            'nombre' => $postulacion['nombre'],
            'apellido' => '',
            'tipo_doc_id' => $postulacion['tipo_documento'],
            'numero_documento' => $postulacion['numero_documento'],
            'correo' => $postulacion['correo'],
            'vacante_titulo' => $postulacion['vacante_titulo']
        ];
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("Error al obtener documentos: " . $e->getMessage());
    $documentos = [];
    $archivos_procesados = [];
}

// Obtener documentos subidos a través de los botones (tabla documentos)
try {
    $stmt = $conexion->prepare("
        SELECT * FROM documentos 
        WHERE subido_por != 'postulante'
        ORDER BY fecha_subida DESC
    ");
    $stmt->execute();
    $documentos_subidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Log del error
    error_log("Error al obtener documentos subidos: " . $e->getMessage());
    $documentos_subidos = [];
}

// Separar documentos por tipo de sección
$documentos_recursos = [];
$documentos_archivo = [];
foreach ($documentos_subidos as $doc) {
    if ($doc['tipo_seccion'] === 'recursos') {
        $documentos_recursos[] = $doc;
    } else {
        $documentos_archivo[] = $doc;
    }
}

// Formatear documentos de usuarios para incluirlos en la tabla de archivo
$archivos_usuarios = [];
foreach ($documentos as $doc) {
    $archivos_usuarios[] = [
        'id' => $doc['id'],
        'nombre' => $doc['nombre'] . ' ' . $doc['apellido'],
        'nombre_archivo' => $doc['nombre_archivo'],
        'ruta_archivo' => $doc['ruta_archivo'],
        'tipo_documento' => $doc['tipo_documento'],
        'nombre_original' => $doc['nombre_original'],
        'fecha_subida' => $doc['fecha_subida'],
        'tamano_archivo' => $doc['tamano_archivo'],
        'descripcion' => $doc['descripcion'] ?? '',
        'usuario_id' => $doc['usuario_id'],
        'es_usuario_registrado' => true // Para identificar que proviene de un usuario registrado
    ];
}

// Combinar todos los archivos en un solo array para la tabla de archivo
$archivos_procesados = array_merge($archivos_procesados, $archivos_usuarios);

// Ordenar por fecha (más reciente primero)
usort($archivos_procesados, function($a, $b) {
    return strtotime($b['fecha_subida']) - strtotime($a['fecha_subida']);
});

// Crear un mapa para evitar duplicados basado en usuario_id + tipo_documento
$documento_map = [];

// Primero procesamos los documentos de usuarios registrados
foreach ($documentos as $doc) {
    // Crear una clave única para cada combinación usuario+tipo de documento
    $key = ($doc['usuario_id'] ?? 'unknown') . '-' . ($doc['tipo_documento'] ?? 'unknown');
    
    // Si ya existe un documento de este tipo para este usuario, lo saltamos
    if (isset($documento_map[$key])) {
        continue;
    }
    
    // Si no existe, lo agregamos al mapa y al array
    $documento_map[$key] = true;
    
    $archivos_procesados[] = [
        'id' => $doc['id'],
        'nombre' => $doc['nombre'] . ' ' . $doc['apellido'],
        'nombre_archivo' => $doc['nombre_archivo'],
        'ruta_archivo' => $doc['ruta_archivo'],
        'tipo_documento' => $doc['tipo_documento'],
        'nombre_original' => $doc['nombre_original'],
        'fecha_subida' => $doc['fecha_subida'],
        'tamano_archivo' => $doc['tamano_archivo'],
        'descripcion' => $doc['descripcion'] ?? '',
        'usuario_id' => $doc['usuario_id'],
        'es_usuario_registrado' => true // Para identificar que proviene de un usuario registrado
    ];
}

// Limpiar archivos_procesados para eliminar posibles duplicados 
// (archivos de postulaciones que ya están como documentos de usuario)
$archivos_procesados_sin_duplicados = [];
$archivo_keys = [];

foreach ($archivos_procesados as $archivo) {
    // Crear una clave única basada en el nombre de la persona y tipo de documento
    $nombre = strtolower($archivo['nombre'] ?? '');
    $tipo = $archivo['tipo_documento'] ?? 'unknown';
    $key = $nombre . '-' . $tipo;
    
    // Si no hemos visto esta combinación antes, la agregamos
    if (!isset($archivo_keys[$key])) {
        $archivo_keys[$key] = true;
        $archivos_procesados_sin_duplicados[] = $archivo;
    }
}

// Reemplazar el array original con el que no tiene duplicados
$archivos_procesados = $archivos_procesados_sin_duplicados;

// Ordenar por fecha (más reciente primero)
usort($archivos_procesados, function($a, $b) {
    return strtotime($b['fecha_subida']) - strtotime($a['fecha_subida']);
});

// Configuración de tipos de documentos
$tipos_documentos = [
    'hoja_vida' => ['nombre' => 'HV', 'color' => '#eb0045', 'icono' => 'fas fa-user-tie'],
    'certificado' => ['nombre' => 'Certificado', 'color' => '#059669', 'icono' => 'fas fa-graduation-cap'],
    'certificacion' => ['nombre' => 'Formación Adicional', 'color' => '#7c2d12', 'icono' => 'fas fa-award'],
    'experiencia_laboral' => ['nombre' => 'Experiencia', 'color' => '#1d4ed8', 'icono' => 'fas fa-briefcase'],
    'otros' => ['nombre' => 'Otros', 'color' => '#6b7280', 'icono' => 'fas fa-file-alt'],
    'postulacion' => ['nombre' => 'Postulación', 'color' => '#eb0045', 'icono' => 'fas fa-paper-plane'],
    'excel_corporativo' => ['nombre' => 'Excel', 'color' => '#097746', 'icono' => 'fas fa-file-excel'],
    'pdf' => ['nombre' => 'PDF', 'color' => '#dc2626', 'icono' => 'fas fa-file-pdf'],
    'excel' => ['nombre' => 'Excel', 'color' => '#097746', 'icono' => 'fas fa-file-excel'],
    'word' => ['nombre' => 'Word', 'color' => '#2563eb', 'icono' => 'fas fa-file-word'],
    'docx' => ['nombre' => 'Word', 'color' => '#2563eb', 'icono' => 'fas fa-file-word'],
    'otro' => ['nombre' => 'Documento', 'color' => '#6b7280', 'icono' => 'fas fa-file']
];

// Verificar si el archivo Excel de prospectos existe
$excel_prospectos = [];
$excel_path = $_SERVER['DOCUMENT_ROOT'] . '/gh/Documentos/Recursos/Prospectos.xlsx';
if (file_exists($excel_path)) {
    $excel_prospectos[] = [
        'nombre_archivo' => 'Prospectos.xlsx',
        'ruta_archivo' => '/gh/Documentos/Recursos/Prospectos.xlsx',
        'tipo_documento' => 'excel_corporativo',
        'nombre_original' => 'Base de Datos - Prospectos.xlsx',
        'fecha_subida' => date('Y-m-d H:i:s', filemtime($excel_path)),
        'tamano_archivo' => filesize($excel_path),
        'descripcion' => 'Base de datos completa de prospectos y candidatos registrados en el sistema'
    ];
}

$total_documentos = count($archivos_procesados) + count($excel_prospectos) + count($documentos_recursos) + count($documentos_archivo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Documentos - Portal Administrativo</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="/gh/Css/navbar.css">
  <link rel="stylesheet" href="/gh/Css/header-universal.css">
  <link rel="stylesheet" href="/gh/Css/archivo.css">
  <link rel="icon" href="/gh/Img/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
</head>
<body>
<div class="layout-admin">
  <?php include __DIR__ . '/../Modulos/navbar.php'; ?>

  <main class="contenido-principal">
    <!-- Header universal -->
    <div class="header-universal">
      <div class="header-content">
        <div class="header-title">
          <i class="fas fa-database"></i>
          <h1>Gestión de Documentos</h1>
        </div>
        <div class="header-stats">
          <div class="stat-item">
            <span class="stat-number"><?= $total_documentos ?></span>
            <span class="stat-label">Total</span>
          </div>
          <div class="stat-item">
            <span class="stat-number"><?= count($archivos_procesados) + count($documentos_archivo) ?></span>
            <span class="stat-label">Archivos</span>
          </div>
          <div class="stat-item">
            <span class="stat-number"><?= count($excel_prospectos) + count($documentos_recursos) ?></span>
            <span class="stat-label">Recursos</span>
          </div>
        </div>
      </div>
    </div>

    <div class="archivos-container">
      <!-- Filtros y búsqueda -->
      <div class="search-bar">
        <div class="search-input-container">
          <i class="fas fa-search"></i>
          <input type="text" 
                 id="searchInput" 
                 placeholder="Buscar por nombre, usuario o tipo de documento..." 
                 class="search-input">
        </div>
        <div class="filters">
          <select id="filtroTipo" class="filter-select">
            <option value="">Todos los tipos</option>
            <option value="hoja_vida">HV</option>
            <option value="certificado">Certificado</option>
            <option value="certificacion">Formación Adicional</option>
            <option value="experiencia_laboral">Experiencia</option>
            <option value="otros">Otros</option>
            <option value="postulacion">Postulación</option>
            <option value="sistema">Archivos del Sistema</option>
          </select>
        </div>
        <div class="search-results">
          <span id="resultCount"><?= $total_documentos ?></span> documento(s) encontrado(s)
        </div>
      </div>

      <!-- Los documentos de usuarios ahora se muestran en la tabla unificada de Archivo -->

      <!-- Recursos -->
      <?php if (count($excel_prospectos) > 0 || count($documentos_recursos) > 0): ?>
        <div class="section-header">
          <h2><i class="fas fa-archive recursos-icon"></i> Recursos</h2>
          <button onclick="abrirModalUpload('recursos')" class="btn-add-file recursos-btn">
            <i class="fas fa-plus"></i>
            <span>Añadir Recurso</span>
          </button>
        </div>
        
        <div class="table-container">
          <table class="tabla-archivos">
            <thead>
              <tr>
                <th><i class="fas fa-file-alt"></i> Archivo</th>
                <th><i class="fas fa-tag"></i> Tipo</th>
                <th><i class="fas fa-calendar"></i> Última Actualización</th>
                <th><i class="fas fa-cogs"></i> Acciones</th>
              </tr>
            </thead>
            <tbody id="tabla-excel-corporativo">
              <?php foreach ($excel_prospectos as $excel): ?>
                <tr class="documento-row" 
                    data-tipo="excel" 
                    data-usuario="sistema corporativo"
                    data-documento="<?= strtolower($excel['nombre_original']) ?>">
                  <td>
                    <div class="file-info">
                      <div class="file-icon">
                        <i class="<?= $tipos_documentos['excel_corporativo']['icono'] ?>"></i>
                      </div>
                      <div class="file-details">
                        <div class="file-name-container">
                          <span class="file-name"><?= htmlspecialchars($excel['nombre_original']) ?></span>
                          <button onclick="editarNombreArchivo('prospectos', '<?= htmlspecialchars(addslashes($excel['nombre_original'])) ?>', 'recursos')" class="btn-edit-filename" title="Editar nombre">
                            <i class="fas fa-pencil-alt"></i>
                          </button>
                        </div>
                        <span class="file-size"><?= number_format($excel['tamano_archivo'] / 1024, 0) ?> KB</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="tipo-badge" style="background: <?= $tipos_documentos['excel_corporativo']['color'] ?>;">
                      <i class="<?= $tipos_documentos['excel_corporativo']['icono'] ?>"></i>
                      <?= $tipos_documentos['excel_corporativo']['nombre'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="fecha-subida"><?= date('d/m/Y H:i', strtotime($excel['fecha_subida'])) ?></span>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <button onclick="previsualizarExcel('<?= htmlspecialchars($excel['ruta_archivo']) ?>', '<?= htmlspecialchars($excel['nombre_original']) ?>')" 
                              class="btn-action btn-view"
                              title="Vista previa del Excel">
                        <i class="fas fa-eye"></i>
                        Ver
                      </button>
                      <a href="<?= htmlspecialchars($excel['ruta_archivo']) ?>" 
                         download 
                         class="btn-action btn-download"
                         title="Descargar archivo Excel">
                        <i class="fas fa-download"></i>
                        Descargar
                      </a>
                      <button onclick="eliminarArchivo('<?= htmlspecialchars($excel['nombre_archivo']) ?>', 'recursos')" 
                              class="btn-action btn-delete"
                              title="Eliminar archivo">
                        <i class="fas fa-trash"></i>
                        Eliminar
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              
              <?php foreach ($documentos_recursos as $doc): ?>
                <tr>
                  <td>
                    <div class="file-info">
                      <div class="file-icon">
                        <i class="<?= $tipos_documentos[$doc['tipo_documento']]['icono'] ?? 'fas fa-file' ?>"></i>
                      </div>
                      <div class="file-details">
                        <div class="file-name-container">
                          <span class="file-name"><?= htmlspecialchars($doc['nombre_original']) ?></span>
                          <button onclick="editarNombreArchivo('<?= $doc['id'] ?>', '<?= htmlspecialchars(addslashes($doc['nombre_original'])) ?>', 'recursos')" class="btn-edit-filename" title="Editar nombre">
                            <i class="fas fa-pencil-alt"></i>
                          </button>
                        </div>
                        <span class="file-size"><?= number_format($doc['tamano_archivo'] / 1024, 0) ?> KB</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="tipo-badge" style="background: <?= $tipos_documentos[$doc['tipo_documento']]['color'] ?? '#6b7280' ?>;">
                      <i class="<?= $tipos_documentos[$doc['tipo_documento']]['icono'] ?? 'fas fa-file' ?>"></i>
                      <?= $tipos_documentos[$doc['tipo_documento']]['nombre'] ?? 'Documento' ?>
                    </span>
                  </td>
                  <td>
                    <span class="fecha-subida"><?= date('d/m/Y H:i', strtotime($doc['fecha_subida'])) ?></span>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <?php if ($doc['tipo_documento'] === 'excel'): ?>
                        <button onclick="previsualizarExcel('<?= htmlspecialchars($doc['ruta_archivo']) ?>', '<?= htmlspecialchars($doc['nombre_original']) ?>')" 
                                class="btn-action btn-view"
                                title="Vista previa del Excel">
                          <i class="fas fa-eye"></i>
                          Ver
                        </button>
                      <?php elseif ($doc['tipo_documento'] === 'word' || $doc['tipo_documento'] === 'docx'): ?>
                        <button onclick="previsualizarDocx('<?= htmlspecialchars($doc['ruta_archivo']) ?>', '<?= htmlspecialchars($doc['nombre_original']) ?>')" 
                                class="btn-action btn-view"
                                title="Vista previa del documento Word">
                          <i class="fas fa-eye"></i>
                          Ver
                        </button>
                      <?php else: ?>
                        <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" 
                           target="_blank" 
                           class="btn-action btn-view"
                           title="Ver documento">
                          <i class="fas fa-eye"></i>
                          Ver
                        </a>
                      <?php endif; ?>
                      <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" 
                         download 
                         class="btn-action btn-download"
                         title="Descargar archivo">
                        <i class="fas fa-download"></i>
                        Descargar
                      </a>
                      <button onclick="eliminarArchivo('<?= htmlspecialchars($doc['nombre_archivo']) ?>', 'recursos')" 
                              class="btn-action btn-delete"
                              title="Eliminar archivo">
                        <i class="fas fa-trash"></i>
                        Eliminar
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="section-header">
          <h2><i class="fas fa-archive recursos-icon"></i> Recursos</h2>
          <button onclick="abrirModalUpload('recursos')" class="btn-add-file recursos-btn">
            <i class="fas fa-plus"></i>
            <span>Añadir Recurso</span>
          </button>
        </div>
        
        <div class="empty-section">
          <div class="empty-icon">
            <i class="fas fa-archive"></i>
          </div>
          <h3>No hay recursos</h3>
          <p>Sube archivos Excel u otros recursos corporativos aquí.</p>
          <button onclick="abrirModalUpload('recursos')" class="btn-upload-empty">
            <i class="fas fa-plus"></i>
            Subir Primer Recurso
          </button>
        </div>
      <?php endif; ?>

      <!-- Archivo Unificado - Contiene tanto los documentos de usuarios como postulaciones -->
      <?php if (count($archivos_procesados) > 0 || count($documentos_archivo) > 0): ?>
        <div class="section-header">
          <h2><i class="fas fa-paper-plane"></i> Archivo</h2>
          <button onclick="abrirModalUpload('archivo')" class="btn-add-file archivo-btn">
            <i class="fas fa-plus"></i>
            <span>Añadir Archivo</span>
          </button>
        </div>
        
        <div class="table-container">
          <table class="tabla-archivos">
            <thead>
              <tr>
                <th><i class="fas fa-user"></i> Usuario</th>
                <th><i class="fas fa-file-alt"></i> Archivo</th>
                <th><i class="fas fa-tag"></i> Tipo</th>
                <th><i class="fas fa-calendar"></i> Fecha</th>
                <th><i class="fas fa-cogs"></i> Acciones</th>
              </tr>
            </thead>
            <tbody id="tabla-archivos-unificados">
              <?php foreach ($archivos_procesados as $archivo): ?>
                <tr class="documento-row" 
                    data-tipo="<?= $archivo['tipo_documento'] ?? 'postulacion' ?>" 
                    data-usuario="<?= strtolower($archivo['nombre']) ?>"
                    data-documento="<?= strtolower($archivo['nombre_original']) ?>">
                  <td>
                    <div class="usuario-info">
                      <strong><?= htmlspecialchars($archivo['nombre']) ?></strong>
                      <?php if (isset($archivo['es_usuario_registrado']) && $archivo['es_usuario_registrado']): ?>
                        <span class="usuario-id">ID: <?= $archivo['usuario_id'] ?></span>
                      <?php elseif (isset($archivo['vacante_titulo'])): ?>
                        <span class="usuario-vacante" style="font-size: 0.9em; color: #eb0045; font-weight: 500;">
                          <?= htmlspecialchars($archivo['vacante_titulo']) ?>
                        </span>
                      <?php else: ?>
                        <span class="usuario-id">Postulación</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <div class="file-info">
                      <i class="<?= $tipos_documentos[$archivo['tipo_documento'] ?? 'postulacion']['icono'] ?> file-icon" 
                         style="color: <?= $tipos_documentos[$archivo['tipo_documento'] ?? 'postulacion']['color'] ?>;"></i>
                      <div class="file-details">
                        <div class="file-name-container">
                          <span class="file-name"><?= htmlspecialchars($archivo['nombre_original']) ?></span>
                          <button onclick="editarNombreArchivo('<?= $archivo['id'] ?? 'postulacion_' . ($archivo['numero_documento'] ?? 'sin_id') ?>', '<?= htmlspecialchars(addslashes($archivo['nombre_original'])) ?>', '<?= isset($archivo['es_usuario_registrado']) ? 'documentos_usuarios' : 'archivo' ?>')" class="btn-edit-filename" title="Editar nombre">
                            <i class="fas fa-pencil-alt"></i>
                          </button>
                        </div>
                        <?php if (isset($archivo['tamano_archivo'])): ?>
                          <span class="file-size"><?= number_format($archivo['tamano_archivo'] / 1024, 0) ?> KB</span>
                        <?php endif; ?>
                        <?php if (isset($archivo['descripcion']) && $archivo['descripcion']): ?>
                          <span class="file-description"><?= htmlspecialchars($archivo['descripcion']) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="tipo-badge" style="background: <?= $tipos_documentos[$archivo['tipo_documento'] ?? 'postulacion']['color'] ?>;">
                      <i class="<?= $tipos_documentos[$archivo['tipo_documento'] ?? 'postulacion']['icono'] ?>"></i>
                      <?= $tipos_documentos[$archivo['tipo_documento'] ?? 'postulacion']['nombre'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="fecha-subida"><?= date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?></span>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <a href="/gh/visualizar_documento.php?archivo=<?= urlencode($archivo['ruta_archivo']) ?>" 
                         target="_blank" 
                         class="btn-action btn-view"
                         title="Ver documento">
                        <i class="fas fa-eye"></i>
                        Ver
                      </a>
                      <a href="/gh/descargar_documento.php?archivo=<?= urlencode($archivo['ruta_archivo']) ?>" 
                         class="btn-action btn-download"
                         title="Descargar documento">
                        <i class="fas fa-download"></i>
                        Descargar
                      </a>
                      <button onclick="<?= isset($archivo['es_usuario_registrado']) ? "eliminarDocumento('{$archivo['id']}')" : "eliminarArchivo('{$archivo['nombre_archivo']}', 'archivo')" ?>" 
                              class="btn-action btn-delete"
                              title="Eliminar archivo">
                        <i class="fas fa-trash"></i>
                        Eliminar
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              
              <?php 
              // Filtrar los documentos del administrador para evitar duplicados con los ya mostrados
              $docs_admin_filtrados = [];
              foreach ($documentos_archivo as $doc) {
                  // Para documentos del administrador, verificamos por nombre_original
                  $doc_nombre = strtolower($doc['nombre_original']);
                  $doc_tipo = $doc['tipo_documento'] ?? 'unknown';
                  $key = 'admin-' . $doc_nombre . '-' . $doc_tipo;
                  
                  // Si no tenemos un documento con este nombre ya mostrado, lo incluimos
                  if (!isset($archivo_keys[$key])) {
                      $archivo_keys[$key] = true;
                      $docs_admin_filtrados[] = $doc;
                  }
              }
              
              // Ahora mostramos solo los que no son duplicados
              foreach ($docs_admin_filtrados as $doc): 
              ?>
                <tr class="documento-row" 
                    data-tipo="<?= $doc['tipo_documento'] ?>" 
                    data-usuario="admin"
                    data-sistema="true"
                    data-documento="<?= strtolower($doc['nombre_original']) ?>">
                  <td>
                    <div class="usuario-info">
                      <strong>Administrador</strong>
                      <span class="usuario-id" style="font-size: 0.8em; color: #6b7280;">
                        Archivo del Sistema
                      </span>
                    </div>
                  </td>
                  <td>
                    <div class="file-info">
                      <i class="<?= $tipos_documentos[$doc['tipo_documento']]['icono'] ?? 'fas fa-file' ?> file-icon" 
                         style="color: <?= $tipos_documentos[$doc['tipo_documento']]['color'] ?? '#6b7280' ?>; font-size: 1.2em;"></i>
                      <div class="file-details">
                        <div class="file-name-container">
                          <span class="file-name"><?= htmlspecialchars($doc['nombre_original']) ?></span>
                          <button onclick="editarNombreArchivo('<?= $doc['id'] ?>', '<?= htmlspecialchars(addslashes($doc['nombre_original'])) ?>', 'archivo')" class="btn-edit-filename" title="Editar nombre">
                            <i class="fas fa-pencil-alt"></i>
                          </button>
                        </div>
                        <span class="file-size"><?= number_format($doc['tamano_archivo'] / 1024, 0) ?> KB</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="tipo-badge" style="background: <?= $tipos_documentos[$doc['tipo_documento']]['color'] ?? '#6b7280' ?>;">
                      <i class="<?= $tipos_documentos[$doc['tipo_documento']]['icono'] ?? 'fas fa-file' ?>"></i>
                      <?= $tipos_documentos[$doc['tipo_documento']]['nombre'] ?? 'Documento' ?>
                    </span>
                  </td>
                  <td>
                    <span class="fecha-subida"><?= date('d/m/Y H:i', strtotime($doc['fecha_subida'])) ?></span>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <?php if ($doc['tipo_documento'] === 'excel'): ?>
                        <button onclick="previsualizarExcel('<?= htmlspecialchars($doc['ruta_archivo']) ?>', '<?= htmlspecialchars($doc['nombre_original']) ?>')" 
                                class="btn-action btn-view"
                                title="Vista previa del Excel">
                          <i class="fas fa-eye"></i>
                          Ver
                        </button>
                      <?php elseif ($doc['tipo_documento'] === 'word' || $doc['tipo_documento'] === 'docx'): ?>
                        <button onclick="previsualizarDocx('<?= htmlspecialchars($doc['ruta_archivo']) ?>', '<?= htmlspecialchars($doc['nombre_original']) ?>')" 
                                class="btn-action btn-view"
                                title="Vista previa del documento Word">
                          <i class="fas fa-eye"></i>
                          Ver
                        </button>
                      <?php else: ?>
                        <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" 
                           target="_blank" 
                           class="btn-action btn-view"
                           title="Ver documento">
                          <i class="fas fa-eye"></i>
                          Ver
                        </a>
                      <?php endif; ?>
                      <a href="<?= htmlspecialchars($doc['ruta_archivo']) ?>" 
                         download 
                         class="btn-action btn-download"
                         title="Descargar documento">
                        <i class="fas fa-download"></i>
                        Descargar
                      </a>
                      <button onclick="eliminarArchivo('<?= htmlspecialchars($doc['nombre_archivo']) ?>', 'archivo')" 
                              class="btn-action btn-delete"
                              title="Eliminar archivo">
                        <i class="fas fa-trash"></i>
                        Eliminar
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="section-header">
          <h2><i class="fas fa-paper-plane"></i> Archivo</h2>
          <button onclick="abrirModalUpload('archivo')" class="btn-add-file archivo-btn">
            <i class="fas fa-plus"></i>
            <span>Añadir Archivo</span>
          </button>
        </div>
        
        <div class="empty-section">
          <div class="empty-icon">
            <i class="fas fa-paper-plane"></i>
          </div>
          <h3>No hay archivos</h3>
          <p>Sube documentos de postulaciones y archivos procesados aquí.</p>
          <button onclick="abrirModalUpload('archivo')" class="btn-upload-empty">
            <i class="fas fa-plus"></i>
            Subir Primer Archivo
          </button>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Modal de Upload -->
<div id="uploadModal" class="modal-overlay" style="display: none;">
  <div class="modal-content upload-modal">
    <div class="modal-header">
      <h3 id="modalTitle">
        <i class="fas fa-cloud-upload-alt"></i>
        Subir Archivo
      </h3>
      <button type="button" class="modal-close" onclick="cerrarModalUpload()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <form id="uploadForm" enctype="multipart/form-data" onsubmit="subirArchivo(event)">
      <div class="modal-body">
        <input type="hidden" id="tipoSeccion" name="tipo_seccion" value="">
        
        <div class="upload-zone" id="uploadZone" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
          <div class="upload-icon">
            <i class="fas fa-cloud-upload-alt"></i>
          </div>
          <div class="upload-text">
            <h4>Arrastra y suelta tu archivo aquí</h4>
            <p>o haz clic para seleccionar</p>
          </div>
          <input type="file" id="fileInput" name="archivo" accept=".pdf,.xlsx,.xls,.doc,.docx" onchange="handleFileSelect(event)" style="display: none;">
          <button type="button" class="btn-select-file" onclick="document.getElementById('fileInput').click()">
            <i class="fas fa-folder-open"></i>
            Seleccionar Archivo
          </button>
        </div>
        
        <div id="filePreview" class="file-preview" style="display: none;">
          <div class="file-info">
            <div class="file-icon">
              <i class="fas fa-file"></i>
            </div>
            <div class="file-details">
              <span class="file-name"></span>
              <span class="file-size"></span>
            </div>
          </div>
          <button type="button" class="btn-remove-file" onclick="removeFile()">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="cerrarModalUpload()">
          <i class="fas fa-times"></i>
          Cancelar
        </button>
        <button type="submit" class="btn-primary" id="btnSubir" disabled>
          <i class="fas fa-upload"></i>
          Subir Archivo
        </button>
      </div>
    </form>
  </div>
</div>

  <!-- Token CSRF para llamadas AJAX -->
  <script>
    window.CSRF_TOKEN = '<?= htmlspecialchars($csrf_token) ?>';
  </script>
  <!-- Cargar JS al final -->
  <script src="/gh/Js/archivo.js" defer></script>
</body>
</html>
