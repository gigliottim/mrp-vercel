# Spec: Replicación funcional — Maestro de Productos y Parte con variante por defecto

**Fecha**: 2026-09-06
**Estado**: Aprobado por el usuario
**Objetivo**: Alcanzar paridad funcional completa con el PHP legacy en dos páginas del frontend Next.js, manteniendo el diseño actual simple de shadcn/ui (sin embellecimiento visual hasta terminar el proyecto).

## Referencias del PHP legacy (comportamiento a replicar)

- `app/controllers/Productos/ComposicionController.php` — `maestro()`, `addItem()`, `updateItem()`, `deleteItem()`, `validateCandidates()`
- `views/pages/productos/composicion/maestro.php` + `_modalAgregar/_modalEditar/_modalReemplazar.php`
- `app/controllers/Admin/PartesVariantesController.php` — `validatePart()`, `createParteWithDefaultVariant()`, `buildDefaultVariantData()`
- `views/pages/admin/partes/manager/_parte_form.php`

## Página A: `/productos/maestro` (Composición de variantes)

### Comportamiento actual (gaps a corregir)

- Selector de variante: `<select>` nativo sin búsqueda, sin filtros por tipo.
- `maestro-form.tsx` crea un **BOM completo nuevo** (`POST /bom`) por cada componente agregado — bug de paridad: el PHP hace get-or-create de la cabecera y agrega un detalle.
- Sin selección de nodo en el árbol, sin editar/eliminar/reemplazar componente por ítem.

### Comportamiento objetivo

1. **Selector de variante**: combobox con búsqueda client-side (input + lista filtrada) sobre las variantes ya cargadas (`GET /variantes?perPage=500`). Al elegir, navega `?id_variante=X` (patrón GET actual).
2. **Filtros por tipo de parte**: fila de botones toggle ("Todos" + un botón por código de tipo de `tipos_partes`), filtra la lista del combobox por `tipo_codigo`.
3. **Árbol**: tabla plana indentada por `nivel` (estilo actual), filas clickeables → selecciona el nodo (state client-side).
4. **Panel de detalles del nodo**: tabla shadcn `Table` con hijos directos del nodo seleccionado: código, detalle + tipo, cantidad, unidad, y columna Acciones (DropdownMenu: Editar / Reemplazar / Eliminar).
5. **Agregar componente**: Dialog con Select de variante componente (excluye la seleccionada y el nodo actual), validación previa vía `GET /bom/validar-candidatos`, cantidad + Select de unidad → `POST /bom/detalle`.
6. **Editar componente**: Dialog con cantidad + unidad → `PATCH /bom/detalle/:detalleId`.
7. **Reemplazar componente**: Dialog con Select de variante nueva → `POST /bom/reemplazar` (endpoint existente) con `bom_ids=[bomId]`.
8. **Eliminar componente**: AlertDialog de confirmación → `DELETE /bom/detalle/:detalleId`.
9. **Feedback**: toasts (sonner) de éxito/error; éxito → `revalidatePath` de la página.

**Fuera de alcance**: vista "árbol" alternativa del panel derecho del PHP, botón "Destino de partes", importar/exportar (ya existe `/maestro/importar`).

## Página B: `/productos/partes` (alta de parte con variante por defecto)

### Comportamiento actual (gaps a corregir)

- `partes-form.tsx` solo envía: codigo, id_tipo, id_grupo, detalle, largo_alto, ancho, espesor_profundidad, activo. Faltan: unidades de medida (compra/uso/dimensiones/superficie/volumen) y factor de conversión.
- `POST /api/v1/partes` crea la parte **sin variante** — rompe el invariante "toda parte tiene al menos una variante".

### Comportamiento objetivo

1. **Formulario extendido** (paridad con `validatePart()` del PHP):
   - UM Compra (Select, opcional), UM Uso (Select, opcional).
   - Factor de conversión: visible solo si compra ≠ uso; requerido en ese caso; forzado a 1 si compra = uso.
   - Superficie + UM Superficie, Volumen + UM Volumen (Selects).
   - UM para Largo/Alto, Ancho, Espesor/Profundidad (hoy sin unidad).
   - Código en uppercase (transform al enviar).
2. **Validaciones cliente** (zod, igual que el PHP): codigo requerido (≤50), detalle requerido (≤255), id_tipo/id_grupo requeridos positivos, factor requerido cuando compra≠uso.
3. **Variante por defecto** creada server-side en el API (decisión del usuario: lógica en Hono, no en server actions ni trigger).
4. **Post-creación**: toast "Parte creada con su variante base"; el listado y `/partes/manager` (CRUD de variantes existente) muestran la variante default sin cambios.

**Fuera de alcance**: acordeón manager del PHP, calculadora de dimensiones, autocalculado de factor desde ancho (mL→m²).

## API Hono — cambios

### `api/src/routes/bom.ts`

