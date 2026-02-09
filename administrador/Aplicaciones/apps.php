<?php
session_start();
include '../auth.php';
include '../csrf_protection.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplicaciones - Portal Gestión Humana</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/gh/Css/navbar.css">
    <link rel="stylesheet" href="/gh/Css/header-universal.css">
    <link rel="stylesheet" href="/gh/Css/usuarios.css">
    <link rel="stylesheet" href="/gh/Css/apps.css">
    <link rel="icon" href="/gh/Img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="layout-admin">
    <?php include __DIR__ . "/../Modulos/navbar.php"; ?>
    <main class="contenido-principal">
        <!-- Header universal -->
        <div class="header-universal">
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-th-large"></i>
                    <h1>Aplicaciones</h1>
                </div>
                <div class="header-info">
                    <span class="info-text">Accede a todas las herramientas y sistemas corporativos</span>
                </div>
            </div>
        </div>

        <div class="apps-container">
            <div class="apps-grid">
                <!-- Aula Virtual - Tickets -->
                <div class="app-card">
                    <div class="app-status active">Activo</div>
                    <div class="app-header">
                        <div class="app-icon">
                            <i class="fa-solid fa-ticket" style="font-size: 3rem; color: #eb0045;"></i>
                        </div>
                        <h3 class="app-title">E-Learning Moodle</h3>
                        <p class="app-subtitle">Campus Virtual</p>
                    </div>
                    <div class="app-content">
                        <p class="app-description">
                            Accede al campus virtual de E-Learning Moodle para nuevos cursos & capacitaciones.
                        </p>
                        <ul class="app-features">
                            <li><i class="fas fa-check"></i> Cursos Disponibles Cada Mes</li>
                            <li><i class="fas fa-check"></i> Autenticación de Doble Factor (MFA)</li>
                            <li><i class="fas fa-check"></i> Enfoque Efectivo Ante Necesidades de Aprendizaje</li>
                            <li><i class="fas fa-check"></i> Escalabilidad de procedimientos SIG</li>
                        </ul>
                    </div>
                    <div class="app-action">
                        <a href="#" class="btn-ingresar" onclick="abrirAulaVirtual(); return false;">
                            <i class="fas fa-graduation-cap"></i>
                            Ingresar al Aula Virtual
                        </a>
                    </div>
                </div>

                <!-- Portal de Postulaciones -->
                <div class="app-card">
                    <div class="app-status active">Activo</div>
                    <div class="app-header">
                        <div class="app-icon">
                            <img src="/gh/Img/Postulacion.gif" alt="Postulaciones">
                        </div>
                        <h3 class="app-title">Postulaciones</h3>
                        <p class="app-subtitle">Portal de Empleo</p>
                    </div>
                    <div class="app-content">
                        <p class="app-description">
                            Sistema de gestión de postulaciones y ofertas laborales
                            para candidatos externos e internos.
                        </p>
                        <ul class="app-features">
                            <li><i class="fas fa-check"></i> Ofertas laborales activas</li>
                            <li><i class="fas fa-check"></i> Gestión de candidatos</li>
                            <li><i class="fas fa-check"></i> Seguimiento de procesos</li>
                            <li><i class="fas fa-check"></i> Base de datos de CV</li>
                        </ul>
                    </div>
                    <div class="app-action">
                        <a href="/gh/postulacion.php" class="btn-ingresar">
                            <i class="fas fa-user-plus"></i>
                            Ver Postulaciones
                        </a>
                    </div>
                </div>

                <!-- ChatPDF -->
                <div class="app-card">
                    <div class="app-status active">Activo</div>
                    <div class="app-header">
                        <div class="app-icon">
                            <img src="/gh/Img/chatpdf.png" alt="ChatPDF">
                        </div>
                        <h3 class="app-title">ChatPDF</h3>
                        <p class="app-subtitle">IA Corporativa</p>
                    </div>
                    <div class="app-content">
                        <p class="app-description">
                            Herramienta de inteligencia artificial para análisis y
                            consulta interactiva de documentos PDF corporativos.
                        </p>
                        <ul class="app-features">
                            <li><i class="fas fa-check"></i> Análisis de documentos</li>
                            <li><i class="fas fa-check"></i> Consultas inteligentes</li>
                            <li><i class="fas fa-check"></i> Extracción de información</li>
                            <li><i class="fas fa-check"></i> Respuestas contextuales</li>
                        </ul>
                    </div>
                    <div class="app-action">
                        <a href="#" class="btn-ingresar" onclick="abrirChatPDF(); return false;">
                            <i class="fas fa-robot"></i>
                            Abrir ChatPDF
                        </a>
                    </div>
                </div>

                <!-- Base de Datos Prospectos -->
                <div class="app-card">
                    <div class="app-status active">Activo</div>
                    <div class="app-header">
                        <div class="app-icon">
                            <i class="fas fa-file-excel" style="font-size: 3rem; color: #107c41;"></i>
                        </div>
                        <h3 class="app-title">Base de Datos</h3>
                        <p class="app-subtitle">Prospectos</p>
                    </div>
                    <div class="app-content">
                        <p class="app-description">
                            Base de datos de candidatos extraída automáticamente 
                            de las hojas de vida mediante inteligencia artificial.
                        </p>
                        <ul class="app-features">
                            <li><i class="fas fa-check"></i> Datos extraídos con IA</li>
                            <li><i class="fas fa-check"></i> Actualización automática</li>
                            <li><i class="fas fa-check"></i> Información de candidatos</li>
                            <li><i class="fas fa-check"></i> Exportable a Excel</li>
                        </ul>
                    </div>
                    <div class="app-action">
                        <a href="/gh/Excel/Prospectos.xlsx" class="btn-ingresar" download>
                            <i class="fas fa-download"></i>
                            Descargar Excel
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once '../../mensaje_alerta.php'; ?>
    </main>
</div>

<script>
function abrirAulaVirtual() {
    Swal.fire({
        title: 'Aula Virtual',
        text: '¿Deseas acceder al campus virtual de E-Learning?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#eb0045',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, ingresar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Abrir Aula Virtual en nueva ventana
            window.open('https://aulavirtual.selcomp.com.co/login/index.php?loginredirect=1', '_blank');

            Swal.fire({
                title: 'Cargando...',
                text: 'Redirigiendo al Aula Virtual',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

function abrirChatPDF() {
    Swal.fire({
        title: 'Acceder a ChatPDF',
        text: '¿Deseas abrir la herramienta ChatPDF?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#eb0045',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, abrir',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Abrir ChatPDF en nueva ventana
            window.open('https://www.chatpdf.com/es', '_blank');

            Swal.fire({
                title: 'Cargando IA...',
                text: 'Iniciando ChatPDF',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

// Efectos adicionales
document.addEventListener('DOMContentLoaded', function() {
    // Agregar efecto de ripple a los botones
    const buttons = document.querySelectorAll('.btn-ingresar');

    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');

            this.appendChild(ripple);

            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
});
</script>
</body>
</html>
