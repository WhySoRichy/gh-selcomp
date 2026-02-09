# Directorio de Avatares

Este directorio contiene las fotos de perfil de los usuarios del sistema.

## Características:
- Las imágenes se redimensionan automáticamente a 200x200px
- Se guardan en formato JPEG con calidad del 85%
- Nomenclatura: `user_{ID}.jpg`
- Tamaño máximo: 5MB
- Formatos permitidos: JPEG, PNG, GIF, WebP

## Seguridad:
- Solo usuarios autenticados pueden subir avatares
- Validación de tipo de archivo
- Protección CSRF implementada
- Verificación de imagen real

---
*Este directorio es gestionado automáticamente por el sistema*