| Endpoint | Auth | Comportamiento |
|---|---|---|
| `POST /bom/detalle` | Admin | Body `{variante_padre_id, variante_componente_id, cantidad, unidad_medida_id}`. Busca BOM activa del padre; si no existe, crea cabecera (version 1.0, fecha hoy, activa). Valida con RPC `bom_validate_add`; si inválida → 422 `{error.message}`. Inserta detalle. 201 con detalle creado. |
| `PATCH /bom/detalle/:detalleId` | Admin | Body `{cantidad?, unidad_medida_id?}`. Actualiza el detalle. 200. |
| `DELETE /bom/detalle/:detalleId` | Admin | Elimina el detalle. 204. |
| `GET /bom/validar-candidatos?variante_padre_id=X&candidate_ids=1,2,3` | Auth | Devuelve `{valid_ids: number[], invalid: Record<string,string>}` usando `bom_validate_add` por candidato. |

### `api/src/routes/partes.ts`

- `POST /` pasa a llamar RPC nueva `crear_parte_con_variante(p_company_id, p_codigo, p_id_tipo, p_id_grupo, p_detalle, p_dims, p_um, p_factor)` → inserta `partes` + `variantes` atómicamente (variante default: `codigo_variante` = código de la parte truncado a 50, `detalle` = detalle de la parte o 'Variante base', `estado='activa'`, `lote_minimo=1`, `punto_pedido=0`). Devuelve `{parte, variante}`.
- Errores mapeados: unique `partes_codigo_key` → 409 "El código ya existe"; unique variante → 409 con mensaje claro.
- `PATCH /:id` queda igual (solo actualiza parte; no toca variantes).

### Migración SQL

`supabase/migrations/20260906000027_crear_parte_con_variante.sql`:
- `CREATE FUNCTION public.crear_parte_con_variante(...)` con `SECURITY DEFINER`, guard de `company_id` (mismo patrón de `rpc_tenant_guard`), insert en `partes`, insert en `variantes` con `id_parte` devuelto, `EXCEPTION` → error descriptivo. Si el código de variante colisiona dentro de la misma transacción → rollback.
- `GRANT EXECUTE TO authenticated`.
- Aplicar con `supabase_apply_migration` al proyecto `ooyiahzawilmdfualggx`.

## Archivos

```
Modifica:
  api/src/routes/bom.ts
  api/src/routes/partes.ts
  frontend/app/(dashboard)/productos/maestro/page.tsx
  frontend/app/(dashboard)/productos/maestro/actions.ts
  frontend/app/(dashboard)/productos/partes/partes-form.tsx
  frontend/app/(dashboard)/productos/partes/actions.ts (mensaje de éxito)
Elimina:
  frontend/app/(dashboard)/productos/maestro/maestro-form.tsx (reemplazado por componentes nuevos)
Nuevos:
  supabase/migrations/20260906000027_crear_parte_con_variante.sql
  frontend/app/(dashboard)/productos/maestro/variante-selector.tsx (combobox + filtros tipo)
  frontend/app/(dashboard)/productos/maestro/arbol-estructura.tsx (tabla indentada clicable)
  frontend/app/(dashboard)/productos/maestro/detalle-panel.tsx (hijos del nodo + acciones)
  frontend/app/(dashboard)/productos/maestro/componente-dialogs.tsx (agregar/editar/reemplazar/eliminar)
```

## Manejo de errores

- API: formato existente `{error: {code, message}}`; códigos VALIDATION (400), NOT_FOUND (404), CONFLICT (409), DB_ERROR (500).
- Server actions: propagan `message` a la UI → toasts sonner.
- Validación de ciclos/autoreferencia: rechazada por `bom_validate_add` → 422 con el mensaje del RPC.

## Testing / verificación

No hay suite automatizada en el repo. Gate mínimo:

1. `npx tsc --noEmit` en `frontend/` y en `api/` sin errores.
2. Build del frontend y del api sin errores.
3. Verificación manual en dev (checklist):
   - Crear parte → verificar variante default en `/partes/manager` y en tabla `variantes`.
   - Crear parte con código duplicado → error claro.
   - Maestro: agregar componente a variante sin BOM → se crea cabecera + detalle.
   - Agregar componente recursivo (la misma variante) → error de validación.
   - Editar cantidad/unidad → se refleja en el árbol.
   - Reemplazar componente → where-used de ese BOM.
   - Eliminar componente → desaparece del árbol.
   - Combobox: buscar por código/detalle, filtrar por tipo.

## Decisiones registradas

- **Paridad funcional completa**, sin embellecimiento visual (usuario).
- **Variante default en API Hono** vía RPC transaccional, no en server actions ni trigger (usuario).
- **CRUD de componentes con endpoints granulares** por detalle, reutilizando `bom_validate_add` (usuario).
- Estética: shadcn/ui base existente, sin trabajo de diseño hasta completar el proyecto (usuario).