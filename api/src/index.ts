import { Hono } from 'hono'
import { unidadesMedida } from './routes/unidades-medida.js'
import { tiposPartes } from './routes/tipos-partes.js'
import { gruposPartes } from './routes/grupos-partes.js'
import { tiposDepositos } from './routes/tipos-depositos.js'
import { tiposDepositosMovimientos } from './routes/tipos-depositos-movimientos.js'
import { entidades } from './routes/entidades.js'
import { almacenes } from './routes/almacenes.js'
import { configuracion } from './routes/configuracion.js'
import { companies } from './routes/companies.js'
import { partes } from './routes/partes.js'
import { variantes } from './routes/variantes.js'
import { bom } from './routes/bom.js'
import { centrosTrabajo } from './routes/centros-trabajo.js'
import { rutasProduccion } from './routes/rutas-produccion.js'
import { ordenesProduccion } from './routes/ordenes-produccion.js'
import { movimientosInventario } from './routes/movimientos-inventario.js'
import { compras } from './routes/compras.js'
import { planificacion } from './routes/planificacion.js'
import { menu } from './routes/menu.js'
import { sugerencias, inventario } from './routes/sugerencias.js'
import { reportes } from './routes/reportes.js'
import { empresa } from './routes/empresa.js'

const app = new Hono()

app.get('/api/health', (c) => c.json({ status: 'ok', version: '0.3.0' }))

app.route('/api/v1/unidades-medida', unidadesMedida)
app.route('/api/v1/tipos-partes', tiposPartes)
app.route('/api/v1/grupos-partes', gruposPartes)
app.route('/api/v1/tipos-depositos', tiposDepositos)
app.route('/api/v1/tipos-depositos-movimientos', tiposDepositosMovimientos)
app.route('/api/v1/entidades', entidades)
app.route('/api/v1/almacenes', almacenes)
app.route('/api/v1/configuracion', configuracion)
app.route('/api/v1/companies', companies)
app.route('/api/v1/partes', partes)
app.route('/api/v1/variantes', variantes)
app.route('/api/v1/bom', bom)
app.route('/api/v1/centros-trabajo', centrosTrabajo)
app.route('/api/v1/rutas-produccion', rutasProduccion)
app.route('/api/v1/ordenes-produccion', ordenesProduccion)
app.route('/api/v1/movimientos-inventario', movimientosInventario)
app.route('/api/v1/compras', compras)
app.route('/api/v1/planificacion', planificacion)
app.route('/api/v1/menu', menu)
app.route('/api/v1/sugerencias', sugerencias)
app.route('/api/v1/inventario', inventario)
app.route('/api/v1/reportes', reportes)
app.route('/api/v1/empresa', empresa)

export default app
