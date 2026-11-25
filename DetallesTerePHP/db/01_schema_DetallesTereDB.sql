-- ============================================================
-- DetallesTereDB — Esquema relacional (SQL Server 2019)
-- Normalización: 1FN / 2FN / 3FN
-- 22 tablas. Collation Modern_Spanish_CI_AI (ñ como letra
-- propia, ignora tildes en búsquedas, insensible a mayúsculas)
-- ============================================================

IF DB_ID('DetallesTereDB') IS NULL
    CREATE DATABASE DetallesTereDB
        COLLATE Modern_Spanish_CI_AI;
GO

USE DetallesTereDB;
GO

-- Requerido para índices filtrados (sqlcmd inicia QUOTED_IDENTIFIER OFF)
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

-- ============================================================
-- 1. USUARIO — roles: admin | empleado (ambos abren caja)
-- ============================================================
CREATE TABLE dbo.USUARIO (
    ID_USUARIO  INT IDENTITY(1,1) PRIMARY KEY,
    USERNAME    VARCHAR(50)  NOT NULL,
    NOMBRE      VARCHAR(50)  NOT NULL,
    APELLIDO    VARCHAR(50)  NOT NULL,
    CORREO      VARCHAR(75)  NULL,
    CONTRASENA  VARCHAR(255) NOT NULL,   -- hash BCrypt
    ROL         VARCHAR(12)  NOT NULL,
    ESTADO      BIT          NOT NULL DEFAULT 1,
    CONSTRAINT UQ_USUARIO_USERNAME UNIQUE (USERNAME),
    CONSTRAINT UQ_USUARIO_CORREO   UNIQUE (CORREO),
    CONSTRAINT CK_USUARIO_ROL CHECK (ROL IN ('admin', 'empleado'))
);
GO

-- ============================================================
-- 2. CAJA — apertura/cierre manual por usuario.
--    1 caja abierta por usuario (filtro FECHA_CIERRE IS NULL).
-- ============================================================
CREATE TABLE dbo.CAJA (
    ID_CAJA        INT IDENTITY(1,1) PRIMARY KEY,
    ID_USUARIO     INT           NOT NULL,
    MONTO_INICIAL  DECIMAL(10,2) NOT NULL,
    MONTO_FINAL    DECIMAL(10,2) NULL,   -- calculado al cierre
    MONTO_REAL     DECIMAL(10,2) NULL,   -- conteo físico al cierre
    DIFERENCIA     DECIMAL(10,2) NULL,   -- MONTO_REAL - MONTO_FINAL
    FECHA_APERTURA DATETIME2     NOT NULL,
    FECHA_CIERRE   DATETIME2     NULL,
    CONSTRAINT FK_CAJA_USUARIO FOREIGN KEY (ID_USUARIO)
        REFERENCES dbo.USUARIO(ID_USUARIO),
    CONSTRAINT CK_CAJA_MONTO_INICIAL CHECK (MONTO_INICIAL >= 0 AND MONTO_INICIAL <= 1000000),
    CONSTRAINT CK_CAJA_MONTO_FINAL   CHECK (MONTO_FINAL   >= 0 AND MONTO_FINAL   <= 1000000),
    CONSTRAINT CK_CAJA_MONTO_REAL    CHECK (MONTO_REAL    >= 0 AND MONTO_REAL    <= 1000000)
);
GO

-- 1 caja abierta por usuario
CREATE UNIQUE INDEX UX_CAJA_ABIERTA
    ON dbo.CAJA (ID_USUARIO)
    WHERE FECHA_CIERRE IS NULL;
GO

-- ============================================================
-- 3. CATEGORIA — tabla única, NOMBRE NO UNIQUE.
--    Agrupa productos, materiales y arreglos vía FK.
-- ============================================================
CREATE TABLE dbo.CATEGORIA (
    ID_CATEGORIA INT IDENTITY(1,1) PRIMARY KEY,
    NOMBRE       VARCHAR(100)  NOT NULL,
    DESCRIPCION  VARCHAR(256)  NULL
);
GO

