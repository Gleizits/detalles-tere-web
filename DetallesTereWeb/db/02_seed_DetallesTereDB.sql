-- ============================================================
-- DetallesTereDB — Datos iniciales (seed)
-- Contraseña del admin inicial: 191224 (misma que Tomcat).
-- Hash BCrypt (cost 10). Cambiar tras primer login.
-- ============================================================

USE DetallesTereDB;
GO

-- Usuario administrador inicial (único)
IF NOT EXISTS (SELECT 1 FROM dbo.USUARIO WHERE USERNAME = 'admin')
BEGIN
    INSERT INTO dbo.USUARIO (USERNAME, NOMBRE, APELLIDO, CORREO, CONTRASENA, ROL, ESTADO)
    VALUES (
        'admin',
        'Admin',
        'DetallesTere',
        'admin@detallestere.local',
        '$2a$10$9BWWoqEZYccCVkiROZ7LNuVKGZBX5.iGbd36bwHug6w.90qZjxo2m',
        'admin',
        1
    );
END
GO

-- Categorías base (NOMBRE no es UNIQUE, admite repetidas)
IF NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE NOMBRE = 'Sin categoría')
BEGIN
    INSERT INTO dbo.CATEGORIA (NOMBRE, DESCRIPCION)
    VALUES ('Sin categoría', 'Categoría por defecto');
END
GO
