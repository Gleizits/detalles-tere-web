# Hosting y despliegue — DetallesTereTS

**Estado actual:** el código está preparado para Vercel, pero **no hay URL pública confirmada** ni base de datos en la nube. La base original sigue siendo SQL Server `DetallesTereDB`; no se cambia de motor.

## 1. Decisión de hosting

| Opción | Resultado de la evaluación |
|---|---|
| **Vercel + Azure SQL Database** | **Recomendada.** Vercel ejecuta el handler `api/index.ts`; Azure SQL conserva SQL Server y ofrece nivel gratuito. |
| Railway Free para SQL Server | Descartada: 512 MB de RAM no son suficientes para SQL Server. |
| OCI Always Free ARM | Descartada: SQL Server para Linux no soporta ARM en esa instancia. |
| Render (`render.yaml`) | Alternativa histórica; el despliegue principal de esta rama es Vercel. Si se usa Render, debe apuntar a la rama `DB`. |

## 2. Base de datos: del `.bak` a Azure SQL

Backup local identificado:

```text
D:\DetallesTereDB.bak
1 019 904 bytes
23/11/2026 23:26:49
```

La restauración/exportación requiere una sesión con privilegios de administrador en Windows; en este entorno `Start-Service MSSQLSERVER` y `sqlservr.exe -c` fallaron con acceso denegado (`error 5`). Por eso los pasos siguientes quedan documentados, **no ejecutados**.

### 2.1 Restaurar localmente

```powershell
# Ver los nombres lógicos de archivos del backup
& "C:\Program Files\Microsoft SQL Server\Client SDK\ODBC\170\Tools\Binn\SQLCMD.EXE" `
  -S localhost,1433 -U sa -P "<password>" `
  -Q "RESTORE FILELISTONLY FROM DISK = 'D:\DetallesTereDB.bak';"

# Restaurar (sustituye los nombres lógicos por los del resultado anterior)
& "C:\Program Files\Microsoft SQL Server\Client SDK\ODBC\170\Tools\Binn\SQLCMD.EXE" `
  -S localhost,1433 -U sa -P "<password>" `
  -Q "RESTORE DATABASE DetallesTereDB FROM DISK = 'D:\DetallesTereDB.bak' WITH REPLACE,
      MOVE '<logico_mdf>' TO 'C:\MSSQL\DATA\DetallesTereDB.mdf',
      MOVE '<logico_ldf>' TO 'C:\MSSQL\DATA\DetallesTereDB.ldf';"
```

### 2.2 Completar los scripts del PC4

Con la base restaurada, ejecutar en este orden:

```text
DetallesTerePHP/db/01_schema_DetallesTereDB.sql   (si se reconstruye desde cero)
DetallesTerePHP/db/02_seed_DetallesTereDB.sql     (usuario admin / Admin123!)
DetallesTerePHP/db/03_seed_500_registros.sql      (500 filas por tabla de proceso)
DetallesTerePHP/db/04_consultas_powerbi.sql       (6 vistas de Power BI)
```

Si se restaura el `.bak` original, **no** volver a ejecutar `01`/`02`; solo `03` y `04`.

### 2.3 Exportar a `.bacpac`

Instalar SqlPackage en un equipo con permisos y exportar:

```powershell
dotnet tool install -g microsoft.sqlpackage

SqlPackage /a:Export `
  /ssn:localhost /sdn:DetallesTereDB /su:sa /sp:"<password>" `
  /tf:D:\DetallesTereDB.bacpac
```

### 2.4 Importar a Azure SQL Database

1. Crear un servidor Azure SQL y una base `DetallesTereDB` en el nivel gratuito.
2. Configurar el firewall. Vercel no garantiza IPs de salida fijas: para una demo se puede abrir `0.0.0.0`–`255.255.255.255`; para producción usar IP estática, túnel o proxy y restringir el acceso.
3. Importar:

```powershell
SqlPackage /a:Import `
  /tsn:<servidor>.database.windows.net /tdn:DetallesTereDB `
  /tu:<admin> /tp:"<password>" `
  /sf:D:\DetallesTereDB.bacpac
```

## 3. Variables en Vercel

Proyecto: repositorio actual, **rama `DB`**. Vercel detecta Express y usa `api/index.ts` con el rewrite de `vercel.json`.

| Variable | Valor para Azure SQL / Vercel |
|---|---|
| `DB_SERVER` | `<servidor>.database.windows.net` |
| `DB_PORT` | `1433` |
| `DB_NAME` | `DetallesTereDB` |
| `DB_USER` | Usuario SQL de Azure |
| `DB_PASSWORD` | Contraseña segura (nunca versionar) |
| `DB_TRUST_CERT` | `false` |
| `DB_ENCRYPT` | `true` |
| `DB_TIMEOUT` | `10` |
| `SESSION_SECRET` | Cadena larga y aleatoria; obligatoria con `APP_PRODUCTION=true` |
| `SESSION_MINUTES` | `30` |
| `COOKIE_SECURE` | `true` |
| `APP_PRODUCTION` | `true` |
| `APP_TIMEZONE` | `America/Lima` |
| `BASE_URL` | URL pública de Vercel (opcional) |
| `CONTACT_EMAIL` | `Geissel.Espino@upsjb.edu.pe` (destino del formulario); no se usa API externa |
| `WHATSAPP_NUMBER` | `51932677948` (+51 932 677 948); el botón abre el chat con ese número |
| `MAPS_EMBED_SRC` | iframe real; el valor por defecto muestra Ica sin dirección inventada |
| `FACEBOOK_URL`, `INSTAGRAM_URL`, `TIKTOK_URL` | Enlaces reales; vacíos = no aparecen |

## 4. Verificación después de publicar

```powershell
npm run typecheck
npm run build
npm run smoke
```

En la URL pública comprobar:

- `/` carga la portada y el contador `dt_visitas` aumenta al recargar.
- `/login` permite entrar con `admin` / `Admin123!`.
- Los POST sin `csrfToken` devuelven 403.
- `/manual-usuario` y `/manual-programador` son públicos y se abren desde la parte superior de `/`.
- `/assets/css/landing.css`, `/assets/js/landing.js` y `/assets/img/logo-upsjb.png` responden 200.

## 5. Evidencia pendiente de deploy

| Dato | Valor |
|---|---|
| Proveedor | Vercel |
| Rama desplegada | `DB` |
| URL pública | **Pendiente** |
| Base de datos | **Pendiente** de importar a Azure SQL |
| Fecha de deploy | **Pendiente** |
| Captura de portada/login | **Pendiente** |
| Resultado de `npm run smoke` sobre la URL | **Pendiente** |

No publicar la portada con correo, WhatsApp, dirección o redes sociales inventados: completar primero las variables de la sección 3.
