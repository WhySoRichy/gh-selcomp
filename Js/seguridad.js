/**
 * Funciones JavaScript para la página de seguridad de la cuenta
 * Maneja la visualización de formularios, validación y carga de historial
 * Versión: 1.1 - Actualizado: 17/07/2025
 */

// Variables globales
let paginaActual = 1;
const registrosPorPagina = 10;

// Esperar a que se cargue el documento
document.addEventListener('DOMContentLoaded', function() {
    // Configurar manejo de mensajes de SweetAlert si hay mensajes guardados
    if (typeof mensajeTitulo !== 'undefined' && typeof mensajeTexto !== 'undefined') {
        Swal.fire({
            title: mensajeTitulo,
            text: mensajeTexto,
            icon: mensajeTipo || 'info',
            confirmButtonText: 'Entendido'
        });
    }
    
    // Inicializar la validación de contraseñas si existe el formulario
    const formCambioPass = document.getElementById('form-cambiar-password');
    if (formCambioPass) {
        configurarValidacionContraseña();
    }
    
    // Añadir manejador de evento para el botón de exportar historial si existe
    const btnExportar = document.querySelector('.btn-export');
    if (btnExportar) {
        btnExportar.addEventListener('click', exportarHistorialCSV);
    }
});

/**
 * Muestra el formulario de cambio de contraseña
 */
function mostrarFormularioCambioContraseña() {
    const overlay = document.getElementById('modal-overlay');
    const modal = document.getElementById('form-cambio-contraseña');
    
    // Mostrar elementos
    overlay.style.display = 'block';
    modal.style.display = 'block';
    
    // Permitir que ocurran las transiciones
    setTimeout(() => {
        overlay.classList.add('active');
        modal.classList.add('active');
        
        // Añadir focus al primer campo después de la animación
        setTimeout(() => {
            document.getElementById('actual_contrasena').focus();
        }, 300);
    }, 10);
    
    document.body.classList.add('modal-open');
    
    // Configurar validación en tiempo real
    configurarValidacionContraseña();
}

/**
 * Muestra el historial de accesos
 */
function mostrarHistorialAccesos() {
    const overlay = document.getElementById('modal-overlay');
    const modal = document.getElementById('historial-accesos');
    
    // Mostrar elementos
    overlay.style.display = 'block';
    modal.style.display = 'block';
    
    // Permitir que ocurran las transiciones
    setTimeout(() => {
        overlay.classList.add('active');
        modal.classList.add('active');
    }, 10);
    
    document.body.classList.add('modal-open');
    cargarHistorialAccesos();
}

/**
 * Oculta un formulario o contenedor por su ID
 */
function ocultarFormulario(id) {
    const overlay = document.getElementById('modal-overlay');
    const modal = document.getElementById(id);
    
    // Iniciar animación de cierre
    overlay.classList.remove('active');
    modal.classList.remove('active');
    
    // Ocultar después de la animación
    setTimeout(() => {
        overlay.style.display = 'none';
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }, 300);
}

/**
 * Obtiene la fecha y hora actual formateada
 * @return {Object} - Objeto con la fecha formateada y timestamp
 */
function obtenerFechaHoraActual() {
    const ahora = new Date();
    
    // Formato para mostrar
    const dia = String(ahora.getDate()).padStart(2, '0');
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const anio = ahora.getFullYear();
    const hora = String(ahora.getHours()).padStart(2, '0');
    const minutos = String(ahora.getMinutes()).padStart(2, '0');
    const segundos = String(ahora.getSeconds()).padStart(2, '0');
    
    // Formato para enviar a servidor (YYYY-MM-DD HH:MM:SS)
    const fechaSQL = `${anio}-${mes}-${dia} ${hora}:${minutos}:${segundos}`;
    
    return {
        formateada: `${dia}/${mes}/${anio} ${hora}:${minutos}:${segundos}`,
        sql: fechaSQL,
        timestamp: ahora.getTime()
    };
}

