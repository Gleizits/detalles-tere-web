-- ============================================================
-- DetallesTereTS — 6 consultas/vistas para Power BI (PC4, item 6)
-- Cada vista involucra al menos 5 tablas de DetallesTereDB.
-- No requiere iniciar Power BI: solo crear estas vistas y conectarse
-- desde Power BI Desktop a SQL Server / Azure SQL.
-- ============================================================

USE DetallesTereDB;
GO

-- ------------------------------------------------------------
-- 1) Ventas por día, vendedor, caja, cliente, categoría y artículo
--    Tablas (10): VENTA, DETALLE_VENTA, CAJA, USUARIO, CLIENTE,
--    INVENTARIO, PRODUCTO, MATERIAL, ARREGLO, CATEGORIA.
--    KPIs: unidades, ingresos, costo vigente, margen estimado.
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_PBI_Ventas_Detalle AS
SELECT
    v.ID_VENTA,
    dv.ID_DETALLE_VENTA,
    v.FECHA_VENTA,
    v.ESTADO AS ESTADO_VENTA,
    v.TIPO_PAGO,
    c.ID_CAJA,
    u.NOMBRE + ' ' + u.APELLIDO AS VENDEDOR,
    COALESCE(cl.NOMBRE, 'Publico general') AS CLIENTE,
    COALESCE(pr.NOMBRE, ma.NOMBRE, ar.NOMBRE) AS ARTICULO,
    cat.NOMBRE AS CATEGORIA,
    inv.TIPO_ALMACEN,
    inv.UNIDAD_MEDIDA,
    dv.CANTIDAD,
    dv.COSTO_UNITARIO AS PRECIO_VENTA,
    dv.SUBTOTAL,
    COALESCE(pr.PRECIO_COMPRA, ma.PRECIO_COMPRA, ar.PRECIO_COMPRA, 0) * dv.CANTIDAD AS COSTO_VIGENTE,
    dv.SUBTOTAL - COALESCE(pr.PRECIO_COMPRA, ma.PRECIO_COMPRA, ar.PRECIO_COMPRA, 0) * dv.CANTIDAD AS MARGEN_ESTIMADO,
    dv.FECHA_CREACION,
    dv.FECHA_VENCIMIENTO,
    dv.ESTADO AS ESTADO_LINEA
FROM dbo.VENTA v
JOIN dbo.DETALLE_VENTA dv ON dv.ID_VENTA = v.ID_VENTA
JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = dv.ID_INVENTARIO
LEFT JOIN dbo.CAJA c ON c.ID_CAJA = v.ID_CAJA
LEFT JOIN dbo.USUARIO u ON u.ID_USUARIO = c.ID_USUARIO
LEFT JOIN dbo.CLIENTE cl ON cl.ID_CLIENTE = dv.ID_CLIENTE
LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO
LEFT JOIN dbo.MATERIAL ma ON ma.ID_MATERIAL = inv.ID_MATERIAL
LEFT JOIN dbo.ARREGLO ar ON ar.ID_DETALLE = inv.ID_DETALLE
LEFT JOIN dbo.CATEGORIA cat
       ON cat.ID_CATEGORIA = COALESCE(pr.ID_CATEGORIA, ma.ID_CATEGORIA, ar.ID_CATEGORIA);
GO

-- ------------------------------------------------------------
-- 2) Ingresos por proveedor, usuario, categoría y artículo
--    Tablas (9): INGRESO, PROVEEDOR, USUARIO, DETALLE_INGRESO,
--    INVENTARIO, PRODUCTO, MATERIAL, ARREGLO, CATEGORIA.
--    KPIs: unidades, total de compra, precio unitario, margen potencial.
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_PBI_Ingresos_Detalle AS
SELECT
    ig.ID_INGRESO,
    ig.FECHA_INGRESO,
    pv.NOMBRE AS PROVEEDOR,
    u.NOMBRE + ' ' + u.APELLIDO AS USUARIO,
    COALESCE(pr.NOMBRE, ma.NOMBRE, ar.NOMBRE) AS ARTICULO,
    cat.NOMBRE AS CATEGORIA,
    inv.TIPO_ALMACEN,
    inv.UNIDAD_MEDIDA,
    di.ID_DETALLE_INGRESO,
    di.CANTIDAD,
    di.PRECIO_COMPRA_UNITARIO,
    di.SUBTOTAL,
    di.OBSERVACIONES,
    COALESCE(pr.PRECIO_VENTA, ma.PRECIO_VENTA, ar.PRECIO_VENTA, 0) AS PRECIO_VENTA_VIGENTE,
    (COALESCE(pr.PRECIO_VENTA, ma.PRECIO_VENTA, ar.PRECIO_VENTA, 0) - di.PRECIO_COMPRA_UNITARIO) * di.CANTIDAD AS MARGEN_POTENCIAL
