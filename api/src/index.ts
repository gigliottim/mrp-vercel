import { Hono } from 'hono'
import { unidadesMedida } from './routes/unidades-medida'
import { tiposPartes } from './routes/tipos-partes'
import { gruposPartes } from './routes/grupos-partes'
import { tiposDepositos } from './routes/tipos-depositos'
import { tiposDepositosMovimientos } from './routes/tipos-depositos-movimientos'
import { entidades } from './routes/entidades'
import { almacenes } from './routes/almacenes'
import { configuracion } from './routes/configuracion'
import { companies } from './routes/companies'

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
