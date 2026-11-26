-- DetallesTereTS — roles PostgreSQL en Neon.
-- Ejecutar conectado a DetallesTereDB con el rol owner del proyecto,
-- después de 01 → 02 → 03 → 04.
--
-- IMPORTANTE: reemplaza <PASSWORD_DT_APP> y <PASSWORD_PBI_READER> por
-- contraseñas largas y aleatorias antes de ejecutar. No subas este archivo
-- con contraseñas reales al repositorio.

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'dt_app') THEN
        CREATE ROLE dt_app LOGIN PASSWORD '<PASSWORD_DT_APP>';
    ELSE
        ALTER ROLE dt_app WITH LOGIN PASSWORD '<PASSWORD_DT_APP>';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'pbi_reader') THEN
        CREATE ROLE pbi_reader LOGIN PASSWORD '<PASSWORD_PBI_READER>';
    ELSE
        ALTER ROLE pbi_reader WITH LOGIN PASSWORD '<PASSWORD_PBI_READER>';
    END IF;
END $$;

-- Ninguno de los dos roles necesita crear bases ni objetos.
ALTER ROLE dt_app NOSUPERUSER NOCREATEDB NOCREATEROLE;
ALTER ROLE pbi_reader NOSUPERUSER NOCREATEDB NOCREATEROLE;

GRANT CONNECT ON DATABASE "DetallesTereDB" TO dt_app, pbi_reader;
GRANT USAGE ON SCHEMA dbo TO dt_app, pbi_reader;

-- La app lee y escribe; no necesita ser owner.
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA dbo TO dt_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA dbo TO dt_app;
GRANT EXECUTE ON FUNCTION dbo.utcnow() TO dt_app;

-- Power BI solo lee las vistas y tablas.
GRANT SELECT ON ALL TABLES IN SCHEMA dbo TO pbi_reader;
GRANT EXECUTE ON FUNCTION dbo.utcnow() TO pbi_reader;

-- Si en el futuro se crean tablas/vistas nuevas, mantener los permisos.
ALTER DEFAULT PRIVILEGES IN SCHEMA dbo
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO dt_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA dbo
    GRANT USAGE, SELECT ON SEQUENCES TO dt_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA dbo
    GRANT SELECT ON TABLES TO pbi_reader;

-- Comprobación: ambos roles deben existir y tener LOGIN.
SELECT rolname, rolcanlogin, rolsuper
FROM pg_roles
WHERE rolname IN ('dt_app', 'pbi_reader')
ORDER BY rolname;
