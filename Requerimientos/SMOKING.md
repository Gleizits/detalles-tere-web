# SMOKING — DetallesTereTS (PC4, item 4)

**Smoke testing = pruebas de humo.** Se ejecuta después de cada despliegue o cambio importante. Hay una parte automática (sin BD) y una parte manual (con BD).

## 1. Smoke automático (sin base de datos)

```powershell
npm install
npx playwright install chromium   # solo la primera vez
npm run smoke
```

Cubre: portada pública, contador de visitas, mapa, WhatsApp, 6 cards, manuales públicos, assets, login, rechazo CSRF en `POST /contacto` y `POST /login`, correo `mailto:` y página 404.

## 2. Smoke manual (con `DetallesTereDB` restaurada)

Usuario: `admin` / `Admin123!`. Marca ✅ o ❌ y guarda una captura en `Requerimientos/smoking-evidencias/`.

| # | Módulo | Paso | Resultado esperado |
|---|---|---|---|
| 1 | Acceso | Login `admin` | Entra a `/panel`, ve todos los módulos |
| 2 | Acceso | Login `empleado` | No ve Ingresos, Términos, Reportes ni Usuarios |
| 3 | Seguridad | POST sin `csrfToken` | 403 «Token de seguridad inválido o ausente» |
| 4 | Caja | Abrir caja con S/ 100 | Queda una caja abierta; abrir otra falla |
| 5 | Caja | Vender en efectivo y cerrar con monto real distinto | `DIFERENCIA = MONTO_REAL - MONTO_FINAL` |
| 6 | Venta | Venta Yape | Se registra `TIPO_PAGO='YAPE'` y descuenta stock |
| 7 | Venta | Venta en efectivo con vuelto | En caja entra el TOTAL, el vuelto no se contabiliza |
| 8 | Cotización | Crear cotización | `FECHA_VENCIMIENTO = FECHA_CREACION + 24 h` |
| 9 | Cotización | Convertir cotización vigente en venta | Pasa a `ESTADO='VENDIDO'` |
| 10 | Búsqueda | Buscar «Vela Volcán» y «Vela Chispa» | Encuentra el mismo ítem si ambas son sinónimos |
| 11 | Inventario | Alta de producto por paquete | Guarda `UNIDAD_MEDIDA` y `STOCK_MIN` |
| 12 | Inventario | Ajuste con motivo | Se crea `DETALLE_AJUSTE` y `MOVIMIENTO_INVENTARIO` |
| 13 | Ingresos | Admin registra proveedor + precio de compra | Empleado no ve el precio de compra |
| 14 | Pedidos | Adelanto menor al 50 % | El sistema lo rechaza |
| 15 | Pedidos | Adelanto 60 %, nombre de recoge opcional | Plazos: pago 1 mes, recojo 2 meses, canje 3 meses |
| 16 | Pedidos | Marcar `HECHO` e intentar cancelar | No se puede cancelar (punto de no retorno) |
| 17 | Reportes | Reporte diario y mensual | Muestra ventas, ingresos, costo y margen |
| 18 | Reportes | Descargar CSV | Archivo `reporte-detallestere-*.csv` con BOM UTF-8 |
| 19 | Manuales | Abrir Manual del usuario desde el menú | Carga dentro del sistema |
| 20 | Manuales | Abrir ambos manuales desde la barra superior de la portada | Cargan sin iniciar sesión |
| 21 | Responsive | 360 px, 768 px, 1366 px | Sin desbordamiento horizontal |
| 22 | Sesión | Logout y expiración | No permite volver a `/panel` sin login |
| 23 | Paginación | Listados con más de 10 filas (Inventario, Usuarios, Pedidos, Términos, Ingresos, Caja, Reportes y Venta) | Aparecen Anterior/Siguiente, números de página y selector «Por página» |
| 24 | Paginación | Navegar de página y cambiar a 25/50/100 | Conserva `q` y filtros; el total no cambia |
| 25 | Inventario | Crear categoría en «Categorías» y repetir el nombre | La nueva aparece en el formulario de artículos; el duplicado muestra error |

## 3. Criterio de aceptación

Todos los casos ✅. Si alguno falla, registrar captura, paso exacto y error en `Requerimientos/smoking-evidencias/resultado.md` antes de corregir.
