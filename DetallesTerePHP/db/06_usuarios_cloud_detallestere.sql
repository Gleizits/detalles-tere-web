-- ============================================================
-- DetallesTereDB — usuarios y permisos para despliegue cloud
-- PARTE 2/2: ejecutar conectado a la base "DetallesTereDB".
-- ============================================================
-- Ejecutar después de 05_usuarios_cloud_master.sql y después de
-- haber cargado el esquema/datos (01 → 02 → 03 → 04 o BACPAC).
-- ============================================================

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'dt_app')
    CREATE USER dt_app FROM LOGIN dt_app;
GO

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'pbi_reader')
    CREATE USER pbi_reader FROM LOGIN pbi_reader;
GO

-- La app lee y escribe; no necesita db_owner.
IF NOT EXISTS (
    SELECT 1
    FROM sys.database_role_members drm
    JOIN sys.database_principals rol ON rol.principal_id = drm.role_principal_id
    JOIN sys.database_principals usr ON usr.principal_id = drm.member_principal_id
    WHERE rol.name = N'db_datareader' AND usr.name = N'dt_app'
)
    ALTER ROLE db_datareader ADD MEMBER dt_app;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.database_role_members drm
    JOIN sys.database_principals rol ON rol.principal_id = drm.role_principal_id
    JOIN sys.database_principals usr ON usr.principal_id = drm.member_principal_id
    WHERE rol.name = N'db_datawriter' AND usr.name = N'dt_app'
)
    ALTER ROLE db_datawriter ADD MEMBER dt_app;
GO

-- Power BI solo lee.
IF NOT EXISTS (
    SELECT 1
    FROM sys.database_role_members drm
    JOIN sys.database_principals rol ON rol.principal_id = drm.role_principal_id
    JOIN sys.database_principals usr ON usr.principal_id = drm.member_principal_id
    WHERE rol.name = N'db_datareader' AND usr.name = N'pbi_reader'
)
    ALTER ROLE db_datareader ADD MEMBER pbi_reader;
GO
