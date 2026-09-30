# Contact Form PHP

Proyecto de David Costa para desarrollar un formulario de contacto con HTML, CSS y PHP.

## Estado actual

El repositorio contiene la estructura inicial de una página web. `index.html` incluye los metadatos de la página, enlaza la hoja de estilos y carga un icono de Font Awesome mediante CDN. `style.css` todavía está vacío.

**El formulario y el procesamiento en PHP aún no están implementados. Actualmente no se pueden enviar mensajes.**

## Tecnologías

- HTML5 para la estructura de la página.
- CSS para los estilos, pendiente de implementación.
- Font Awesome 5.15.3 para el icono, cargado desde cdnjs.
- PHP previsto para procesar el formulario; todavía no hay archivos PHP.

## Ejecutar la página

### Requisitos

- Git para clonar el repositorio, o descargarlo desde GitHub con **Code → Download ZIP**.
- Un navegador web.
- Acceso a Internet para cargar el icono de Font Awesome desde el CDN.

### Instalación

```sh
git clone https://github.com/davidcostacv/Contact-Form-PHP.git
cd Contact-Form-PHP
```

Abre `index.html` en el navegador. En esta versión únicamente se muestra un icono; no se necesita instalar paquetes, configurar una base de datos ni ejecutar PHP.

## Estructura

```text
Contact-Form-PHP/
├── index.html          # Página inicial
├── style.css           # Hoja de estilos vacía
├── app_screenshot.png  # Imagen de plantilla heredada
├── LICENSE             # Licencia Apache 2.0
└── README.md           # Documentación del proyecto
```

La imagen `app_screenshot.png` es una plantilla gráfica y no representa la interfaz actual; por eso no se presenta como captura de la aplicación.

## Próximos pasos

- Crear los campos de nombre, correo electrónico, asunto y mensaje.
- Añadir estilos adaptables a pantallas móviles y de escritorio.
- Implementar validación en el servidor y procesamiento del formulario en PHP.
- Configurar el envío de correo y mostrar resultados de éxito o error.
- Incorporar protección CSRF, medidas antispam y pruebas del procesamiento.
- Añadir una captura real cuando la interfaz esté terminada.

## Pruebas y publicación

El repositorio todavía no incluye pruebas automatizadas ni una demo publicada. Para verificar la página inicial, abre `index.html` y comprueba que el icono se carga y que el navegador no informa de recursos faltantes.

GitHub Pages puede servir la página HTML estática. Cuando se implemente el procesamiento PHP, será necesario un servidor compatible con PHP para ejecutar esa parte; GitHub Pages no ejecuta PHP.

## Contribuir

Puedes proponer mejoras en la [página de issues](https://github.com/davidcostacv/Contact-Form-PHP/issues) o enviar un pull request. Describe el problema, los cambios realizados y cómo verificarlos.

## Autor

**David Costa** · [@davidcostacv](https://github.com/davidcostacv)

## Licencia

Este proyecto utiliza la licencia [Apache 2.0](LICENSE).
