/**
 * Función para exportar el historial de accesos a un archivo CSV
 */
function exportarHistorialCSV() {
    // Mostrar mensaje de carga
    Swal.fire({
        title: 'Generando archivo...',
        text: 'Preparando la exportación del historial',
        icon: 'info',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Obtener todos los registros para exportar
    fetch(`api_historial_accesos.php?limite=1000`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error de servidor: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.exito) {
                // Generar CSV
                let csvContent = 'data:text/csv;charset=utf-8,';
                
                // Encabezados
                csvContent += 'Fecha y Hora,Dirección IP,Dispositivo,Navegador,Estado,Detalles\n';
                
                // Datos
                data.historial.forEach(acceso => {
                    let ipMostrar = acceso.ip_acceso;
                    if (ipMostrar === '::1' || ipMostrar === '127.0.0.1') {
                        ipMostrar = 'Local (Mismo equipo)';
                    }
                    
                    let dispositivo = formatearDispositivo(acceso.dispositivo).replace(/,/g, ' ');
                    let navegador = acceso.navegador.replace(/,/g, ' ');
                    let detalles = acceso.detalles.replace(/,/g, ' ');
                    
                    csvContent += `${acceso.fecha_formateada},${ipMostrar},${dispositivo},${navegador},${acceso.estado_texto},${detalles}\n`;
                });
                
                // Descargar el archivo
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement('a');
                link.setAttribute('href', encodedUri);
                link.setAttribute('download', `historial_accesos_${new Date().toISOString().split('T')[0]}.csv`);
                document.body.appendChild(link);
                
                // Cerrar el diálogo de carga
                Swal.close();
                
                // Descargar el archivo
                link.click();
                document.body.removeChild(link);
                
                // Mostrar mensaje de éxito
                Swal.fire({
                    title: '¡Archivo generado!',
                    text: 'El historial de accesos ha sido exportado correctamente',
                    icon: 'success',
                    confirmButtonText: 'Aceptar'
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.mensaje || 'No se pudo generar el archivo',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            }
        })
        .catch(error => {
            console.error('Error al exportar historial:', error);
            Swal.fire({
                title: 'Error',
                text: 'No se pudo generar el archivo: ' + error.message,
                icon: 'error',
                confirmButtonText: 'Aceptar'
            });
        });
}