FROM dbo.INGRESO ig
JOIN dbo.PROVEEDOR pv ON pv.ID_PROVEEDOR = ig.ID_PROVEEDOR
JOIN dbo.USUARIO u ON u.ID_USUARIO = ig.ID_USUARIO
JOIN dbo.DETALLE_INGRESO di ON di.ID_INGRESO = ig.ID_INGRESO
JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = di.ID_INVENTARIO
LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO
LEFT JOIN dbo.MATERIAL ma ON ma.ID_MATERIAL = inv.ID_MATERIAL
LEFT JOIN dbo.ARREGLO ar ON ar.ID_DETALLE = inv.ID_DETALLE
LEFT JOIN dbo.CATEGORIA cat
       ON cat.ID_CATEGORIA = COALESCE(pr.ID_CATEGORIA, ma.ID_CATEGORIA, ar.ID_CATEGORIA);
GO

-- ------------------------------------------------------------
-- 3) Pedidos reservados con saldo y plazos
--    Tablas (10): PEDIDOS, CLIENTE, USUARIO, DETALLE_PEDIDO,
--    INVENTARIO, PRODUCTO, MATERIAL, ARREGLO, CATEGORIA, VENTA.
--    KPIs: total, adelanto, pagado, saldo, días para cada plazo.
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_PBI_Pedidos_Saldo AS
WITH ARTICULOS AS (
    SELECT
        dp.ID_PEDIDO,
        SUM(dp.CANTIDAD) AS UNIDADES,
        SUM(dp.SUBTOTAL) AS TOTAL_DETALLES,
        STRING_AGG(CONVERT(varchar(200), COALESCE(pr.NOMBRE, ma.NOMBRE, ar.NOMBRE)), ' | ') AS ARTICULOS,
        STRING_AGG(CONVERT(varchar(200), cat.NOMBRE), ' | ') AS CATEGORIAS
    FROM dbo.DETALLE_PEDIDO dp
    JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = dp.ID_INVENTARIO
    LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO
    LEFT JOIN dbo.MATERIAL ma ON ma.ID_MATERIAL = inv.ID_MATERIAL
    LEFT JOIN dbo.ARREGLO ar ON ar.ID_DETALLE = inv.ID_DETALLE
    LEFT JOIN dbo.CATEGORIA cat
           ON cat.ID_CATEGORIA = COALESCE(pr.ID_CATEGORIA, ma.ID_CATEGORIA, ar.ID_CATEGORIA)
    GROUP BY dp.ID_PEDIDO
),
PAGOS AS (
    SELECT
        ID_PEDIDO,
        SUM(TOTAL) AS PAGADO,
        COUNT(*) AS NUMERO_PAGOS,
        MAX(FECHA_VENTA) AS ULTIMO_PAGO
    FROM dbo.VENTA
    WHERE ID_PEDIDO IS NOT NULL
    GROUP BY ID_PEDIDO
)
SELECT
    p.ID_PEDIDO,
    p.FECHA_CREACION,
    COALESCE(cl.NOMBRE, p.NOMBRE_PAGA) AS CLIENTE,
    p.NOMBRE_PAGA,
    p.NOMBRE_RECOGE,
    p.CELULAR,
    p.FECHA_RECOJO,
    p.HORA_RECOJO,
    u.NOMBRE + ' ' + u.APELLIDO AS REGISTRADO_POR,
    p.ESTADO,
    p.ESTADO_PRODUCCION,
    a.ARTICULOS,
    a.CATEGORIAS,
    a.UNIDADES,
    a.TOTAL_DETALLES,
    p.TOTAL,
    p.ADELANTO,
    COALESCE(pg.PAGADO, 0) AS PAGADO,
    p.TOTAL - p.ADELANTO - COALESCE(pg.PAGADO, 0) AS SALDO,
    COALESCE(pg.NUMERO_PAGOS, 0) AS NUMERO_PAGOS,
    pg.ULTIMO_PAGO,
    p.FECHA_LIMITE_PAGO,
    p.FECHA_LIMITE_RECOJO,
    p.FECHA_LIMITE_CANJE,
    p.FECHA_PAGO_TOTAL,
    DATEDIFF(DAY, SYSDATETIME(), p.FECHA_LIMITE_PAGO) AS DIAS_PARA_PAGO,
    DATEDIFF(DAY, SYSDATETIME(), p.FECHA_LIMITE_RECOJO) AS DIAS_PARA_RECOJO,
    DATEDIFF(DAY, SYSDATETIME(), p.FECHA_LIMITE_CANJE) AS DIAS_PARA_CANJE
