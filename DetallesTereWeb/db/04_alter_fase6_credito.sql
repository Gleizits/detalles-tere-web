-- DetallesTereDB — Migración Fase 6: canje de crédito de cliente
-- Ejecutar una sola vez después de crear la tabla VENTA.

IF COL_LENGTH('dbo.CREDITO_CLIENTE', 'ID_VENTA') IS NULL
BEGIN
    ALTER TABLE dbo.CREDITO_CLIENTE
        ADD ID_VENTA INT NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_CREDITO_VENTA'
)
BEGIN
    ALTER TABLE dbo.CREDITO_CLIENTE
        ADD CONSTRAINT FK_CREDITO_VENTA
        FOREIGN KEY (ID_VENTA) REFERENCES dbo.VENTA(ID_VENTA);
END;
GO
