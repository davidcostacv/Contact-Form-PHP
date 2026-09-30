# Diseño aprobado: formulario de contacto

El usuario autorizó completar el formulario y la documentación el 30 de septiembre de 2026.

- PHP 8.2 o superior, sin Composer ni JavaScript obligatorio.
- `public/index.php`: interfaz española responsive y controlador GET/POST; solo public/ se sirve por HTTP.
- `src/contact.php`: normalización, validación, CSRF, honeypot, límite por sesión y preparación del correo.
- `config.example.php`: configuración copiable fuera del directorio público; demo por defecto sin correo ni persistencia.
- Correo mediante mail() del hosting, remitente fijo configurado y Reply-To validado. Nunca prometer entrega efectiva.
- Errores accesibles, valores conservados y escapados; redirección tras éxito para evitar reenvío.
- Tokens de sesión, cookies HttpOnly/SameSite, espera de 30 segundos entre intentos válidos y honeypot.
- Pruebas PHP del procesamiento y pruebas HTTP con transporte local de correo simulado.
- README: instalación, configuración real, límites, pruebas, licencia y captura real.

La entrega será un pull request revisable. No incluye hosting ni credenciales de correo.
