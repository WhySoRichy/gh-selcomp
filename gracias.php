<?php

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>¡Postulación Exitosa! - Selcomp Ingeniería SAS</title>
  <link rel="icon" href="Img/logo.png" type="image/png"/>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    html, body {
      height: 100%;
      overflow: hidden;
    }
    
    body {
      background-image: url('Img/Fondo.jpg');
      background-size: cover;
      background-position: center;
      display: flex;
      justify-content: center;
      align-items: center;
      font-family: 'Montserrat', sans-serif;
      padding: 20px;
    }

    /* Botón volver */
    .btn-back {
      position: fixed;
      display: inline-flex;
      align-items: center;
      top: 25px;
      left: 25px;
      gap: 8px;
      color: #404e62;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.85rem;
      padding: 10px 18px;
      border-radius: 10px;
      transition: all 0.2s ease;
      background: white;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      z-index: 1000;
    }

    .btn-back:hover {
      background-color: #404e62;
      color: white;
    }

    /* Contenedor principal - horizontal */
    .main-container {
      width: 100%;
      max-width: 1300px;
      display: flex;
      gap: 24px;
      align-items: stretch;
    }

    /* Tarjeta izquierda - Éxito */
    .success-card {
      background-color: #404e62;
      border-radius: 20px;
      padding: 20px;
      flex: 0 0 420px;
      display: flex;
      flex-direction: column;
    }

    .success-content {
      background: white;
      border-radius: 16px;
      padding: 40px 35px;
      text-align: center;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .success-content img {
      width: 200px;
      margin: 0 auto 25px;
    }

    .success-icon {
      width: 90px;
      height: 90px;
      background: linear-gradient(135deg, #eb0045 0%, #c4003a 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 25px;
      animation: scaleIn 0.5s ease-out;
    }

    .success-icon i {
      font-size: 42px;
      color: white;
    }

    @keyframes scaleIn {
      0% { transform: scale(0); opacity: 0; }
      50% { transform: scale(1.2); }
      100% { transform: scale(1); opacity: 1; }
    }

    .success-content h1 {
      font-size: 1.6rem;
      color: #eb0045;
      margin-bottom: 15px;
      font-weight: 700;
      line-height: 1.3;
    }

    .success-content .subtitle {
      font-size: 1.05rem;
      color: #64748b;
      line-height: 1.6;
    }

    /* Tarjeta derecha - Información */
    .info-card {
      background-color: #404e62;
      border-radius: 20px;
      padding: 20px;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .info-content {
      background: white;
      border-radius: 16px;
      padding: 35px 40px;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .section-title {
      font-size: 1rem;
      color: #404e62;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .section-title i {
      color: #eb0045;
    }

    /* Steps horizontal */
    .steps-container {
      display: flex;
      gap: 24px;
      flex: 1;
    }

    .step-item {
      flex: 1;
      text-align: center;
      padding: 28px 20px;
      background: #f8fafc;
      border-radius: 14px;
      border: 1px solid #e2e8f0;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .step-number {
      width: 48px;
      height: 48px;
      background: #eb0045;
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.2rem;
      margin-bottom: 16px;
    }

    .step-item h3 {
      font-size: 1.05rem;
      color: #1e293b;
      font-weight: 600;
      margin-bottom: 10px;
    }

    .step-item p {
      font-size: 0.9rem;
      color: #64748b;
      line-height: 1.5;
    }

    /* Mensaje importante */
    .important-notice {
      background: #fdf2f8;
      border-left: 4px solid #eb0045;
      padding: 18px 22px;
      border-radius: 0 12px 12px 0;
      margin-top: 22px;
    }

    .important-notice p {
      font-size: 0.95rem;
      color: #9f1239;
      line-height: 1.5;
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .important-notice i {
      font-size: 1.15rem;
      margin-top: 2px;
      color: #eb0045;
    }

    /* Responsive */
    @media (max-width: 900px) {
      html, body {
        overflow: auto;
      }
      
      .main-container {
        flex-direction: column;
        max-width: 500px;
      }

      .success-card {
        flex: none;
      }

      .steps-container {
        flex-direction: column;
        gap: 12px;
      }

      .step-item {
        flex-direction: row;
        text-align: left;
        gap: 15px;
        padding: 15px;
      }

      .step-number {
        margin-bottom: 0;
        flex-shrink: 0;
      }

      .step-item div {
        flex: 1;
      }
    }

    @media (max-width: 500px) {
      .btn-back {
        top: 15px;
        left: 15px;
        padding: 8px 14px;
        font-size: 0.8rem;
      }

      .success-content {
        padding: 25px 20px;
      }

      .success-content img {
        width: 140px;
      }

      .success-icon {
        width: 60px;
        height: 60px;
      }

      .success-icon i {
        font-size: 28px;
      }

      .success-content h1 {
        font-size: 1.15rem;
      }
    }
  </style>
</head>
<body>

  <!-- Botón volver -->
  <a href="index.php" class="btn-back">
    <i class="fas fa-arrow-left"></i>
    Volver al Inicio
  </a>

  <div class="main-container">
    
    <!-- Tarjeta izquierda - Éxito -->
    <div class="success-card">
      <div class="success-content">
        <img src="/gh/Img/selcomp 2k.png" alt="Selcomp Logo"/>
        <div class="success-icon">
          <i class="fas fa-check"></i>
        </div>
        <h1>¡Tu postulación fue enviada exitosamente!</h1>
        <p class="subtitle">
          Hemos recibido tu hoja de vida y ya hace parte de nuestro banco de talentos.
        </p>
      </div>
    </div>

    <!-- Tarjeta derecha - Información -->
    <div class="info-card">
      <div class="info-content">
        <h2 class="section-title">
          <i class="fas fa-route"></i>
          ¿Qué sigue ahora?
        </h2>

        <div class="steps-container">
          <div class="step-item">
            <div class="step-number">1</div>
            <div>
              <h3>Revisión de tu perfil</h3>
              <p>Nuestro equipo de Gestión Humana revisará tu hoja de vida y verificará que tu perfil coincida con los requisitos.</p>
            </div>
          </div>
          <div class="step-item">
            <div class="step-number">2</div>
            <div>
              <h3>Te contactaremos</h3>
              <p>Si tu perfil es seleccionado, nos comunicaremos contigo al correo o teléfono que registraste.</p>
            </div>
          </div>
          <div class="step-item">
            <div class="step-number">3</div>
            <div>
              <h3>Futuras oportunidades</h3>
              <p>Tu información quedará guardada y podrás ser considerado para futuras vacantes.</p>
            </div>
          </div>
        </div>

        <!-- Mensaje importante -->
        <div class="important-notice">
          <p>
            <i class="fas fa-info-circle"></i>
            <span><strong>Importante:</strong> No es necesario que nos contactes para verificar el estado de tu postulación. Si eres seleccionado, ¡nosotros te llamaremos!</span>
          </p>
        </div>
      </div>
    </div>

  </div>

</body>
</html>
