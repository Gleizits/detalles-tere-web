# DetallesTereTS — versión Node.js / TypeScript (rama `DB`)

Migración de la versión PHP a **TypeScript + Express**, pensada para desplegarse en **Vercel** sin cambiar la base de datos ni la interfaz:

- Misma base: **SQL Server / DetallesTereDB**.
- Mismas rutas, formularios, menús, estilos y scripts.
- Misma autenticación BCrypt (compatible con los hashes `$2a$` del seed).
- Sesión firmada en cookie (sin archivos ni estado en disco, apta para serverless).
- CSRF, cabeceras de seguridad y permisos de administrador conservados.
- Portada pública responsive, manuales dentro del sistema y maqueta HTML/CSS.

## Estructura

```
api/index.ts          Entrada serverless de Vercel (exporta la app Express)
src/app.ts            Middlewares globales y manejo de errores
src/routes.ts         Mapa de rutas (idéntico a la versión PHP)
src/controllers/      Lógica de cada pantalla/formulario
src/dao/              Consultas SQL parametrizadas (mssql)
src/models/           Entidades
src/views/            Plantillas EJS convertidas desde las vistas PHP
src/session.ts        Sesión firmada, CSRF y mensajes flash
src/view.ts           Renderizado con plantilla común
public/assets/        CSS, JS, fuentes e imágenes (Vercel los sirve por CDN)
DetallesTerePHP/      Versión PHP original, conservada como referencia
DetallesTerePHP/db/   Esquema, seed, 500 registros y 6 vistas para Power BI
maqueta/              Maqueta estática HTML + CSS de las pantallas principales
tests/smoke.spec.ts   Pruebas de humo automáticas (sin BD)
Requerimientos/       LRPD, SMOKING y guía de Power BI
HOSTING.md            Restauración del .bak y publicación en Vercel/Azure SQL
```

## Desarrollo local

1. Copia `.env.example` a `.env` y ajusta `DB_SERVER`, `DB_USER` y `DB_PASSWORD`.
   También se usa automáticamente `DetallesTerePHP/.env` si no hay `.env` en la raíz.
2. Instala dependencias:

   ```powershell
   npm install
   ```

3. Arranca:

   ```powershell
   npm run dev
   ```

4. Abre `http://localhost:3000/`. Usuario del seed: `admin` / `Admin123!`.

Verificación:

```powershell
npm run typecheck   # o npm run build
npx playwright install chromium   # solo la primera vez
npm run smoke       # portada, CSRF, login, assets y responsive sin BD
```

## Despliegue en Vercel

1. Crea el proyecto apuntando a este repositorio y selecciona la rama `DB`.
2. Vercel detecta el framework **Express** y usa `api/index.ts` como función.
3. Configura las variables de entorno del panel de Vercel. Las mínimas son:

   | Variable | Valor recomendado |
   |---|---|
   | `DB_SERVER` | Host público de SQL Server / Azure SQL |
   | `DB_PORT` | `1433` |
   | `DB_NAME` | `DetallesTereDB` |
   | `DB_USER` | Usuario SQL con permisos de lectura/escritura |
   | `DB_PASSWORD` | Contraseña segura (no versionar) |
   | `DB_TRUST_CERT` | `false` en Azure SQL; `true` si certificado autofirmado |
   | `DB_ENCRYPT` | `true` en Azure SQL |
   | `SESSION_SECRET` | Cadena larga y aleatoria (obligatoria) |
   | `APP_PRODUCTION` | `true` |
   | `COOKIE_SECURE` | `true` |
   | `SESSION_MINUTES` | `30` |
   | `APP_TIMEZONE` | `America/Lima` |

4. Para correo, WhatsApp, mapa y redes, completa **solo datos reales** según `.env.example`. Las variables vacías no muestran enlaces inventados. El botón de WhatsApp abre el chat con **+51 932 677 948** (`51932677948`) y el formulario abre el cliente de correo con `mailto:` a `Geissel.Espino@upsjb.edu.pe`: no usa ninguna API externa.
5. La base de datos **no** se crea en Vercel. La guía completa `.bak → .bacpac → Azure SQL` está en [`HOSTING.md`](HOSTING.md).
6. Si la base es nueva, ejecuta `01_schema_DetallesTereDB.sql` y `02_seed_DetallesTereDB.sql`; después `03_seed_500_registros.sql` y `04_consultas_powerbi.sql`.

### Notas serverless

- Vercel sirve `public/assets/**` directamente por CDN; `express.static()` solo se usa en local.
- La sesión viaja en una cookie firmada con HMAC, así que no depende de disco ni de memoria compartida.
- El límite de intentos de login es por instancia de función, igual que era por proceso en PHP.
- `mssql` mantiene un pool por instancia; en planes con escalado automático cada instancia tiene el suyo.

## PC4 — dónde está cada entrega

| Requisito | Ubicación |
|---|---|
| Portada “carita facherita” responsive, WhatsApp, ingredientes, logo, docente/curso/ciclo, carrusel, cards 3x2, visitas, mapas, correo y redes | `src/views/landing.ejs`, `public/assets/css/landing.css`, `public/assets/js/landing.js`, `src/controllers/landingController.ts` |
| LRPD en carpeta | `Requerimientos/LRPD/` |
| 500 registros por tabla de proceso | `DetallesTerePHP/db/03_seed_500_registros.sql` |
| SMOKING | `tests/smoke.spec.ts`, `Requerimientos/SMOKING.md`, `npm run smoke` |
| PPT concisa | `Presentacion_DetallesTere.pptx` |
| 6 consultas con ≥ 5 tablas para Power BI | `DetallesTerePHP/db/04_consultas_powerbi.sql`, `Requerimientos/powerbi/README.md` |
| Maqueta HTML/CSS acorde a la BD | `maqueta/` |
| Paginación en Inventario, Usuarios, Pedidos, Términos, Ingresos, Caja, Reportes y Venta | `src/pagination.ts`, `src/views/comunes/paginacion.ejs`, `public/assets/css/paginacion.css`, `public/assets/js/paginacion.js` |
| Crear categoría desde Inventario | `src/views/inventario.ejs`, `src/dao/categoriaDao.ts` |
| Manuales dentro del sistema, públicos y accesibles desde la barra superior de la portada | `/manual-usuario` y `/manual-programador` |
| Hosting | Vercel + Azure SQL; procedimiento y evidencia pendiente en `HOSTING.md` |

**Power BI no se inicia en esta entrega.** Solo quedan preparadas las seis vistas SQL y su mapa a visuales.