FROM dbo.PEDIDOS p
LEFT JOIN dbo.CLIENTE cl ON cl.ID_CLIENTE = p.ID_CLIENTE
JOIN dbo.USUARIO u ON u.ID_USUARIO = p.ID_USUARIO
LEFT JOIN ARTICULOS a ON a.ID_PEDIDO = p.ID_PEDIDO
LEFT JOIN PAGOS pg ON pg.ID_PEDIDO = p.ID_PEDIDO;
GO

-- ------------------------------------------------------------
-- 4) Cotizaciones de 24 horas: vigente / vencida / convertida
--    Tablas (10): VENTA, DETALLE_VENTA, CLIENTE, CAJA, USUARIO,
--    INVENTARIO, PRODUCTO, MATERIAL, ARREGLO, CATEGORIA.
--    KPIs: horas restantes, subtotal, estado de la cotización.
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_PBI_Cotizaciones_24h AS
SELECT
    v.ID_VENTA,
    dv.ID_DETALLE_VENTA,
    dv.FECHA_CREACION,
    dv.FECHA_VENCIMIENTO,
    v.FECHA_VENTA,
    COALESCE(cl.NOMBRE, 'Publico general') AS CLIENTE,
    u.NOMBRE + ' ' + u.APELLIDO AS REGISTRADO_POR,
    COALESCE(pr.NOMBRE, ma.NOMBRE, ar.NOMBRE) AS ARTICULO,
    cat.NOMBRE AS CATEGORIA,
    dv.CANTIDAD,
    dv.COSTO_UNITARIO,
    dv.SUBTOTAL,
    DATEDIFF(HOUR, SYSDATETIME(), dv.FECHA_VENCIMIENTO) AS HORAS_RESTANTES,
    CASE
        WHEN v.ESTADO = 'VENDIDO' OR dv.ESTADO = 'VENDIDO' THEN 'CONVERTIDA'
        WHEN dv.FECHA_VENCIMIENTO IS NULL THEN 'SIN_VENCIMIENTO'
        WHEN dv.FECHA_VENCIMIENTO < SYSDATETIME() THEN 'VENCIDA'
        ELSE 'VIGENTE'
    END AS SITUACION
FROM dbo.VENTA v
JOIN dbo.DETALLE_VENTA dv ON dv.ID_VENTA = v.ID_VENTA
JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = dv.ID_INVENTARIO
LEFT JOIN dbo.CLIENTE cl ON cl.ID_CLIENTE = dv.ID_CLIENTE
LEFT JOIN dbo.CAJA c ON c.ID_CAJA = v.ID_CAJA
LEFT JOIN dbo.USUARIO u ON u.ID_USUARIO = c.ID_USUARIO
LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO
LEFT JOIN dbo.MATERIAL ma ON ma.ID_MATERIAL = inv.ID_MATERIAL
LEFT JOIN dbo.ARREGLO ar ON ar.ID_DETALLE = inv.ID_DETALLE
LEFT JOIN dbo.CATEGORIA cat
       ON cat.ID_CATEGORIA = COALESCE(pr.ID_CATEGORIA, ma.ID_CATEGORIA, ar.ID_CATEGORIA)
WHERE v.ESTADO IN ('COTIZACION', 'VENDIDO')
  AND dv.ESTADO IN ('COTIZACION', 'VENDIDO');
GO

-- ------------------------------------------------------------
-- 5) Movimientos de inventario por usuario, tipo, artículo y categoría
--    Tablas (7): MOVIMIENTO_INVENTARIO, USUARIO, INVENTARIO,
--    PRODUCTO, MATERIAL, ARREGLO, CATEGORIA.
--    KPIs: entradas, salidas, ajustes, stock actual y mínimo.
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_PBI_Movimientos_Inventario AS
SELECT
    mi.ID_MOVIMIENTO,
    mi.FECHA_MOVIMIENTO,
    mi.TIPO_MOVIMIENTO,
    u.NOMBRE + ' ' + u.APELLIDO AS USUARIO,
    COALESCE(pr.NOMBRE, ma.NOMBRE, ar.NOMBRE) AS ARTICULO,
    cat.NOMBRE AS CATEGORIA,
    inv.TIPO_ALMACEN,
    inv.UNIDAD_MEDIDA,
    inv.STOCK_ACT,
    inv.STOCK_MIN,
    mi.CANTIDAD,
    CASE WHEN mi.TIPO_MOVIMIENTO = 'SALIDA' THEN -mi.CANTIDAD ELSE mi.CANTIDAD END AS CANTIDAD_SIGNADA,
    CASE WHEN inv.STOCK_ACT <= inv.STOCK_MIN THEN 1 ELSE 0 END AS REQUIERE_REPOSICION
