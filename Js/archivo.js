/**
 * Edita el nombre de un archivo
 * @param {string} id - ID del documento
 * @param {string} nombreActual - Nombre actual del archivo
 * @param {string} tipo - Tipo de archivo ('documentos_usuarios', 'recursos', 'archivo')
 */
function editarNombreArchivo(id, nombreActual, tipo) {
  Swal.fire({
    title: '<i class="fas fa-edit" style="margin-right:10px; color:#eb0045;"></i>Editar nombre del archivo',
    html: `
      <div class="edit-filename-container">
        <label for="nuevo-nombre" class="swal2-label">Nombre del archivo:</label>
        <input 
          id="nuevo-nombre" 
          class="swal2-input" 
          value="${nombreActual}" 
          placeholder="Ingrese el nuevo nombre"
          autofocus>
      </div>
    `,
    showCancelButton: true,
    customClass: {
      popup: 'edit-filename-modal',
      confirmButton: 'swal-confirm-btn',
      cancelButton: 'swal-cancel-btn'
    },
    confirmButtonText: 'Guardar',
    cancelButtonText: 'Cancelar',
    focusConfirm: false,
    animation: true,
    showClass: {
      popup: 'animate__animated animate__fadeInDown animate__faster'
    },
    hideClass: {
      popup: 'animate__animated animate__fadeOutUp animate__faster'
    },
    preConfirm: () => {
      const nuevoNombre = document.getElementById('nuevo-nombre').value;
      if (!nuevoNombre.trim()) {
        Swal.showValidationMessage('El nombre no puede estar vacío');
        return false;
      }
      return nuevoNombre;
    }
  }).then((result) => {
    if (result.isConfirmed && result.value) {
      guardarNuevoNombre(id, result.value, tipo);
    }
  });
}

/**
 * Guarda el nuevo nombre del archivo en el servidor
 * @param {string} id - ID del documento
 * @param {string} nuevoNombre - Nuevo nombre del archivo
 * @param {string} tipo - Tipo de archivo ('documentos_usuarios', 'recursos', 'archivo')
 */
function guardarNuevoNombre(id, nuevoNombre, tipo) {
  Swal.fire({
    title: 'Guardando...',
    text: 'Actualizando nombre del archivo',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  fetch('update_nombre_archivo.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      id: id,
      nuevo_nombre: nuevoNombre,
      tipo: tipo,
      csrf_token: window.CSRF_TOKEN
    })
  })
  .then(response => response.json().catch(() => ({ success: false, message: 'Error al procesar respuesta' })))
  .then(data => {
    if (data.success) {
      // Actualizar el nombre en la UI sin recargar
      actualizarNombreEnUI(id, nuevoNombre, tipo);
      
      Swal.fire({
        title: '¡Actualizado!',
        text: data.message || 'Nombre actualizado correctamente',
        icon: 'success',
        confirmButtonText: 'OK',
        timer: 2000,
        timerProgressBar: true
      });
    } else {
      Swal.fire({
        title: 'Error',
        text: data.message || 'No se pudo actualizar el nombre',
        icon: 'error',
        confirmButtonText: 'OK'
      });
    }
  })
  .catch(error => {
    Swal.fire({
      title: 'Error',
      text: 'Error de conexión. Intente de nuevo.',
      icon: 'error',
      confirmButtonText: 'OK'
    });
  });
}

/**
 * Actualiza el nombre del archivo en la UI sin recargar la página
 */
function actualizarNombreEnUI(id, nuevoNombre, tipo) {
  // Buscar el botón de edición que corresponde a este archivo
  const botones = document.querySelectorAll('.btn-edit-filename');
  botones.forEach(btn => {
    const onclick = btn.getAttribute('onclick');
    if (onclick && onclick.includes(`'${id}'`) && onclick.includes(`'${tipo}'`)) {
      // Encontramos el botón, actualizar el nombre en el span hermano
      const container = btn.closest('.file-name-container');
      if (container) {
        const nombreSpan = container.querySelector('.file-name');
        if (nombreSpan) {
          nombreSpan.textContent = nuevoNombre;
        }
        // Actualizar también el onclick del botón con el nuevo nombre
        btn.setAttribute('onclick', `editarNombreArchivo('${id}', '${nuevoNombre.replace(/'/g, "\\'")}', '${tipo}')`);
      }
    }
  });
}

