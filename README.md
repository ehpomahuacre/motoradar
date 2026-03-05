# Motolegal - Noticias de motocicletas (PHP + Tailwind)

Pequeña página en PHP que consume la GNews API y muestra noticias sobre motocicletas en Perú.

Cómo usar

- Coloca el proyecto en tu servidor o usa el servidor embebido de PHP.
- Desde la carpeta del proyecto ejecuta:

```bash
php -S localhost:8000
```

- Abre en tu navegador: `http://localhost:8000/`

Parámetros

- Puedes cambiar la búsqueda añadiendo `?q=tu+busqueda` en la URL. Por defecto busca `motocicleta`.
- El token de API está en la parte superior de `index.php` en la variable `$API_TOKEN`. Cámbialo si quieres usar otro token.

Notas

- Usa PHP >= 7 con cURL habilitado.
- El diseño usa Tailwind CDN para simplicidad.

->Index.php es para fecha de hoy que le publishAT(gnews.io)
->Index-fecha-aleatoria.php es para noticias sea fecha de aletoria relacionada noticias sobre motos 
->index-whatsapp.php es para compartir  o reenviar  whatsapp cualquier numero  sin api de whatsapp
