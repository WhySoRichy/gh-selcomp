<img width="100%" src="https://capsule-render.vercel.app/api?type=waving&color=0:404e62,100:eb0045&height=180&section=header&text=Portal%20de%20Gestión%20Humana&fontSize=36&fontColor=ffffff&animation=fadeIn&fontAlignY=35"/>

<div align="center">

<a href="https://github.com/WhySoRichy/gh-selcomp">
  <img src="https://readme-typing-svg.herokuapp.com?font=Fira+Code&weight=600&size=22&pause=1000&color=EB0045&center=true&vCenter=true&random=false&width=600&lines=Sistema+de+Recursos+Humanos;Gesti%C3%B3n+Documental+Segura;Extracci%C3%B3n+de+CVs+con+IA;Desarrollado+en+PHP+%2B+MySQL" alt="Typing SVG" />
</a>

<br><br>

<img src="https://img.shields.io/badge/PHP-8.4-eb0045?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4">
<img src="https://img.shields.io/badge/MySQL-8.0+-404e62?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL 8+">
<img src="https://img.shields.io/badge/JavaScript-ES6+-eb0045?style=for-the-badge&logo=javascript&logoColor=white" alt="JavaScript ES6+">
<img src="https://img.shields.io/badge/Python-3.10+-404e62?style=for-the-badge&logo=python&logoColor=white" alt="Python 3.10+">
<img src="https://img.shields.io/badge/Groq_AI-Llama_3.1-eb0045?style=for-the-badge" alt="Groq AI">

<br><br>