-- ============================================================
-- 4. PRODUCTO — PRECIO_COMPRA solo lo lee/inserta admin.
-- ============================================================
CREATE TABLE dbo.PRODUCTO (
    ID_PRODUCTO    INT IDENTITY(1,1) PRIMARY KEY,
    ID_CATEGORIA   INT           NULL,
    NOMBRE         VARCHAR(50)   NOT NULL,
    TIPO           VARCHAR(50)   NULL,
    PRECIO_COMPRA  DECIMAL(10,2) NULL,
    PRECIO_VENTA   DECIMAL(10,2) NOT NULL,
    CONSTRAINT FK_PRODUCTO_CATEGORIA FOREIGN KEY (ID_CATEGORIA)
        REFERENCES dbo.CATEGORIA(ID_CATEGORIA),
    CONSTRAINT CK_PRODUCTO_PRECIO_COMPRA CHECK (PRECIO_COMPRA >= 0 AND PRECIO_COMPRA <= 1000000),
    CONSTRAINT CK_PRODUCTO_PRECIO_VENTA  CHECK (PRECIO_VENTA  >= 0 AND PRECIO_VENTA  <= 1000000)
);
GO

-- ============================================================
-- 5. MATERIAL — mismas reglas que producto
-- ============================================================
CREATE TABLE dbo.MATERIAL (
    ID_MATERIAL    INT IDENTITY(1,1) PRIMARY KEY,
    ID_CATEGORIA   INT           NULL,
    NOMBRE         VARCHAR(50)   NOT NULL,
    TIPO           VARCHAR(50)   NULL,
    PRECIO_COMPRA  DECIMAL(10,2) NULL,
    PRECIO_VENTA   DECIMAL(10,2) NOT NULL,
    CONSTRAINT FK_MATERIAL_CATEGORIA FOREIGN KEY (ID_CATEGORIA)
        REFERENCES dbo.CATEGORIA(ID_CATEGORIA),
    CONSTRAINT CK_MATERIAL_PRECIO_COMPRA CHECK (PRECIO_COMPRA >= 0 AND PRECIO_COMPRA <= 1000000),
    CONSTRAINT CK_MATERIAL_PRECIO_VENTA  CHECK (PRECIO_VENTA  >= 0 AND PRECIO_VENTA  <= 1000000)
);
GO

-- ============================================================
-- 6. ARREGLO — PK ID_DETALLE (según conceptual)
-- ============================================================
CREATE TABLE dbo.ARREGLO (
    ID_DETALLE     INT IDENTITY(1,1) PRIMARY KEY,
    ID_CATEGORIA   INT           NULL,
    NOMBRE         VARCHAR(100)  NOT NULL,
    PRECIO_COMPRA  DECIMAL(10,2) NULL,
    PRECIO_VENTA   DECIMAL(10,2) NOT NULL,
    CONSTRAINT FK_ARREGLO_CATEGORIA FOREIGN KEY (ID_CATEGORIA)
        REFERENCES dbo.CATEGORIA(ID_CATEGORIA),
    CONSTRAINT CK_ARREGLO_PRECIO_COMPRA CHECK (PRECIO_COMPRA >= 0 AND PRECIO_COMPRA <= 1000000),
    CONSTRAINT CK_ARREGLO_PRECIO_VENTA  CHECK (PRECIO_VENTA  >= 0 AND PRECIO_VENTA  <= 1000000)
);
GO

-- ============================================================
-- 7. DETALLE_ARREGLO — qué lleva cada arreglo (N-M producto/
--    material -> arreglo). CANT_* según corresponda.
-- ============================================================
CREATE TABLE dbo.DETALLE_ARREGLO (
    ID_DETALLE_ARREGLO INT IDENTITY(1,1) PRIMARY KEY,
    ID_DETALLE         INT NOT NULL,   -- FK a ARREGLO (cabecera)
    ID_PRODUCTO        INT NULL,
    ID_MATERIAL        INT NULL,
    CANT_PRODUCTO      INT NULL,
    CANT_MATERIAL      INT NULL,
    CONSTRAINT FK_DETALLE_ARREGLO_ARREGLO FOREIGN KEY (ID_DETALLE)
        REFERENCES dbo.ARREGLO(ID_DETALLE),
    CONSTRAINT FK_DETALLE_ARREGLO_PRODUCTO FOREIGN KEY (ID_PRODUCTO)
        REFERENCES dbo.PRODUCTO(ID_PRODUCTO),
    CONSTRAINT FK_DETALLE_ARREGLO_MATERIAL FOREIGN KEY (ID_MATERIAL)
        REFERENCES dbo.MATERIAL(ID_MATERIAL),
    CONSTRAINT CK_DETALLE_ARREGLO_UNO CHECK (
        (CASE WHEN ID_PRODUCTO IS NOT NULL THEN 1 ELSE 0 END +
         CASE WHEN ID_MATERIAL IS NOT NULL THEN 1 ELSE 0 END) = 1
    )
);
GO

