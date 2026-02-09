/**
 * Gestión del formulario de perfil de usuario
 * Validaciones en tiempo real y envío AJAX
 */

document.addEventListener('DOMContentLoaded', function() {
    const formulario = document.getElementById('perfil-form');
    const campos = {
        nombre: document.getElementById('nombre'),
        apellido: document.getElementById('apellido'),
        telefono: document.getElementById('telefono'),
        direccion: document.getElementById('direccion'),
        fecha_nacimiento: document.getElementById('fecha_nacimiento'),
        estado_civil: document.getElementById('estado_civil'),
        emergencia_contacto: document.getElementById('emergencia_contacto'),
        emergencia_telefono: document.getElementById('emergencia_telefono'),
        acerca_de_mi: document.getElementById('acerca_de_mi')
    };
    
    // Elementos del avatar
    const avatarUpload = document.getElementById('avatar-upload');
    const avatarPreview = document.querySelector('.avatar-preview');
    const removeAvatarBtn = document.getElementById('remove-avatar');
    
    // Contador de caracteres para "Acerca de mí"
    const acercaTextarea = document.getElementById('acerca_de_mi');
    const acercaCount = document.getElementById('acerca-count');
    if (acercaTextarea && acercaCount) {
        acercaTextarea.addEventListener('input', function() {
            acercaCount.textContent = this.value.length;
        });
    }
    
    // Limpiar clases de validación al cargar con delay
    setTimeout(() => {
        limpiarValidacionInicial();
    }, 100);
    
    // Configurar validaciones en tiempo real
    configurarValidaciones();
    
    // Configurar manejo de avatar
    configurarAvatar();
    
    // Manejar envío del formulario
    if (formulario) {
        formulario.addEventListener('submit', manejarEnvio);
    }
    
    /**
     * Limpia cualquier clase de validación al cargar la página
     */
    function limpiarValidacionInicial() {
        console.log('Limpiando validaciones iniciales...');
        Object.values(campos).forEach(campo => {
            if (campo) {
                console.log('Limpiando campo:', campo.id);
                campo.classList.remove('error', 'success', 'valid');
                campo.removeAttribute('data-touched');
                
                // Forzar estilo inicial
                campo.style.borderColor = '';
                campo.style.backgroundColor = '';
                
                const errorElement = campo.parentNode.querySelector('.error-message');
                if (errorElement) {
                    errorElement.style.display = 'none';
                }
            }
        });
    }
    
    /**
     * Configura las validaciones en tiempo real para todos los campos
     */
    function configurarValidaciones() {
        // Validación para nombre y apellido
        [campos.nombre, campos.apellido].forEach(campo => {
            if (campo) {
                campo.addEventListener('blur', function() {
                    campo.dataset.touched = 'true';
                    validarTexto({target: campo});
                });
                campo.addEventListener('input', function() {
                    if (campo.dataset.touched === 'true') {
                        validarTexto({target: campo});
                    }
                });
            }
        });
        
        // Validación para teléfonos
        [campos.telefono, campos.emergencia_telefono].forEach(campo => {
            if (campo) {
                campo.addEventListener('blur', function() {
                    campo.dataset.touched = 'true';
                    validarTelefono({target: campo});
                });
                campo.addEventListener('input', function() {
                    if (campo.dataset.touched === 'true') {
                        validarTelefono({target: campo});
                    }
                });
            }
        });
        
        // Validación para fecha de nacimiento
        if (campos.fecha_nacimiento) {
            campos.fecha_nacimiento.addEventListener('blur', function() {
                campos.fecha_nacimiento.dataset.touched = 'true';
                validarFechaNacimiento({target: campos.fecha_nacimiento});
            });
            campos.fecha_nacimiento.addEventListener('change', function() {
                if (campos.fecha_nacimiento.dataset.touched === 'true') {
                    validarFechaNacimiento({target: campos.fecha_nacimiento});
                }
            });
        }
        
        // Validación para contacto de emergencia
        if (campos.emergencia_contacto) {
            campos.emergencia_contacto.addEventListener('blur', function() {
                campos.emergencia_contacto.dataset.touched = 'true';
                validarTexto({target: campos.emergencia_contacto});
            });
            campos.emergencia_contacto.addEventListener('input', function() {
                if (campos.emergencia_contacto.dataset.touched === 'true') {
                    validarTexto({target: campos.emergencia_contacto});
                }
            });
        }
    }
    
    /**
     * Valida campos de texto (nombre, apellido, contacto de emergencia)
     */
    function validarTexto(event) {
        const campo = event.target;
        const valor = campo.value.trim();
        const esRequerido = campo.hasAttribute('required');
        const fueInteractuado = campo.dataset.touched === 'true';
        
        limpiarError(campo);
        
        if (esRequerido && valor === '') {
            if (fueInteractuado) {
                mostrarError(campo, 'Este campo es obligatorio');
            }
            return false;
        }
        
        if (valor && (valor.length < 2 || valor.length > 100)) {
            if (fueInteractuado) {
                mostrarError(campo, 'Debe tener entre 2 y 100 caracteres');
            }
            return false;
        }
        
        if (valor && !/^[a-zA-ZÀ-ÿ\s'-]+$/.test(valor)) {
            if (fueInteractuado) {
                mostrarError(campo, 'Solo se permiten letras, espacios, apostrofes y guiones');
            }
            return false;
        }
        
        if (fueInteractuado) {
            mostrarValido(campo);
        }
        return true;
    }
    
    /**
     * Valida números de teléfono
     */
    function validarTelefono(event) {
        const campo = event.target;
        const valor = campo.value.trim();
        const fueInteractuado = campo.dataset.touched === 'true';
        
        limpiarError(campo);
        
        if (valor === '') {
            return true; // Los teléfonos no son obligatorios
        }
        
        if (!/^[0-9+\-\s()]{7,15}$/.test(valor)) {
            if (fueInteractuado) {
                mostrarError(campo, 'Formato inválido (7-15 caracteres, números, +, -, espacios y paréntesis)');
            }
            return false;
        }
        
        if (fueInteractuado) {
            mostrarValido(campo);
        }
        return true;
    }
    
    /**
     * Valida fecha de nacimiento
     */
    function validarFechaNacimiento(event) {
        const campo = event.target;
        const valor = campo.value;
        const fueInteractuado = campo.dataset.touched === 'true';
        
        limpiarError(campo);
        
        if (!valor) {
            return true; // La fecha no es obligatoria
        }
        
        const fechaNacimiento = new Date(valor);
        const hoy = new Date();
        const edad = hoy.getFullYear() - fechaNacimiento.getFullYear();
        const mesActual = hoy.getMonth();
        const diaActual = hoy.getDate();
        const mesNacimiento = fechaNacimiento.getMonth();
        const diaNacimiento = fechaNacimiento.getDate();
        
        // Ajustar edad si no ha pasado el cumpleaños este año
        let edadReal = edad;
        if (mesActual < mesNacimiento || (mesActual === mesNacimiento && diaActual < diaNacimiento)) {
            edadReal--;
        }
        
        if (edadReal < 16 || edadReal > 120) {
            if (fueInteractuado) {
                mostrarError(campo, 'La edad debe estar entre 16 y 120 años');
            }
            return false;
        }
        
        if (fueInteractuado) {
            mostrarValido(campo);
        }
        return true;
    }
    
    /**
     * Valida todo el formulario
     */
    function validarFormulario() {
        let esValido = true;
        
        // Marcar todos los campos como tocados para mostrar validaciones
        Object.values(campos).forEach(campo => {
            if (campo) {
                campo.dataset.touched = 'true';
            }
        });
        
        // Validar campos obligatorios
        if (!validarTexto({target: campos.nombre})) esValido = false;
        if (!validarTexto({target: campos.apellido})) esValido = false;
        
        // Validar campos opcionales si tienen contenido
        if (campos.telefono.value && !validarTelefono({target: campos.telefono})) esValido = false;
        if (campos.emergencia_telefono.value && !validarTelefono({target: campos.emergencia_telefono})) esValido = false;
        if (campos.fecha_nacimiento.value && !validarFechaNacimiento({target: campos.fecha_nacimiento})) esValido = false;
        if (campos.emergencia_contacto.value && !validarTexto({target: campos.emergencia_contacto})) esValido = false;
        
        return esValido;
    }
    
    /**
     * Maneja el envío del formulario
     */
    function manejarEnvio(event) {
        event.preventDefault();
        console.log('Formulario enviado por AJAX');
        
        if (!validarFormulario()) {
            mostrarToast('Por favor, corrige los errores antes de continuar', 'error');
            return;
        }
        
        const botonSubmit = formulario.querySelector('button[type="submit"]');
        const textoOriginal = botonSubmit.innerHTML;
        
        // Mostrar estado de carga
        botonSubmit.disabled = true;
        botonSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        
        // Crear FormData
        const formData = new FormData(formulario);
        
        // Enviar datos
        fetch('/gh/usuario/procesar_perfil.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Respuesta del servidor:', data);
            if (data.success) {
                mostrarToast(data.message, 'success');
                console.log('Toast de éxito mostrado');
                
                // Si se actualizó el avatar, actualizar la interfaz
                if (data.avatar_url) {
                    actualizarAvatarEnInterfaz(data.avatar_url);
                }
                
                // Actualizar información en la interfaz si es necesario
                if (data.data) {
                    actualizarInfoPerfil(data.data);
                }
                
                // Remover clases de error/éxito después de un guardado exitoso
                setTimeout(() => {
                    Object.values(campos).forEach(campo => {
                        if (campo) limpiarError(campo);
                    });
                }, 2000);
            } else {
                mostrarToast(data.message, 'error');
                console.log('Toast de error mostrado');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarToast('Error de conexión. Inténtalo nuevamente.', 'error');
        })
        .finally(() => {
            // Restaurar botón
            botonSubmit.disabled = false;
            botonSubmit.innerHTML = textoOriginal;
        });
    }
    
    /**
     * Actualiza la información del perfil en la interfaz
     */
    function actualizarInfoPerfil(datos) {
        // Actualizar header del perfil
        const nombreCompleto = document.querySelector('.perfil-info h2');
        if (nombreCompleto && datos.nombre && datos.apellido) {
            nombreCompleto.textContent = `${datos.nombre} ${datos.apellido}`;
        }
    }
    
    /**
     * Actualizar avatar en toda la interfaz
     */
    function actualizarAvatarEnInterfaz(avatarUrl) {
        // Actualizar en el header del perfil
        const headerAvatar = document.querySelector('.perfil-header .perfil-avatar');
        if (headerAvatar) {
            headerAvatar.innerHTML = `<img src="${avatarUrl}" alt="Avatar">`;
        }
        
        // Actualizar en el preview del formulario
        if (avatarPreview) {
            avatarPreview.innerHTML = `<img src="${avatarUrl}" alt="Avatar">`;
        }
        
        // Agregar botón de eliminar si no existe
        const existingRemoveBtn = document.getElementById('remove-avatar');
        if (!existingRemoveBtn && document.querySelector('.avatar-controls')) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn-remove';
            removeBtn.id = 'remove-avatar';
            removeBtn.innerHTML = '<i class="fas fa-trash"></i> Eliminar';
            document.querySelector('.avatar-controls').appendChild(removeBtn);
            
            // Reconfigurar eventos
            configurarAvatar();
        }
    }
    
    /**
     * Muestra un error en un campo
     */
    function mostrarError(campo, mensaje) {
        campo.classList.add('error');
        campo.classList.remove('success', 'valid');
        campo.setAttribute('data-touched', 'true');
        
        // Buscar o crear elemento de error
        let errorElement = campo.parentNode.querySelector('.error-message');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'error-message';
            campo.parentNode.appendChild(errorElement);
        }
        
        errorElement.textContent = mensaje;
        errorElement.style.display = 'block';
    }
    
    /**
     * Muestra que un campo es válido
     */
    function mostrarValido(campo) {
        campo.classList.add('success');
        campo.classList.remove('error');
        campo.setAttribute('data-touched', 'true');
        
        const errorElement = campo.parentNode.querySelector('.error-message');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
    }
    
    /**
     * Limpia errores de un campo
     */
    function limpiarError(campo) {
        // Solo limpiar clases si el campo ha sido tocado
        if (campo.dataset.touched === 'true') {
            campo.classList.remove('error', 'success', 'valid');
        }
        
        const errorElement = campo.parentNode.querySelector('.error-message');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
    }
    
    /**
     * Muestra un toast notification
     */
    function mostrarToast(mensaje, tipo) {
        // Remover toast anterior si existe
        const toastAnterior = document.querySelector('.toast-notification');
        if (toastAnterior) {
            toastAnterior.remove();
        }
        
        // Crear toast mejorado
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${tipo}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                <span>${mensaje}</span>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Mostrar toast inmediatamente
        setTimeout(() => {
            toast.classList.add('show');
        }, 50);
        
        // Ocultar toast después de 6 segundos
        setTimeout(() => {
            if (toast.parentElement) {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 500);
            }
        }, 6000);
    }
    
    /**
     * Configurar funcionalidad de avatar
     */
    function configurarAvatar() {
        // Preview de imagen al seleccionar archivo
        if (avatarUpload) {
            avatarUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validar tipo de archivo
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Tipo de archivo no válido',
                            text: 'Solo se permiten archivos JPEG, PNG, GIF y WebP'
                        });
                        e.target.value = '';
                        return;
                    }
                    
                    // Validar tamaño (5MB máximo)
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    if (file.size > maxSize) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Archivo muy grande',
                            text: 'El archivo debe ser menor a 5MB'
                        });
                        e.target.value = '';
                        return;
                    }
                    
                    // Mostrar preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        mostrarPreviewAvatar(e.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Eliminar avatar
        if (removeAvatarBtn) {
            removeAvatarBtn.addEventListener('click', function() {
                Swal.fire({
                    title: '¿Eliminar foto de perfil?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#eb0045',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        eliminarAvatar();
                    }
                });
            });
        }
    }
    
    /**
     * Mostrar preview del avatar
     */
    function mostrarPreviewAvatar(src) {
        if (avatarPreview) {
            const existingImg = avatarPreview.querySelector('img');
            const existingIcon = avatarPreview.querySelector('i');
            
            if (existingImg) {
                existingImg.src = src;
            } else {
                if (existingIcon) existingIcon.remove();
                const img = document.createElement('img');
                img.src = src;
                img.alt = 'Preview avatar';
                avatarPreview.appendChild(img);
            }
        }
    }
    
    /**
     * Eliminar avatar
     */
    function eliminarAvatar() {
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        
        fetch('procesar_perfil.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar ícono predeterminado
                if (avatarPreview) {
                    avatarPreview.innerHTML = '<i class="fas fa-user-circle"></i>';
                }
                
                // Actualizar avatar en header también
                const headerAvatar = document.querySelector('.perfil-header .perfil-avatar');
                if (headerAvatar) {
                    headerAvatar.innerHTML = '<i class="fas fa-user-circle"></i>';
                }
                
                // Remover botón de eliminar
                if (removeAvatarBtn) {
                    removeAvatarBtn.remove();
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'Avatar eliminado',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al eliminar el avatar'
            });
        });
    }
});
