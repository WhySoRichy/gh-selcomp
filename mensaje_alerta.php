<?php
    if (isset($_SESSION['mensaje'])) {
        $titulo = $_SESSION['titulo'] ?? 'Notificación';
        $mensaje = $_SESSION['mensaje'];
        $tipo_alerta = $_SESSION['tipo_alerta'] ?? 'info';

        echo '<script>' .
             'Swal.fire({' .
             'title: ' . json_encode($titulo) . ',' .
             'text: ' . json_encode($mensaje) . ',' .
             'icon: ' . json_encode($tipo_alerta) . ',' .
             'confirmButtonColor: "#eb0045"' .
             '});' .
             '</script>';

        unset($_SESSION['titulo']);
        unset($_SESSION['mensaje']);
        unset($_SESSION['tipo_alerta']);
    }
?>

