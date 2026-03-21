# Plan de Refactorización: Unificación de Partes y Variantes (Estilo Resumen Grupos)

## 📌 1. Objetivo
Modificar el diseño y la estructura de las pantallas de gestión de Partes y Variantes (`/productos/partes` y `?tab=variantes`). El objetivo es:
1. **Unificar** ambos listados en una sola vista.
2. **Replicar el diseño** de estilo "Acordeón" que utiliza el reporte de resúmenes (`/reportes/resumen-grupos?id_grupo=1`). Cada **Parte** será la cabecera expansible del acordeón, y al hacer click, se desplegará una **Tabla con sus Variantes** correspondientes.
3. **Mejorar el Buscador** para que la búsqueda sea global: deberá buscar coincidencias tanto en el `código` y `detalle` de la parte, como en el `código` y `detalle` de sus variantes.

---

## 📂 2. Archivos a Modificar / Eliminar

Los archivos involucrados se encuentran dentro de `views\pages\admin\partes\`. La idea es reducir la complejidad del árbol de componentes actual.

### Archivos a Modificar
* `views\pages\admin\partes\index.php`: Será el archivo principal que contendrá el nuevo HTML (Acordeón). Ya no utilizará pestañas (Tabs).

### Archivos a Eliminar (Unificación)
Al condensar todo en una única vista, se vuelve innecesaria la separación por tabs. Podremos integrar de forma más limpia el HTML y eliminar los siguientes archivos que fragmentan la interfaz actual:
* ❌ `views\pages\admin\partes\_partes_tab.php`
* ❌ `views\pages\admin\partes\_variantes_tab.php`
* ❌ `views\pages\admin\partes\components\_parte_table.php`
* ❌ `views\pages\admin\partes\components\_variante_table.php`

> **Nota:** La estructura de tablas pasará a escribirse directamente dentro del bucle del Acordeón en `index.php` (o de un único componente nuevo si se prefiere encapsular).

---

## 💻 3. Nuevo Diseño de la Interfaz (UI/UX)

Se eliminarán los selectores superiores de *"Pestaña: Partes | Variantes"*. La interfaz consistirá en:

1. **Barra Superior**: Contendrá el título central, el botón de "Crear Nueva Parte/Variante" y la **Barra de Búsqueda global**.
2. **Contenedor Principal (Accordion)**: Un `<div class="accordion" id="accordionPartes">`.
3. **Elemento Padre (La Parte)**: Cada elemento padre iterará sobre el array de Partes.
   * Usará la clase `.accordion-item`.
   * El botón expansible (`.accordion-button`) contendrá un flexbox con el `Código` de la parte, `Detalle`, el `Grupo` al que pertenece, las etiquetas/badges (ej. cantidad de variantes) y **un grupo de botones de acción exclusivos para la parte** (Editar, Historial, etc.). *Es importante prevenir el cierre del acordeón al hacer clic en estos botones usando `stopPropagation`.*
4. **Elemento Hijo (Las Variantes)**: Al expandir el acordeón (`.accordion-collapse`), se mostrará su `.accordion-body`.
   * Mostrará una tabla estilizada (`<table class="table table-sm table-hover">`).
   * Contendrá columnas para las variantes: `Código Variante`, `Detalle`, `Estado`, `Unidad de Medida / Atributos` y **Botones de acción (Editar Variante)**.

**Esquema visual:**
```text
[Buscador Global: "Buscar código o detalle..."] 

▼ PT-001 | Base Metálica | Grupo: Estructuras  [Badge: 3 Variantes]  [Editar Parte]
  |
  |-- Tabla de Variantes:
  |    | Cod  | Detalle        | Estado  | Acciones |
  |    | V-01 | Base Pintada   | Activa  | [Editar] |
  |    | V-02 | Base Óxido     | Inact.  | [Editar] |
  |    | V-03 | Base Inox      | Activa  | [Editar] |

