-- DetallesTereTS — seed base (equivalente a 02_seed SQL Server).
-- Usuario admin inicial: admin / Admin123! (hash BCrypt cost 10).
INSERT INTO dbo.USUARIO (USERNAME, NOMBRE, APELLIDO, CORREO, CONTRASENA, ROL, ESTADO)
SELECT 'admin', 'Admin', 'DetallesTere', 'admin@detallestere.local',
       '$2b$10$oKYeKvECiPs0qWM1Ap1A9eboNLPeDxlFQcvxRE.J.VJp/J/vUAdy2',
       'admin', 1
WHERE NOT EXISTS (SELECT 1 FROM dbo.USUARIO WHERE USERNAME = 'admin');

INSERT INTO dbo.CATEGORIA (NOMBRE, DESCRIPCION)
SELECT 'Sin categoría', 'Categoría por defecto'
WHERE NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE NOMBRE = 'Sin categoría');
