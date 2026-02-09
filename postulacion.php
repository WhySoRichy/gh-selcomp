<?php
session_start();
require 'conexion/conexion.php';
require_once 'administrador/csrf_protection.php';

// Generar token CSRF
$csrf_token = generar_token_csrf();

// Headers de seguridad
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="Css/postulacion.css">
  <link rel="icon" type="image/ico" href="Img/logo.png">
  <title>Selcomp Ingeniería SAS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.14/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.14/dist/sweetalert2.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</head>
<body>
  <!-- Botón de regreso al inicio -->
  <div class="back-button">
    <a href="index.php" class="btn-back animate__animated animate__fadeInLeft">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16">
        <path fill-rule="evenodd" d="M15 8a.75.75 0 0 1-.75.75H3.56l3.22 3.22a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06l4.5-4.5a.75.75 0 1 1 1.06 1.06L3.56 7.25h10.69A.75.75 0 0 1 15 8z"/>
      </svg>
      Volver al inicio
    </a>
  </div>

  <!-- Contenedor para centrar el formulario -->
  <div class="postulacion-wrapper">
    <!-- Formulario de postulación -->
    <form action="procesar_postulacion.php" method="POST" enctype="multipart/form-data" autocomplete="off" class="animate__animated animate__fadeIn">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <img src="Img/Selcomp 2k.png" id="logo" alt="Logo Selcomp">
    <h2>Únete a nuestra comunidad</h2>

    <div class="input-container">
      <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombres y apellidos completos*" maxlength="255" required>
      <img src="Img/user-registro.png" alt="">
    </div>

    <div class="input-container">
      <img src="Img/idcard.png" alt="">
      <select id="tipoDocumento" name="tipoDocumento" required>
        <option value="" disabled selected>Seleccione tipo de documento*</option>
        <option value="CC">Cédula de ciudadanía</option>
        <option value="CE">Cédula de extranjería</option>
        <option value="TI">Tarjeta de identidad</option>
        <option value="Pasaporte">Pasaporte</option>
      </select>
    </div>

    <div class="input-container">
      <input type="text" class="form-control" id="numeroDocumento" name="numeroDocumento" placeholder="Número de documento*" maxlength="20" required pattern="[A-Za-z0-9]+" title="Solo letras y números">
      <img src="Img/hashtag.png" alt="">
    </div>

    <div class="input-container">
      <input type="email" class="form-control" id="correo" name="correo" placeholder="Correo*" maxlength="255" required>
      <img src="Img/correo.png" alt="">
    </div>

    <?php
      $stmt = $conexion->prepare("SELECT id, titulo FROM vacantes");
      $stmt->execute();
      $vacantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Obtener vacante preseleccionada desde URL
      $vacante_seleccionada = $_GET['vacante'] ?? '';
    ?>
    <div class="input-container">
      <img src="Img/vacante.png" alt="Vacante">
      <select id="vacante" name="vacante" required>
        <option value="" disabled <?= empty($vacante_seleccionada) ? 'selected' : '' ?>>Elige una vacante</option>
        <?php foreach($vacantes as $vac): ?>
          <option value="<?= htmlspecialchars($vac['id']) ?>"
                  <?= ($vacante_seleccionada == $vac['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($vac['titulo']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

<div class="file-upload-container input-container">
  <input type="file" class="form-control" id="archivo" name="archivo" accept=".pdf" required style="opacity: 0; position: absolute; z-index: -1;">
  <label for="archivo" class="custom-file-label">
    <img src="Img/archivo.png" alt="">
    <span id="archivo-nombre">Seleccionar archivo</span>
  </label>
  <p class="archivo-info">Formato admitido: PDF (máx 10MB)</p>
</div>

    <div class="tratamiento">
      <input type="checkbox" name="tratamientoDatos" id="tratamientoDatos" required>
      <label for="tratamientoDatos">
        Acepto el tratamiento de datos – <a href="#" onclick="mostrarPolitica(); return false;" class="enlace">Leer Política</a>
      </label>
    </div>

    <input type="submit" class="btn animate__animated animate__pulse animate__infinite animate__slower" value="Enviar">
  </form>
  </div><!-- Cierre de postulacion-wrapper -->

  <script src="Js/mostrarPolitica.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <?php require_once 'mensaje_alerta.php'; ?>
  <script>
  // Configuración de validación por tipo de documento
  const configDocumentos = {
    'CC': {
      min: 6,
      max: 10,
      soloNumeros: true,
      nombre: 'Cédula de ciudadanía',
      placeholder: 'Ej: 1234567890'
    },
    'CE': {
      min: 6,
      max: 12,
      soloNumeros: false,
      nombre: 'Cédula de extranjería',
      placeholder: 'Ej: 123456'
    },
    'TI': {
      min: 10,
      max: 11,
      soloNumeros: true,
      nombre: 'Tarjeta de identidad',
      placeholder: 'Ej: 1234567890'
    },
    'Pasaporte': {
      min: 5,
      max: 15,
      soloNumeros: false,
      nombre: 'Pasaporte',
      placeholder: 'Ej: AB1234567'
    }
  };

  // Asegurarnos que el DOM esté completamente cargado antes de ejecutar el código
  document.addEventListener('DOMContentLoaded', function() {
    const tipoDocumento = document.getElementById('tipoDocumento');
    const numeroDocumento = document.getElementById('numeroDocumento');
    const form = document.querySelector('form');

    // Validar número de documento según tipo seleccionado
    if (tipoDocumento && numeroDocumento) {
      tipoDocumento.addEventListener('change', function() {
        const tipo = this.value;
        const config = configDocumentos[tipo];

        if (config) {
          // Actualizar atributos del input
          numeroDocumento.maxLength = config.max;
          numeroDocumento.placeholder = config.placeholder;
          numeroDocumento.value = ''; // Limpiar valor previo

          // Actualizar patrón según tipo
          if (config.soloNumeros) {
            numeroDocumento.pattern = '[0-9]+';
            numeroDocumento.title = `Solo números (${config.min}-${config.max} dígitos)`;
            numeroDocumento.inputMode = 'numeric';
          } else {
            numeroDocumento.pattern = '[A-Za-z0-9]+';
            numeroDocumento.title = `Letras y números (${config.min}-${config.max} caracteres)`;
            numeroDocumento.inputMode = 'text';
          }
        }
      });

      // Filtrar caracteres no permitidos mientras escribe
      numeroDocumento.addEventListener('input', function(e) {
        const tipo = tipoDocumento.value;
        const config = configDocumentos[tipo];

        if (config) {
          let valor = this.value;

          // Filtrar caracteres según tipo
          if (config.soloNumeros) {
            valor = valor.replace(/[^0-9]/g, '');
          } else {
            valor = valor.replace(/[^A-Za-z0-9]/g, '');
          }

          // Limitar longitud
          if (valor.length > config.max) {
            valor = valor.substring(0, config.max);
          }

          this.value = valor.toUpperCase();
        }
      });
    }

    // Validación antes de enviar el formulario
    if (form) {
      form.addEventListener('submit', function(e) {
        const tipo = tipoDocumento.value;
        const numero = numeroDocumento.value.trim();
        const config = configDocumentos[tipo];

        if (!tipo) {
          e.preventDefault();
          Swal.fire('Error', 'Seleccione un tipo de documento', 'error');
          return false;
        }

        if (config) {
          // Validar longitud mínima
          if (numero.length < config.min) {
            e.preventDefault();
            Swal.fire('Error', `El ${config.nombre} debe tener al menos ${config.min} caracteres`, 'error');
            return false;
          }

          // Validar longitud máxima
          if (numero.length > config.max) {
            e.preventDefault();
            Swal.fire('Error', `El ${config.nombre} no puede tener más de ${config.max} caracteres`, 'error');
            return false;
          }

          // Validar formato
          if (config.soloNumeros && !/^[0-9]+$/.test(numero)) {
            e.preventDefault();
            Swal.fire('Error', `El ${config.nombre} solo puede contener números`, 'error');
            return false;
          }

          if (!config.soloNumeros && !/^[A-Za-z0-9]+$/.test(numero)) {
            e.preventDefault();
            Swal.fire('Error', `El ${config.nombre} solo puede contener letras y números`, 'error');
            return false;
          }
        }

        // Validar nombre (solo letras, espacios y caracteres especiales válidos)
        const nombre = document.getElementById('nombre').value.trim();
        if (!/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s'-]+$/.test(nombre)) {
          e.preventDefault();
          Swal.fire('Error', 'El nombre solo puede contener letras y espacios', 'error');
          return false;
        }

        if (nombre.length < 3) {
          e.preventDefault();
          Swal.fire('Error', 'El nombre debe tener al menos 3 caracteres', 'error');
          return false;
        }

        // Validar correo con regex más estricto
        const correo = document.getElementById('correo').value.trim();
        const regexCorreo = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        if (!regexCorreo.test(correo)) {
          e.preventDefault();
          Swal.fire('Error', 'Ingrese un correo electrónico válido', 'error');
          return false;
        }

        return true;
      });
    }

    const archivoInput = document.getElementById('archivo');
    if (archivoInput) {
      archivoInput.addEventListener('change', function(e) {
        const nombreArchivo = e.target.files[0] ? e.target.files[0].name : "Seleccionar archivo";
        const nombreElement = document.getElementById('archivo-nombre');
        if (nombreElement) {
          nombreElement.textContent = nombreArchivo;
        }

        // Añadir clase animada cuando se selecciona un archivo
        const fileLabel = document.querySelector('.custom-file-label');
        if (fileLabel) {
          fileLabel.classList.add('animate__animated', 'animate__pulse');
          setTimeout(() => {
            fileLabel.classList.remove('animate__animated', 'animate__pulse');
          }, 1000);
        }
      });
    }

    // Animación en los inputs al enfocar
    const inputs = document.querySelectorAll('input, select');
    inputs.forEach(input => {
      input.addEventListener('focus', function() {
        const container = this.closest('.input-container');
        if (container) {
          container.classList.add('animate__animated', 'animate__headShake');
          setTimeout(() => {
            container.classList.remove('animate__animated', 'animate__headShake');
          }, 1000);
        }
      });
    });
  });
</script>
</body>
</html>
