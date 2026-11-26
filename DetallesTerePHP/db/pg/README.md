# Scripts PostgreSQL / Neon

Orden de ejecución en el SQL Editor de Neon, conectado a `DetallesTereDB`:

| Archivo | Qué hace |
|---|---|
| `00_create_database.sql` | Crea `DetallesTereDB` si la base inicial del branch todavía es `neondb`. Ejecutar en `neondb`; omite si ya existe. |
| `01_schema_DetallesTereDB.sql` | Extensión `citext`, esquema `dbo`, función `dbo.utcnow()` y 22 tablas. |
| `02_seed_DetallesTereDB.sql` | Usuario `admin` / `Admin123!` y categoría inicial. |
| `03_seed_500_registros.sql` | Completa hasta 500 filas por tabla de proceso; idempotente y transaccional. |
| `04_consultas_powerbi.sql` | Seis vistas `dbo."vw_PBI_*"` para Power BI. |
| `05_roles_y_permisos.sql` | Roles `dt_app` y `pbi_reader`. **Reemplazar los marcadores de contraseña antes de ejecutar.** |

Notas:

- Los nombres de vista y columnas van citados para conservar la grafía del SQL Server original.
- Usa la URL directa de Neon (sin `-pooler`) para estos scripts; la app en Vercel usa la URL pooled (con `-pooler`).
- No subas contraseñas reales ni la connection string completa al repositorio.
- La guía paso a paso está en `../../HOSTING.md`.
