# Docker PHP 8.x + PhpSpreadsheet (XLSX/PDF)

Este proyecto ya quedo migrado para usar:
- `phpoffice/phpspreadsheet`
- `dompdf/dompdf`

## Estado actual detectado

Contenedor app:
- `lemp-phpfpm` (`bitnami/php-fpm:latest`)

PHP dentro del contenedor:
- `PHP 8.5.2`
- Extensiones ya disponibles: `zip`, `gd`, `mbstring`, `dom`, `xml`, `xmlwriter`, `xmlreader`, etc.

Composer:
- Disponible en contenedor (`/opt/bitnami/php/bin/composer`)

## Dependencias instaladas

Se instalaron en `/projects/mrp`:
- `phpoffice/phpspreadsheet:^5.5`
- `dompdf/dompdf:^3.1`

Archivos generados/actualizados:
- `composer.json`
- `composer.lock`
- `vendor/`

## Cambios de codigo aplicados

1. `bootstrap/autoload.php`
- Carga `vendor/autoload.php` si existe.

2. `app/services/Reportes/ListadoIngenieriaExportService.php`
- Exporta XLSX con `PhpSpreadsheet` (`IOFactory::createWriter(..., 'Xlsx')`)
- Exporta PDF con `PhpSpreadsheet` + writer `Dompdf`
- Mantiene formato tabular y wrap de celdas

## Validacion ejecutada

Dentro de `lemp-phpfpm`:
- Se genero XLSX y PDF de prueba con el servicio.
- Resultado de prueba: `xlsx=6838`, `pdf=2416` bytes.

## Requisitos para persistir en una imagen Docker propia

Si queres dejar esto fijo en tu Dockerfile (en lugar de instalar a mano en runtime), en la etapa de `php-fpm` asegura:

1. Paquetes del sistema (ejemplo Debian/Ubuntu base):
- `libzip-dev`
- `libpng-dev`
- `libjpeg-dev`
- `libfreetype6-dev`
- `libxml2-dev`
- `libonig-dev`
- `unzip`
- `git`

2. Extensiones PHP:
- `zip`
- `gd`
- `mbstring`
- `dom`
- `xml`
- `xmlwriter`
- `xmlreader`

3. Composer install:
- `composer install --no-interaction --prefer-dist`

## Comandos utiles (tu stack actual)

Instalar deps en contenedor:
```bash
docker exec -it lemp-phpfpm sh -lc "cd /projects/mrp && composer install --no-interaction --prefer-dist"
```

Reinstalar solo estas librerias:
```bash
docker exec -it lemp-phpfpm sh -lc "cd /projects/mrp && composer require phpoffice/phpspreadsheet dompdf/dompdf --no-interaction"
```

Ver modulos PHP:
```bash
docker exec -it lemp-phpfpm php -m
```

## Nota sobre NGINX

No se requiere cambio en `nginx` para estas exportaciones.
Solo pasa el request al `php-fpm` y la app devuelve `Content-Type` y `Content-Disposition` de descarga.
