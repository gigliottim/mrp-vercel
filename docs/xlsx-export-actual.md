# Exportacion XLSX actual (Listado Ingenieria)

Archivo fuente:
- `app/services/Reportes/ListadoIngenieriaExportService.php`

## Flujo actual

1. `ReportesController::listadoIngenieria()` detecta `?export=xlsx`.
2. Se arma `headers + rows` usando:
   - `ListadoIngenieriaExportService::buildTabularData()`
3. Se genera el binario XLSX con:
   - `ListadoIngenieriaExportService::generateXlsx($headers, $rows)`
4. El controlador devuelve descarga con headers:
   - `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
   - `Content-Disposition: attachment; filename="...xlsx"`

## Como se construye el XLSX

El XLSX se construye como un paquete OpenXML (ZIP) con estos entries:

- `[Content_Types].xml`
- `_rels/.rels`
- `docProps/app.xml`
- `docProps/core.xml`
- `xl/workbook.xml`
- `xl/_rels/workbook.xml.rels`
- `xl/styles.xml`
- `xl/worksheets/sheet1.xml`

La hoja (`sheet1.xml`) se arma con:
- Encabezados en fila 1
- Datos desde fila 2
- Strings como `inlineStr`
- Numericos como `<v>`
- Sanitizado XML (`xmlSafeText`) para evitar caracteres invalidos

## Estrategia de empaquetado ZIP

`generateXlsx()` intenta en este orden:

1. `ZipArchive` si esta disponible
2. `PharData` si `ZipArchive` no esta disponible
3. Fallback ZIP manual (`buildZipArchive`) si no hay ninguna de las dos

Motivo:
- En este entorno `ZipArchive=0` y `PharData=1`, por eso actualmente se usa `PharData`.

## Observaciones tecnicas

- No se usan formulas ni estilos avanzados (es intencionalmente simple).
- No se usa `sharedStrings.xml`; se usa `inlineStr` para simplificar compatibilidad.
- La columna `Nivel` llega con prefijo `_` desde los datos para preservar ceros en Excel.

## Puntos donde podria mejorar (si queres que lo cambie)

1. Agregar `sharedStrings.xml` para mejor compatibilidad con algunos lectores estrictos.
2. Agregar estilos reales de tabla (header bold, bordes, formatos numericos).
3. Definir anchos de columna y freeze pane.
4. Agregar auto filtro en encabezado.
5. Forzar text format en columna `Nivel` (ademas del `_`) para evitar conversiones automaticas.
6. Si preferis, reemplazar todo por libreria dedicada (`PhpSpreadsheet`) cuando el proyecto tenga Composer activo.

## Como validar rapido localmente

1. Descargar desde la UI (`Exportar Excel (XLSX)`).
2. Renombrar temporalmente `archivo.xlsx` a `archivo.zip`.
3. Verificar que el ZIP contiene la estructura listada arriba.
4. Abrir en Excel y confirmar columnas/filas.
