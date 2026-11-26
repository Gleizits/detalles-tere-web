# Consultas listas para Power BI — DetallesTereTS

**Power BI no se inicia en esta entrega.** Aquí quedan las seis vistas PostgreSQL (equivalentes a las consultas T-SQL del PC4), con mínimo 5 tablas por consulta.

## Cómo usarlas

1. En Neon, ejecuta `DetallesTerePHP/db/pg/00_create_database.sql` solo si hace falta, y luego `01_schema_DetallesTereDB.sql`, `02_seed_DetallesTereDB.sql`, `03_seed_500_registros.sql`, `04_consultas_powerbi.sql` y `05_roles_y_permisos.sql`, en ese orden.
2. En Power BI Desktop (o Web, si el tenant lo permite): **Obtener datos → PostgreSQL database**.
3. Usa el host de la **URL directa** de Neon (sin `-pooler`), base `DetallesTereDB`, usuario `pbi_reader` y `sslmode=require`.
4. Selecciona las seis vistas `dbo."vw_PBI_*"`. No hace falta traer las 22 tablas.
5. Crea una tabla de fechas en Power BI (`CALENDAR`) y relationa por `FECHA_VENTA`, `FECHA_INGRESO`, `FECHA_CREACION` o `FECHA_MOVIMIENTO` según corresponda.

> Los nombres de vista y de columnas están **citados** en PostgreSQL para conservar la grafía original del SQL Server (`vw_PBI_Ventas_Detalle`, `ID_VENTA`, etc.). En el navegador de Power BI aparecen con esa misma grafía.

> Si el tenant universitario bloquea el conector PostgreSQL o el refresh en la Nube, no hay PC encendida de por medio: la alternativa es un endpoint autenticado en Vercel (pendiente) o publicar el `.pbix` con datos importados como evidencia estática.

## Mapa consulta → visual

| # | Vista SQL | Tablas que intervienen | Visual sugerido | Medidas sugeridas |
|---|---|---|---|---|
| 1 | `dbo."vw_PBI_Ventas_Detalle"` | 10: `VENTA`, `DETALLE_VENTA`, `CAJA`, `USUARIO`, `CLIENTE`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA` | Matriz día × categoría; tarjetas de ingresos/margen | `SUM(SUBTOTAL)`, `SUM(CANTIDAD)`, `SUM(MARGEN_ESTIMADO)` |
| 2 | `dbo."vw_PBI_Ingresos_Detalle"` | 9: `INGRESO`, `PROVEEDOR`, `USUARIO`, `DETALLE_INGRESO`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA` | Barras apiladas por proveedor y categoría | `SUM(SUBTOTAL)`, `AVG(PRECIO_COMPRA_UNITARIO)`, `SUM(MARGEN_POTENCIAL)` |
| 3 | `dbo."vw_PBI_Pedidos_Saldo"` | 10: `PEDIDOS`, `CLIENTE`, `USUARIO`, `DETALLE_PEDIDO`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA`, `VENTA` | Tabla de saldo pendiente + indicador de días al plazo | `SUM(SALDO)`, `MAX(DIAS_PARA_PAGO)`, `COUNT(ID_PEDIDO)` |
| 4 | `dbo."vw_PBI_Cotizaciones_24h"` | 10: `VENTA`, `DETALLE_VENTA`, `CLIENTE`, `CAJA`, `USUARIO`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA` | Embudo `VIGENTE / VENCIDA / CONVERTIDA` | `COUNT(ID_DETALLE_VENTA)`, `SUM(SUBTOTAL)`, `MIN(HORAS_RESTANTES)` |
| 5 | `dbo."vw_PBI_Movimientos_Inventario"` | 7: `MOVIMIENTO_INVENTARIO`, `USUARIO`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA` | Combinado entradas vs salidas; alerta de stock mínimo | `SUM(CANTIDAD_SIGNADA)`, `SUM(REQUIERE_REPOSICION)` |
| 6 | `dbo."vw_PBI_Sinonimos_Ventas"` | 10: `TERMINOS`, `USUARIO`, `DETALLE_TERMINO`, `INVENTARIO`, `PRODUCTO`, `MATERIAL`, `ARREGLO`, `CATEGORIA`, `DETALLE_VENTA`, `VENTA` | Top 10 de sinónimos por unidades | `SUM(LINEAS_VENDIDAS)`, `SUM(UNIDADES)`, `SUM(TOTAL_VENDIDO)` |

## Notas de modelo

- `ARTICULO = COALESCE(PRODUCTO.NOMBRE, MATERIAL.NOMBRE, ARREGLO.NOMBRE)` porque `INVENTARIO` permite exactamente una de esas tres claves.
- `CATEGORIA = COALESCE(pr.ID_CATEGORIA, ma.ID_CATEGORIA, ar.ID_CATEGORIA)`.
- `STRING_AGG(... ORDER BY ...)` reemplaza al `STRING_AGG(... WITHIN GROUP (ORDER BY ...))` de T-SQL.
- `DIAS_PARA_*` usa diferencia de fechas (`date - current_date`) y `HORAS_RESTANTES` usa `EXTRACT(EPOCH ...)/3600`.
- En la vista 6 se usa `COUNT(DISTINCT ID_DETALLE_VENTA)` dentro de un CTE para no contar dos veces una misma línea cuando un artículo tiene varios sinónimos.
- El costo en las vistas 1 y 2 es el **costo vigente** del catálogo; el costo histórico de la venta queda registrado en `DETALLE_VENTA.COSTO_UNITARIO` como precio de venta del momento.