-- ============================================================
-- 8. INVENTARIO — 1 inventario con TIPO_ALMACEN (no 2 tablas).
--    Exactamente 1 de 3: producto | material | detalle(arreglo).
--    TIPO_ALMACEN y UNIDAD_MEDIDA libres (VARCHAR), no catálogo.
-- ============================================================
CREATE TABLE dbo.INVENTARIO (
    ID_INVENTARIO  INT IDENTITY(1,1) PRIMARY KEY,
    ID_PRODUCTO    INT         NULL,
    ID_MATERIAL    INT         NULL,
    ID_DETALLE     INT         NULL,   -- FK a ARREGLO
    STOCK_ACT      INT         NOT NULL DEFAULT 0,
    STOCK_MIN      INT         NOT NULL DEFAULT 0,
    TIPO_ALMACEN   VARCHAR(35) NOT NULL,
    UNIDAD_MEDIDA  VARCHAR(50) NOT NULL,
    CONSTRAINT FK_INVENTARIO_PRODUCTO FOREIGN KEY (ID_PRODUCTO)
        REFERENCES dbo.PRODUCTO(ID_PRODUCTO),
    CONSTRAINT FK_INVENTARIO_MATERIAL FOREIGN KEY (ID_MATERIAL)
        REFERENCES dbo.MATERIAL(ID_MATERIAL),
    CONSTRAINT FK_INVENTARIO_ARREGLO FOREIGN KEY (ID_DETALLE)
        REFERENCES dbo.ARREGLO(ID_DETALLE),
    CONSTRAINT CK_INVENTARIO_STOCK_ACT CHECK (STOCK_ACT >= 0),
    CONSTRAINT CK_INVENTARIO_STOCK_MIN CHECK (STOCK_MIN >= 0),
    CONSTRAINT CK_INVENTARIO_UNO CHECK (
        (CASE WHEN ID_PRODUCTO IS NOT NULL THEN 1 ELSE 0 END +
         CASE WHEN ID_MATERIAL IS NOT NULL THEN 1 ELSE 0 END +
         CASE WHEN ID_DETALLE  IS NOT NULL THEN 1 ELSE 0 END) = 1
    )
);
GO

-- ============================================================
-- 9. TERMINOS — palabras/sinónimos que el admin agrega
--    (3FN: separado de INVENTARIO)
-- ============================================================
CREATE TABLE dbo.TERMINOS (
    ID_TERMINO  INT IDENTITY(1,1) PRIMARY KEY,
    ID_USUARIO  INT         NOT NULL,   -- quién lo registró
    PALABRA     VARCHAR(75) NOT NULL,
    CONSTRAINT FK_TERMINOS_USUARIO FOREIGN KEY (ID_USUARIO)
        REFERENCES dbo.USUARIO(ID_USUARIO),
    CONSTRAINT UQ_TERMINOS_PALABRA UNIQUE (PALABRA)
);
GO

-- ============================================================
-- 10. DETALLE_TERMINO — N-M término <-> inventario
-- ============================================================
CREATE TABLE dbo.DETALLE_TERMINO (
    ID_DETALLE_TERMINO INT IDENTITY(1,1) PRIMARY KEY,
    ID_TERMINO         INT NOT NULL,
    ID_INVENTARIO      INT NOT NULL,
    CONSTRAINT FK_DETALLE_TERMINO_TERMINOS FOREIGN KEY (ID_TERMINO)
        REFERENCES dbo.TERMINOS(ID_TERMINO),
    CONSTRAINT FK_DETALLE_TERMINO_INVENTARIO FOREIGN KEY (ID_INVENTARIO)
        REFERENCES dbo.INVENTARIO(ID_INVENTARIO),
    CONSTRAINT UQ_DETALLE_TERMINO UNIQUE (ID_TERMINO, ID_INVENTARIO)
);
GO