/**
 * Elimina un documento de usuario registrado (tabla documentos_usuarios)
 * Esta función es usada por administradores para eliminar documentos
 * que fueron subidos por usuarios registrados
 * @param {string|number} id - ID del documento en documentos_usuarios
 */
function eliminarDocumento(id) {
  if (!id) {
    Swal.fire({
      title: 'Error',
      text: 'ID de documento no válido',
      icon: 'error',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  Swal.fire({
    title: '¿Estás seguro?',
    text: 'Se eliminará este documento del usuario. Esta acción no se puede deshacer.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: 'Eliminando...',
        text: 'Eliminando el documento',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      fetch('delete_documento.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          id: id,
          csrf_token: window.CSRF_TOKEN
        })
      })
      .then(response => response.json().catch(() => ({ success: false, message: 'Error al procesar respuesta' })))
      .then(data => {
        if (data.success) {
          // Eliminar la fila de la tabla sin recargar
          eliminarFilaDeTabla(id, 'documentos_usuarios');
          
          Swal.fire({
            title: '¡Eliminado!',
            text: data.message || 'Documento eliminado correctamente',
            icon: 'success',
            confirmButtonText: 'OK',
            timer: 2000,
            timerProgressBar: true
          });
        } else {
          Swal.fire({
            title: 'Error',
            text: data.message || 'Error al eliminar el documento',
            icon: 'error',
            confirmButtonText: 'OK'
          });
        }
      })
      .catch(error => {
        Swal.fire({
          title: 'Error',
          text: 'Error de conexión. Intente de nuevo.',
          icon: 'error',
          confirmButtonText: 'OK'
        });
      });
    }
  });
}

/**
 * Elimina una fila de la tabla sin recargar la página
 */
function eliminarFilaDeTabla(id, tipo) {
  // Buscar el botón de eliminar que corresponde a este archivo
  const botones = document.querySelectorAll('.btn-delete');
  botones.forEach(btn => {
    const onclick = btn.getAttribute('onclick');
    if (onclick && onclick.includes(`eliminarDocumento('${id}')`)) {
      // Encontramos el botón, eliminar la fila
      const row = btn.closest('tr');
      if (row) {
        row.style.transition = 'opacity 0.3s, transform 0.3s';
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        setTimeout(() => row.remove(), 300);
      }
    }
  });
}

/**
 * Funcionalidad para la gestión de archivos en el sistema
 * Este archivo contiene todas las funciones JavaScript para manejar archivos
 */

