<?php

/**
 * Mapa de rutas de la aplicación.
 *
 * Sustituye a las anotaciones @WebServlet y a los <filter-mapping> de
 * web.xml. El orden de "soloAdmin" replica RequiereAdministradorFilter.
 *
 * @param Enrutador $e Enrutador a configurar.
 */
function rutas(Enrutador $e): void
{
    // Públicas: accesibles sin sesión.
    $e->publica('/login');

    // Sesión obligatoria.
    $e->get('/panel',   Controlador\PanelControlador::class, 'index');
    $e->get('/caja',    Controlador\CajaControlador::class, 'index');
    $e->post('/caja',   Controlador\CajaControlador::class, 'procesar');

    $e->get('/venta',   Controlador\VentaControlador::class, 'index');
    $e->post('/venta',  Controlador\VentaControlador::class, 'procesar');

    $e->get('/pedidos',  Controlador\PedidoControlador::class, 'index');
    $e->post('/pedidos', Controlador\PedidoControlador::class, 'procesar');

    $e->get('/inventario',  Controlador\InventarioControlador::class, 'index');
    $e->post('/inventario', Controlador\InventarioControlador::class, 'procesar');

    // Solo administrador (como RequiereAdministradorFilter).
    $e->soloAdmin('/ingresos');
    $e->get('/ingresos',  Controlador\IngresoControlador::class, 'index');
    $e->post('/ingresos', Controlador\IngresoControlador::class, 'procesar');

    $e->soloAdmin('/terminos');
    $e->get('/terminos',  Controlador\TerminoControlador::class, 'index');
    $e->post('/terminos', Controlador\TerminoControlador::class, 'procesar');

    $e->soloAdmin('/reportes');
    $e->get('/reportes',  Controlador\ReporteControlador::class, 'index');
    $e->post('/reportes', Controlador\ReporteControlador::class, 'procesar');

    $e->soloAdmin('/usuarios');
    $e->get('/usuarios',  Controlador\UsuarioControlador::class, 'index');
    $e->post('/usuarios', Controlador\UsuarioControlador::class, 'procesar');

    // Autenticación.
    $e->get('/login',   Controlador\LoginControlador::class, 'mostrar');
    $e->post('/login',  Controlador\LoginControlador::class, 'procesar');
    $e->post('/logout', Controlador\LogoutControlador::class, 'procesar');
    $e->get('/logout',  Controlador\LogoutControlador::class, 'procesar');
}
