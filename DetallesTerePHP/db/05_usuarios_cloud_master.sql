-- ============================================================
-- DetallesTereDB — logins para despliegue en Azure SQL Database
-- PARTE 1/2: ejecutar conectado a la base "master" del servidor lógico.
-- ============================================================
-- IMPORTANTE:
--   1. Reemplaza <PASSWORD_DT_APP> y <PASSWORD_PBI_READER> por contraseñas
--      largas y aleatorias antes de ejecutar.
--   2. No subas este archivo con contraseñas reales al repositorio.
--   3. No uses "sa" ni el administrador del servidor desde la aplicación.
-- ============================================================

IF NOT EXISTS (SELECT 1 FROM sys.sql_logins WHERE name = N'dt_app')
    CREATE LOGIN dt_app WITH PASSWORD = N'<PASSWORD_DT_APP>';
GO

IF NOT EXISTS (SELECT 1 FROM sys.sql_logins WHERE name = N'pbi_reader')
    CREATE LOGIN pbi_reader WITH PASSWORD = N'<PASSWORD_PBI_READER>';
GO
