<?php
session_start();
include 'auth.php';
include 'navegacion.php';

// Devolver la página anterior segura
echo obtener_pagina_anterior_segura();
?>