// Funcionalidad de búsqueda y filtros avanzados
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('searchInput');
  const filtroTipo = document.getElementById('filtroTipo');
  const resultCount = document.getElementById('resultCount');
  
  // Añadir evento para escuchar los cambios en los filtros
  
  // Función para normalizar tipos de documentos
  function normalizarTipoDocumento(tipo) {
    // Normalizar los tipos para HV
    if (tipo === 'hv') return 'hoja_vida';
    if (tipo === 'cv') return 'hoja_vida';
    if (tipo === 'resume') return 'hoja_vida';
    if (tipo === 'curriculum') return 'hoja_vida';
    
    // Manejar otros casos especiales
    if (tipo === 'otro') return 'sistema'; // Para compatibilidad con filtros anteriores
    
    // Devolver el tipo original para otros casos
    return tipo;
  }
  
  // Comprobar si hay un filtro en la URL
  function obtenerParametroURL(nombre) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(nombre);
  }
  
  // Verificar si hay un filtro predefinido en la URL
  const filtroURL = obtenerParametroURL('filtro');
  if (filtroURL && filtroTipo) {
    filtroTipo.value = filtroURL;
  }
  
  // Iniciar con todos los documentos visibles
  setTimeout(function() {
    aplicarFiltros();
  }, 300);

  function aplicarFiltros() {
    // Seleccionar todas las filas de documentos en todas las tablas (para capturar cualquier cambio dinámico)
    const allRows = document.querySelectorAll('.documento-row');
    
    const searchTerm = searchInput.value.toLowerCase().trim();
    const tipoSeleccionado = filtroTipo.value;
    let visibleCount = 0;

    allRows.forEach(row => {
      const usuario = (row.dataset.usuario || '').toLowerCase();
      const documento = (row.dataset.documento || '').toLowerCase();
      const tipo = row.dataset.tipo || '';
      
      const tipoNormalizado = normalizarTipoDocumento(tipo);
      
      // Verificar si coincide con la búsqueda
      const coincideBusqueda = !searchTerm || 
                              usuario.includes(searchTerm) || 
                              documento.includes(searchTerm);
      
      // Verificar si coincide con el filtro de tipo, con normalización
      let coincideTipo = !tipoSeleccionado; // Si no hay selección, coincide
      
      if (tipoSeleccionado) {
        // Caso especial para "Archivos del Sistema"
        if (tipoSeleccionado === 'sistema') {
          // Debe tener el atributo data-sistema="true" 
          coincideTipo = row.dataset.sistema === 'true';
        } else if (tipo) {
          // Para otros tipos, usar normalización
          const tipoNormalizado = normalizarTipoDocumento(tipo);
          coincideTipo = tipoNormalizado === tipoSeleccionado;
        } else {
          coincideTipo = false;
        }
      }
      
      if (coincideBusqueda && coincideTipo) {
        row.style.display = '';
        visibleCount++;
        
        // Si el filtro está activo, destacamos la fila para depuración
        if (tipoSeleccionado) {
          if (tipoSeleccionado === 'sistema') {
            console.log(`✓ COINCIDE [Archivo del Sistema]: ${documento.substring(0, 20)}...`);
          } else {
            console.log(`✓ COINCIDE [${tipoSeleccionado}]: ${normalizarTipoDocumento(tipo)}`);
          }
        }
      } else {
        row.style.display = 'none';
        
        // Si el filtro está activo, mostramos por qué no coincidió
        if (tipoSeleccionado) {
          if (tipoSeleccionado === 'sistema') {
            console.log(`✗ NO COINCIDE [Archivo del Sistema]: ${documento.substring(0, 20)}...`);
          } else if (tipo) {
            console.log(`✗ NO COINCIDE [${tipoSeleccionado}]: ${normalizarTipoDocumento(tipo)}`);
          }
        }
      }
    });

    // Actualizar contador con documentos filtrados
    if (resultCount) {
      resultCount.textContent = visibleCount;
    }
    
    // Actualizar texto en la búsqueda
    const searchResults = document.querySelector('.search-results');
    if (searchResults) {
      searchResults.innerHTML = `<span id="resultCount">${visibleCount}</span> documento(s) encontrado(s)`;
    }
  }

  // Event listeners
  if (searchInput) searchInput.addEventListener('input', aplicarFiltros);
  if (filtroTipo) filtroTipo.addEventListener('change', aplicarFiltros);
});

/**
 * Previsualiza archivos Excel
 * @param {string} rutaArchivo - Ruta al archivo Excel
 * @param {string} nombreArchivo - Nombre del archivo
 */
