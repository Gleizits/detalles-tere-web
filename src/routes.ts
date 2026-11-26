import type { Express } from 'express';
import { asyncRoute } from './http';
import { requireAdmin, requireAuth } from './middleware';
import * as landing from './controllers/landingController';
import * as manual from './controllers/manualController';
import { mostrarLogin, procesarLogin, procesarLogout } from './controllers/authController';
import * as caja from './controllers/cajaController';
import * as ingreso from './controllers/ingresoController';
import * as inventario from './controllers/inventarioController';
import * as pedido from './controllers/pedidoController';
import * as panel from './controllers/panelController';
import * as reporte from './controllers/reporteController';
import * as termino from './controllers/terminoController';
import * as usuario from './controllers/usuarioController';
import * as venta from './controllers/ventaController';

export function registerRoutes(app: Express): void {
  app.get('/', asyncRoute(landing.index));
  app.post('/contacto', asyncRoute(landing.contacto));

  app.get('/manual-usuario', asyncRoute(manual.usuario));
  app.get('/manual-programador', asyncRoute(manual.programador));

  app.get('/login', asyncRoute(mostrarLogin));
  app.post('/login', asyncRoute(procesarLogin));
  app.get('/logout', asyncRoute(procesarLogout));
  app.post('/logout', asyncRoute(procesarLogout));

  app.get('/panel', requireAuth, asyncRoute(panel.index));

  app.get('/caja', requireAuth, asyncRoute(caja.index));
  app.post('/caja', requireAuth, asyncRoute(caja.procesar));

  app.get('/venta', requireAuth, asyncRoute(venta.index));
  app.post('/venta', requireAuth, asyncRoute(venta.procesar));

  app.get('/pedidos', requireAuth, asyncRoute(pedido.index));
  app.post('/pedidos', requireAuth, asyncRoute(pedido.procesar));

  app.get('/inventario', requireAuth, asyncRoute(inventario.index));
  app.post('/inventario', requireAuth, asyncRoute(inventario.procesar));

  app.get('/ingresos', requireAuth, requireAdmin, asyncRoute(ingreso.index));
  app.post('/ingresos', requireAuth, requireAdmin, asyncRoute(ingreso.procesar));

  app.get('/terminos', requireAuth, requireAdmin, asyncRoute(termino.index));
  app.post('/terminos', requireAuth, requireAdmin, asyncRoute(termino.procesar));

  app.get('/reportes', requireAuth, requireAdmin, asyncRoute(reporte.index));
  app.post('/reportes', requireAuth, requireAdmin, asyncRoute(reporte.procesar));

  app.get('/usuarios', requireAuth, requireAdmin, asyncRoute(usuario.index));
  app.post('/usuarios', requireAuth, requireAdmin, asyncRoute(usuario.procesar));
}
