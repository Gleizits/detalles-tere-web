-- ============================================================
-- DetallesTereDB — Migración Fase 1: CRUD de Inventario
-- 1) Baja lógica (ESTADO) en PRODUCTO / MATERIAL / ARREGLO:
--    nunca se borra un artículo para no perder el histórico
--    de ventas (3FN: los hechos se conservan).
-- 2) Enlace FK PRODUCTO -> MATERIAL para la doble naturaleza
--    de artículos (ej.: limpiapipas es producto y material).
-- ============================================================

USE DetallesTereDB;
GO

-- 1. ESTADO (1 = activo, 0 = baja lógica)
IF COL_LENGTH('dbo.PRODUCTO', 'ESTADO') IS NULL
    ALTER TABLE dbo.PRODUCTO
        ADD ESTADO BIT NOT NULL
            CONSTRAINT DF_PRODUCTO_ESTADO DEFAULT 1;
GO

IF COL_LENGTH('dbo.MATERIAL', 'ESTADO') IS NULL
    ALTER TABLE dbo.MATERIAL
        ADD ESTADO BIT NOT NULL
            CONSTRAINT DF_MATERIAL_ESTADO DEFAULT 1;
GO

IF COL_LENGTH('dbo.ARREGLO', 'ESTADO') IS NULL
    ALTER TABLE dbo.ARREGLO
        ADD ESTADO BIT NOT NULL
            CONSTRAINT DF_ARREGLO_ESTADO DEFAULT 1;
GO

-- 2. Enlace producto <-> material (una sola FK, se mantiene 3FN:
--    PRODUCTO solo referencia su contraparte MATERIAL, sin atributos
--    redundantes en la fila.)
IF COL_LENGTH('dbo.PRODUCTO', 'ID_MATERIAL') IS NULL
    ALTER TABLE dbo.PRODUCTO
        ADD ID_MATERIAL INT NULL
            CONSTRAINT FK_PRODUCTO_MATERIAL
                FOREIGN KEY REFERENCES dbo.MATERIAL(ID_MATERIAL);
GO