function previsualizarExcel(rutaArchivo, nombreArchivo) {
  if (typeof XLSX === 'undefined') {
    Swal.fire({
      title: 'Error',
      text: 'La librería XLSX no está cargada correctamente',
      icon: 'error',
      confirmButtonText: 'OK'
    });
    return;
  }

  // Mostrar loading con colores corporativos
  Swal.fire({
    title: 'Cargando Excel...',
    html: `
      <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem; color: #404E62;">
        <div style="font-size: 1rem; color: #64748b;">Procesando archivo</div>
        <div style="font-weight: 600; color: #404E62; background: #fef2f2; padding: 0.5rem 1rem; border-radius: 8px; border-left: 4px solid #eb0045;">${nombreArchivo}</div>
        <div style="font-size: 0.875rem; color: #94a3b8;">Esto puede tardar unos segundos...</div>
      </div>
    `,
    allowOutsideClick: false,
    showConfirmButton: false,
    background: 'white',
    didOpen: () => {
      Swal.showLoading();
    }
  });

  // Cargar el archivo Excel
  fetch(rutaArchivo)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.arrayBuffer();
    })
    .then(data => {
      // Leer el archivo con SheetJS
      const workbook = XLSX.read(data, { type: 'array' });
      const sheetName = workbook.SheetNames[0];
      const worksheet = workbook.Sheets[sheetName];
      
      // Obtener información del archivo
      const range = XLSX.utils.decode_range(worksheet['!ref'] || 'A1');
      const totalRows = range.e.r + 1;
      const totalCols = range.e.c + 1;
      
      // Convertir a HTML
      const htmlTable = XLSX.utils.sheet_to_html(worksheet, {
        id: 'excel-preview-table',
        editable: false
      });

      // Mostrar modal con colores corporativos
      Swal.fire({
        title: `${nombreArchivo}`,
        html: `
          <div class="excel-preview-container">
            <div class="excel-info">
              <h3>${nombreArchivo}</h3>
              <div class="info-stats">
                <div class="stat-box">
                  <span class="stat-label">Hoja de cálculo</span>
                  <span class="stat-value">${sheetName}</span>
                </div>
                <div class="stat-box">
                  <span class="stat-label">Total de filas</span>
                  <span class="stat-value">${totalRows}</span>
                </div>
                <div class="stat-box">
                  <span class="stat-label">Total de columnas</span>
                  <span class="stat-value">${totalCols}</span>
                </div>
              </div>
            </div>
            <div class="excel-content">
              ${htmlTable}
            </div>
          </div>
        `,
        width: '92%',
        showCancelButton: true,
        confirmButtonColor: '#eb0045',
        cancelButtonColor: '#404E62',
        confirmButtonText: '<i class="fas fa-download"></i> Descargar Excel',
        cancelButtonText: '<i class="fas fa-times"></i> Cerrar',
        customClass: {
          popup: 'excel-preview-popup',
          htmlContainer: 'excel-preview-html'
        },
        allowOutsideClick: true,
        allowEscapeKey: true,
        showCloseButton: true
      }).then((result) => {
        if (result.isConfirmed) {
          // Mostrar confirmación de descarga
          Swal.fire({
            title: 'Preparando descarga...',
            text: 'El archivo Excel se está preparando',
            timer: 1000,
            showConfirmButton: false,
            background: 'white',
            color: '#404E62'
          }).then(() => {
            // Descargar archivo
            const link = document.createElement('a');
            link.href = rutaArchivo;
            link.download = nombreArchivo;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Confirmación final
            Swal.fire({
              title: '¡Descarga exitosa!',
              text: `"${nombreArchivo}" se ha descargado correctamente`,
              timer: 2000,
              showConfirmButton: false,
              background: 'white',
              color: '#404E62'
            });
          });
        }
      });
    })
    .catch(error => {
      console.error('Error:', error);
      Swal.fire({
        title: 'Error',
        text: 'No se pudo cargar el archivo Excel',
        icon: 'error',
        confirmButtonText: 'OK'
      });
    });
}

/**
 * Previsualiza archivos DOCX
 * @param {string} rutaArchivo - Ruta al archivo DOCX
 * @param {string} nombreArchivo - Nombre del archivo
 */
