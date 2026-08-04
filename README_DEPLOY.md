# Protocolo Familiar MVC Simple

Versión autocontenida sin Composer ni `vendor`.

## Subida

Sube toda la carpeta `mvc-simple` al servidor. La ruta pública será:

`/mvc-simple/public`

Si la renombras, conserva la estructura interna.

## Configuración

1. Copia `.env.example` como `.env`.
2. Completa la conexión DB y `WEB_PASSWORD_HASH`.
3. Genera hash de contraseña:

```bash
php -r "echo password_hash('TU_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
```

## Instalación DB

Ejecuta solo por SSH/CLI:

```bash
cd /ruta/a/mvc-simple
php bin/install.php
```

El instalador crea las tablas faltantes y siembra los catalogos base, incluyendo la estructura recomendada de 20 carpetas/pestanas para el protocolo.

## Rutas

- `/public/login`
- `/public/protocolo-familiar`
- `/public/protocolo-familiar/nuevo`
- `/public/protocolo-familiar/{id}`
- `/public/catalogos`
- `/public/catalogos/nuevo`
- `/public/system/status`

## Seguridad

- No subas `.env` con credenciales a repositorios.
- No ejecutes migraciones por HTTP.
- No borres datos físicamente; los catálogos usan `activo`.
