<div align="center">

# Portal de Gestión Humana

<img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8+">
<img src="https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL 8+">
<img src="https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=flat-square&logo=javascript&logoColor=black" alt="JavaScript ES6+">
<img src="https://img.shields.io/badge/Python-3.10+-3776AB?style=flat-square&logo=python&logoColor=white" alt="Python 3.10+">
<img src="https://img.shields.io/badge/Groq_AI-Llama_3.1-FF6B35?style=flat-square" alt="Groq AI">

<br><br>

**Sistema integral de recursos humanos con portal de postulaciones,<br>gestión documental, notificaciones y extracción de CVs con IA**

<br>

[Características](#características) · 
[Arquitectura](#arquitectura) · 
[Seguridad](#seguridad) · 
[Instalación](#instalación) · 
[Base de Datos](#base-de-datos)

<br>

---

</div>

## Descripción General

Portal web empresarial desarrollado en **PHP** para la gestión integral del proceso de selección y administración de personal. Permite a candidatos postularse a vacantes, a usuarios gestionar su perfil y documentos, y a administradores manejar todo el ciclo de reclutamiento.

### El Problema que Resuelve

| Desafío | Solución |
|---------|----------|
| Procesos de postulación dispersos | Portal centralizado con formularios validados |
| Documentos confidenciales sin control | Gestión segura con permisos por rol |
| Extracción manual de datos de CVs | IA automatizada con Llama 3.1 |
| Accesos sin auditoría | Historial completo con 2FA opcional |

<br>

---

## Características

<table>
<tr>
<td width="33%" valign="top">

### Portal Público

- Login con autenticación segura
- Formulario de postulación validado
- Recuperación de contraseña por email
- Validación de documentos de identidad

</td>
<td width="33%" valign="top">

### Panel de Usuario

- Dashboard personalizado
- Gestión de perfil con avatar
- Visualización de vacantes
- Centro de notificaciones
- Configuración de 2FA

</td>
<td width="33%" valign="top">

### Panel Administrativo

- CRUD completo de usuarios
- Gestión de vacantes
- Banco de Hojas de Vida
- Sistema de notificaciones
- Auditoría de accesos

</td>
</tr>
</table>

### Extractor de CVs con Inteligencia Artificial

El sistema incluye un módulo de **procesamiento inteligente de PDFs** que extrae información estructurada de hojas de vida:

```
CV.pdf → PyMuPDF → Groq AI (Llama 3.1) → Datos estructurados → Excel
```

**Datos extraídos:** Nombre completo, nivel educativo, años de experiencia, resumen laboral

<br>

---

## Arquitectura

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

### Estructura del Proyecto

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
├── config.php               # Configuración central
├── index.php                # Login principal
└── postulacion.php          # Formulario de postulación
```

<br>

---

## Seguridad

El sistema implementa múltiples capas de seguridad siguiendo estándares de la industria:

| Protección | Implementación | Detalles |
|:-----------|:---------------|:---------|
| **SQL Injection** | PDO Prepared Statements | Queries con parámetros bindeados |
| **XSS** | `htmlspecialchars()` | Escape de toda salida HTML |
| **CSRF** | Token-based | Tokens de 30 min con `hash_equals()` |
| **Fuerza Bruta** | Bloqueo progresivo | 5 intentos → 15 min bloqueo |
| **2FA** | Código por email | 6 dígitos, expira en 5 min |
| **Session Hijacking** | Regeneración periódica | ID regenerado cada 5 min |
| **Session Fixation** | `session_regenerate_id(true)` | En cada login exitoso |
| **Inactividad** | Timeout automático | 30 min → logout |

### Headers HTTP de Seguridad

```php
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
```

<br>

---

## Instalación

### Requisitos Previos

| Componente | Versión | Obligatorio |
|------------|---------|:-----------:|
| PHP | 8.0+ | ✓ |
| MySQL | 8.0+ | ✓ |
| Composer | Latest | ✓ |
| Python | 3.10+ | Opcional |
| Apache/Nginx/IIS | - | ✓ |

### Configuración

```bash
# 1. Clonar repositorio
git clone https://github.com/WhySoRichy/gh-selcomp.git
cd gh-selcomp

# 2. Instalar dependencias PHP
composer install

# 3. Configurar entorno
cp .env.example .env
# Editar .env con credenciales

# 4. Importar base de datos
mysql -u root -p < database/schema.sql

# 5. (Opcional) Dependencias Python para IA
pip install groq pymupdf openpyxl
```

<br>

---

## Base de Datos

### Tablas Principales

| Tabla | Propósito |
|-------|-----------|
| `usuarios` | Empleados y administradores del sistema |
| `vacantes` | Ofertas de trabajo publicadas |
| `postulaciones` | Candidatos que aplicaron |
| `documentos_usuarios` | Archivos subidos por usuarios |
| `notificaciones` | Comunicación interna |
| `historial_accesos` | Auditoría de login/logout |
| `codigos_2fa` | Códigos temporales para 2FA |
| `bloqueos_acceso` | Control de fuerza bruta |

### Diagrama Entidad-Relación

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

<br>

---

## API de Notificaciones

| Método | Endpoint | Descripción |
|:------:|----------|-------------|
| `GET` | `/notificaciones/api.php?accion=listar` | Lista notificaciones |
| `GET` | `/notificaciones/api.php?accion=obtener&id=X` | Obtiene una notificación |
| `POST` | `/notificaciones/api.php?accion=crear` | Crea notificación |
| `POST` | `/notificaciones/api.php?accion=actualizar` | Actualiza notificación |
| `POST` | `/notificaciones/api.php?accion=eliminar` | Elimina notificación |

> Todas las llamadas requieren sesión activa y token CSRF para métodos POST.

<br>

---

## Stack Tecnológico

<table>
<tr>
<td align="center" width="20%">
<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/php/php-original.svg" width="40">
<br><strong>PHP 8</strong>
<br><sub>Backend</sub>
</td>
<td align="center" width="20%">
<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/mysql/mysql-original.svg" width="40">
<br><strong>MySQL</strong>
<br><sub>Base de Datos</sub>
</td>
<td align="center" width="20%">
<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/javascript/javascript-original.svg" width="40">
<br><strong>JavaScript</strong>
<br><sub>Frontend</sub>
</td>
<td align="center" width="20%">
<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/python/python-original.svg" width="40">
<br><strong>Python</strong>
<br><sub>IA/ML</sub>
</td>
<td align="center" width="20%">
<img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/css3/css3-original.svg" width="40">
<br><strong>CSS3</strong>
<br><sub>Estilos</sub>
</td>
</tr>
</table>

**Librerías:** PHPMailer · SweetAlert2 · Font Awesome · PyMuPDF · OpenPyXL

<br>

---

## Métricas del Proyecto

<div align="center">

| Líneas de Código | Archivos PHP | Archivos CSS | Tablas BD |
|:----------------:|:------------:|:------------:|:---------:|
| **~33,000** | **60+** | **20+** | **14** |

</div>

<br>

---

<div align="center">

## Autor

<br>

**Ricardo Hernández**

*Desarrollador Web Full Stack*

<br>

[![LinkedIn](https://img.shields.io/badge/LinkedIn-0A66C2?style=for-the-badge&logo=linkedin&logoColor=white)](https://co.linkedin.com/in/ricardoit)
[![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/WhySoRichy)
[![Email](https://img.shields.io/badge/Email-EA4335?style=for-the-badge&logo=gmail&logoColor=white)](mailto:richygg2003@gmail.com)

<br>

---

<br>

<sub>Proyecto de portafolio profesional · Uso no comercial · Ver [LICENSE](LICENSE)</sub>

<br>

<img src="https://img.shields.io/github/stars/WhySoRichy/gh-selcomp?style=social" alt="GitHub Stars">

</div>