-- ============================================================
-- 11. CLIENTE — solo NOMBRE y NUM_WSP (sin DNI)
-- ============================================================
CREATE TABLE dbo.CLIENTE (
    ID_CLIENTE INT IDENTITY(1,1) PRIMARY KEY,
    NOMBRE     VARCHAR(90)  NOT NULL,
    NUM_WSP    VARCHAR(12)  NOT NULL
);
GO

-- ============================================================
-- 12. CREDITO_CLIENTE — saldo a favor (canje por otro producto)
-- ============================================================
CREATE TABLE dbo.CREDITO_CLIENTE (
    ID_CREDITO_CLIENTE INT IDENTITY(1,1) PRIMARY KEY,
    ID_CLIENTE         INT           NOT NULL,
    ID_INVENTARIO      INT           NULL,   -- ítem canjeado
    MONTO              DECIMAL(10,2) NOT NULL,
    ESTADO             VARCHAR(12)   NOT NULL,
    FECHA              DATETIME2     NOT NULL,
    FECHA_VENCIMIENTO  DATETIME2     NULL,
    ID_USUARIO         INT           NOT NULL,
    CONSTRAINT FK_CREDITO_CLIENTE FOREIGN KEY (ID_CLIENTE)
        REFERENCES dbo.CLIENTE(ID_CLIENTE),
    CONSTRAINT FK_CREDITO_INVENTARIO FOREIGN KEY (ID_INVENTARIO)
        REFERENCES dbo.INVENTARIO(ID_INVENTARIO),
    CONSTRAINT FK_CREDITO_USUARIO FOREIGN KEY (ID_USUARIO)
        REFERENCES dbo.USUARIO(ID_USUARIO),
    CONSTRAINT CK_CREDITO_MONTO CHECK (MONTO > 0 AND MONTO <= 1000000),
    CONSTRAINT CK_CREDITO_ESTADO CHECK (ESTADO IN ('ACTIVO', 'CANJEADO'))
);
GO

-- ============================================================
-- 13. VENTA — cabecera. ID_DETALLE_VENTA = primera línea
--    (cabecera referencial del conceptual). TIPO_PAGO:
--    YAPE o EFECTIVO (solo registro manual, sin escaneo).
--    TOTAL editable solo por admin.
-- ============================================================
CREATE TABLE dbo.VENTA (
    ID_VENTA          INT IDENTITY(1,1) PRIMARY KEY,
    ID_CAJA           INT           NULL,   -- NULL si es cotización
    ID_DETALLE_VENTA  INT           NULL,   -- FK posterior (cabecera)
    ID_PEDIDO         INT           NULL,   -- pago de reserva (FK al final del archivo)
    TOTAL             DECIMAL(10,2) NOT NULL,
    TIPO_PAGO         VARCHAR(8)    NULL,   -- NULL en cotización
    -- Nota: el efectivo recibido y el vuelto NO se guardan. El efectivo que
    -- entra a caja es el TOTAL de la venta (el vuelto se devuelve), por lo que
    -- el reporte de caja usa TOTAL de ventas en EFECTIVO. CREDITO = canje de
    -- CREDITO_CLIENTE (no ingresa efectivo, se excluye del efectivo de caja).
    ESTADO            VARCHAR(12)   NOT NULL,
    FECHA_VENTA       DATETIME2     NOT NULL,
    CONSTRAINT CK_VENTA_TOTAL CHECK (TOTAL >= 0 AND TOTAL <= 1000000),
    CONSTRAINT CK_VENTA_TIPO_PAGO CHECK (TIPO_PAGO IN ('YAPE', 'EFECTIVO', 'CREDITO')),
    CONSTRAINT CK_VENTA_ESTADO CHECK (ESTADO IN ('COTIZACION', 'VENDIDO', 'CANCELADO'))
);
GO