/**
 * Carga el historial de accesos desde el servidor
 */
function cargarHistorialAccesos(pagina = 1) {
    paginaActual = pagina;
    const contenedor = document.getElementById('contenido-historial');
    
    // Mostrar loader con animación mejorada
    contenedor.innerHTML = `
        <div class="loading-text">
            <i class="fas fa-circle-notch fa-spin"></i>
            <span>Cargando tu historial de accesos...</span>
        </div>
    `;
    
    // Añadir timestamp para evitar problemas de caché
    const timestamp = new Date().getTime();
    
    // Realizar la petición AJAX para obtener el HTML directamente
    fetch(`obtener_historial_accesos.php?pagina=${pagina}&limite=${registrosPorPagina}&t=${timestamp}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error de servidor: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            // Insertar el HTML directamente ya que viene formateado del servidor
            contenedor.innerHTML = html;
            
            // Buscar y corregir fechas inválidas después de insertar el contenido
            corregirFechasInvalidas();
        })
        .catch(error => {
            console.error('Error al cargar el historial:', error);
            // Mostrar mensaje de error
            contenedor.innerHTML = `
                <div class="error-container">
                    <p class="error-text"><i class="fas fa-exclamation-triangle"></i> Error al cargar el historial</p>
                    <p class="error-details">${error.message}</p>
                    <div class="error-actions">
                        <button onclick="cargarHistorialAccesos()" class="btn-error-action">
                            <i class="fas fa-sync-alt"></i> Reintentar
                        </button>
                        <a href="/gh/usuario/reparar_historial.php" class="btn-error-action" style="background-color: #eb0045; color: white;">
                            <i class="fas fa-tools"></i> Reparar historial
                        </a>
                    </div>
                </div>
            `;
        });
}

/**
 * Muestra el historial de accesos en la interfaz
 */
function mostrarHistorial(historial, paginacion) {
    const contenedor = document.getElementById('contenido-historial');
    
    if (historial.length === 0) {
        contenedor.innerHTML = '<p class="empty-text"><i class="fas fa-info-circle"></i> No hay registros de acceso recientes.</p>';
        return;
    }
    
    // Botón para exportar CSV
    let html = `
        <div class="historial-actions">
            <button onclick="exportarHistorialCSV()" class="btn-export">
                <i class="fas fa-file-download"></i> Exportar Historial
            </button>
        </div>
    `;
    
    // Crear la tabla
    html += `
        <div class="table-responsive">
            <table class="historial-table">
                <thead>
                    <tr>
                        <th>Fecha y Hora</th>
                        <th>Tiempo</th>
                        <th>Dirección IP</th>
                        <th>Dispositivo</th>
                        <th>Estado</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    // Añadir las filas
    historial.forEach(acceso => {
        // Formatear la dirección IP para que sea más amigable
        let ipMostrar = acceso.ip_acceso;
        if (ipMostrar === '::1' || ipMostrar === '127.0.0.1') {
            ipMostrar = 'Local (Mismo equipo)';
        }
        
        // Validar y corregir fecha inválida
        let fechaMostrar = validarFechaHistorial(acceso.fecha_formateada);
        
        html += `
            <tr class="acceso-${acceso.estado}">
                <td>${fechaMostrar}</td>
                <td><small>${acceso.tiempo_transcurrido}</small></td>
                <td>${ipMostrar}</td>
                <td><span title="${acceso.navegador}">${formatearDispositivo(acceso.dispositivo)}</span></td>
                <td><span class="estado-${acceso.estado}">${acceso.estado_texto}</span></td>
                <td>${acceso.detalles}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    // Añadir paginación
    if (paginacion.total_paginas > 1) {
        html += '<div class="paginacion">';
        
        // Botón anterior
        if (paginacion.pagina_actual > 1) {
            html += `<button onclick="cargarHistorialAccesos(${paginacion.pagina_actual - 1})" class="btn-pag"><i class="fas fa-chevron-left"></i></button>`;
        } else {
            html += `<button disabled class="btn-pag disabled"><i class="fas fa-chevron-left"></i></button>`;
        }
        
        // Botones de páginas
        let inicio = Math.max(1, paginacion.pagina_actual - 2);
        let fin = Math.min(paginacion.total_paginas, inicio + 4);
        
        for (let i = inicio; i <= fin; i++) {
            if (i === paginacion.pagina_actual) {
                html += `<button class="btn-pag active">${i}</button>`;
            } else {
                html += `<button onclick="cargarHistorialAccesos(${i})" class="btn-pag">${i}</button>`;
            }
        }
        
        // Botón siguiente
        if (paginacion.pagina_actual < paginacion.total_paginas) {
            html += `<button onclick="cargarHistorialAccesos(${paginacion.pagina_actual + 1})" class="btn-pag"><i class="fas fa-chevron-right"></i></button>`;
        } else {
            html += `<button disabled class="btn-pag disabled"><i class="fas fa-chevron-right"></i></button>`;
        }
        
        html += '</div>';
    }
    
    contenedor.innerHTML = html;
}

/**
 * Formatea el nombre del dispositivo para mostrar
 */
function formatearDispositivo(dispositivo) {
    // Si el UA es demasiado largo, lo truncamos
    if (dispositivo.length > 30) {
        // Intentamos detectar el dispositivo/navegador principal
        if (dispositivo.includes('iPhone')) return 'iPhone';
        if (dispositivo.includes('iPad')) return 'iPad';
        if (dispositivo.includes('Android')) return 'Android';
        if (dispositivo.includes('Windows')) return 'Windows';
        if (dispositivo.includes('Macintosh')) return 'Mac';
        if (dispositivo.includes('Linux')) return 'Linux';
        
        // Si no encontramos nada específico, truncamos
        return dispositivo.substring(0, 30) + '...';
    }
    
    return dispositivo;
}

/**
 * Valida y corrige fechas inválidas en el historial
 * @param {string} fechaStr - La fecha formateada que viene del servidor
 * @return {string} - La fecha correcta formateada
 */
function validarFechaHistorial(fechaStr) {
    // Verificar si es una fecha inválida (como 30/11/-0001, 0000-00-00 00:00:00, etc)
    if (!fechaStr || 
        fechaStr.includes('-0001') || 
        fechaStr.includes('undefined') || 
        fechaStr.includes('0000-00-00') || 
        fechaStr === '00/00/0000' ||
        fechaStr === '00/00/0000 00:00') {
        
        // Obtener la fecha actual formateada
        const ahora = new Date();
        const dia = String(ahora.getDate()).padStart(2, '0');
        const mes = String(ahora.getMonth() + 1).padStart(2, '0');
        const anio = ahora.getFullYear();
        const hora = String(ahora.getHours()).padStart(2, '0');
        const minutos = String(ahora.getMinutes()).padStart(2, '0');
        const segundos = String(ahora.getSeconds()).padStart(2, '0');
        
        return `${dia}/${mes}/${anio} ${hora}:${minutos}:${segundos}`;
    }
    
    return fechaStr;
}

// Event listeners cuando se carga el documento
document.addEventListener('DOMContentLoaded', function() {
    // Configurar el overlay para cerrar modales al hacer clic
    const overlay = document.getElementById('modal-overlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            document.getElementById('form-cambio-contraseña').style.display = 'none';
            document.getElementById('historial-accesos').style.display = 'none';
            this.style.display = 'none';
            document.body.classList.remove('modal-open');
        });
    }
    
    // Permitir cerrar modales con la tecla Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.getElementById('form-cambio-contraseña').style.display = 'none';
            document.getElementById('historial-accesos').style.display = 'none';
            document.getElementById('modal-overlay').style.display = 'none';
            document.body.classList.remove('modal-open');
        }
    });
    
    // Validación de coincidencia de contraseñas
    const nuevaContrasena = document.getElementById('nueva_contrasena');
    const confirmarContrasena = document.getElementById('confirmar_contrasena');
    const passwordMatch = document.getElementById('password-match');
    
    function validarCoincidencia() {
        if (confirmarContrasena.value === '') {
            passwordMatch.textContent = '';
            passwordMatch.className = 'password-match';
            confirmarContrasena.classList.remove('valid', 'invalid');
            return;
        }
        
        if (nuevaContrasena.value === confirmarContrasena.value) {
            passwordMatch.textContent = '¡Las contraseñas coinciden!';
            passwordMatch.className = 'password-match match';
            confirmarContrasena.classList.remove('invalid');
            confirmarContrasena.classList.add('valid');
        } else {
            passwordMatch.textContent = 'Las contraseñas no coinciden';
            passwordMatch.className = 'password-match no-match';
            confirmarContrasena.classList.remove('valid');
            confirmarContrasena.classList.add('invalid');
        }
    }
    
    // Validar complejidad de la contraseña
    function validarComplejidad() {
        const valor = nuevaContrasena.value;
        
        if (valor.length < 8) {
            nuevaContrasena.classList.remove('valid');
            nuevaContrasena.classList.add('invalid');
            return;
        }
        
        const tieneUpperCase = /[A-Z]/.test(valor);
        const tieneLowerCase = /[a-z]/.test(valor);
        const tieneNumero = /[0-9]/.test(valor);
        const tieneEspecial = /[!@#$%^&*]/.test(valor);
        
        if (tieneUpperCase && tieneLowerCase && tieneNumero && tieneEspecial) {
            nuevaContrasena.classList.remove('invalid');
            nuevaContrasena.classList.add('valid');
        } else {
            nuevaContrasena.classList.remove('valid');
            nuevaContrasena.classList.add('invalid');
        }
    }
    
    // Añadir listeners para validar en tiempo real
    if (nuevaContrasena && confirmarContrasena) {
        nuevaContrasena.addEventListener('input', function() {
            validarComplejidad();
            validarCoincidencia();
        });
        confirmarContrasena.addEventListener('input', validarCoincidencia);
    }
    
    // Validación del formulario de cambio de contraseña
    const formCambiarPassword = document.getElementById('form-cambiar-password');
    if (formCambiarPassword) {
        formCambiarPassword.addEventListener('submit', function(event) {
            const nueva = nuevaContrasena.value;
            const confirmacion = confirmarContrasena.value;
            
            // Validar longitud
            if (nueva.length < 8) {
                event.preventDefault();
                Swal.fire({
                    title: 'Contraseña demasiado corta',
                    text: 'La contraseña debe tener al menos 8 caracteres',
                    icon: 'error',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
            
            // Validar que coincidan
            if (nueva !== confirmacion) {
                event.preventDefault();
                Swal.fire({
                    title: 'Las contraseñas no coinciden',
                    text: 'Por favor verifica que ambas contraseñas sean iguales',
                    icon: 'error',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
            
            // Validar complejidad de la contraseña
            const tieneUpperCase = /[A-Z]/.test(nueva);
            const tieneLowerCase = /[a-z]/.test(nueva);
            const tieneNumero = /[0-9]/.test(nueva);
            const tieneEspecial = /[!@#$%^&*]/.test(nueva);
            
            if (!tieneUpperCase || !tieneLowerCase || !tieneNumero || !tieneEspecial) {
                event.preventDefault();
                Swal.fire({
                    title: 'Contraseña no segura',
                    text: 'La contraseña debe incluir mayúsculas, minúsculas, números y caracteres especiales (!@#$%^&*)',
                    icon: 'warning',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
        });
    }
    
    // Inicializar notificaciones de alerta si hay mensajes en sesión
    if (typeof Swal !== 'undefined' && 
        typeof mensajeTitulo !== 'undefined' && 
        typeof mensajeTexto !== 'undefined' && 
        typeof mensajeTipo !== 'undefined') {
        
        Swal.fire({
            title: mensajeTitulo,
            text: mensajeTexto,
            icon: mensajeTipo,
            confirmButtonText: 'Entendido'
        });
    }
});

/**
 * Corrige las fechas inválidas directamente en el DOM
 */
function corregirFechasInvalidas() {
    // Buscar todas las celdas de fecha en la tabla de historial
    const celdasFecha = document.querySelectorAll('.historial-table tbody tr td:first-child');
    
    celdasFecha.forEach(celda => {
        const fechaTexto = celda.textContent.trim();
        
        // Si es una fecha inválida, reemplazarla
        if (fechaTexto.includes('0000-00-00') || 
            fechaTexto === '00/00/0000' || 
            fechaTexto === '00/00/0000 00:00' || 
            fechaTexto.includes('-0001')) {
            
            celda.textContent = obtenerFechaHoraActual().formateada;
            celda.classList.add('fecha-corregida');
            
            // Añadir un tooltip para indicar que la fecha fue corregida
            celda.setAttribute('title', 'Fecha original inválida, se muestra la fecha actual');
        }
    });
}

/**
 * Exporta el historial de accesos a formato CSV
 */
function exportarHistorialCSV() {
    // Obtener fecha actual para el nombre del archivo
    const ahora = new Date();
    const fechaArchivo = `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}`;
    
    // Encabezados del CSV
    let csvContent = "Fecha y Hora,IP,Dispositivo,Estado,Detalles\n";
    
    // Obtener todas las filas de la tabla
    const filas = document.querySelectorAll('.historial-table tbody tr');
    
    filas.forEach(fila => {
        // Extraer datos de cada celda, validando fechas
        const celdas = fila.querySelectorAll('td');
        if (celdas.length >= 5) {
            // Validar la fecha usando nuestra función
            const fecha = validarFechaHistorial(celdas[0].textContent.trim());
            const ip = celdas[2].textContent.trim();
            const dispositivo = celdas[3].textContent.trim();
            const estado = celdas[4].textContent.trim();
            const detalles = celdas[5] ? celdas[5].textContent.trim() : '';
            
            // Escapar campos para CSV y añadir la línea
            csvContent += `"${fecha}","${ip}","${dispositivo}","${estado}","${detalles}"\n`;
        }
    });
    
    // Crear enlace para descargar
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    
    link.setAttribute('href', url);
    link.setAttribute('download', `historial_accesos_${fechaArchivo}.csv`);
    link.style.display = 'none';
    
    // Añadir, simular clic y remover
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Configura la validación en tiempo real para el formulario de cambio de contraseña
 */
function configurarValidacionContraseña() {
    const nuevaContrasena = document.getElementById('nueva_contrasena');
    const confirmarContrasena = document.getElementById('confirmar_contrasena');
    const passwordMatch = document.getElementById('password-match');
    
    if (!nuevaContrasena || !confirmarContrasena || !passwordMatch) return;
    
    // Validar coincidencia de contraseñas en tiempo real
    confirmarContrasena.addEventListener('input', function() {
        const nueva = nuevaContrasena.value;
        const confirmacion = confirmarContrasena.value;
        
        if (confirmacion === '') {
            passwordMatch.textContent = '';
            passwordMatch.className = 'password-match';
        } else if (nueva === confirmacion) {
            passwordMatch.textContent = '¡Las contraseñas coinciden!';
            passwordMatch.className = 'password-match password-match-success';
        } else {
            passwordMatch.textContent = 'Las contraseñas no coinciden';
            passwordMatch.className = 'password-match password-match-error';
        }
    });
    
    // Verificar fortaleza de la contraseña
    nuevaContrasena.addEventListener('input', function() {
        const nueva = nuevaContrasena.value;
        const tieneUpperCase = /[A-Z]/.test(nueva);
        const tieneLowerCase = /[a-z]/.test(nueva);
        const tieneNumero = /[0-9]/.test(nueva);
        const tieneEspecial = /[!@#$%^&*]/.test(nueva);
        const longitudValida = nueva.length >= 8;
        
        // Actualizar la apariencia del campo según la fortaleza
        if (nueva.length === 0) {
            nuevaContrasena.classList.remove('password-weak', 'password-medium', 'password-strong');
        } else if (longitudValida && tieneUpperCase && tieneLowerCase && tieneNumero && tieneEspecial) {
            nuevaContrasena.classList.remove('password-weak', 'password-medium');
            nuevaContrasena.classList.add('password-strong');
        } else if (longitudValida && ((tieneUpperCase && tieneLowerCase && tieneNumero) || 
                 (tieneUpperCase && tieneLowerCase && tieneEspecial) || 
                 (tieneUpperCase && tieneNumero && tieneEspecial) || 
                 (tieneLowerCase && tieneNumero && tieneEspecial))) {
            nuevaContrasena.classList.remove('password-weak', 'password-strong');
            nuevaContrasena.classList.add('password-medium');
        } else {
            nuevaContrasena.classList.remove('password-medium', 'password-strong');
            nuevaContrasena.classList.add('password-weak');
        }
    });
}

/**
 * Toggle Autenticación de Doble Factor (2FA)
 * @param {boolean} activar - true para activar, false para desactivar
 */
async function toggle2FA(activar) {
    const accion = activar ? 'activar' : 'desactivar';
    const titulo = activar ? 'Activar Verificación en 2 Pasos' : 'Desactivar Verificación en 2 Pasos';
    const mensaje = activar 
        ? 'Al activar, recibirás un código por email cada vez que inicies sesión. ¿Deseas continuar?'
        : '¿Estás seguro de desactivar la verificación en 2 pasos? Tu cuenta será menos segura.';
    const iconoConfirm = activar ? 'question' : 'warning';
    
    const confirmacion = await Swal.fire({
        title: titulo,
        text: mensaje,
        icon: iconoConfirm,
        showCancelButton: true,
        confirmButtonColor: '#eb0045',
        cancelButtonColor: '#6b7280',
        confirmButtonText: activar ? 'Sí, activar' : 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    });
    
    if (!confirmacion.isConfirmed) {
        return;
    }
    
    // Mostrar loading
    Swal.fire({
        title: 'Procesando...',
        text: 'Por favor espera',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        // Obtener token CSRF dedicado para 2FA
        const csrfInput = document.getElementById('csrf_token_2fa');
        const csrfToken = csrfInput ? csrfInput.value : '';
        
        if (!csrfToken) {
            Swal.fire({
                title: 'Error',
                text: 'Token de seguridad no encontrado. Recarga la página.',
                icon: 'error',
                confirmButtonText: 'Recargar'
            }).then(() => window.location.reload());
            return;
        }
        
        const formData = new FormData();
        formData.append('activar', activar.toString());
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('toggle_2fa.php', {
            method: 'POST',
            body: formData
        });
        
        // Verificar que la respuesta sea válida
        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Respuesta no JSON:', responseText);
            throw new Error('Respuesta inválida del servidor');
        }
        
        if (data.success) {
            await Swal.fire({
                title: activar ? '¡Activado!' : 'Desactivado',
                text: data.message,
                icon: 'success',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#eb0045'
            });
            
            // Recargar página para reflejar cambios
            window.location.reload();
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message || 'No se pudo actualizar la configuración',
                icon: 'error',
                confirmButtonText: 'Entendido'
            });
        }
    } catch (error) {
        console.error('Error al toggle 2FA:', error);
        Swal.fire({
            title: 'Error',
            text: 'Error de conexión. Por favor intenta nuevamente.',
            icon: 'error',
            confirmButtonText: 'Entendido'
        });
    }
}
