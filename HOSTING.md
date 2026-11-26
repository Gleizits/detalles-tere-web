# Hosting y despliegue — DetallesTereTS

**Decisión final (2026-11-25):** `Vercel Hobby` para la app + `Neon Free (PostgreSQL)` para la BD + `Power BI Web` pendiente de configurar.

> La PC ya no queda encendida: la app se ejecuta en Vercel y la BD en Neon. Neon escala a cero y se despierta solo cuando hay conexiones.
>
> La cuenta **Azure for Students** que se creó durante la guía anterior **queda sin uso** para este proyecto.
>
> **Estado real:** el proyecto Neon ya existe; la URL pública de Vercel y el refresh de Power BI Web están pendientes. No compartas contraseñas ni la connection string completa en el chat: solo deben ir en variables de entorno.

---

## 0. Datos reales que ya están en el código

| Dato | Valor |
|---|---|
| Correo del formulario | `Geissel.Espino@upsjb.edu.pe` |
| WhatsApp | `+51 932 677 948` → `https://wa.me/51932677948?text=...` |
| Usuario demo | `admin` / `Admin123!` |
| Zona horaria | `America/Lima` |
| Base lógica | `DetallesTereDB` |
| Esquema lógico | `dbo` (se conserva también en PostgreSQL) |

Reglas:

- No usar el rol owner de Neon desde la app; usar `dt_app`.
- No subir contraseñas al repositorio: el script de roles trae marcadores `<...>`.
- Vercel Hobby es gratuito para uso personal/académico, no comercial.
- Neon Free es `$0/mes`; no cambiar a plan pago.
- El backup local `D:\DetallesTereDB.bak` **no es portable** a PostgreSQL. La migración se hace con los scripts de `DetallesTerePHP/db/pg/`.

---

## 1. Proyecto Neon ya creado

| Dato | Valor |
|---|---|
| Proyecto | `detalles-tere` |
| Project ID | `gentle-flower-15646036` |
| Rama | `production` (`br-dark-poetry-b64n7x0t`), default |
| Región | AWS South America East 1 (São Paulo) |
| Plan | Free |
| PostgreSQL | 18 |
| Bases | 1 |
| Computes | 0 |
| Expira | Never |

> **0 computes es normal**: Neon Free usa scale-to-zero. El compute se inicia automáticamente al conectar y se apaga tras ~5 minutos de inactividad.

Si la base inicial se llama `neondb` y aún no existe `DetallesTereDB`:

1. Abre el branch `production` → **SQL Editor**.
2. Conéctate a la base inicial `neondb`.
3. Pega y ejecuta `DetallesTerePHP/db/pg/00_create_database.sql`.
4. En el selector de base del SQL Editor cambia a `DetallesTereDB`.
5. Ejecuta ahí el resto de scripts.

Para migraciones externas (`psql`) usa la **URL directa** (sin `-pooler`). Para la app en Vercel usa la **URL pooled** (hostname con `-pooler`). Ambas se copian desde la sección **Connection Details** de Neon; no escribas la contraseña en el repo ni en el chat.

Si prefieres `psql` en lugar del SQL Editor:

```powershell
$env:PGDATABASE_URL = 'postgresql://<owner>:<password>@<host-directo>/DetallesTereDB?sslmode=require'
psql $env:PGDATABASE_URL -f DetallesTerePHP/db/pg/01_schema_DetallesTereDB.sql
psql $env:PGDATABASE_URL -f DetallesTerePHP/db/pg/02_seed_DetallesTereDB.sql
psql $env:PGDATABASE_URL -f DetallesTerePHP/db/pg/03_seed_500_registros.sql
psql $env:PGDATABASE_URL -f DetallesTerePHP/db/pg/04_consultas_powerbi.sql
# Editar 05 con contraseñas reales antes de ejecutarlo:
psql $env:PGDATABASE_URL -f DetallesTerePHP/db/pg/05_roles_y_permisos.sql
```

---

## 2. Cargar esquema, datos y vistas

En el SQL Editor de Neon, conectado a `DetallesTereDB`, ejecuta **en este orden**:

```text
DetallesTerePHP/db/pg/01_schema_DetallesTereDB.sql
DetallesTerePHP/db/pg/02_seed_DetallesTereDB.sql
DetallesTerePHP/db/pg/03_seed_500_registros.sql
DetallesTerePHP/db/pg/04_consultas_powerbi.sql
DetallesTerePHP/db/pg/05_roles_y_permisos.sql   ← editar contraseñas antes
```

- `01` crea la extensión `citext`, el esquema `dbo`, la función `dbo.utcnow()` y las 22 tablas con `IDENTITY`.
- `02` crea el admin y una categoría inicial.
- `03` completa hasta **500 filas por tabla** de forma transaccional e idempotente.
- `04` crea las seis vistas `dbo.vw_PBI_*`.
- `05` crea los roles `dt_app` y `pbi_reader` con permisos mínimos.

### Verificación de datos

```sql
SELECT 'USUARIO' AS tabla, COUNT(*) AS filas FROM dbo.USUARIO
UNION ALL SELECT 'INVENTARIO', COUNT(*) FROM dbo.INVENTARIO
UNION ALL SELECT 'CATEGORIA', COUNT(*) FROM dbo.CATEGORIA
ORDER BY tabla;

SELECT table_name
FROM information_schema.views
WHERE table_schema = 'dbo' AND table_name LIKE 'vw_PBI_%'
ORDER BY table_name;
```

Esperado: **500 / 500 / 500** y **6 vistas**.

Para comprobar las seis vistas con sus nombres exactos (están citados):

