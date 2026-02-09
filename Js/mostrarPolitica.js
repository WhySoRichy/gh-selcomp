function mostrarPolitica() {
    // Crear overlay de fondo oscuro - SIN BLUR para mejor rendimiento
    var overlay = document.createElement('div');
    overlay.id = 'modalOverlay';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.backgroundColor = 'rgba(15, 23, 42, 0.75)';
    overlay.style.zIndex = '9998';
    overlay.style.opacity = '0';
    overlay.style.transition = 'opacity 0.2s ease';
    overlay.onclick = cerrarAlerta;

    // Crear el modal con contenido legal completo
    var modal = document.createElement('div');
    modal.id = 'modalPolitica';
    modal.innerHTML = `
        <div style="position: relative;">
            <!-- Header del modal -->
            <div style="background-color: #404e62; margin: -30px -30px 0 -30px; padding: 20px 30px; border-radius: 16px 16px 0 0; position: relative;">
                <h3 style="color: white; font-size: 1.15rem; font-weight: 600; margin: 0; text-align: center;">
                    <i class="fas fa-shield-alt" style="margin-right: 10px;"></i>
                    Autorización de Tratamiento de Datos Personales
                </h3>
                <button onclick="cerrarAlerta()" style="position: absolute; top: 50%; right: 16px; transform: translateY(-50%); background: rgba(255,255,255,0.15); color: white; border: none; border-radius: 8px; width: 32px; height: 32px; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; transition: background-color 0.15s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.25)'" onmouseout="this.style.backgroundColor='rgba(255,255,255,0.15)'">×</button>
            </div>
            
            <!-- Contenido del modal -->
            <div style="background: white; margin: 0 -30px; padding: 24px 30px;">
                <div style="max-height: 350px; overflow-y: auto; padding-right: 10px; line-height: 1.7;">
                    
                    <p style="text-align: justify; color: #374151; font-size: 14px; margin-bottom: 16px;">
                        En cumplimiento de la <strong>Ley 1581 de 2012</strong> y el <strong>Decreto 1377 de 2013</strong>, autorizo de manera voluntaria, 
                        previa, explícita, informada e inequívoca a <strong style="color: #eb0045;">SELCOMP INGENIERÍA S.A.S.</strong>, identificada con 
                        NIT 800.071.819-0, para recolectar, almacenar, usar y tratar mis datos personales, incluyendo datos sensibles, con las siguientes finalidades:
                    </p>
                    
                    <div style="background: #f8fafc; border-left: 3px solid #eb0045; padding: 12px 16px; margin-bottom: 16px; border-radius: 0 8px 8px 0;">
                        <p style="color: #475569; font-size: 13px; margin: 0;"><strong>Finalidades del tratamiento:</strong></p>
                        <ul style="color: #64748b; font-size: 13px; margin: 8px 0 0 0; padding-left: 20px;">
                            <li>Gestión del proceso de selección y contratación de personal</li>
                            <li>Verificación de información y referencias laborales</li>
                            <li>Comunicación sobre el estado de mi postulación</li>
                            <li>Conformación de base de datos para futuras vacantes</li>
                        </ul>
                    </div>
                    
                    <div style="background: #f8fafc; border-left: 3px solid #404e62; padding: 12px 16px; margin-bottom: 16px; border-radius: 0 8px 8px 0;">
                        <p style="color: #475569; font-size: 13px; margin: 0;"><strong>Mis derechos como titular:</strong></p>
                        <ul style="color: #64748b; font-size: 13px; margin: 8px 0 0 0; padding-left: 20px;">
                            <li>Conocer, actualizar y rectificar mis datos personales</li>
                            <li>Solicitar prueba de la autorización otorgada</li>
                            <li>Revocar la autorización y/o solicitar la supresión de mis datos</li>
                            <li>Presentar quejas ante la Superintendencia de Industria y Comercio</li>
                        </ul>
                    </div>
                    
                    <p style="text-align: justify; color: #64748b; font-size: 13px; margin-bottom: 16px;">
                        <strong>Conservación:</strong> Mis datos serán conservados por un período de <strong>2 años</strong> después de finalizado el proceso de selección, 
                        o hasta que solicite su eliminación.
                    </p>
                    
                    <p style="text-align: justify; color: #64748b; font-size: 13px; margin-bottom: 0;">
                        <strong>Contacto:</strong> Para ejercer mis derechos puedo escribir al correo de contacto de la empresa.
                    </p>
                    
                </div>
            </div>
            
            <!-- Footer con botón -->
            <div style="background: #f8fafc; margin: 0 -30px -30px -30px; padding: 20px 30px; border-radius: 0 0 16px 16px; border-top: 1px solid #e2e8f0;">
                <button onclick="cerrarAlerta()" style="background-color: #eb0045; padding: 14px 40px; color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 15px; font-weight: 600; width: 100%; transition: background-color 0.15s ease;" onmouseover="this.style.backgroundColor='#d30040'" onmouseout="this.style.backgroundColor='#eb0045'">
                    <i class="fas fa-check" style="margin-right: 8px;"></i>He leído y acepto
                </button>
            </div>
        </div>
    `;

    // Estilos del modal - optimizados
    modal.style.position = 'fixed';
    modal.style.top = '50%';
    modal.style.left = '50%';
    modal.style.transform = 'translate(-50%, -50%) scale(0.95)';
    modal.style.padding = '30px';
    modal.style.backgroundColor = '#404e62';
    modal.style.borderRadius = '16px';
    modal.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.25)';
    modal.style.zIndex = '9999';
    modal.style.maxWidth = '600px';
    modal.style.width = '94%';
    modal.style.opacity = '0';
    modal.style.transition = 'transform 0.2s ease-out, opacity 0.2s ease-out';

    // Agregar overlay y modal al documento
    document.body.appendChild(overlay);
    document.body.appendChild(modal);

    // Animación de entrada
    setTimeout(() => {
        overlay.style.opacity = '1';
        modal.style.opacity = '1';
        modal.style.transform = 'translate(-50%, -50%) scale(1)';
    }, 10);

    // Estilo personalizado para el scrollbar del contenido
    const style = document.createElement('style');
    style.textContent = `
        #modalPolitica div::-webkit-scrollbar {
            width: 6px;
        }
        #modalPolitica div::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        #modalPolitica div::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        #modalPolitica div::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    `;
    document.head.appendChild(style);
}

// Función para cerrar la alerta con animación
function cerrarAlerta() {
    const modal = document.getElementById('modalPolitica');
    const overlay = document.getElementById('modalOverlay');
    
    if (modal && overlay) {
        // Animación de salida
        modal.style.opacity = '0';
        modal.style.transform = 'translate(-50%, -50%) scale(0.7)';
        overlay.style.opacity = '0';
        
        // Eliminar elementos después de la animación
        setTimeout(() => {
            if (modal.parentNode) modal.parentNode.removeChild(modal);
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }, 300);
    }
}

// Asociar la función mostrarPolitica al evento clic del enlace
const enlace = document.getElementById("enlaceTratamientoDatos");
if (enlace) {
    enlace.addEventListener("click", function(e) {
        e.preventDefault();
        mostrarPolitica();
    });
}
