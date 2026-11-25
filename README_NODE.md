# DetallesTere — versión Node.js / TypeScript (rama `DB`)

Migración de la versión PHP a **TypeScript + Express**, pensada para desplegarse en **Vercel** sin cambiar la base de datos ni la interfaz:

- Misma base: **SQL Server / DetallesTereDB**.
- Mismas rutas, formularios, menús, estilos y scripts.
- Misma autenticación BCrypt (compatible con los hashes `$2a$` del seed).
- Sesión firmada en cookie (sin archivos ni estado en disco, apta para serverless).
- CSRF, cabeceras de seguridad y permisos de administrador conservados.

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
db/ (en DetallesTerePHP/db/) Esquema y seed originales
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

4. Abre `http://localhost:3000/login`. Usuario del seed: `admin` / `Admin123!`.

Verificación de tipos:

```powershell
npm run typecheck
```

## Despliegue en Vercel

1. Crea el proyecto apuntando a este repositorio y selecciona la rama `DB`.
2. Vercel detecta el framework **Express** y usa `api/index.ts` como función.
3. Configura estas variables de entorno en el panel de Vercel:

   | Variable | Valor recomendado |
   |---|---|
   | `DB_SERVER` | Host público de tu SQL Server |
   | `DB_PORT` | `1433` |
   | `DB_NAME` | `DetallesTereDB` |
   | `DB_USER` | Usuario SQL con permisos de lectura/escritura |
   | `DB_PASSWORD` | Contraseña segura (no versionar) |
   | `DB_TRUST_CERT` | `true` si el certificado es autofirmado |
   | `DB_ENCRYPT` | `true` si tu servidor exige cifrado |
   | `SESSION_SECRET` | Cadena larga y aleatoria (obligatoria) |
   | `APP_PRODUCTION` | `true` |
   | `COOKIE_SECURE` | `true` |
   | `SESSION_MINUTES` | `30` |
   | `APP_TIMEZONE` | `America/Lima` |

4. La base de datos **no** se crea en Vercel: debe estar accesible desde Internet con el firewall abierto a las IPs de salida de Vercel (o usar una IP estática / túnel / proxy).
5. Si la base es nueva, ejecuta primero los scripts `DetallesTerePHP/db/01_schema_DetallesTereDB.sql` y `02_seed_DetallesTereDB.sql`.

### Notas serverless

- Vercel sirve `public/assets/**` directamente por CDN; `express.static()` solo se usa en local.
- La sesión viaja en una cookie firmada con HMAC, así que no depende de disco ni de memoria compartida.
- El límite de intentos de login es por instancia de función, igual que era por proceso en PHP.
- `mssql` mantiene un pool por instancia; en planes con escalado automático cada instancia tiene el suyo.

## Base de datos

Los scripts originales siguen en `DetallesTerePHP/db/`. No se han modificado: la migración es de aplicación, no de esquema.