```sql
SELECT 'vw_PBI_Ventas_Detalle' AS vista,
       (SELECT COUNT(*) FROM dbo."vw_PBI_Ventas_Detalle") AS filas
UNION ALL SELECT 'vw_PBI_Ingresos_Detalle',
       (SELECT COUNT(*) FROM dbo."vw_PBI_Ingresos_Detalle")
UNION ALL SELECT 'vw_PBI_Pedidos_Saldo',
       (SELECT COUNT(*) FROM dbo."vw_PBI_Pedidos_Saldo")
UNION ALL SELECT 'vw_PBI_Cotizaciones_24h',
       (SELECT COUNT(*) FROM dbo."vw_PBI_Cotizaciones_24h")
UNION ALL SELECT 'vw_PBI_Movimientos_Inventario',
       (SELECT COUNT(*) FROM dbo."vw_PBI_Movimientos_Inventario")
UNION ALL SELECT 'vw_PBI_Sinonimos_Ventas',
       (SELECT COUNT(*) FROM dbo."vw_PBI_Sinonimos_Ventas")
ORDER BY vista;
```

---

## 3. Roles y cadenas de conexión

| Rol | Uso | Permisos | Cadena |
|---|---|---|---|
| owner del proyecto | migraciones / SQL Editor | propietario de `dbo` | URL directa, sin `-pooler` |
| `dt_app` | Vercel / app | SELECT, INSERT, UPDATE, DELETE + USAGE en secuencias + EXECUTE en `dbo.utcnow()` | URL pooled, con `-pooler` |
| `pbi_reader` | Power BI Web | solo SELECT | URL directa, sin `-pooler` |

La app usa `pg` con un pool pequeño por instancia serverless. La URL pooled de Neon es la que debe configurarse en Vercel; la directa se reserva para cargar scripts y para Power BI.

---

## 4. Publicar la app en Vercel

1. <https://vercel.com/signup> → conectar cuenta GitHub.
2. **Add New → Project** → importar este repositorio, rama `DB`.
3. Framework: **Express**. Build: `npm run build`. Node: **20**.
4. Environment variables:

| Variable | Valor |
|---|---|
| `DATABASE_URL` | URL **pooled** de Neon para `dt_app` (con `-pooler`, `sslmode=require`) |
| `DB_TIMEOUT` | `10` |
| `SESSION_SECRET` | cadena larga y aleatoria |
| `SESSION_MINUTES` | `30` |
| `COOKIE_SECURE` | `true` |
| `APP_PRODUCTION` | `true` |
| `APP_TIMEZONE` | `America/Lima` |
| `BASE_URL` | `https://<proyecto>.vercel.app` |
| `CONTACT_EMAIL` | `Geissel.Espino@upsjb.edu.pe` |
| `WHATSAPP_NUMBER` | `51932677948` |
| `MAPS_EMBED_SRC` | `https://www.google.com/maps?q=Ica%2C%20Peru&output=embed` |
| `FACEBOOK_URL` | vacío si no hay enlace real |
| `INSTAGRAM_URL` | vacío si no hay enlace real |
| `TIKTOK_URL` | vacío si no hay enlace real |

> Con `APP_PRODUCTION=true`, la app exige `DATABASE_URL` y `SESSION_SECRET`; si falta alguna, el arranque falla a propósito.

5. Deploy. Vercel sirve `public/assets/**` por CDN y usa `api/index.ts` como función serverless.

---

## 5. Power BI Web

**Estado: pendiente.** El tenant universitario puede o no permitir el conector PostgreSQL directo; eso se valida cuando se inicie Power BI Web.

Si el tenant permite **Obtener datos → PostgreSQL database**:

1. Servidor: host de la URL directa de Neon (sin `-pooler`).
2. Base: `DetallesTereDB`.
3. Autenticación: usuario `pbi_reader` y su contraseña.
4. Seleccionar solo las seis vistas `dbo."vw_PBI_*"` listadas en `Requerimientos/powerbi/README.md`.
5. Publicar al workspace y configurar refresh programado.
6. No se necesita gateway local ni PC encendida si el conector es nativo/Nube.

Si el bloquea el conector o el refresh directo, las alternativas sin PC encendida son:

- exponer un endpoint autenticado en Vercel que devuelva las seis vistas (no implementado todavía), o
- publicar el `.pbix` con datos importados como evidencia estática.

No inventes credenciales ni datos de contacto para completar esta sección.

---

## 6. Verificación final

En local, antes de publicar:

```powershell
npm run typecheck
npm run smoke
```

En la URL pública comprobar:

- `/` carga y el contador `dt_visitas` aumenta.
- `/login` entra con `admin` / `Admin123!`.
- POST sin `csrfToken` devuelve 403.
- `/manual-usuario` y `/manual-programador` son públicos y están en la barra superior.
- Paginación, WhatsApp y correo reales funcionan.

En Neon vigilar **Monitoring → Compute hours** del mes. Cerrar el SQL Editor cuando termines; dejar conexiones abiertas retrasa el scale-to-zero.

---

## 7. Evidencia pendiente de deploy

| Dato | Valor |
|---|---|
| Proveedor app | Vercel Hobby |
| Rama desplegada | `DB` |
| URL pública | **Pendiente** |
| Proyecto Neon | `detalles-tere` / `gentle-flower-15646036` |
| Base cargada | **Pendiente de verificar en Neon** |
| Roles creados | **Pendiente de verificar en Neon** |
| Fecha de deploy | **Pendiente** |
| Capturas de portada/login | **Pendiente** |
| Refresh de Power BI Web | **Pendiente** |

---

## 8. Nota sobre el `.bak` local

El backup `D:\DetallesTereDB.bak` sirve para recuperar SQL Server en la PC, pero **no** se puede restaurar en PostgreSQL/Neon. Para llevar los datos a la nube se usan los scripts `DetallesTerePHP/db/pg/01` → `05`.
