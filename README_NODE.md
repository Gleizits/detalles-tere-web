# DetallesTereTS — versión Node.js / TypeScript (rama `DB`)

Migración de la versión PHP a **TypeScript + Express**, desplegada en **Vercel** con base **PostgreSQL en Neon**:

- Base lógica: **DetallesTereDB**, esquema `dbo`, en **Neon Free**.
- Driver: **`pg`**; los DAO conservan marcadores `?` y se convierten a `$1..$n` en `src/db.ts`.
- Mismas rutas, formularios, menús, estilos y scripts.
- Misma autenticación BCrypt (hash `$2b$` del seed).
- Sesión firmada en cookie (sin archivos ni estado en disco, apta para serverless).
- CSRF, cabeceras de seguridad y permisos de administrador conservados.
- Portada pública responsive, manuales dentro del sistema y maqueta HTML/CSS.

## Estructura

```
api/index.ts          Entrada serverless de Vercel (exporta la app Express)
src/app.ts            Middlewares globales y manejo de errores
src/routes.ts         Mapa de rutas (idéntico a la versión PHP)
src/controllers/      Lógica de cada pantalla/formulario
src/dao/              Consultas SQL parametrizadas (pg)
src/models/           Entidades
src/views/            Plantillas EJS convertidas desde las vistas PHP
src/session.ts        Sesión firmada, CSRF y mensajes flash
src/view.ts           Renderizado con plantilla común
public/assets/        CSS, JS, fuentes e imágenes (Vercel los sirve por CDN)
DetallesTerePHP/      Versión PHP original, conservada como referencia
DetallesTerePHP/db/pg/ Esquema, seed, 500 registros, vistas y roles para Neon
maqueta/              Maqueta estática HTML + CSS de las pantallas principales
tests/smoke.spec.ts   Pruebas de humo automáticas (sin BD)
Requerimientos/       LRPD, SMOKING y guía de Power BI
HOSTING.md            Guía completa Neon + Vercel + Power BI Web
```

## Desarrollo local

1. Copia `.env.example` a `.env` y ajusta `DATABASE_URL` con la URL **pooled** de Neon (hostname con `-pooler`).
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
   | `DATABASE_URL` | URL **pooled** de Neon para `dt_app`, con `sslmode=require` |
   | `DB_TIMEOUT` | `10` |
   | `SESSION_SECRET` | Cadena larga y aleatoria (obligatoria) |
   | `APP_PRODUCTION` | `true` |
   | `COOKIE_SECURE` | `true` |
   | `SESSION_MINUTES` | `30` |
   | `APP_TIMEZONE` | `America/Lima` |
   | `BASE_URL` | `https://<proyecto>.vercel.app` |
   | `CONTACT_EMAIL` | `Geissel.Espino@upsjb.edu.pe` |
   | `WHATSAPP_NUMBER` | `51932677948` |

4. Para correo, WhatsApp, mapa y redes, completa **solo datos reales** según `.env.example`. Las variables vacías no muestran enlaces inventados. El botón de WhatsApp abre el chat con **+51 932 677 948** (`51932677948`) y el formulario abre el cliente de correo con `mailto:` a `Geissel.Espino@upsjb.edu.pe`: no usa ninguna API externa.
5. La base de datos **no** se crea en Vercel. La guía completa está en [`HOSTING.md`](HOSTING.md).
6. Si la base es nueva, ejecuta en Neon `00_create_database.sql` (si hace falta), y después `01_schema_DetallesTereDB.sql`, `02_seed_DetallesTereDB.sql`, `03_seed_500_registros.sql`, `04_consultas_powerbi.sql` y `05_roles_y_permisos.sql` desde `DetallesTerePHP/db/pg/`.

### Notas serverless

- Vercel sirve `public/assets/**` directamente por CDN; `express.static()` solo se usa en local.
- La sesión viaja en una cookie firmada con HMAC, así que no depende de disco ni de memoria compartida.
- El límite de intentos de login es por instancia de función, igual que era por proceso en PHP.
- `pg` mantiene un pool pequeño por instancia (`max: 10`); usa siempre la URL pooled de Neon, nunca la directa.
- Las claves de fila se normalizan a MAYÚSCULAS en `src/db.ts`, porque los DAO vienen de SQL Server/PHP.

## PC4 — dónde está cada entrega

| Requisito | Ubicación |
|---|---|
| Portada “carita facherita” responsive, WhatsApp, ingredientes, logo, docente/curso/ciclo, carrusel, cards 3x2, visitas, mapas, correo y redes | `src/views/landing.ejs`, `public/assets/css/landing.css`, `public/assets/js/landing.js`, `src/controllers/landingController.ts` |
| LRPD en carpeta | `Requerimientos/LRPD/` |
| 500 registros por tabla de proceso | `DetallesTerePHP/db/pg/03_seed_500_registros.sql` |
| SMOKING | `tests/smoke.spec.ts`, `Requerimientos/SMOKING.md`, `npm run smoke` |
| PPT concisa | `Presentacion_DetallesTere.pptx` |
| 6 consultas con ≥ 5 tablas para Power BI | `DetallesTerePHP/db/pg/04_consultas_powerbi.sql`, `Requerimientos/powerbi/README.md` |
| Maqueta HTML/CSS acorde a la BD | `maqueta/` |
| Paginación en Inventario, Usuarios, Pedidos, Términos, Ingresos, Caja, Reportes y Venta | `src/pagination.ts`, `src/views/comunes/paginacion.ejs`, `public/assets/css/paginacion.css`, `public/assets/js/paginacion.js` |
| Crear categoría desde Inventario | `src/views/inventario.ejs`, `src/dao/categoriaDao.ts` |
| Manuales dentro del sistema, públicos y accesibles desde la barra superior de la portada | `/manual-usuario` y `/manual-programador` |
| Hosting | Vercel + Neon PostgreSQL; procedimiento y evidencia pendiente en `HOSTING.md` |

**Power BI no se inicia en esta entrega.** Solo quedan preparadas las seis vistas PostgreSQL y su mapa a visuales.
