# Consultas listas para Power BI — DetallesTereTS

**Power BI no se inicia en esta entrega.** Aquí quedan las seis consultas (vistas SQL) que el docente pidió en el PC4, con mínimo 5 tablas por consulta.

## Cómo usarlas

1. Ejecuta `DetallesTerePHP/db/01_schema_DetallesTereDB.sql`, `02_seed_DetallesTereDB.sql`, `03_seed_500_registros.sql` y, al final, `04_consultas_powerbi.sql`.
2. En Power BI Desktop: **Obtener datos → SQL Server** (o **Azure SQL Database**), servidor y credenciales de `.env`, base `DetallesTereDB`.
3. Selecciona las seis vistas `dbo.vw_PBI_*`. No hace falta traer las 22 tablas.
4. Crea una tabla de fechas en Power BI (`CALENDAR`) y relationa por `FECHA_VENTA`, `FECHA_INGRESO`, `FECHA_CREACION` o `FECHA_MOVIMIENTO` según corresponda.

## Mapa consulta → visual

| # | Vista SQL | Tablas que intervienen | Visual sugerido | Medidas sugeridas |
|---|---|---|---|---|
| 1 | `vw_PBI_Ventas_Detalle` | 10: `VENTA`, `DETALLE_VENTA`, `CAJA`, `USUARIO`, `CLIENTE`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA` | Matriz día × categoría; tarjetas de ingresos/margen | `SUM(SUBTOTAL)`, `SUM(CANTIDAD)`, `SUM(MARGEN_ESTIMADO)` |
| 2 | `vw_PBI_Ingresos_Detalle` | 9: `INGRESO`, `PROVEEDOR`, `USUARIO`, `DETALLE_INGRESO`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA` | Barras apiladas por proveedor y categoría | `SUM(SUBTOTAL)`, `AVG(PRECIO_COMPRA_UNITARIO)`, `SUM(MARGEN_POTENCIAL)` |
| 3 | `vw_PBI_Pedidos_Saldo` | 10: `PEDIDOS`, `CLIENTE`, `USUARIO`, `DETALLE_PEDIDO`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA`, `VENTA` | Tabla de saldo pendiente + indicador de días al plazo | `SUM(SALDO)`, `MAX(DIAS_PARA_PAGO)`, `COUNT(ID_PEDIDO)` |
| 4 | `vw_PBI_Cotizaciones_24h` | 10: `VENTA`, `DETALLE_VENTA`, `CLIENTE`, `CAJA`, `USUARIO`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA` | Embudo `VIGENTE / VENCIDA / CONVERTIDA` | `COUNT(ID_DETALLE_VENTA)`, `SUM(SUBTOTAL)`, `MIN(HORAS_RESTANTES)` |
| 5 | `vw_PBI_Movimientos_Inventario` | 7: `MOVIMIENTO_INVENTARIO`, `USUARIO`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA` | Combinado entradas vs salidas; alerta de stock mínimo | `SUM(CANTIDAD_SIGNADA)`, `SUM(REQUIERE_REPOSICION)` |
| 6 | `vw_PBI_Sinonimos_Ventas` | 10: `TERMINOS`, `USUARIO`, `DETALLE_TERMINO`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA`, `DETALLE_VENTA`, `VENTA` | Top 10 de sinónimos por unidades | `SUM(LINEAS_VENDIDAS)`, `SUM(UNIDADES)`, `SUM(TOTAL_VENDIDO)` |

## Notas de modelo

- `ARTICULO = COALESCE(PRODUCTO.NOMBRE, MATERIAL.NOMBRE, ARREGLO.NOMBRE)` porque `INVENTARIO` permite exactamente una de esas tres claves.
- `CATEGORIA = COALESCE(pr.ID_CATEGORIA, ma.ID_CATEGORIA, ar.ID_CATEGORIA)`.
- En la vista 6 se usa `COUNT(DISTINCT ID_DETALLE_VENTA)` dentro de un CTE para no contar dos veces una misma línea cuando un artículo tiene varios sinónimos.
- El costo en las vistas 1 y 2 es el **costo vigente** del catálogo; el costo histórico de la venta queda registrado en `DETALLE_VENTA.COSTO_UNITARIO` como precio de venta del momento.