-- ============================================================
-- 14. DETALLE_VENTA — intermedia N-M inventario <-> venta.
--    Doble rol: líneas confirmadas y cotizaciones 24h
--    (ESTADO='COTIZACION' + FECHA_VENCIMIENTO).
--    COSTO_UNITARIO fija precio de venta al momento.
-- ============================================================
CREATE TABLE dbo.DETALLE_VENTA (
    ID_DETALLE_VENTA   INT IDENTITY(1,1) PRIMARY KEY,
    ID_INVENTARIO      INT           NOT NULL,
    ID_CLIENTE         INT           NULL,   -- NULL = público general
    CANTIDAD           INT           NOT NULL,
    COSTO_UNITARIO     DECIMAL(10,2) NOT NULL,
    SUBTOTAL           DECIMAL(10,2) NOT NULL,
    ID_CAJA            INT           NULL,
    ID_VENTA           INT           NULL,
    FECHA_CREACION     DATETIME2     NOT NULL,
    FECHA_VENCIMIENTO  DATETIME2     NULL,   -- cotización: +24h
    ESTADO             VARCHAR(12)   NOT NULL,
    CONSTRAINT FK_DETALLE_VENTA_INVENTARIO FOREIGN KEY (ID_INVENTARIO)
        REFERENCES dbo.INVENTARIO(ID_INVENTARIO),
    CONSTRAINT FK_DETALLE_VENTA_CLIENTE FOREIGN KEY (ID_CLIENTE)
        REFERENCES dbo.CLIENTE(ID_CLIENTE),
    CONSTRAINT FK_DETALLE_VENTA_CAJA FOREIGN KEY (ID_CAJA)
        REFERENCES dbo.CAJA(ID_CAJA),
    CONSTRAINT FK_DETALLE_VENTA_VENTA FOREIGN KEY (ID_VENTA)
        REFERENCES dbo.VENTA(ID_VENTA),
    CONSTRAINT CK_DETALLE_VENTA_CANTIDAD CHECK (CANTIDAD > 0),
    CONSTRAINT CK_DETALLE_VENTA_COSTO CHECK (COSTO_UNITARIO >= 0 AND COSTO_UNITARIO <= 1000000),
    CONSTRAINT CK_DETALLE_VENTA_SUBTOTAL CHECK (SUBTOTAL >= 0 AND SUBTOTAL <= 1000000),
    CONSTRAINT CK_DETALLE_VENTA_ESTADO CHECK (ESTADO IN ('COTIZACION', 'VENDIDO', 'CANCELADO'))
);
GO

-- ============================================================
-- 15. PEDIDOS — reservados. Reglas:
--    * 3 meses para acercarse y cambiar por producto del mismo valor
--    * 2 meses para recoger
--    * 1 mes para completar pago total (adm. puede editar)
--    * ADELANTO mínimo 50% del TOTAL
-- ============================================================
CREATE TABLE dbo.PEDIDOS (
    ID_PEDIDO            INT IDENTITY(1,1) PRIMARY KEY,
    ID_CLIENTE           INT           NULL,
    NOMBRE_PAGA          VARCHAR(90)   NOT NULL,
    NOMBRE_RECOGE        VARCHAR(90)   NULL,
    CELULAR              VARCHAR(12)   NOT NULL,
    FECHA_RECOJO         DATE          NOT NULL,
    HORA_RECOJO          VARCHAR(5)    NULL,   -- 'HH:mm'
    ADELANTO             DECIMAL(10,2) NOT NULL DEFAULT 0,
    TOTAL                DECIMAL(10,2) NOT NULL,
    ESTADO               VARCHAR(20)   NOT NULL,
    -- Estado de producción: el empleado/admin lo marca HECHO cuando empieza a
    -- hacer el pedido; a partir de ahí es punto de no retorno (no cancela, paga total).
    ESTADO_PRODUCCION    VARCHAR(12)   NOT NULL DEFAULT 'PENDIENTE',
    ID_USUARIO           INT           NOT NULL,
    FECHA_CREACION       DATETIME2     NOT NULL,
    FECHA_LIMITE_CANJE   DATETIME2     NOT NULL,   -- +3 meses
    FECHA_LIMITE_RECOJO  DATETIME2     NOT NULL,   -- +2 meses
    FECHA_LIMITE_PAGO    DATETIME2     NOT NULL,   -- +1 mes
    FECHA_PAGO_TOTAL     DATETIME2     NULL,
    CONSTRAINT FK_PEDIDOS_CLIENTE FOREIGN KEY (ID_CLIENTE)
        REFERENCES dbo.CLIENTE(ID_CLIENTE),
    CONSTRAINT FK_PEDIDOS_USUARIO FOREIGN KEY (ID_USUARIO)
        REFERENCES dbo.USUARIO(ID_USUARIO),
    CONSTRAINT CK_PEDIDOS_ADELANTO CHECK (ADELANTO >= 0 AND ADELANTO <= 1000000),
    CONSTRAINT CK_PEDIDOS_TOTAL CHECK (TOTAL > 0 AND TOTAL <= 1000000),
    CONSTRAINT CK_PEDIDOS_ESTADO CHECK (ESTADO IN ('PENDIENTE', 'COMPLETO', 'RECOGIDO', 'CANCELADO')),
    CONSTRAINT CK_PEDIDOS_ESTADO_PRODUCCION CHECK (ESTADO_PRODUCCION IN ('PENDIENTE', 'HECHO', 'CANCELADO'))
);
GO

