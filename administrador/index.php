<?php
session_start();
include 'auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Inicio - Portal Gestión Humana</title>
    <link rel="stylesheet" href="/gh/Css/navbar.css">
    <link rel="stylesheet" href="/gh/Css/inicio.css">
    <link rel="icon" href="/gh/Img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . "/Modulos/navbar.php"; ?>
    <!-- Aquí inicia el main que cierra navbar.php -->
        <main class="contenido-principal">
            <div class="dashboard-content">
                <!-- Logo grande en el centro -->
                <img src="/gh/Img/Selcomp 2k.png" alt="SELCOMP" class="dashboard-logo">
                <div class="dashboard-cards">
                    <!-- Card 1 -->
                    <div class="card-app">
                        <h2>Banco Hv</h2>
                        <p>Entra a la bibliotecla de archivos para selección de prospectos</p>
                    <a class="btn-ingresar" href="/gh/administrador/Archivos/archivo.php">INGRESAR</a>
                    <img src="/gh/Img/Banco HV.png" alt="Banco HV">
                    </div>
                    <!-- Card 2 -->
                    <div class="card-app">
                        <h2>Aplicaciones</h2>
                        <p>Accede a las diferentes apps que tenemos preparadas para ti</p>
                        <button class="btn-ingresar" onclick="window.location.href='/gh/administrador/Aplicaciones/apps.php'">INGRESAR</button>
                        <img src="/gh/Img/Aplicaciones.png" alt="Aplicaciones">
                    </div>
                </div>
            </div>
        </main>
    </div> <!-- cierre de .layout-admin -->
</body>
</html>
