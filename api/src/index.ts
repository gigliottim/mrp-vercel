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

const app = new Hono()

app.get('/api/health', (c) => c.json({ status: 'ok', version: '0.2.0' }))

app.route('/api/v1/unidades-medida', unidadesMedida)
app.route('/api/v1/tipos-partes', tiposPartes)
app.route('/api/v1/grupos-partes', gruposPartes)
app.route('/api/v1/tipos-depositos', tiposDepositos)
app.route('/api/v1/tipos-depositos-movimientos', tiposDepositosMovimientos)
app.route('/api/v1/entidades', entidades)
app.route('/api/v1/almacenes', almacenes)
app.route('/api/v1/configuracion', configuracion)
app.route('/api/v1/companies', companies)

export default app
