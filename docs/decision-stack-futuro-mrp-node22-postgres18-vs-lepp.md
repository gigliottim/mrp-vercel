# Decision de stack futuro para MRP

Fecha: 2026-03-10
Autor: Evaluacion tecnica para roadmap MRP

## Objetivo
Definir si conviene para versiones futuras del MRP:
1. Migrar a `Node.js 22 + PostgreSQL 18`.
2. Mantener y evolucionar el stack actual `LEPP`:
- `bitnami/nginx:latest`
- `bitnami/php-fpm:latest`
- `bitnami/postgresql:latest`
- `dpage/pgadmin4:latest`

## Resumen ejecutivo
Recomendacion principal: **seguir en el stack actual (LEPP) para el core del MRP en el corto y mediano plazo**, y adoptar Node.js de forma incremental para servicios donde aporta ventaja clara (tiempo real, colas, integraciones, workers).

Recomendacion de base de datos: **si o si avanzar a PostgreSQL 18**, independientemente del lenguaje backend.

Esto minimiza riesgo operativo en un sistema multiempresa, evita una reescritura costosa y permite capturar beneficios modernos sin frenar delivery.

## Contexto actual del proyecto
- Backend principal en PHP con arquitectura por capas (controllers/services/repositories).
- Operacion multiempresa con provisioning y migraciones por tenant.
- Muchas piezas de dominio ya maduras (migraciones, scripts de smoke test, utilidades operativas).
- Convencion de mantenibilidad estricta (archivos pequenos y separacion de responsabilidades).

Implicacion: existe inversion fuerte en codigo y procesos del stack actual.

## Comparativa directa

### Opcion A: Node.js 22 + PostgreSQL 18
Ventajas:
- Muy buen ecosistema para APIs modernas, workers y event-driven.
- Excelente para WebSocket, streaming y tareas I/O intensivas.
- Unificacion backend/frontend JS/TS si se decide estandarizar en TypeScript.
- Tooling moderno para observabilidad y testing.

Desventajas/riesgos:
- Reescritura del dominio MRP (alto riesgo de regresiones funcionales).
- Curva de migracion de equipo, pipelines, despliegue y debugging.
- Riesgo alto en logica sensible multiempresa (tenant context, migraciones por empresa, permisos).
- Costo de oportunidad: meses de migracion en vez de features de negocio.

### Opcion B: LEPP actual (Nginx + PHP-FPM + PostgreSQL)
Ventajas:
- Menor riesgo: aprovecha codigo, procesos y know-how existentes.
- Time-to-market mas rapido para nuevas funcionalidades.
- Stack probado en tu operacion real.
- Facil de endurecer con mejores practicas (tests, observabilidad, colas).

Desventajas:
- Algunas capacidades en tiempo real/event-driven requieren mas trabajo en PHP puro.
- Menor atractivo para cierto perfil de talento full JS.
- Si no se moderniza arquitectura, puede crecer deuda tecnica.

## Matriz de decision (1-5)
Criterio | Peso | Node 22 + PG18 | LEPP actual
--- | --- | --- | ---
Riesgo operativo multiempresa | 5 | 2 | 5
Velocidad de entrega 6-12 meses | 5 | 2 | 5
Costo total de migracion | 4 | 1 | 5
Escalabilidad futura | 4 | 5 | 4
Mantenibilidad del dominio actual | 5 | 2 | 5
Capacidades tiempo real/eventos | 3 | 5 | 3
Contratacion/perfil mercado | 2 | 4 | 3

Lectura: para el horizonte inmediato, LEPP gana por menor riesgo y mayor retorno rapido; Node gana en casos especificos, no necesariamente en reescritura total.

## Recomendacion tecnica
1. Mantener el core transaccional MRP en PHP (actual).
2. Separar capacidades nuevas en servicios desacoplados (arquitectura hibrida):
- Servicio Node para WebSocket/notificaciones en tiempo real.
- Workers Node para integraciones externas o tareas I/O pesadas.
- Mantener PostgreSQL como fuente de verdad unica.
3. Definir contratos API claros entre core PHP y servicios Node.
4. Subir observabilidad y pruebas antes de mover partes criticas.

## Plan sugerido por fases

### Fase 0 (2-4 semanas)
- Baseline de performance (APM, logs estructurados, tiempos p95/p99).
- KPI de negocio y tecnicos para tomar decisiones con datos.
- Hardening de CI/CD y smoke tests multiempresa.

### Fase 1 (1-2 meses)
- Upgrade a `PostgreSQL 18` en entorno controlado.
- Probar una capacidad puntual en Node (ejemplo: notificaciones en tiempo real).
- Medir costo operativo vs beneficio real.

### Fase 2 (2-4 meses)
- Si el piloto funciona, extender Node solo a dominios no core.
- Mantener el core de inventario, costos y movimientos en PHP.
- Implementar colas/eventos y trazabilidad end-to-end.

### Fase 3 (decision anual)
- Evaluar migracion mayor solo si se cumplen triggers:
- Evidencia de cuellos de botella no resolubles en PHP.
- Equipo listo para operar Node a escala (on-call, observabilidad, seguridad).
- Cobertura de tests alta en dominio critico para evitar regresiones.

## Riesgos a controlar
- Reescritura por moda tecnologica en lugar de necesidad real.
- Duplicar logica de negocio en dos stacks sin contratos claros.
- Debilitar aislamiento por tenant durante migraciones parciales.
- Incrementar complejidad de despliegue sin automatizacion suficiente.

## Decision propuesta
Para tu MRP hoy:
- **No recomiendo una migracion total inmediata a Node.js 22.**
- **Si recomiendo evolucion hibrida:** LEPP para core + Node para casos donde aporta ventaja directa.
- **Si recomiendo PostgreSQL 18** como mejora transversal de plataforma.

## Checklist para validar esta decision en 90 dias
- [ ] KPI p95/p99 y errores por modulo definidos y medidos.
- [ ] Piloto Node en produccion controlada con metricas.
- [ ] Sin regresiones en flujos criticos (compras, stock, produccion, costos).
- [ ] Automatizacion de migraciones multiempresa robusta.
- [ ] Decidir con datos: expandir modelo hibrido o mantener foco LEPP.

## Conclusion
La mejor relacion riesgo/beneficio para tu escenario multiempresa es **continuar sobre LEPP y modernizar incrementalmente**, en lugar de reescribir todo. Node.js 22 tiene valor real, pero como complemento estrategico primero, no como reemplazo total del core en esta etapa.
