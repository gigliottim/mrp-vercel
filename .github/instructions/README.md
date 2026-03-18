# 📘 Instrucciones del Proyecto

Esta carpeta contiene las instrucciones y lineamientos obligatorios para el desarrollo del proyecto.

---

## 📄 Archivo Principal

### [unik.instructions.md](unik.instructions.md)

**Documento obligatorio de lectura para todo el equipo.**

Este archivo contiene:
- 📏 Estándares de codificación (límite 400 líneas/archivo)
- 🏗️ Estructura completa del proyecto
- 📐 Patrones de arquitectura con ejemplos de código
- ✅ Reglas de oro y principios SOLID
- 🚫 Anti-patrones prohibidos
- 📝 Templates para planificación y documentación
- 🔄 Convenciones de commits
- 🛠️ Scripts automatizados
- ❓ FAQ y recursos adicionales

**Última actualización**: 4 de febrero de 2026  
**Versión**: 2.0  
**Líneas**: 1,519

---

## 🔗 Documentación Relacionada

Estos archivos complementan las instrucciones principales:

### En la raíz del proyecto:

1. **[CUMPLIMIENTO_ESTANDARES.md](../../CUMPLIMIENTO_ESTANDARES.md)**
   - Guía de cumplimiento y procesos
   - Estado de auditoría del proyecto
   - Herramientas de validación
   - Plan de acción y refactorización
   - KPIs y métricas

2. **[CHECKLIST_PRE_COMMIT.md](../../CHECKLIST_PRE_COMMIT.md)**
   - Checklist obligatorio antes de commit
   - Validación paso a paso
   - Verificaciones manuales
   - Estructura de commits

3. **[README.md](../../README.md)**
   - Documentación general del proyecto
   - Sección de estándares de código
   - Enlaces a toda la documentación

---

## 🛠️ Herramientas

### Scripts de Validación

#### Windows (PowerShell)
```powershell
# Validar todo el proyecto
.\scripts\validate-code-standards.ps1

# Validación detallada
.\scripts\validate-code-standards.ps1 -Detailed

# Validar carpeta específica
.\scripts\validate-code-standards.ps1 -Path "app/services"
```

#### Desde VS Code
```
Ctrl+Shift+B → "🔍 Validar Estándares de Código"
```

O desde la paleta de comandos:
```
Ctrl+Shift+P → "Tasks: Run Task" → Seleccionar tarea
```

### Tareas Disponibles

- 🔍 **Validar Estándares de Código** - Validación completa
- 📊 **Validar Estándares (Detallado)** - Con estadísticas
- 📁 **Validar directorio actual** - Solo carpeta actual
- 🔍 **Contar líneas del archivo actual** - Archivo abierto
- 🚀 **Iniciar WebSocket Server** - Servidor de desarrollo
- 🔄 **Ejecutar Migraciones** - Base de datos
- 🧹 **Limpiar caché y logs** - Mantenimiento

---

## 📐 Configuración del Editor

El proyecto incluye configuración estandarizada para VS Code:

### Archivos de configuración:

- **[.vscode/settings.json](../../.vscode/settings.json)**
  - Rulers visuales en 80, 120 y 400 líneas
  - Línea roja visible en el límite máximo (400)
  - Formateo automático al guardar
  - Configuraciones específicas por lenguaje

- **[.vscode/tasks.json](../../.vscode/tasks.json)**
  - Tareas predefinidas para validación
  - Atajos de teclado
  - Integración con scripts del proyecto

- **[.vscode/extensions.json](../../.vscode/extensions.json)**
  - Extensiones recomendadas
  - IntelliSense mejorado
  - Herramientas de calidad

- **[.editorconfig](../../.editorconfig)**
  - Configuración universal para cualquier editor
  - Estándares de indentación y formato
  - Compatible con VS Code, JetBrains, Sublime, etc.

---

## 🚀 Para Empezar

### 1. Leer Documentación (Primera Vez)

**Orden recomendado:**

1. [README.md](../../README.md) - Vista general del proyecto
2. [unik.instructions.md](unik.instructions.md) - Estándares completos **(COMPLETO)**
3. [CUMPLIMIENTO_ESTANDARES.md](../../CUMPLIMIENTO_ESTANDARES.md) - Procesos
4. [CHECKLIST_PRE_COMMIT.md](../../CHECKLIST_PRE_COMMIT.md) - Referencia rápida

**Tiempo estimado**: 45-60 minutos

### 2. Configurar Entorno

```powershell
# 1. Abrir VS Code en el proyecto
code .

# 2. Instalar extensiones recomendadas
# VS Code mostrará automáticamente un mensaje

# 3. Verificar configuración
# Los rulers (líneas guía) deben ser visibles en el editor

# 4. Ejecutar validación inicial
.\scripts\validate-code-standards.ps1
```

### 3. Antes de Cada Commit

```powershell
# 1. Validar código
.\scripts\validate-code-standards.ps1

# 2. Seguir checklist
# Ver CHECKLIST_PRE_COMMIT.md

# 3. Commit con mensaje descriptivo
git commit -m "tipo: descripción"
```

---

## 🎯 Reglas Fundamentales (Resumen)

1. **Límite de 400 líneas por archivo**
   - Sin excepciones
   - Detener y dividir si se excede

2. **Separación de responsabilidades**
   - Controllers: Solo coordinan
   - Services: Solo lógica de negocio
   - Repositories: Solo queries
   - Views: Solo presentación

