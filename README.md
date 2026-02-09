# 🏢 Portal de Gestión Humana | HR Management Portal

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Python](https://img.shields.io/badge/Python-3.10+-3776AB?style=for-the-badge&logo=python&logoColor=white)
![Groq AI](https://img.shields.io/badge/Groq_AI-Llama_3.1-FF6B35?style=for-the-badge)

**Sistema completo de gestión de recursos humanos con portal de postulaciones, gestión documental, notificaciones y extracción inteligente de CVs con IA.**

[Características](#-características) •
[Arquitectura](#-arquitectura) •
[Seguridad](#-seguridad) •
[Instalación](#-instalación) •
[API](#-api)

</div>

---

## 📋 Descripción

Portal web empresarial desarrollado en PHP para la gestión integral del proceso de selección y administración de personal. El sistema permite a candidatos postularse a vacantes, a usuarios gestionar su perfil y documentos, y a administradores manejar todo el ciclo de reclutamiento.

### 🎯 Problema que resuelve

- Centralización del proceso de postulación de candidatos
- Gestión segura de documentos confidenciales (hojas de vida, certificados, etc.)
- Automatización de extracción de datos de CVs usando IA
- Control de acceso robusto con autenticación de dos factores
- Auditoría completa de accesos al sistema

---

## ✨ Características

### 👤 Portal Público
- ✅ Login con autenticación segura
- ✅ Formulario de postulación con validación de documentos
- ✅ Recuperación de contraseña por email
- ✅ Validación de tipos y formatos de documento de identidad

### 👨‍💼 Panel de Usuario
- ✅ Dashboard personalizado
- ✅ Gestión de perfil con avatar
- ✅ Visualización de vacantes disponibles
- ✅ Gestión de documentos personales
- ✅ Centro de notificaciones
- ✅ Configuración de seguridad y 2FA

### 🛡️ Panel de Administrador
- ✅ Gestión CRUD de usuarios
- ✅ Gestión de vacantes (crear, editar, eliminar)
- ✅ Banco de Hojas de Vida centralizado
- ✅ Sistema de notificaciones con adjuntos
- ✅ Historial de accesos global
- ✅ Configuración de seguridad avanzada

### 🤖 Extractor de CVs con IA
- ✅ Procesamiento de PDFs con PyMuPDF
- ✅ Extracción inteligente usando Groq AI (Llama 3.1)
- ✅ Exportación automática a Excel
- ✅ Datos extraídos: nombre, educación, experiencia, años de experiencia

---

## 🏗️ Arquitectura

```
┌─────────────────────────────────────────────────────────────────┐
│                        FRONTEND                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐ │
│  │ Portal Login │  │ Panel Usuario│  │   Panel Administrador  │ │
│  │  + Postular  │  │  (Dashboard) │  │   (CRUD + Reportes)    │ │
│  └──────────────┘  └──────────────┘  └────────────────────────┘ │
│         HTML5 + CSS3 (Montserrat) + JavaScript + SweetAlert2    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        BACKEND (PHP 8)                          │
│  ┌────────────────┐  ┌────────────────┐  ┌──────────────────┐  │
│  │ Autenticación  │  │  CRUD APIs     │  │  File Management │  │
│  │ (Session+2FA)  │  │  (PDO MySQL)   │  │  (Upload/View)   │  │
│  └────────────────┘  └────────────────┘  └──────────────────┘  │
│                                                                  │
│  ┌────────────────┐  ┌────────────────┐  ┌──────────────────┐  │
│  │ CSRF Protection│  │ Brute Force    │  │   PHPMailer      │  │
│  │ (Token Based)  │  │ Protection     │  │   (SMTP)         │  │
│  └────────────────┘  └────────────────┘  └──────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    SERVICIOS EXTERNOS                           │
│  ┌────────────────┐  ┌────────────────┐  ┌──────────────────┐  │
│  │   MySQL 8.0    │  │   Groq AI API  │  │   SMTP Server    │  │
│  │   (Database)   │  │  (Llama 3.1)   │  │   (Gmail, etc)   │  │
│  └────────────────┘  └────────────────┘  └──────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### 📁 Estructura del Proyecto

```
gh/
├── 📁 administrador/          # Panel de administración
│   ├── 📁 Archivos/           # Gestión de banco de HVs
│   ├── 📁 Usuarios/           # CRUD de usuarios
│   ├── 📁 Vacantes/           # CRUD de vacantes
│   ├── 📁 Modulos/            # Componentes reutilizables
│   ├── auth.php               # Middleware de autenticación admin
│   ├── csrf_protection.php    # Sistema CSRF
│   ├── seguridad.php          # Panel de seguridad
│   └── historial_accesos.php  # Auditoría de accesos
│
├── 📁 usuario/                # Panel de usuario
│   ├── 📁 Modulos/            # Navbar y componentes
│   ├── perfil.php             # Gestión de perfil
│   ├── documentos.php         # Mis documentos
│   ├── vacantes.php           # Ver vacantes
│   ├── notificaciones.php     # Centro de notificaciones
│   └── seguridad.php          # Configuración 2FA
│
├── 📁 conexion/               # Capa de base de datos
│   └── conexion.php           # PDO connection
│
├── 📁 seguridad/              # Módulos de seguridad
│   └── proteccion_fuerza_bruta.php
│
├── 📁 notificaciones/         # API REST de notificaciones
│   ├── api.php                # Endpoints CRUD
│   └── 📁 js/, 📁 css/
│
├── 📁 Excel/                  # Extractor de CVs con IA
│   ├── extractor_hv.py        # Script principal
│   └── procesar_hv_async.php  # Trigger desde PHP
│
├── 📁 Documentos/             # Almacenamiento de archivos
│   ├── Postulaciones/         # CVs de candidatos
│   ├── HojasDeVida/           # HVs de empleados
│   ├── Certificados/          # Certificados académicos
│   └── Notificaciones/        # Adjuntos de notificaciones
│
├── 📁 Css/                    # Estilos por módulo
├── 📁 Js/                     # Scripts del cliente
├── 📁 Img/                    # Assets e imágenes
│   └── Avatars/               # Fotos de perfil
│
├── config.php                 # Configuración central (.env loader)
├── index.php                  # Login principal
├── postulacion.php            # Formulario público de postulación
├── verificar_2fa.php          # Verificación de código 2FA
├── procesar_login.php         # Lógica de autenticación
└── vendor/                    # Dependencias (PHPMailer)
```

---

## 🔐 Seguridad

El sistema implementa múltiples capas de seguridad siguiendo las mejores prácticas:

| Capa | Implementación | Descripción |
|------|----------------|-------------|
| **SQL Injection** | PDO Prepared Statements | Todas las queries usan parámetros bindeados |
| **XSS** | `htmlspecialchars()` | Escape de toda salida de datos al HTML |
| **CSRF** | Token-based (30 min TTL) | Tokens en formularios con `hash_equals()` |
| **Fuerza Bruta** | Bloqueo progresivo | 5 intentos = 15 min de bloqueo por IP+email |
| **2FA** | Código 6 dígitos por email | Expira en 5 min, máximo 5 intentos |
| **Session Hijacking** | Regeneración periódica | ID regenerado cada 5 minutos |
| **Session Fixation** | `session_regenerate_id(true)` | En cada login exitoso |
| **Inactividad** | Timeout automático | 30 min sin actividad = logout |
| **Headers HTTP** | Security headers | X-Frame-Options, X-XSS-Protection, etc. |
| **Validación de archivos** | MIME + extensión + tamaño | Doble validación de uploads |

### 🛡️ Headers de Seguridad

```php
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
```

---

## 🚀 Instalación

### Requisitos

- PHP 8.0+
- MySQL 8.0+
- Composer
- Python 3.10+ (para extractor de CVs)
- Servidor web (Apache/Nginx/IIS)

### Pasos

1. **Clonar el repositorio**
```bash
git clone https://github.com/tu-usuario/gh-selcomp.git
cd gh-selcomp
```

2. **Instalar dependencias PHP**
```bash
composer install
```

3. **Configurar variables de entorno**
```bash
cp .env.example .env
# Editar .env con tus credenciales
```

4. **Importar base de datos**
```bash
mysql -u root -p < database/schema.sql
```

5. **Instalar dependencias Python (opcional, para IA)**
```bash
pip install groq pymupdf openpyxl
```

6. **Configurar servidor web**
   - Apuntar document root a la carpeta del proyecto
   - Habilitar mod_rewrite (Apache) o equivalente

---

## 📊 Base de Datos

### Tablas principales

| Tabla | Descripción |
|-------|-------------|
| `usuarios` | Usuarios del sistema (empleados y admins) |
| `vacantes` | Ofertas de trabajo activas |
| `postulaciones` | Candidatos que aplicaron a vacantes |
| `documentos_usuarios` | Archivos subidos por usuarios |
| `notificaciones` | Sistema de comunicación interna |
| `historial_accesos` | Auditoría de login/logout |
| `codigos_2fa` | Códigos temporales para 2FA |
| `bloqueos_acceso` | Control de fuerza bruta |

### Diagrama Entidad-Relación

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│    usuarios     │       │    vacantes     │       │  postulaciones  │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id (PK)         │       │ id (PK)         │◄──────│ vacante_id (FK) │
│ email           │       │ titulo          │       │ id (PK)         │
│ password_hash   │       │ descripcion     │       │ nombre          │
│ nombre          │       │ ciudad          │       │ tipo_documento  │
│ apellido        │       │ fecha_pub       │       │ numero_documento│
│ cargo           │       └─────────────────┘       │ correo          │
│ area            │                                 │ archivo         │
│ rol             │                                 └─────────────────┘
│ tiene_2fa       │
└────────┬────────┘
         │
    ┌────┴────┬─────────────┬──────────────┬──────────────┐
    │         │             │              │              │
    ▼         ▼             ▼              ▼              ▼
┌────────┐ ┌────────┐ ┌───────────┐ ┌───────────┐ ┌──────────────┐
│docs_   │ │historial│ │codigos_  │ │password_ │ │notificaciones│
│usuarios│ │_accesos │ │2fa       │ │resets    │ │              │
├────────┤ ├────────┤ ├───────────┤ ├───────────┤ ├──────────────┤
│usuario │ │usuario │ │usuario_id│ │user_id   │ │autor_id (FK) │
│_id(FK) │ │_id(FK) │ │(FK)      │ │(FK)      │ │id (PK)       │
│tipo_doc│ │fecha   │ │codigo    │ │token_hash│ │nombre        │
│ruta    │ │ip      │ │expira_en │ │expires_at│ │cuerpo        │
│estado  │ │exito   │ │usado     │ └───────────┘ │destino       │
└────────┘ └────────┘ └───────────┘              │prioridad     │
                                                 └──────┬───────┘
                                                        │
                    ┌───────────────┬───────────────────┼───────────────┐
                    │               │                   │               │
                    ▼               ▼                   ▼               ▼
            ┌─────────────┐ ┌─────────────┐ ┌─────────────────┐ ┌─────────────┐
            │notif_       │ │notif_       │ │notif_           │ │bloqueos_   │
            │usuarios     │ │archivos     │ │respuestas       │ │acceso      │
            ├─────────────┤ ├─────────────┤ ├─────────────────┤ ├─────────────┤
            │notif_id(FK) │ │notif_id(FK) │ │notif_id(FK)     │ │ip          │
            │usuario_id   │ │nombre_arch  │ │usuario_id(FK)   │ │email       │
            └─────────────┘ │ruta_archivo │ │respuesta        │ │intentos    │
                            └─────────────┘ └─────────────────┘ │bloqueado   │
                                                                └─────────────┘
```

---

## 🔌 API

### Endpoints de Notificaciones

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/notificaciones/api.php?accion=listar` | Lista notificaciones |
| GET | `/notificaciones/api.php?accion=obtener&id=X` | Obtiene una notificación |
| POST | `/notificaciones/api.php?accion=crear` | Crea notificación (admin) |
| POST | `/notificaciones/api.php?accion=actualizar` | Actualiza notificación |
| POST | `/notificaciones/api.php?accion=eliminar` | Elimina notificación |

### Autenticación de API

Todas las llamadas requieren:
- Sesión activa (`$_SESSION['usuario_id']`)
- Token CSRF en header o body para POST

---

## 🤖 Extractor de CVs con IA

El sistema incluye un extractor inteligente que procesa hojas de vida en PDF y extrae información estructurada usando Groq AI (Llama 3.1).

### Uso

```python
python Excel/extractor_hv.py ruta/al/cv.pdf
```

### Datos extraídos

- Nombres y apellidos
- Nivel educativo
- Años de experiencia
- Resumen de experiencia laboral

### Output

Los datos se exportan automáticamente a `Documentos/Recursos/Prospectos.xlsx`

---

## 📈 Estadísticas del Proyecto

| Métrica | Valor |
|---------|-------|
| **Líneas de código** | ~33,000 |
| **Archivos PHP** | 60+ |
| **Archivos CSS** | 20+ |
| **Archivos JS** | 6 |
| **Tablas de BD** | 10+ |

---

## 🛠️ Tecnologías Utilizadas

- **Backend:** PHP 8, PDO, MySQL
- **Frontend:** HTML5, CSS3, JavaScript ES6
- **UI Libraries:** SweetAlert2, Font Awesome, Animate.css
- **Email:** PHPMailer (SMTP)
- **IA:** Python, Groq API, Llama 3.1
- **PDF Processing:** PyMuPDF (fitz)
- **Excel:** OpenPyXL

---

## 👨‍💻 Autor

<div align="center">

**Ricardo Hernández**  
*Desarrollador Web Full Stack*

[![LinkedIn](https://img.shields.io/badge/LinkedIn-0077B5?style=for-the-badge&logo=linkedin&logoColor=white)](https://co.linkedin.com/in/ricardoit)
[![GitHub](https://img.shields.io/badge/GitHub-100000?style=for-the-badge&logo=github&logoColor=white)](https://github.com/WhySoRichy)

</div>

---

## 📄 Licencia

Este proyecto es de código abierto con fines de portafolio profesional.  
Libre para revisar, estudiar y referenciar.

---

<div align="center">

**⭐ Si te gusta este proyecto, no olvides dejar una estrella ⭐**

*Gracias por visitar mi portafolio*

</div>