-- ============================================================
-- 16. DETALLE_PEDIDO — líneas del pedido reservado
-- ============================================================
CREATE TABLE dbo.DETALLE_PEDIDO (
    ID_DETALLE_PEDIDO INT IDENTITY(1,1) PRIMARY KEY,
    ID_PEDIDO         INT           NOT NULL,
    ID_INVENTARIO     INT           NOT NULL,
    CANTIDAD          INT           NOT NULL,
    COSTO_UNITARIO    DECIMAL(10,2) NOT NULL,
    SUBTOTAL          DECIMAL(10,2) NOT NULL,
    CONSTRAINT FK_DETALLE_PEDIDO_PEDIDO FOREIGN KEY (ID_PEDIDO)
        REFERENCES dbo.PEDIDOS(ID_PEDIDO),
    CONSTRAINT FK_DETALLE_PEDIDO_INVENTARIO FOREIGN KEY (ID_INVENTARIO)
        REFERENCES dbo.INVENTARIO(ID_INVENTARIO),
    CONSTRAINT CK_DETALLE_PEDIDO_CANTIDAD CHECK (CANTIDAD > 0),
    CONSTRAINT CK_DETALLE_PEDIDO_COSTO CHECK (COSTO_UNITARIO >= 0 AND COSTO_UNITARIO <= 1000000),
    CONSTRAINT CK_DETALLE_PEDIDO_SUBTOTAL CHECK (SUBTOTAL >= 0 AND SUBTOTAL <= 1000000)
);
GO

-- ============================================================
-- 17. PROVEEDOR — solo NOMBRE y NUMERO
-- ============================================================
CREATE TABLE dbo.PROVEEDOR (
    ID_PROVEEDOR INT IDENTITY(1,1) PRIMARY KEY,
    NOMBRE       VARCHAR(50)  NOT NULL,
    NUMERO       VARCHAR(12)  NOT NULL
);
GO

-- ============================================================
-- 18. INGRESO — solo admin registra entradas al inventario
-- ============================================================
CREATE TABLE dbo.INGRESO (
    ID_INGRESO     INT IDENTITY(1,1) PRIMARY KEY,
    ID_PROVEEDOR   INT       NOT NULL,
    ID_USUARIO     INT       NOT NULL,   -- admin
    FECHA_INGRESO  DATE      NOT NULL,
    CONSTRAINT FK_INGRESO_PROVEEDOR FOREIGN KEY (ID_PROVEEDOR)
        REFERENCES dbo.PROVEEDOR(ID_PROVEEDOR),
    CONSTRAINT FK_INGRESO_USUARIO FOREIGN KEY (ID_USUARIO)
        REFERENCES dbo.USUARIO(ID_USUARIO)
);
GO

-- ============================================================
-- 19. DETALLE_INGRESO — líneas del ingreso
-- ============================================================
CREATE TABLE dbo.DETALLE_INGRESO (
    ID_DETALLE_INGRESO    INT IDENTITY(1,1) PRIMARY KEY,
    ID_INGRESO            INT           NOT NULL,
    ID_INVENTARIO         INT           NOT NULL,
    CANTIDAD              INT           NOT NULL,
    PRECIO_COMPRA_UNITARIO DECIMAL(10,2) NOT NULL,
    SUBTOTAL              DECIMAL(10,2) NOT NULL,
    OBSERVACIONES         VARCHAR(100)  NULL,
    CONSTRAINT FK_DETALLE_INGRESO_INGRESO FOREIGN KEY (ID_INGRESO)
        REFERENCES dbo.INGRESO(ID_INGRESO),
    CONSTRAINT FK_DETALLE_INGRESO_INVENTARIO FOREIGN KEY (ID_INVENTARIO)
        REFERENCES dbo.INVENTARIO(ID_INVENTARIO),
    CONSTRAINT CK_DETALLE_INGRESO_CANTIDAD CHECK (CANTIDAD > 0),
    CONSTRAINT CK_DETALLE_INGRESO_PRECIO CHECK (PRECIO_COMPRA_UNITARIO >= 0 AND PRECIO_COMPRA_UNITARIO <= 1000000),
    CONSTRAINT CK_DETALLE_INGRESO_SUBTOTAL CHECK (SUBTOTAL >= 0 AND SUBTOTAL <= 1000000)
);
GO