3. **Assets externalizados**
   - No JavaScript inline
   - No CSS inline

4. **Documentación de refactorizaciones**
   - Template obligatorio
   - 4 commits organizados

---

## 📊 Límites por Tipo de Archivo

| Tipo | Límite | Tolerancia | Acción si excede |
|------|--------|------------|------------------|
| Controller | 300 | 350 | Extraer a Services |
| Service | 400 | 450 | Dividir responsabilidades |
| Repository | 400 | 450 | Dividir queries |
| Model | 300 | 350 | Extraer comportamiento |
| View | 200 | 250 | Dividir en componentes |
| JavaScript | 300 | 350 | Modularizar |
| CSS | 400 | 450 | Dividir por componentes |

---

## ❓ Preguntas Frecuentes

### ¿Por qué 400 líneas?

Un archivo de 400 líneas es lo suficientemente grande para contener lógica significativa, pero lo suficientemente pequeño para:
- Entenderse en menos de 5 minutos
- Mantener una sola responsabilidad
- Facilitar code reviews
- Reducir bugs
- Mejorar testabilidad

### ¿Qué hago si un archivo excede el límite?

1. **STOP** - No agregues más código
2. **Analiza** - Identifica responsabilidades separables
3. **Divide** - Crea módulos especializados
4. **Documenta** - Usa el template de refactorización
5. **Valida** - Ejecuta el script de validación

### ¿Puedo hacer excepciones temporalmente?

**NO.** Las excepciones se convierten en deuda técnica. Es más fácil mantener la disciplina desde el inicio que refactorizar después.

### ¿Cómo divido un archivo complejo?

Consulta la sección "Patrones de Arquitectura" en [unik.instructions.md](unik.instructions.md) con ejemplos completos de código.

Patrones comunes:
- Extraer a Service Layer
- Dividir en módulos especializados
- Crear componentes de vista
- Modularizar JavaScript/CSS

---

## 🔄 Actualizaciones

### Última revisión
- **Fecha**: 8 de febrero de 2026
- **Cambios**: Creación del sistema de cumplimiento completo

### Próxima revisión
- **Fecha**: 8 de mayo de 2026
- **O cuando**: Se detecten nuevos anti-patrones

---

## 📞 Soporte

### Si tienes dudas:

1. **Buscar en la documentación**
   - Casi todas las preguntas están respondidas

2. **Revisar ejemplos**
   - Código de ejemplo en unik.instructions.md

3. **Preguntar al equipo**
   - Code review
   - Pair programming

4. **Abrir issue**
   - Documentar el caso específico
   - Proponer solución

---

## 🎓 Capacitación

### Para nuevos desarrolladores:

1. Lectura de documentación (2-3 horas)
2. Configuración de entorno (30 minutos)
3. Ejercicio práctico de refactorización (2 horas)
4. Code review con mentor (1 hora)

### Para desarrolladores existentes:

1. Revisión de estándares (1 hora)
2. Actualización de configuración (15 minutos)
3. Refactorización de código legacy (gradual)

---

## ✅ Checklist de Onboarding

### Nuevo Desarrollador:

- [ ] ✅ Leer README.md
- [ ] ✅ Leer unik.instructions.md (completo)
- [ ] ✅ Leer CUMPLIMIENTO_ESTANDARES.md
- [ ] ✅ Instalar extensiones de VS Code recomendadas
- [ ] ✅ Configurar Git hooks (opcional)
- [ ] ✅ Ejecutar validación inicial del proyecto
- [ ] ✅ Revisar código existente bien estructurado
- [ ] ✅ Realizar ejercicio de refactorización
- [ ] ✅ Code review con mentor
- [ ] ✅ Primer commit siguiendo estándares

### Desarrollador Existente:

- [ ] ✅ Leer unik.instructions.md
- [ ] ✅ Leer CUMPLIMIENTO_ESTANDARES.md
- [ ] ✅ Actualizar configuración de VS Code
- [ ] ✅ Ejecutar validación del proyecto
- [ ] ✅ Priorizar archivos para refactorización
- [ ] ✅ Comprometerse con los estándares

---

## 🌟 Beneficios de Seguir los Estándares

### Para el Proyecto:
- ✅ Código más mantenible
- ✅ Menos bugs
- ✅ Más fácil de escalar
- ✅ Mejor calidad general
- ✅ Onboarding más rápido

### Para Desarrolladores:
- ✅ Código más fácil de entender
- ✅ Menos tiempo en debugging
- ✅ Code reviews más rápidas
- ✅ Menos frustración
- ✅ Orgullo profesional

### Para el Equipo:
- ✅ Estándares compartidos
- ✅ Mejor comunicación
- ✅ Menos conflictos en PRs
- ✅ Cultura de calidad
- ✅ Desarrollo más rápido

---

## 📌 Recordatorio Final

> **"La excelencia en el código no es un acto, sino un hábito."**

Los estándares existen para ayudarnos a crear software de calidad. No son restricciones arbitrarias, sino principios probados que mejoran la experiencia de desarrollo.

**Cumplimiento = Profesionalismo**

---

**¿Preguntas?** Consulta [unik.instructions.md](unik.instructions.md) o [CUMPLIMIENTO_ESTANDARES.md](../../CUMPLIMIENTO_ESTANDARES.md)

**¿Encontraste un error?** Abre un issue o propón una mejora

**¿Necesitas ayuda?** Contacta al equipo