▶ PT-002 | Tubo Soportes | Grupo: Estructuras   [Badge: 1 Variantes]  [Editar Parte]
```

---

## 🔍 4. Lógica del Buscador Global

El requerimiento dice: *"el buscador debe buscar en codigo y detalle de la parte y la variante"*.

### Impacto en el Controlador/Modelo (Backend):
El controlador que renderiza `/productos/partes` actualmente hace consultas separadas para Partes (paginadas) y Variantes (paginadas o filtradas por parte). Al unificar la vista:

1. **Paginación principal:** Las consultas contarán cantidad de **Partes**.
2. **Criterio WHERE extendido:** Modificar el repositorio o el constructor de la consulta (`PartesRepository`) cuando `$search` incluya un término. 
   La consulta quedará conceptualmente así:
   ```sql
   SELECT DISTINCT p.* 
   FROM partes p
   LEFT JOIN variantes v ON p.id = v.id_parte
   WHERE 
       p.codigo ILIKE '%termino%' 
       OR p.detalle ILIKE '%termino%'
       OR v.codigo ILIKE '%termino%' 
       OR v.detalle ILIKE '%termino%'
   ```
3. **Carga Ansiosa (Eager Loading):** Una vez obtenidas las partes afectadas de esta página, el controlador deberá inyectar dentro de cada `Parte` sus `Variantes` relacionadas para armar el acordeón correctamente en PHP.

---

## 🛠 5. Implementación en Código (Ejemplo Estructural para index.php)

Dentro de `index.php`, reconstruir el loop principal:

```php
<div class="accordion mb-4" id="accordionPartesList">
    <?php foreach ($partes_con_variantes as $index => $parte) : ?>
        <div class="accordion-item shadow-sm mb-2 border">
            <!-- HEADER: Datos de la Parte -->
            <h2 class="accordion-header" id="heading_<?= $parte['id'] ?>">
                <div class="d-flex align-items-center w-100 pe-3 bg-light accordion-button-container">
                    <button class="accordion-button collapsed flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?= $parte['id'] ?>">
                        <div class="d-flex w-100 justify-content-between align-items-center me-3">
                            <div>
                                <span class="fw-bold"><?= View::escape($parte['codigo']) ?></span>
                                <span class="text-muted ms-2 px-2 border-start border-2"><?= View::escape($parte['detalle']) ?></span>
                            </div>
                            <div>
                                <span class="badge bg-secondary"><?= count($parte['variantes']) ?> variantes</span>
                            </div>
                        </div>
                    </button>
                    <!-- Acciones directas sobre la PARTE -->
                    <div class="ms-auto" style="z-index: 10;">
                        <a href="<?= url('productos/partes/' . $parte['id'] . '/editar') ?>" class="btn btn-sm btn-outline-primary" aria-label="Editar Parte">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                    </div>
                </div>
            </h2>

            <!-- BODY: Tabla de Variante(s) -->
            <div id="collapse_<?= $parte['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#accordionPartesList">
                <div class="accordion-body p-0">
                    <table class="table table-hover table-sm m-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Código Variante</th>
                                <th>Detalle</th>
                                <th>Estado</th>
                                <th class="text-end pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parte['variantes'] as $variante) : ?>
                                <tr>
                                    <td class="ps-3 fw-medium"><?= View::escape($variante['codigo']) ?></td>
                                    <td><?= View::escape($variante['detalle']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $variante['estado'] === 'activa' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($variante['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="<?= url('productos/variantes/' . $variante['id'] . '/editar') ?>" class="btn btn-sm btn-light border">
                                            <i class="fa-solid fa-pencil text-primary"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($parte['variantes'])): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No posee variantes</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
```

## 📝 6. Pasos a seguir

1. **Controlador:** Modificar el `PartesController` para que `$parts_con_variantes` retorne las partes con un *Left Join* incluyendo la capacidad cruzada en la barra de búsqueda y adjunte directamente todas sus variantes como un sub-array de la parte resultante.
2. **Vista:** Reemplazar los componentes divididos (`_partes_tab`, `_variantes_tab`, tablas) por la lógica mostrada más arriba directo en el `index.php` (o crear un componente único `_partes_accordion.php`).
3. **JavaScript:** Adaptar/Eliminar los eventos antiguos que manejaban los *Tabs* en `partes-search.js` o scripts dedicados, asegurando que la barra de búsqueda ahora ejecute un filtro que dispare la nueva query global.
4. **Eliminación y Limpieza:** Borrar los antiguos `_tab.php` y `_table.php` que estaban en carpeta `components`.
