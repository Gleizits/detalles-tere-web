# Hosting y despliegue — DetallesTereTS

**Decisión final (2026-11-25):** `Vercel Hobby` para la app + `Azure SQL Database free tier` para la BD + `Power BI Web` del universitario.

> La PC ya no queda encendida: la app se ejecuta en Vercel y la BD en Azure. Power BI Web conecta directo a Azure SQL, sin gateway local.
>
> **Estado real:** no existe todavía cuenta Azure/Vercel ni URL pública. Esta guía está escrita **desde cero**, suponiendo que no tienes ninguna cuenta. Yo no puedo crearlas por ti; sí dejé los scripts y la configuración listos.

---

## 0. Antes de empezar

Datos reales que ya están en el código (no inventar otros):

| Dato | Valor |
|---|---|
| Correo del formulario | `Geissel.Espino@upsjb.edu.pe` |
| WhatsApp | `+51 932 677 948` → `https://wa.me/51932677948?text=...` |
| Usuario demo | `admin` / `Admin123!` |
| Zona horaria | `America/Lima` |

Reglas:

- No usar `sa` ni el administrador del servidor desde la app.
- No subir contraseñas al repositorio: los scripts de usuarios traen marcadores `<...>`.
- Vercel Hobby es gratuito pero **solo para uso personal/académico**, no comercial.
- El tier gratuito de Azure SQL **no permite restaurar un `.bak`**: recreamos la base con scripts o importamos un `.bacpac`.
- El backup local `D:\DetallesTereDB.bak` (1 019 904 bytes, 23/11/2026) queda como respaldo; no se usa en Azure free tier.

---

## 1. Crear la cuenta Azure (si no tienes)

1. Entra a <https://azure.microsoft.com/free/> y elige **Crear cuenta gratuita / Start free**.
2. Usa una cuenta Microsoft (hotmail/outlook/live) o crea una nueva.
3. Azure pedirá verificación por teléfono y, normalmente, una tarjeta para identidad. **No cambies a pago**: revisa siempre que el *Estimated cost/month* sea `$0`.
4. Al terminar, abre <https://portal.azure.com> → **Suscripciones** y confirma que aparece una suscripción tipo **Azure Free Trial** o **Pay-As-You-Go**.
   - **Ojo:** si tu suscripción es **Azure for Students Starter**, el free tier de Azure SQL no aplica. Usa una cuenta Azure Free normal.

No necesitas comprar nada ni instalar nada global para crear la BD; se hace desde el portal.

---

## 2. Crear la base de datos gratuita

1. Portal → **Crear un recurso → SQL Database / SQL Database → Create**.
2. En el recuadro de precios elige **Start free / Apply free offer**. Verifica que el resumen de coste diga **$0/mes**.
3. Configura:

| Campo | Valor |
|---|---|
| Resource group | `rg-detallestere` (nuevo) |
| Database name | `DetallesTereDB` |
| Server | nuevo, p. ej. `dt-tere-sql.database.windows.net` |
| Server admin login | el que tú elijas (guárdalo; no lo usa la app) |
| Server admin password | contraseña fuerte (guárdala) |
| Region | la más cercana disponible (p. ej. Brazil South / East US) |

4. En **Networking** del servidor:

- **Allow Azure services and resources to access this server** = **Yes**.
- Firewall rule: nombre `AllowDemoPublic`, IP inicial `0.0.0.0`, IP final `255.255.255.255`.

> Esa regla abierta es necesaria porque Vercel Hobby no da IP de salida fija y Power BI Web sale de rangos administrados por Microsoft. Para producción se restringiría o se pagarían Static IPs de Vercel.

5. En **Compute + storage** confirma:

- General Purpose **serverless**, nivel gratuito.
- **Behavior when free limit reached** = **Auto-pause the database until next month** (NO “Continue using for additional charges”).
- Hasta 4 vCores / 32 GB.

---

## 3. Cargar esquema, datos y vistas

### Opción A — desde el Query Editor del portal (recomendada, sin instalar nada)

1. Abre la BD `DetallesTereDB` → **Query editor (preview)** → entra con el administrador del servidor.
2. Si pide firewall, la regla `AllowDemoPublic` del paso 2 ya lo permite.
3. Ejecuta **en este orden**, pegando el contenido de cada archivo:

```text
DetallesTerePHP/db/01_schema_DetallesTereDB.sql
DetallesTerePHP/db/02_seed_DetallesTereDB.sql
DetallesTerePHP/db/03_seed_500_registros.sql
DetallesTerePHP/db/04_consultas_powerbi.sql
```

`01` comprueba `DB_ID('DetallesTereDB')`; como ya creaste la base desde el portal, no intentará crearla otra vez.

### Opción B — importar un `.bacpac` desde tu SQL Server local

Útil si quieres conservar exactamente los datos que ya tienes en tu PC. Requiere `dotnet` instalado; **no es `npm -g`**:

```powershell
# Instalación local del tool, sin -g
dotnet tool install --tool-path .tools microsoft.sqlpackage

# Exportar desde SQL Server local (ajusta la contraseña de tu sa local)
.\.tools\SqlPackage.exe /a:Export `
  /ssn:localhost /sdn:DetallesTereDB /su:sa /sp:"<clave-local>" `
  /tf:DetallesTereDB.bacpac

# Importar a Azure SQL
.\.tools\SqlPackage.exe /a:Import `
  /tsn:dt-tere-sql.database.windows.net /tdn:DetallesTereDB `
  /tu:<admin-del-servidor> /tp:"<clave-admin>" `
  /sf:DetallesTereDB.bacpac
```

Si el `.bacpac` ya incluye las seis vistas, **no** vuelvas a ejecutar `04`. Si no las incluye, ejecútalo después.

### Verificación de datos

```sql
SELECT 'USUARIO' AS tabla, COUNT(*) AS filas FROM dbo.USUARIO
UNION ALL SELECT 'INVENTARIO', COUNT(*) FROM dbo.INVENTARIO
UNION ALL SELECT 'CATEGORIA', COUNT(*) FROM dbo.CATEGORIA;

SELECT name FROM sys.views WHERE name LIKE 'vw_PBI_%' ORDER BY name;
```

Esperado: ~1000 / ~3000 / ~1001 y 6 vistas.

---

## 4. Crear usuarios SQL para la app y Power BI

1. Abre los dos archivos nuevos y reemplaza los marcadores:

- `DetallesTerePHP/db/05_usuarios_cloud_master.sql` → `<PASSWORD_DT_APP>`
- `DetallesTerePHP/db/06_usuarios_cloud_detallestere.sql` → `<PASSWORD_PBI_READER>`

> Usa contraseñas distintas, largas y aleatorias. No las subas al repo.

2. Ejecuta `05_usuarios_cloud_master.sql` conectado a la base **`master`**.
3. Ejecuta `06_usuarios_cloud_detallestere.sql` conectado a **`DetallesTereDB`**.

Permisos resultantes:

| Usuario | Uso | Permisos |
|---|---|---|
| `dt_app` | Vercel / app | `db_datareader` + `db_datawriter` |
| `pbi_reader` | Power BI Web | `db_datareader` |

---

## 5. Publicar la app en Vercel (cuando ya tengas cuenta)

1. <https://vercel.com/signup> → conectar cuenta GitHub.
2. **Add New → Project** → importar este repositorio, rama `DB`.
3. Framework: **Express**. Build: `npm run build`. Node: 20.
4. Environment variables:

```text
DB_SERVER=dt-tere-sql.database.windows.net
DB_PORT=1433
DB_NAME=DetallesTereDB
DB_USER=dt_app
DB_PASSWORD=<PASSWORD_DT_APP>
DB_ENCRYPT=true
DB_TRUST_CERT=false
DB_TIMEOUT=10
SESSION_SECRET=<cadena-larga-aleatoria>
SESSION_MINUTES=30
COOKIE_SECURE=true
APP_PRODUCTION=true
APP_TIMEZONE=America/Lima
BASE_URL=https://<proyecto>.vercel.app
CONTACT_EMAIL=Geissel.Espino@upsjb.edu.pe
WHATSAPP_NUMBER=51932677948
```

`FACEBOOK_URL`, `INSTAGRAM_URL` y `TIKTOK_URL` se dejan vacíos si no hay enlaces reales (así no aparecen links inventados).

---

## 6. Power BI Web universitario

1. **Obtener datos → SQL Server / Azure SQL Database**.
2. Servidor: `dt-tere-sql.database.windows.net`; base: `DetallesTereDB`.
3. Autenticación: **SQL Server**, usuario `pbi_reader`.
4. Seleccionar únicamente las seis vistas `dbo.vw_PBI_*` de `Requerimientos/powerbi/README.md`.
5. Publicar al workspace, guardar credenciales y configurar **refresh programado**.
6. No instalar gateway: si el portal pide uno, significa que la conexión apunta a `localhost` en vez del FQDN de Azure.

---

## 7. Verificación final

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

Vigilar en Azure: **Metrics → Free amount remaining**. Cerrar Query Editor/SSMS cuando termines; las conexiones abiertas impiden el auto-pause.

---

## 8. Evidencia pendiente de deploy

| Dato | Valor |
|---|---|
| Proveedor app | Vercel Hobby |
| Rama desplegada | `DB` |
| URL pública | **Pendiente** |
| Servidor Azure SQL | **Pendiente** |
| Base cargada | **Pendiente** |
| Fecha de deploy | **Pendiente** |
| Capturas de portada/login | **Pendiente** |
| Refresh de Power BI Web | **Pendiente** |

---

## 9. Nota sobre el `.bak` local

El backup local sigue en `D:\DetallesTereDB.bak`. Sirve para recuperar la BD en SQL Server local, pero **no** para el free tier de Azure SQL. Para llevarlo a la nube, usa la Opción B (`.bacpac`) o los scripts `01`→`04`.

Si en el futuro se contrata un **Azure SQL Managed Instance**, ahí sí se podría restaurar el `.bak` desde Blob Storage; pero esa oferta gratuita dura 12 meses y no permite acceso 24/7 por su cupo de vCore-horas.