function previsualizarDocx(rutaArchivo, nombreArchivo) {
  // Verificar que Mammoth.js esté cargado
  if (typeof mammoth === 'undefined') {
    Swal.fire({
      title: 'Error',
      text: 'La librería Mammoth.js no está cargada correctamente',
      icon: 'error',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  // Mostrar loading con colores corporativos
  Swal.fire({
    title: 'Cargando documento Word...',
    html: `
      <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem; color: #404E62;">
        <div style="font-size: 1rem; color: #64748b;">Procesando archivo DOCX</div>
        <div style="font-weight: 600; color: #404E62; background: #fef2f2; padding: 0.5rem 1rem; border-radius: 8px; border-left: 4px solid #eb0045;">${nombreArchivo}</div>
        <div style="font-size: 0.875rem; color: #94a3b8;">Convirtiendo a HTML...</div>
      </div>
    `,
    allowOutsideClick: false,
    showConfirmButton: false,
    background: 'white',
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  // Cargar el archivo DOCX
  fetch(rutaArchivo)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.arrayBuffer();
    })
    .then(arrayBuffer => {
      // Convertir con Mammoth.js
      return mammoth.convertToHtml({ arrayBuffer: arrayBuffer });
    })
    .then(result => {
      // Obtener el HTML convertido
      const docxHtml = result.value;
      
      // Mostrar modal con el contenido convertido
      Swal.fire({
        title: `${nombreArchivo}`,
        html: `
          <div class="docx-preview-container">
            <div class="docx-content">
              <div class="docx-html-viewer">
                ${docxHtml}
              </div>
            </div>
            <div class="docx-actions">
              <a href="${rutaArchivo}" download class="docx-btn-download">
                <i class="fas fa-download"></i> Descargar
              </a>
              <button onclick="Swal.close()" class="docx-btn-close">
                <i class="fas fa-times"></i> Cerrar
              </button>
            </div>
          </div>
        `,
        width: '900px',
        showConfirmButton: false,
        showCloseButton: true,
        background: 'white',
        customClass: {
          popup: 'docx-preview-popup'
        }
      });
    })
    .catch(error => {
      console.error('Error al procesar DOCX:', error);
      Swal.fire({
        title: 'Error',
        text: 'No se pudo cargar el archivo DOCX',
        icon: 'error',
        confirmButtonText: 'OK'
      });
    });
}

/**
 * Abre el modal para subir archivos
 * @param {string} seccion - Tipo de sección ('recursos' o 'archivo')
 */
function abrirModalUpload(seccion) {
  const modal = document.getElementById('uploadModal');
  const modalTitle = document.getElementById('modalTitle');
  const tipoSeccion = document.getElementById('tipoSeccion');
  
  // Validar que los elementos existan
  if (!modal || !modalTitle || !tipoSeccion) {
    console.error('Elementos del modal no encontrados');
    return;
  }
  
  if (seccion === 'recursos') {
    modalTitle.innerHTML = '<i class="fas fa-archive"></i> Subir Recurso';
  } else if (seccion === 'archivo') {
    modalTitle.innerHTML = '<i class="fas fa-paper-plane"></i> Subir Archivo';
  }
  
  tipoSeccion.value = seccion;
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  // Reset form con validación
  const uploadForm = document.getElementById('uploadForm');
  const filePreview = document.getElementById('filePreview');
  const uploadZone = document.getElementById('uploadZone');
  const btnSubir = document.getElementById('btnSubir');
  
  if (uploadForm) uploadForm.reset();
  if (filePreview) filePreview.style.display = 'none';
  if (uploadZone) uploadZone.style.display = 'block';
  if (btnSubir) btnSubir.disabled = true;
}

/**
 * Cierra el modal de subida de archivos
 */
function cerrarModalUpload() {
  const modal = document.getElementById('uploadModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
}

/**
 * Maneja el evento de arrastrar un archivo sobre la zona de drop
 * @param {Event} e - Evento de arrastre
 */
function handleDragOver(e) {
  e.preventDefault();
  e.stopPropagation();
  const uploadZone = document.getElementById('uploadZone');
  if (uploadZone) {
    uploadZone.classList.add('drag-over');
  }
}

/**
 * Maneja el evento cuando el archivo arrastrado sale de la zona de drop
 * @param {Event} e - Evento de arrastre
 */
function handleDragLeave(e) {
  e.preventDefault();
  e.stopPropagation();
  const uploadZone = document.getElementById('uploadZone');
  if (uploadZone) {
    uploadZone.classList.remove('drag-over');
  }
}

/**
 * Maneja el evento de soltar un archivo en la zona de drop
 * @param {Event} e - Evento de drop
 */
function handleDrop(e) {
  e.preventDefault();
  e.stopPropagation();
  const uploadZone = document.getElementById('uploadZone');
  if (uploadZone) {
    uploadZone.classList.remove('drag-over');
  }
  
  const files = e.dataTransfer.files;
  if (files && files.length > 0) {
    handleFile(files[0]);
  }
}

/**
 * Maneja la selección de archivo mediante el input file
 * @param {Event} e - Evento de cambio del input
 */
function handleFileSelect(e) {
  const file = e.target.files[0];
  if (file) {
    handleFile(file);
  }
}

/**
 * Procesa el archivo seleccionado
 * @param {File} file - Objeto File
 */
function handleFile(file) {
  const allowedTypes = [
    'application/pdf', 
    'application/vnd.ms-excel', 
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 
    'application/msword', 
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
  ];
  
  if (!allowedTypes.includes(file.type)) {
    Swal.fire({
      title: 'Tipo de archivo no válido',
      text: 'Solo se permiten archivos PDF, Excel y Word',
      icon: 'error',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  if (file.size > 10 * 1024 * 1024) {
    Swal.fire({
      title: 'Archivo demasiado grande',
      text: 'El archivo no puede superar los 10MB',
      icon: 'error',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  // Mostrar preview
  const uploadZone = document.getElementById('uploadZone');
  const filePreview = document.getElementById('filePreview');
  const fileName = filePreview.querySelector('.file-name');
  const fileSize = filePreview.querySelector('.file-size');
  const fileIcon = filePreview.querySelector('.file-icon i');
  const btnSubir = document.getElementById('btnSubir');
  
  // Validar que los elementos existan
  if (!uploadZone || !filePreview || !fileName || !fileSize || !fileIcon || !btnSubir) {
    console.error('Elementos del preview no encontrados');
    return;
  }
  
  // Determinar icono según tipo de archivo
  let iconClass = 'fas fa-file';
  if (file.type.includes('pdf')) iconClass = 'fas fa-file-pdf';
  else if (file.type.includes('excel') || file.type.includes('spreadsheet')) iconClass = 'fas fa-file-excel';
  else if (file.type.includes('word')) iconClass = 'fas fa-file-word';
  
  fileIcon.className = iconClass;
  fileName.textContent = file.name;
  fileSize.textContent = formatFileSize(file.size);
  
  uploadZone.style.display = 'none';
  filePreview.style.display = 'flex';
  btnSubir.disabled = false;
}

/**
 * Remueve el archivo seleccionado
 */
function removeFile() {
  const fileInput = document.getElementById('fileInput');
  const uploadZone = document.getElementById('uploadZone');
  const filePreview = document.getElementById('filePreview');
  const btnSubir = document.getElementById('btnSubir');
  
  if (fileInput) fileInput.value = '';
  if (uploadZone) uploadZone.style.display = 'block';
  if (filePreview) filePreview.style.display = 'none';
  if (btnSubir) btnSubir.disabled = true;
}

/**
 * Formatea el tamaño del archivo
 * @param {number} bytes - Tamaño en bytes
 * @returns {string} - Tamaño formateado con unidad
 */
function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Sube el archivo al servidor
 * @param {Event} e - Evento del formulario
 */
function subirArchivo(e) {
  e.preventDefault();
  
  const uploadForm = document.getElementById('uploadForm');
  const btnSubir = document.getElementById('btnSubir');
  
  if (!uploadForm || !btnSubir) {
    console.error('Elementos del formulario no encontrados');
    return;
  }
  
  const formData = new FormData(uploadForm);
  formData.append('csrf_token', window.CSRF_TOKEN);
  
  btnSubir.disabled = true;
  btnSubir.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
  
  fetch('upload_archivo.php', {
    method: 'POST',
    body: formData
  })
  .then(response => {
    // Siempre intentar leer el JSON, incluso si hay error
    return response.json().then(data => {
      return { ok: response.ok, data: data };
    }).catch(() => {
      // Si no se puede parsear JSON, lanzar error con el status
      throw new Error('Error del servidor (código ' + response.status + ')');
    });
  })
  .then(result => {
    // Cerrar modal siempre primero
    cerrarModalUpload();
    
    if (result.data.success) {
      Swal.fire({
        title: '¡Éxito!',
        text: result.data.message,
        icon: 'success',
        confirmButtonText: 'OK',
        confirmButtonColor: '#eb0045'
      }).then(() => {
        location.reload();
      });
    } else {
      Swal.fire({
        title: 'Error',
        text: result.data.message || 'Error desconocido al subir el archivo',
        icon: 'error',
        confirmButtonText: 'OK',
        confirmButtonColor: '#eb0045'
      });
    }
  })
  .catch(error => {
    // Cerrar modal también en caso de error
    cerrarModalUpload();
    
    console.error('Error:', error);
    Swal.fire({
      title: 'Error',
      text: 'Error al subir el archivo: ' + error.message,
      icon: 'error',
      confirmButtonText: 'OK',
      confirmButtonColor: '#eb0045'
    });
  })
  .finally(() => {
    btnSubir.disabled = false;
    btnSubir.innerHTML = '<i class="fas fa-upload"></i> Subir Archivo';
  });
}

/**
 * Elimina un archivo del servidor
 * @param {string} nombreArchivo - Nombre del archivo a eliminar
 * @param {string} tipoSeccion - Tipo de sección ('recursos' o 'archivo')
 */
function eliminarArchivo(nombreArchivo, tipoSeccion) {
  if (!nombreArchivo || !tipoSeccion) {
    console.error('Parámetros inválidos para eliminar archivo');
    return;
  }
  
  // Determinar si es una postulación
  const esPostulacion = nombreArchivo.includes('HV_') && tipoSeccion === 'archivo';
  
  Swal.fire({
    title: '¿Estás seguro?',
    text: esPostulacion ? 
      `Se eliminará la postulación con archivo "${nombreArchivo}"` : 
      `Se eliminará el archivo "${nombreArchivo}"`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: 'Eliminando...',
        text: 'Eliminando el archivo',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      fetch('delete_archivo.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          nombre_archivo: nombreArchivo,
          tipo_seccion: tipoSeccion,
          csrf_token: window.CSRF_TOKEN
        })
      })
      .then(response => response.json().catch(() => ({ success: false, message: 'Error al procesar respuesta' })))
      .then(data => {
        if (data.success) {
          // Eliminar la fila de la tabla sin recargar
          eliminarFilaArchivoDeTabla(nombreArchivo, tipoSeccion);
          
          Swal.fire({
            title: '¡Eliminado!',
            text: data.message,
            icon: 'success',
            confirmButtonText: 'OK',
            timer: 2000,
            timerProgressBar: true
          });
        } else {
          Swal.fire({
            title: 'Error',
            text: data.message || 'Error al eliminar el archivo',
            icon: 'error',
            confirmButtonText: 'OK'
          });
        }
      })
      .catch(error => {
        Swal.fire({
          title: 'Error',
          text: 'Error de conexión. Intente de nuevo.',
          icon: 'error',
          confirmButtonText: 'OK'
        });
      });
    }
  });
}

/**
 * Elimina una fila de archivo de la tabla sin recargar
 */
function eliminarFilaArchivoDeTabla(nombreArchivo, tipoSeccion) {
  const botones = document.querySelectorAll('.btn-delete');
  botones.forEach(btn => {
    const onclick = btn.getAttribute('onclick');
    if (onclick && onclick.includes(`eliminarArchivo('${nombreArchivo}'`) && onclick.includes(`'${tipoSeccion}'`)) {
      const row = btn.closest('tr');
      if (row) {
        const tbody = row.closest('tbody');
        const tableContainer = row.closest('.table-container');
        const sectionHeader = tableContainer ? tableContainer.previousElementSibling : null;
        
        row.style.transition = 'opacity 0.3s, transform 0.3s';
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
          row.remove();
          
          // Verificar si la tabla quedó vacía
          if (tbody && tbody.querySelectorAll('tr').length === 0) {
            // Crear el estado vacío
            const emptySection = document.createElement('div');
            emptySection.className = 'empty-section';
            
            if (tipoSeccion === 'recursos') {
              emptySection.innerHTML = `
                <div class="empty-icon">
                  <i class="fas fa-archive"></i>
                </div>
                <h3>No hay recursos</h3>
                <p>Sube archivos Excel u otros recursos corporativos aquí.</p>
                <button onclick="abrirModalUpload('recursos')" class="btn-upload-empty">
                  <i class="fas fa-plus"></i>
                  Subir Primer Recurso
                </button>
              `;
            } else {
              emptySection.innerHTML = `
                <div class="empty-icon">
                  <i class="fas fa-paper-plane"></i>
                </div>
                <h3>No hay archivos</h3>
                <p>Sube documentos de postulaciones y archivos procesados aquí.</p>
                <button onclick="abrirModalUpload('archivo')" class="btn-upload-empty">
                  <i class="fas fa-plus"></i>
                  Subir Primer Archivo
                </button>
              `;
            }
            
            // Reemplazar la tabla con el estado vacío
            if (tableContainer) {
              tableContainer.replaceWith(emptySection);
            }
            
            // Actualizar contadores
            actualizarContadores();
          } else {
            // Solo actualizar contadores
            actualizarContadores();
          }
        }, 300);
      }
    }
  });
}

/**
 * Actualiza los contadores en el header
 */
function actualizarContadores() {
  const totalEl = document.querySelector('.stat-number');
  const statsItems = document.querySelectorAll('.stat-item .stat-number');
  
  // Contar filas en cada tabla
  const recursosRows = document.querySelectorAll('#tabla-excel-corporativo tr').length;
  const archivosRows = document.querySelectorAll('#tabla-archivos-unificados tr').length;
  const archivosSistema = document.querySelectorAll('tr[data-tipo="excel"], tr[data-tipo="pdf"], tr[data-tipo="word"]').length;
  
  const total = recursosRows + archivosRows;
  
  if (statsItems.length >= 3) {
    statsItems[0].textContent = total; // Total
    statsItems[1].textContent = archivosRows; // Archivos
    statsItems[2].textContent = recursosRows; // Recursos
  }
  
  // Actualizar contador de resultados
  const resultCount = document.getElementById('resultCount');
  if (resultCount) {
    resultCount.textContent = total;
  }
}

// Event listeners globales
document.addEventListener('DOMContentLoaded', function() {
  // Event listener para cerrar modal con ESC
  document.addEventListener('keydown', function(e) {
    const uploadModal = document.getElementById('uploadModal');
    if (uploadModal && uploadModal.style.display === 'flex') {
      if (e.key === 'Escape') {
        e.preventDefault();
        cerrarModalUpload();
      }
    }
  });
  
  // Inicializar los filtros después de cargar la página
  const searchInput = document.getElementById('searchInput');
  const filtroTipo = document.getElementById('filtroTipo');
  
  if (searchInput && filtroTipo) {
    // Llamada inicial para aplicar filtros si hay alguno preseleccionado
    setTimeout(function() {
      if (filtroTipo.value || (searchInput && searchInput.value)) {
        const event = new Event('change');
        filtroTipo.dispatchEvent(event);
      }
    }, 500);
  }
});