FROM dbo.MOVIMIENTO_INVENTARIO mi
JOIN dbo.USUARIO u ON u.ID_USUARIO = mi.ID_USUARIO
JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = mi.ID_INVENTARIO
LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO
LEFT JOIN dbo.MATERIAL ma ON ma.ID_MATERIAL = inv.ID_MATERIAL
LEFT JOIN dbo.ARREGLO ar ON ar.ID_DETALLE = inv.ID_DETALLE
LEFT JOIN dbo.CATEGORIA cat
       ON cat.ID_CATEGORIA = COALESCE(pr.ID_CATEGORIA, ma.ID_CATEGORIA, ar.ID_CATEGORIA);
GO

-- ------------------------------------------------------------
-- 6) Sinónimos más vendidos
--    Tablas (10): TERMINOS, USUARIO, DETALLE_TERMINO, INVENTARIO,
--    PRODUCTO, MATERIAL, ARREGLO, CATEGORIA, DETALLE_VENTA, VENTA.
--    KPIs: líneas vendidas (DISTINCT), unidades y total.
--    El CTE evita duplicar una misma línea de venta por cada sinónimo.
-- ------------------------------------------------------------
CREATE OR ALTER VIEW dbo.vw_PBI_Sinonimos_Ventas AS
WITH VENTAS_ARTICULO AS (
    SELECT
        dv.ID_INVENTARIO,
        COUNT(DISTINCT dv.ID_DETALLE_VENTA) AS LINEAS_VENDIDAS,
        SUM(dv.CANTIDAD) AS UNIDADES,
        SUM(dv.SUBTOTAL) AS TOTAL_VENDIDO
    FROM dbo.DETALLE_VENTA dv
    JOIN dbo.VENTA v ON v.ID_VENTA = dv.ID_VENTA
    WHERE v.ESTADO = 'VENDIDO'
    GROUP BY dv.ID_INVENTARIO
)
SELECT
    t.ID_TERMINO,
    t.PALABRA,
    u.NOMBRE + ' ' + u.APELLIDO AS REGISTRADO_POR,
    COALESCE(pr.NOMBRE, ma.NOMBRE, ar.NOMBRE) AS ARTICULO,
    cat.NOMBRE AS CATEGORIA,
    inv.TIPO_ALMACEN,
    inv.UNIDAD_MEDIDA,
    COALESCE(va.LINEAS_VENDIDAS, 0) AS LINEAS_VENDIDAS,
    COALESCE(va.UNIDADES, 0) AS UNIDADES,
    COALESCE(va.TOTAL_VENDIDO, 0) AS TOTAL_VENDIDO
FROM dbo.TERMINOS t
JOIN dbo.USUARIO u ON u.ID_USUARIO = t.ID_USUARIO
JOIN dbo.DETALLE_TERMINO dt ON dt.ID_TERMINO = t.ID_TERMINO
JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = dt.ID_INVENTARIO
LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO
LEFT JOIN dbo.MATERIAL ma ON ma.ID_MATERIAL = inv.ID_MATERIAL
LEFT JOIN dbo.ARREGLO ar ON ar.ID_DETALLE = inv.ID_DETALLE
LEFT JOIN dbo.CATEGORIA cat
       ON cat.ID_CATEGORIA = COALESCE(pr.ID_CATEGORIA, ma.ID_CATEGORIA, ar.ID_CATEGORIA)
LEFT JOIN VENTAS_ARTICULO va ON va.ID_INVENTARIO = inv.ID_INVENTARIO;
GO

-- Validación rápida: las 6 vistas deben existir y devolver columnas.
SELECT nombre = 'vw_PBI_Ventas_Detalle', filas = (SELECT COUNT(*) FROM dbo.vw_PBI_Ventas_Detalle)
UNION ALL SELECT 'vw_PBI_Ingresos_Detalle', (SELECT COUNT(*) FROM dbo.vw_PBI_Ingresos_Detalle)
UNION ALL SELECT 'vw_PBI_Pedidos_Saldo', (SELECT COUNT(*) FROM dbo.vw_PBI_Pedidos_Saldo)
UNION ALL SELECT 'vw_PBI_Cotizaciones_24h', (SELECT COUNT(*) FROM dbo.vw_PBI_Cotizaciones_24h)
UNION ALL SELECT 'vw_PBI_Movimientos_Inventario', (SELECT COUNT(*) FROM dbo.vw_PBI_Movimientos_Inventario)
UNION ALL SELECT 'vw_PBI_Sinonimos_Ventas', (SELECT COUNT(*) FROM dbo.vw_PBI_Sinonimos_Ventas);
GO
