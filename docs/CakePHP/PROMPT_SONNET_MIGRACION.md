# Prompt para Análisis Arquitectónico y Plan de Migración (Claude Sonnet)

Este documento contiene el prompt optimizado para solicitar a Claude Sonnet el análisis de los módulos del proyecto MRP y la creación de planes de migración hacia CakePHP.

## 📋 Instrucciones de Uso
1. Copia el contenido de la sección **"Prompt para la AI"**.
2. Adjunta al chat los archivos relevantes del módulo que deseas migrar (Controladores, Servicios, Repositorios y esquemas de DB).
3. Envía el prompt.

---

## 🚀 Prompt para la AI

**Rol:** Actúa como un Arquitecto de Software Senior experto en PHP, especializado en la migración de sistemas legacy/custom hacia el framework CakePHP.

**Contexto del Proyecto:**
Estoy migrando un sistema MRP (Manufacturing Resource Planning) desde un framework personalizado hacia CakePHP. El sistema actual ya implementa un patrón MVC con capas de `Services` y `Repositories`, lo que facilita la transición, pero requiere un mapeo preciso para no perder reglas de negocio críticas.

**Objetivo:**
Realizar un análisis arquitectónico exhaustivo de los archivos adjuntos y diseñar un plan de migración detallado, paso a paso, para trasladar la funcionalidad al ecosistema de CakePHP.

**Requerimientos del Análisis:**
1. **Mapeo de Responsabilidades:**
   - Identificar qué lógica del `Controller` actual debe permanecer en el `Controller` de CakePHP y qué debe delegarse a `Components` o `Services`.
   - Analizar los `Services` actuales y determinar cómo se traducen a la lógica de negocio de CakePHP (ya sea en la capa de `Table` o en servicios desacoplados).
   - Mapear los `Repositories` manuales a la estructura de `Table` y `Entity` del ORM de CakePHP.

2. **Análisis de Dependencias:**
   - Identificar dependencias entre módulos y servicios para determinar el orden de migración.
   - Detectar posibles cuellos de botella o riesgos técnicos (especialmente en el manejo de multi-tenancy y conexiones dinámicas a DB).

3. **Plan de Migración Detallado (Entregable):**
   Genera una hoja de ruta técnica dividida en:
   - **Fase 1: Definición de Datos:** Definición de las `Entities` y `Tables` (incluyendo validaciones y asociaciones).
   - **Fase 2: Capa de Negocio:** Traducción de la lógica de los `Services` actuales.
   - **Fase 3: Capa de Presentación/API:** Implementación de rutas y controladores.
   - **Fase 4: Validación:** Estrategia de tests unitarios para asegurar la paridad funcional entre el sistema antiguo y el nuevo.

**Formato de Salida Esperado:**
- **Resumen Ejecutivo:** Breve descripción de la complejidad del módulo.
- **Tabla de Mapeo:** `Componente Actual` $\rightarrow$ `Componente CakePHP` $\rightarrow$ `Acción Requerida`.
- **Guía de Implementación:** Pasos numerados y técnicos para el desarrollador (o para una AI de codificación como Qwen).
- **Alertas de Riesgo:** Cualquier punto donde la "Convención sobre Configuración" de CakePHP choque con la lógica actual.

**Archivos adjuntos para analizar:**
[INSERTAR AQUÍ LA LISTA DE ARCHIVOS ADJUNTOS]