-- ============================================================
-- 20. AJUSTE_INVENTARIO — cabecera del ajuste
-- ============================================================
CREATE TABLE dbo.AJUSTE_INVENTARIO (
    ID_AJUSTE  INT IDENTITY(1,1) PRIMARY KEY,
    ID_USUARIO INT       NOT NULL,
    FECHA      DATETIME2 NOT NULL,
    CONSTRAINT FK_AJUSTE_USUARIO FOREIGN KEY (ID_USUARIO)
        REFERENCES dbo.USUARIO(ID_USUARIO)
);
GO

-- ============================================================
-- 21. DETALLE_AJUSTE — líneas del ajuste
-- ============================================================
CREATE TABLE dbo.DETALLE_AJUSTE (
    ID_DETALLE_AJUSTE INT IDENTITY(1,1) PRIMARY KEY,
    ID_AJUSTE         INT           NOT NULL,
    ID_INVENTARIO     INT           NOT NULL,
    CANTIDAD          INT           NOT NULL,
    TIPO_AJUSTE       VARCHAR(35)   NOT NULL,   -- libre
    MOTIVO            VARCHAR(100)  NULL,
    CONSTRAINT FK_DETALLE_AJUSTE_AJUSTE FOREIGN KEY (ID_AJUSTE)
        REFERENCES dbo.AJUSTE_INVENTARIO(ID_AJUSTE),
    CONSTRAINT FK_DETALLE_AJUSTE_INVENTARIO FOREIGN KEY (ID_INVENTARIO)
        REFERENCES dbo.INVENTARIO(ID_INVENTARIO)
);
GO

-- ============================================================
-- 22. MOVIMIENTO_INVENTARIO — trazabilidad de entradas/salidas
-- ============================================================
CREATE TABLE dbo.MOVIMIENTO_INVENTARIO (
    ID_MOVIMIENTO     INT IDENTITY(1,1) PRIMARY KEY,
    ID_USUARIO        INT           NOT NULL,
    ID_INVENTARIO     INT           NOT NULL,
    FECHA_MOVIMIENTO  DATETIME2     NOT NULL,
    TIPO_MOVIMIENTO   VARCHAR(10)   NOT NULL,
    CANTIDAD          INT           NOT NULL,
    CONSTRAINT FK_MOVIMIENTO_USUARIO FOREIGN KEY (ID_USUARIO)
        REFERENCES dbo.USUARIO(ID_USUARIO),
    CONSTRAINT FK_MOVIMIENTO_INVENTARIO FOREIGN KEY (ID_INVENTARIO)
        REFERENCES dbo.INVENTARIO(ID_INVENTARIO),
    CONSTRAINT CK_MOVIMIENTO_TIPO CHECK (TIPO_MOVIMIENTO IN ('ENTRADA', 'SALIDA', 'AJUSTE')),
    CONSTRAINT CK_MOVIMIENTO_CANTIDAD CHECK (CANTIDAD > 0)
);
GO

-- ============================================================
-- FKs diferidas (la tabla referenciada se crea después)
-- ============================================================
-- VENTA.ID_PEDIDO: pago (adelanto/abono) de un pedido reservado.
-- Permite al reporte de caja separar 'ventas de productos' de
-- 'pagos de reservas' (ambos ingresan efectivo).
ALTER TABLE dbo.VENTA ADD CONSTRAINT FK_VENTA_PEDIDO
    FOREIGN KEY (ID_PEDIDO) REFERENCES dbo.PEDIDOS(ID_PEDIDO);
GO