[Características](#-características) · 
[Arquitectura](#-arquitectura) · 
[Seguridad](#-seguridad) · 
[Instalación](#-instalación) · 
[Base de Datos](#-base-de-datos)

</div>

<img src="https://user-images.githubusercontent.com/73097560/115834477-dbab4500-a447-11eb-908a-139a6edaec5c.gif">

## 📋 Descripción General

Portal web empresarial desarrollado en **PHP** para la gestión integral del proceso de selección y administración de personal. Permite a candidatos postularse a vacantes, a usuarios gestionar su perfil y documentos, y a administradores manejar todo el ciclo de reclutamiento.

### El Problema que Resuelve

| Desafío | Solución |
|---------|----------|
| Procesos de postulación dispersos | Portal centralizado con formularios validados |
| Documentos confidenciales sin control | Gestión segura con permisos por rol |
| Extracción manual de datos de CVs | IA automatizada con Llama 3.1 |
| Accesos sin auditoría | Historial completo con 2FA (TOTP) |

<img src="https://user-images.githubusercontent.com/73097560/115834477-dbab4500-a447-11eb-908a-139a6edaec5c.gif">

## ⚡ Características

<table>
<tr>
<td width="33%" valign="top">

### 🌐 Portal Público

- Login con autenticación segura
- Formulario de postulación validado
- Recuperación de contraseña por email
- Validación de documentos de identidad

</td>
<td width="33%" valign="top">

### 👤 Panel de Usuario

- Dashboard personalizado
- Gestión de perfil con avatar
- Visualización de vacantes
- Centro de notificaciones
- Configuración de 2FA (TOTP)

</td>
<td width="33%" valign="top">

### 🛡️ Panel Administrativo

- CRUD completo de usuarios
- Gestión de vacantes
- Banco de Hojas de Vida
- Sistema de notificaciones
- Auditoría de accesos
- Restablecer MFA de usuarios
- 2FA obligatorio para admins

</td>
</tr>
</table>

### 🤖 Extractor de CVs con Inteligencia Artificial

El sistema incluye un módulo de **procesamiento inteligente de PDFs** que extrae información estructurada de hojas de vida:

```
CV.pdf → PyMuPDF → Groq AI (Llama 3.1) → Datos estructurados → Excel
```

**Datos extraídos:** Nombre completo, nivel educativo, años de experiencia, resumen laboral

<img src="https://user-images.githubusercontent.com/73097560/115834477-dbab4500-a447-11eb-908a-139a6edaec5c.gif">

## 🏗️ Arquitectura

```
┌─────────────────────────────────────────────────────────────────────┐
│                           FRONTEND                                   │
│   Portal Login  ·  Panel Usuario  ·  Panel Administrador            │
│   HTML5 + CSS3 + JavaScript ES6 + SweetAlert2                        │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         BACKEND (PHP 8)                              │
│                                                                      │
│   ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                │
│   │   Auth      │  │   CRUD      │  │   Files     │                │
│   │   + 2FA     │  │   APIs      │  │   Manager   │                │
│   └─────────────┘  └─────────────┘  └─────────────┘                │
│                                                                      │
│   ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                │
│   │   CSRF      │  │   Brute     │  │   SMTP      │                │
│   │   Tokens    │  │   Force     │  │   Mailer    │                │
│   └─────────────┘  └─────────────┘  └─────────────┘                │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       SERVICIOS EXTERNOS                             │
│       MySQL 8.0  ·  Groq AI (Llama 3.1)  ·  SMTP Server             │
└─────────────────────────────────────────────────────────────────────┘
```

<details>
<summary><b>📁 Ver Estructura del Proyecto</b></summary>

```
gh/
├── administrador/           # Panel de administración
│   ├── Archivos/            # Gestión de banco de HVs
│   ├── Usuarios/            # CRUD de usuarios
│   ├── Vacantes/            # CRUD de vacantes
│   ├── auth.php             # Middleware de autenticación
│   ├── csrf_protection.php  # Sistema CSRF
│   └── seguridad.php        # Panel de seguridad
│
├── usuario/                 # Panel de usuario
│   ├── perfil.php           # Gestión de perfil
│   ├── documentos.php       # Documentos personales
│   ├── vacantes.php         # Vacantes disponibles
│   └── notificaciones.php   # Centro de notificaciones
│
├── conexion/                # Capa de base de datos
├── seguridad/               # Módulos de protección
├── notificaciones/          # API REST de notificaciones
├── Excel/                   # Extractor de CVs con IA
├── Documentos/              # Almacenamiento de archivos
├── Css/                     # Estilos por módulo
├── Js/                      # Scripts del cliente
│
├── funciones/               # Helpers (TOTP, fechas)
├── config.php               # Configuración central (.env)
├── index.php                # Login principal
├── verificar_2fa.php        # Verificación código TOTP
├── configurar_2fa.php       # Setup QR + activación 2FA
└── postulacion.php          # Formulario de postulación
```

</details>

<img src="https://user-images.githubusercontent.com/73097560/115834477-dbab4500-a447-11eb-908a-139a6edaec5c.gif">

## 🔐 Seguridad

El sistema implementa múltiples capas de seguridad siguiendo estándares de la industria:

| Protección | Implementación | Detalles |
|:-----------|:---------------|:---------|
| **SQL Injection** | PDO Prepared Statements | Queries con parámetros bindeados |
| **XSS** | `htmlspecialchars()` | Escape de toda salida HTML |
| **CSRF** | Token-based | Tokens de 30 min con `hash_equals()` |
| **Fuerza Bruta** | Bloqueo progresivo | 5 intentos → 15 min bloqueo |
| **2FA (TOTP)** | App Authenticator | Google/Microsoft Authenticator, obligatorio para admins |
| **Session Hijacking** | Regeneración periódica | ID regenerado cada 5 min |
| **Session Fixation** | `session_regenerate_id(true)` | En cada login exitoso |
| **Inactividad** | Timeout automático | 30 min → logout |

<details>
<summary><b>🛡️ Ver Headers HTTP de Seguridad</b></summary>

```php
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
```

</details>

<img src="https://user-images.githubusercontent.com/73097560/115834477-dbab4500-a447-11eb-908a-139a6edaec5c.gif">

## 🚀 Instalación

### Requisitos Previos

| Componente | Versión | Obligatorio |
|------------|---------|:-----------:|
| PHP | 8.4+ | ✓ |
| MySQL | 8.0+ | ✓ |
| Composer | Latest | ✓ |
| Python | 3.10+ | Opcional |
| Apache/Nginx/IIS | - | ✓ |

**Extensiones PHP requeridas:**

| Extensión | Uso |
|-----------|-----|
| `pdo_mysql` | Conexión a base de datos |
| `openssl` | Cifrado AES-256-CBC de secretos TOTP |
| `gd` | Redimensión de avatares |
| `fileinfo` | Validación MIME de archivos subidos |
| `mbstring` | Manejo de strings multibyte (UTF-8) |

### Configuración

```bash
# 1. Clonar repositorio
git clone https://github.com/WhySoRichy/gh-selcomp.git
cd gh-selcomp

# 2. Instalar dependencias PHP
composer install

# 3. Configurar entorno
cp .env.example .env
# Editar .env con tus credenciales (ver sección Variables de Entorno)

# 4. Generar claves de seguridad
php -r "echo 'CSRF_SECRET=' . bin2hex(random_bytes(32)) . PHP_EOL;"
php -r "echo 'APP_KEY=' . bin2hex(random_bytes(32)) . PHP_EOL;"
# Copiar las claves generadas al archivo .env

# 5. Importar base de datos
mysql -u root -p < database/schema.sql

# 6. Configurar servidor web
# Apuntar el document root a la carpeta del proyecto
# Ejemplo Apache: DocumentRoot /var/www/gh
# Ejemplo IIS: Sitio apuntando a C:\inetpub\wwwroot\gh

# 7. (Opcional) Dependencias Python para extractor de CVs con IA
pip install groq pymupdf openpyxl
```

### Variables de Entorno

El archivo `.env` se genera a partir de `.env.example`. Variables **obligatorias**:

| Variable | Descripción | Ejemplo |
|----------|-------------|--------|
| `DB_HOST` | Host de MySQL | `localhost` |
| `DB_NAME` | Nombre de la base de datos | `gestionhumana` |
| `DB_USER` | Usuario MySQL | `root` |
| `DB_PASS` | Contraseña MySQL | `mi_password` |
| `CSRF_SECRET` | Clave para tokens CSRF (64 hex chars) | Generar con `php -r` |
| `APP_KEY` | Clave cifrado TOTP AES-256-CBC (64 hex chars) | Generar con `php -r` |
| `SMTP_USER` | Email para envío de correos | `email@gmail.com` |
| `SMTP_PASS` | App Password de Gmail | `xxxx xxxx xxxx xxxx` |

> **Nota:** Para Gmail, usar [App Passwords](https://myaccount.google.com/apppasswords) en vez de la contraseña.

<img src="https://user-images.githubusercontent.com/73097560/115834477-dbab4500-a447-11eb-908a-139a6edaec5c.gif">

## 💾 Base de Datos

### Tablas Principales

| Tabla | Propósito |
|-------|-----------|
| `usuarios` | Empleados y administradores del sistema |
| `vacantes` | Ofertas de trabajo publicadas |
| `postulaciones` | Candidatos que aplicaron |
| `documentos_usuarios` | Archivos subidos por usuarios |
| `notificaciones` | Comunicación interna |
| `historial_accesos` | Auditoría de login/logout |
| `codigos_2fa` | Códigos temporales 2FA (legacy, email) |
| `bloqueos_acceso` | Control de fuerza bruta |

<details>
<summary><b>📊 Ver Diagrama Entidad-Relación</b></summary>

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│    usuarios     │       │    vacantes     │       │  postulaciones  │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id (PK)         │       │ id (PK)         │◄──────│ vacante_id (FK) │
│ email           │       │ titulo          │       │ nombre          │
│ password_hash   │       │ descripcion     │       │ tipo_documento  │
│ nombre          │       │ ciudad          │       │ numero_documento│
│ rol             │       │ fecha_pub       │       │ correo          │
│ tiene_2fa       │       └─────────────────┘       │ archivo         │
│ secreto_2fa     │
└────────┬────────┘                                 └─────────────────┘
         │
         ├──► documentos_usuarios
         ├──► historial_accesos
         ├──► codigos_2fa
         ├──► password_resets
         └──► notificaciones (autor_id)
                    │
                    ├──► notif_usuarios
                    ├──► notif_archivos
                    └──► notif_respuestas
```

</details>

<img src="https://user-images.githubusercontent.com/73097560/115834477-dbab4500-a447-11eb-908a-139a6edaec5c.gif">

## 🔌 API de Notificaciones

| Método | Endpoint | Descripción |
|:------:|----------|-------------|
| `GET` | `/notificaciones/api.php?accion=listar` | Lista notificaciones |
| `GET` | `/notificaciones/api.php?accion=obtener&id=X` | Obtiene una notificación |
| `POST` | `/notificaciones/api.php?accion=crear` | Crea notificación |
| `POST` | `/notificaciones/api.php?accion=actualizar` | Actualiza notificación |
| `POST` | `/notificaciones/api.php?accion=eliminar` | Elimina notificación |

> Todas las llamadas requieren sesión activa y token CSRF para métodos POST.

<img src="https://user-images.githubusercontent.com/73097560/115834477-dbab4500-a447-11eb-908a-139a6edaec5c.gif">

## 🛠️ Stack Tecnológico

<div align="center">

<table>
<tr>
<td align="center" width="20%">
<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/php/php-original.svg" width="45">
<br><strong>PHP 8</strong>
<br><sub>Backend</sub>
</td>
<td align="center" width="20%">
<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/mysql/mysql-original.svg" width="45">
<br><strong>MySQL</strong>
<br><sub>Base de Datos</sub>
</td>
<td align="center" width="20%">
<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/javascript/javascript-original.svg" width="45">
<br><strong>JavaScript</strong>
<br><sub>Frontend</sub>
</td>
<td align="center" width="20%">
<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/python/python-original.svg" width="45">
<br><strong>Python</strong>
<br><sub>IA/ML</sub>
</td>
<td align="center" width="20%">
<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/css3/css3-original.svg" width="45">
<br><strong>CSS3</strong>
<br><sub>Estilos</sub>
</td>
</tr>
</table>

**Librerías:** PHPMailer · Google2FA · BaconQrCode · SweetAlert2 · Font Awesome · PyMuPDF · OpenPyXL

</div>

<img src="https://user-images.githubusercontent.com/73097560/115834477-dbab4500-a447-11eb-908a-139a6edaec5c.gif">

## 📈 Métricas del Proyecto

<div align="center">

| Líneas de Código | Archivos PHP | Archivos CSS | Tablas BD |
|:----------------:|:------------:|:------------:|:---------:|
| **~33,000** | **60+** | **20+** | **14** |

</div>

<img src="https://user-images.githubusercontent.com/73097560/115834477-dbab4500-a447-11eb-908a-139a6edaec5c.gif">

<div align="center">

## 👨‍💻 Autor

<br>

<a href="https://github.com/WhySoRichy">
  <img src="https://readme-typing-svg.herokuapp.com?font=Fira+Code&weight=500&size=24&pause=1000&color=EB0045&center=true&vCenter=true&random=false&width=400&lines=Ricardo+Hern%C3%A1ndez" alt="Ricardo Hernández" />
</a>

**Desarrollador Web Full Stack**

<br>

[![LinkedIn](https://img.shields.io/badge/LinkedIn-0A66C2?style=for-the-badge&logo=linkedin&logoColor=white)](https://co.linkedin.com/in/ricardoit)
[![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/WhySoRichy)
[![Email](https://img.shields.io/badge/Email-eb0045?style=for-the-badge&logo=gmail&logoColor=white)](mailto:richygg2003@gmail.com)

<br>

---

<br>

<sub>Proyecto de portafolio profesional · Uso no comercial · Ver [LICENSE](LICENSE)</sub>

<br>

<img src="https://img.shields.io/github/stars/WhySoRichy/gh-selcomp?style=social" alt="GitHub Stars">
<img src="https://img.shields.io/github/forks/WhySoRichy/gh-selcomp?style=social" alt="GitHub Forks">

</div>

<img width="100%" src="https://capsule-render.vercel.app/api?type=waving&color=0:eb0045,100:404e62&height=120&section=footer"/>
