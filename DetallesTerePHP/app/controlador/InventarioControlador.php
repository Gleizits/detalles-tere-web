<?php

namespace Controlador;

use ConexionDB;
use Dao\CategoriaDAO;
use Dao\InventarioDAO;
use InvalidArgumentException;
use Modelo\Categoria;
use Modelo\InventarioItem;
use Throwable;

/**
 * Inventario: alta, edición, baja lógica y ajuste manual de stock.
 *
 * Reglas trasladadas de InventarioServlet:
 *  - Empleado y admin dan de alta artículos; el costo solo lo captura el admin.
 *  - La clase del artículo nunca cambia después del alta.
 *  - La baja es lógica (ESTADO), jamás se borra el artículo.
 *  - El delta del ajuste se calcula en el servidor (funciona sin JavaScript).
 *  - Al empleado nunca se le entrega el precio de compra.
 *
 * Nota: si InventarioDAO aún no expone crear/actualizar/ajustarStock (está en
 * migración), este controlador ejecuta las mismas consultas en una transacción
 * usando la conexión existente. Cuando el DAO las incorpore, se usan desde ahí.
 */
final class InventarioControlador extends ControladorBase
{
    private const CLASES = ['PRODUCTO', 'MATERIAL', 'ARREGLO'];
    private const MAX_PRECIO = 1000000.0;

    private const SQL_BASE =
        "SELECT i.ID_INVENTARIO, i.STOCK_ACT, i.STOCK_MIN, i.TIPO_ALMACEN, i.UNIDAD_MEDIDA, "
        . "i.ID_PRODUCTO, i.ID_MATERIAL, i.ID_DETALLE, "
        . "COALESCE(p.ID_CATEGORIA, m.ID_CATEGORIA, a.ID_CATEGORIA) AS ID_CATEGORIA, "
        . "COALESCE(p.ESTADO, m.ESTADO, a.ESTADO) AS ESTADO, "
        . "COALESCE(p.ID_MATERIAL, 0) AS ID_MATERIAL_VINC, "
        . "COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NOMBRE, "
        . "COALESCE(p.TIPO, m.TIPO) AS TIPO, "
        . "c.NOMBRE AS CATEGORIA, "
        . "COALESCE(p.PRECIO_VENTA, m.PRECIO_VENTA, a.PRECIO_VENTA) AS PRECIO_VENTA, "
        . "COALESCE(p.PRECIO_COMPRA, m.PRECIO_COMPRA, a.PRECIO_COMPRA) AS PRECIO_COMPRA, "
        . "CASE WHEN p.ID_PRODUCTO IS NOT NULL THEN 'PRODUCTO' "
        . "     WHEN m.ID_MATERIAL IS NOT NULL THEN 'MATERIAL' "
        . "     ELSE 'ARREGLO' END AS CLASE "
        . "FROM dbo.INVENTARIO i "
        . "LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO "
        . "LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL "
        . "LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE "
        . "LEFT JOIN dbo.CATEGORIA c "
        . "  ON COALESCE(p.ID_CATEGORIA, m.ID_CATEGORIA, a.ID_CATEGORIA) = c.ID_CATEGORIA ";

    /** GET /inventario: lista, formulario nuevo o formulario de edición. */
    public function index(): never
    {
        if (isset($_GET['nuevo'])) {
            $this->mostrarFormulario(null, 'Nuevo artículo');
        }

        if (isset($_GET['editar'])) {
            $item = $this->buscarSeguro(valorParam('editar'));
            if ($item === null) {
                $this->fallar('No encontramos ese artículo.');
            }
            $extras = $this->extras($item->idInventario);
            $this->aplicarExtras($item, $extras);
            $this->mostrarFormulario($item, 'Editar artículo', null, $extras);
        }

        $q = valorParam('q');
        $lista = $this->cargarLista($q);
        $esAdmin = $this->esAdmin();

        // Defensa en profundidad: el empleado no recibe el precio de costo.
        if (!$esAdmin) {
            foreach ($lista['items'] as $item) {
                $item->precioCompra = null;
            }
        }

        $this->ver('inventario', 'Inventario', '/inventario', [
            'items'   => $lista['items'],
            'estados' => $lista['estados'],
            'q'       => $q,
            'total'   => count($lista['items']),
            'esAdmin' => $esAdmin,
        ]);
    }

    /** POST /inventario: crear | editar | ajustar. */
    public function procesar(): never
    {
        switch (valorParam('accion')) {
            case 'crear':
                $this->crear();
                break;
            case 'editar':
                $this->editar();
                break;
            case 'ajustar':
                $this->ajustar();
                break;
            default:
                $this->ir('/inventario');
        }
    }

    private function crear(): never
    {
        $clase = valorParam('clase');
        if (!in_array($clase, self::CLASES, true)) {
            $this->mostrarFormulario(
                null,
                'Nuevo artículo',
                'Elige qué tipo de artículo es: producto, material o arreglo.'
            );
        }

        $item = new InventarioItem();
        $item->clase = $clase;
        $extras = $this->extrasPorFormulario(false);
        $this->aplicarExtras($item, $extras);

        try {
            $this->leerCampos($item, $extras, true);
            $this->guardarCrear($item, $extras);
        } catch (InvalidArgumentException $e) {
            // Volvemos al formulario con lo capturado para no perderlo.
            $this->mostrarFormulario($item, 'Nuevo artículo', $e->getMessage(), $extras);
        } catch (Throwable $e) {
            error_log('Error al crear artículo: ' . $e->getMessage());
            $this->mostrarFormulario($item, 'Nuevo artículo',
                'No pudimos guardar el artículo. Intenta de nuevo.', $extras);
        }

        flash('aviso', 'Artículo "' . $item->nombre . '" guardado en el inventario.');
        $this->ir('/inventario');
    }

    private function editar(): never
    {
        $item = $this->buscarSeguro(valorParam('idInventario'));
        if ($item === null) {
            $this->fallar('No encontramos ese artículo.');
        }

        $extras = $this->extras($item->idInventario);
        $this->aplicarExtras($item, $extras);

        try {
            // La clase no cambia jamás: se toma de la BD, no del formulario.
            $this->leerCampos($item, $extras, false);
            $this->guardarActualizar($item, $extras, $this->esAdmin());
        } catch (InvalidArgumentException $e) {
            $this->mostrarFormulario($item, 'Editar artículo', $e->getMessage(), $extras);
        } catch (Throwable $e) {
            error_log('Error al editar artículo: ' . $e->getMessage());
            $this->mostrarFormulario($item, 'Editar artículo',
                'No pudimos guardar los cambios. Intenta de nuevo.', $extras);
        }

        flash('aviso', 'Cambios guardados en "' . $item->nombre . '".');
        $this->ir('/inventario');
    }

    private function ajustar(): never
    {
        $id = enteroParam('idInventario');
        if ($id <= 0) {
            $this->fallar('No pudimos identificar el artículo.');
        }

        // Si llegan signo + cantidad, el delta se calcula aquí (sin depender del JS).
        if (isset($_POST['signo'], $_POST['cantidad'])) {
            $signo = valorParam('signo') === '-1' ? -1 : 1;
            $cantidad = enteroParam('cantidad');
            if ($cantidad <= 0) {
                $this->fallar('Indica la cantidad a sumar o restar.');
            }
            $delta = $signo * $cantidad;
        } else {
            $delta = enteroParam('delta');
        }

        if ($delta === 0) {
            $this->fallar('La cantidad no puede ser 0.');
        }

        $motivo = mb_substr(valorParam('motivo'), 0, 100);

        try {
            $this->guardarAjuste($id, $delta, $motivo, $this->usuario()->id);
        } catch (Throwable $e) {
            $mensaje = stripos($e->getMessage(), 'stock') !== false
                ? $e->getMessage()
                : 'No pudimos actualizar el stock. Intenta de nuevo.';
            $this->fallar($mensaje);
        }

        $que = $delta > 0 ? 'sumaron' : 'restaron';
        flash('aviso', 'Stock actualizado: se ' . $que . ' ' . abs($delta) . ' unidad(es).');
        $this->ir('/inventario');
    }

    /**
     * Lee y valida todo el formulario. Se asigna cada campo antes de validar
     * para que, si hay error, el formulario conserve lo que se capturó.
     */
    private function leerCampos(InventarioItem $item, array &$extras, bool $alta): void
    {
        $error = null;

        // Nombre
        $nombre = valorParam('nombre');
        $tope = $item->clase === 'ARREGLO' ? 100 : 50;
        if ($nombre === '') {
            $error = 'El nombre del artículo es obligatorio.';
        } elseif (mb_strlen($nombre) > $tope) {
            $error = 'El nombre puede tener máximo ' . $tope . ' caracteres.';
        }
        $item->nombre = $nombre;

        // Tipo (los arreglos no lo llevan)
        if ($item->clase !== 'ARREGLO') {
            $tipo = mb_substr(valorParam('tipo'), 0, 50);
            $item->tipo = $tipo;
        }

        // Categoría: selector o nombre nuevo
        $categoriaNueva = mb_substr(valorParam('categoriaNueva'), 0, 100);
        if ($categoriaNueva !== '') {
            try {
                $extras['idCategoria'] = $this->obOCrearCategoria($categoriaNueva);
            } catch (Throwable $e) {
                $error ??= 'No pudimos guardar la categoría nueva. Intenta de nuevo.';
            }
        } else {
            $idCategoria = $this->enteroOpcional(valorParam('idCategoria'));
            if ($idCategoria === null) {
                $error ??= 'Elige una categoría o escribe una nueva.';
            }
            $extras['idCategoria'] = $idCategoria ?? 0;
        }
        if ($extras['idCategoria'] > 0) {
            $this->asignar($item, 'idCategoria', $extras['idCategoria']);
        }

        // Unidad de medida
        $unidad = valorParam('unidadMedida');
        if ($unidad === '') {
            $error ??= 'Indica la unidad de medida o empaque (ej.: paquete de 12).';
        } elseif (mb_strlen($unidad) > 50) {
            $error ??= 'La unidad de medida puede tener máximo 50 caracteres.';
        }
        $item->unidadMedida = $unidad;

        // Tipo de almacén
        $almacen = valorParam('tipoAlmacen');
        if ($almacen === '') {
            $error ??= 'Indica cómo se guarda (ej.: paquete, unidad, costal).';
        } elseif (mb_strlen($almacen) > 35) {
            $error ??= 'El tipo de almacén puede tener máximo 35 caracteres.';
        }
        $item->tipoAlmacen = $almacen;

        // Stock mínimo
        $stockMin = $this->enteroOpcional(valorParam('stockMin'));
        if ($stockMin === null) {
            $error ??= 'El stock mínimo debe ser un número entero.';
        } elseif ($stockMin < 0) {
            $error ??= 'El stock mínimo no puede ser negativo.';
        } else {
            $item->stockMin = $stockMin;
        }

        // Stock inicial (solo alta) y estado
        if ($alta) {
            $stock = $this->enteroOpcional(valorParam('stockAct'));
            if ($stock === null) {
                $error ??= 'El stock inicial debe ser un número entero.';
            } elseif ($stock < 0) {
                $error ??= 'El stock inicial no puede ser negativo.';
            } else {
                $item->stockAct = $stock;
            }
            $extras['estado'] = true;
        } elseif ($this->esAdmin()) {
            $extras['estado'] = valorParam('estado') === 'on';
        }
        $this->asignar($item, 'estado', $extras['estado']);

        // Precio de venta: obligatorio al crear; vacío en edición = conservar.
        $pvTxt = valorParam('precioVenta');
        if ($pvTxt !== '') {
            $precioVenta = $this->decimalOpcional($pvTxt);
            if ($precioVenta === null) {
                $error ??= 'Captura un precio de venta válido (ej.: 12.50).';
            } else {
                $item->precioVenta = $precioVenta;
            }
        } elseif ($alta) {
            $error ??= 'Captura un precio de venta válido (ej.: 12.50).';
        }

        // Precio de compra: solo admin; al empleado se le ignora.
        if ($this->esAdmin()) {
            $pcTxt = valorParam('precioCompra');
            if ($pcTxt !== '') {
                $precioCompra = $this->decimalOpcional($pcTxt);
                if ($precioCompra === null) {
                    $error ??= 'Captura un precio de compra válido.';
                } else {
                    $item->precioCompra = $precioCompra;
                }
            } else {
                $item->precioCompra = null;
            }
        } else {
            $item->precioCompra = null;
        }

        // Material enlazado (solo productos)
        if ($item->clase === 'PRODUCTO') {
            $extras['idMaterialVinc'] = $this->enteroOpcional(valorParam('idMaterialVinc')) ?? 0;
            $this->asignar($item, 'idMaterialVinc', $extras['idMaterialVinc']);
        }

        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }
    }

    private function mostrarFormulario(
        ?InventarioItem $item,
        string $titulo,
        ?string $error = null,
        ?array $extras = null
    ): never {
        if ($extras === null) {
            $extras = $item !== null
                ? $this->extras($item->idInventario)
                : $this->extrasPorFormulario(false);
        }
        if ($item !== null) {
            $this->aplicarExtras($item, $extras);
        }

        if ($item !== null && !$this->esAdmin()) {
            $item->precioCompra = null;
        }

        $this->ver('inventario_form', $titulo, '/inventario', [
            'item'       => $item,
            'extras'     => $extras,
            'categorias' => $this->listarCategorias(),
            'materiales' => $this->listarMaterialesActivos(),
            'esAdmin'    => $this->esAdmin(),
            'error'      => $error,
        ]);
    }

    /** @return array{items:InventarioItem[], estados:array<int,bool>} */
    private function cargarLista(string $q): array
    {
        $sql = self::SQL_BASE;
        $parametros = [];
        if ($q !== '') {
            $sql .= "WHERE (COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) LIKE ? "
                . "   OR EXISTS (SELECT 1 FROM dbo.DETALLE_TERMINO dt "
                . "              JOIN dbo.TERMINOS t ON dt.ID_TERMINO = t.ID_TERMINO "
                . "              WHERE dt.ID_INVENTARIO = i.ID_INVENTARIO AND t.PALABRA LIKE ?)) ";
            $like = '%' . $q . '%';
            $parametros = [$like, $like];
        }
        $sql .= "ORDER BY NOMBRE";

        $ps = ConexionDB::obtener()->prepare($sql);
        $ps->execute($parametros);

        $items = [];
        $estados = [];
        while ($fila = $ps->fetch()) {
            $item = $this->mapFila($fila);
            $items[] = $item;
            $estados[$item->idInventario] = (bool) $fila['ESTADO'];
        }
        return ['items' => $items, 'estados' => $estados];
    }

    /** @return InventarioItem[] */
    private function listarActivos(): array
    {
        $dao = new InventarioDAO();
        if (method_exists($dao, 'listarActivos')) {
            return $dao->listarActivos();
        }

        $sql = self::SQL_BASE
            . "WHERE COALESCE(p.ESTADO, m.ESTADO, a.ESTADO) = 1 ORDER BY NOMBRE";
        $ps = ConexionDB::obtener()->query($sql);
        $lista = [];
        while ($fila = $ps->fetch()) {
            $lista[] = $this->mapFila($fila);
        }
        return $lista;
    }

    private function buscarSeguro(string $txt): ?InventarioItem
    {
        $id = $this->enteroOpcional($txt);
        return $id !== null && $id > 0 ? (new InventarioDAO())->buscarPorId($id) : null;
    }

    private function mapFila(array $fila): InventarioItem
    {
        $it = new InventarioItem();
        $it->idInventario = (int) $fila['ID_INVENTARIO'];
        $it->stockAct = (int) $fila['STOCK_ACT'];
        $it->stockMin = (int) $fila['STOCK_MIN'];
        $it->tipoAlmacen = (string) $fila['TIPO_ALMACEN'];
        $it->unidadMedida = (string) $fila['UNIDAD_MEDIDA'];
        $it->nombre = (string) $fila['NOMBRE'];
        $it->tipo = (string) ($fila['TIPO'] ?? '');
        $it->categoria = (string) ($fila['CATEGORIA'] ?? '');
        $it->precioVenta = $fila['PRECIO_VENTA'] !== null ? (float) $fila['PRECIO_VENTA'] : null;
        $it->precioCompra = $fila['PRECIO_COMPRA'] !== null ? (float) $fila['PRECIO_COMPRA'] : null;
        $it->clase = (string) $fila['CLASE'];
        return $it;
    }

    /** Datos del formulario que hoy no están en el modelo PHP (ESTADO, etc.). */
    private function extras(int $id): array
    {
        $ps = ConexionDB::obtener()->prepare(self::SQL_BASE . "WHERE i.ID_INVENTARIO = ?");
        $ps->execute([$id]);
        $fila = $ps->fetch();

        if ($fila === false) {
            return $this->extrasPorFormulario(false);
        }

        return [
            'idProducto'     => (int) ($fila['ID_PRODUCTO'] ?? 0),
            'idMaterial'     => (int) ($fila['ID_MATERIAL'] ?? 0),
            'idArreglo'      => (int) ($fila['ID_DETALLE'] ?? 0),
            'idCategoria'    => (int) ($fila['ID_CATEGORIA'] ?? 0),
            'estado'         => (bool) ($fila['ESTADO'] ?? true),
            'idMaterialVinc' => (int) ($fila['ID_MATERIAL_VINC'] ?? 0),
        ];
    }

    /** Valores por defecto / recuperados del POST para el formulario. */
    private function extrasPorFormulario(bool $edicion): array
    {
        return [
            'idProducto'     => $edicion ? enteroParam('idProducto') : 0,
            'idMaterial'     => $edicion ? enteroParam('idMaterial') : 0,
            'idArreglo'      => $edicion ? enteroParam('idArreglo') : 0,
            'idCategoria'    => enteroParam('idCategoria'),
            'estado'         => !$edicion || valorParam('estado') === 'on',
            'idMaterialVinc' => enteroParam('idMaterialVinc'),
        ];
    }

    private function aplicarExtras(InventarioItem $item, array $extras): void
    {
        foreach ($extras as $clave => $valor) {
            $this->asignar($item, $clave, $valor);
        }
    }

    /** @return Categoria[] */
    private function listarCategorias(): array
    {
        $dao = new InventarioDAO();
        if (method_exists($dao, 'listarCategorias')) {
            return $dao->listarCategorias();
        }
        return (new CategoriaDAO())->listar();
    }

    /**
     * El JSP esperaba Categoria(id, nombre) con el ID del MATERIAL.
     * Se mantiene ese contrato aunque el DAO todavía no tenga el método.
     *
     * @return Categoria[]
     */
    private function listarMaterialesActivos(): array
    {
        $dao = new InventarioDAO();
        if (method_exists($dao, 'listarMaterialesActivos')) {
            return $dao->listarMaterialesActivos();
        }

        $ps = ConexionDB::obtener()->query(
            "SELECT ID_MATERIAL, NOMBRE FROM dbo.MATERIAL WHERE ESTADO = 1 ORDER BY NOMBRE"
        );
        $lista = [];
        while ($fila = $ps->fetch()) {
            $c = new Categoria();
            $c->idCategoria = (int) $fila['ID_MATERIAL'];
            $c->nombre = (string) $fila['NOMBRE'];
            $lista[] = $c;
        }
        return $lista;
    }

    private function obOCrearCategoria(string $nombre): int
    {
        $dao = new InventarioDAO();
        if (method_exists($dao, 'obOCrearCategoria')) {
            return (int) $dao->obOCrearCategoria($nombre);
        }

        $cn = ConexionDB::obtener();
        $ps = $cn->prepare("SELECT ID_CATEGORIA FROM dbo.CATEGORIA WHERE NOMBRE = ?");
        $ps->execute([$nombre]);
        $id = $ps->fetchColumn();
        if ($id !== false && $id !== null) {
            return (int) $id;
        }
        return ConexionDB::insertarId(
            "INSERT INTO dbo.CATEGORIA (NOMBRE) VALUES (?)",
            [$nombre]
        );
    }

    private function guardarCrear(InventarioItem $item, array $extras): void
    {
        $dao = new InventarioDAO();
        if (method_exists($dao, 'crear') && $this->modeloCompleto($item)) {
            $dao->crear($item);
            return;
        }

        $clase = $item->clase;
        $idCategoria = ($extras['idCategoria'] ?? 0) > 0 ? (int) $extras['idCategoria'] : null;
        $estado = !empty($extras['estado']) ? 1 : 0;
        $idMatVinc = ($extras['idMaterialVinc'] ?? 0) > 0 ? (int) $extras['idMaterialVinc'] : null;
        $tipo = $clase === 'ARREGLO' || $item->tipo === '' ? null : $item->tipo;

        switch ($clase) {
            case 'PRODUCTO':
                $sqlEntidad = "INSERT INTO dbo.PRODUCTO "
                    . "(ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, PRECIO_VENTA, ESTADO, ID_MATERIAL) "
                    . "VALUES (?, ?, ?, ?, ?, ?, ?)";
                $params = [$idCategoria, $item->nombre, $tipo, $item->precioCompra,
                    $item->precioVenta, $estado, $idMatVinc];
                $columna = 'ID_PRODUCTO';
                break;
            case 'MATERIAL':
                $sqlEntidad = "INSERT INTO dbo.MATERIAL "
                    . "(ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, PRECIO_VENTA, ESTADO) "
                    . "VALUES (?, ?, ?, ?, ?, ?)";
                $params = [$idCategoria, $item->nombre, $tipo, $item->precioCompra,
                    $item->precioVenta, $estado];
                $columna = 'ID_MATERIAL';
                break;
            case 'ARREGLO':
                $sqlEntidad = "INSERT INTO dbo.ARREGLO "
                    . "(ID_CATEGORIA, NOMBRE, PRECIO_COMPRA, PRECIO_VENTA, ESTADO) "
                    . "VALUES (?, ?, ?, ?, ?)";
                $params = [$idCategoria, $item->nombre, $item->precioCompra,
                    $item->precioVenta, $estado];
                $columna = 'ID_DETALLE';
                break;
            default:
                throw new InvalidArgumentException('Clase no válida: ' . $clase);
        }

        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $idEntidad = ConexionDB::insertarId($sqlEntidad, $params);
            $ps = $cn->prepare("INSERT INTO dbo.INVENTARIO ("
                . $columna . ", STOCK_ACT, STOCK_MIN, TIPO_ALMACEN, UNIDAD_MEDIDA) "
                . "VALUES (?, ?, ?, ?, ?)");
            $ps->execute([$idEntidad, $item->stockAct, $item->stockMin,
                $item->tipoAlmacen, $item->unidadMedida]);
            $cn->commit();
        } catch (Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            throw $e;
        }
    }

    private function guardarActualizar(InventarioItem $item, array $extras, bool $admin): void
    {
        $dao = new InventarioDAO();
        if (method_exists($dao, 'actualizar') && $this->modeloCompleto($item)) {
            $dao->actualizar($item, $admin);
            return;
        }

        $clase = $item->clase;
        $idCategoria = ($extras['idCategoria'] ?? 0) > 0 ? (int) $extras['idCategoria'] : null;
        $estado = !empty($extras['estado']) ? 1 : 0;
        $idMatVinc = ($extras['idMaterialVinc'] ?? 0) > 0 ? (int) $extras['idMaterialVinc'] : null;
        $tipo = $item->tipo === '' ? null : $item->tipo;
        $idEntidad = match ($clase) {
            'PRODUCTO' => (int) ($extras['idProducto'] ?? 0),
            'MATERIAL' => (int) ($extras['idMaterial'] ?? 0),
            'ARREGLO'  => (int) ($extras['idArreglo'] ?? 0),
            default    => throw new InvalidArgumentException('Clase no válida: ' . $clase),
        };

        if ($admin) {
            $sql = match ($clase) {
                'PRODUCTO' => "UPDATE dbo.PRODUCTO SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, "
                    . "PRECIO_COMPRA=?, PRECIO_VENTA=?, ESTADO=?, ID_MATERIAL=? WHERE ID_PRODUCTO=?",
                'MATERIAL' => "UPDATE dbo.MATERIAL SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, "
                    . "PRECIO_COMPRA=?, PRECIO_VENTA=?, ESTADO=? WHERE ID_MATERIAL=?",
                'ARREGLO'  => "UPDATE dbo.ARREGLO SET ID_CATEGORIA=?, NOMBRE=?, PRECIO_COMPRA=?, "
                    . "PRECIO_VENTA=?, ESTADO=? WHERE ID_DETALLE=?",
            };
        } else {
            $sql = match ($clase) {
                'PRODUCTO' => "UPDATE dbo.PRODUCTO SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, "
                    . "ID_MATERIAL=? WHERE ID_PRODUCTO=?",
                'MATERIAL' => "UPDATE dbo.MATERIAL SET ID_CATEGORIA=?, NOMBRE=?, TIPO=? "
                    . "WHERE ID_MATERIAL=?",
                'ARREGLO'  => "UPDATE dbo.ARREGLO SET ID_CATEGORIA=?, NOMBRE=? WHERE ID_DETALLE=?",
            };
        }

        $params = [$idCategoria, $item->nombre];
        if ($clase !== 'ARREGLO') {
            $params[] = $tipo;
        }
        if ($admin) {
            $params[] = $item->precioCompra;
            $params[] = $item->precioVenta;
            $params[] = $estado;
        }
        if ($clase === 'PRODUCTO') {
            $params[] = $idMatVinc;
        }
        $params[] = $idEntidad;

        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $cn->prepare($sql)->execute($params);
            $cn->prepare("UPDATE dbo.INVENTARIO SET STOCK_MIN=?, UNIDAD_MEDIDA=?, "
                . "TIPO_ALMACEN=? WHERE ID_INVENTARIO=?")->execute([
                    $item->stockMin, $item->unidadMedida, $item->tipoAlmacen, $item->idInventario
                ]);
            $cn->commit();
        } catch (Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            throw $e;
        }
    }

    private function guardarAjuste(int $id, int $delta, string $motivo, int $idUsuario): void
    {
        $dao = new InventarioDAO();
        if (method_exists($dao, 'ajustarStock')) {
            $dao->ajustarStock($id, $delta, $motivo, $idUsuario);
            return;
        }

        $tipoMov = $delta > 0 ? 'ENTRADA' : 'SALIDA';
        $cantidad = abs($delta);
        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $idAjuste = ConexionDB::insertarId(
                "INSERT INTO dbo.AJUSTE_INVENTARIO (ID_USUARIO, FECHA) VALUES (?, ?)",
                [$idUsuario, date('Y-m-d H:i:s')]
            );
            $cn->prepare("INSERT INTO dbo.DETALLE_AJUSTE "
                . "(ID_AJUSTE, ID_INVENTARIO, CANTIDAD, TIPO_AJUSTE, MOTIVO) "
                . "VALUES (?, ?, ?, ?, ?)")->execute([
                    $idAjuste, $id, $delta, 'MANUAL', $motivo
                ]);

            $ps = $cn->prepare("UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT + ? "
                . "WHERE ID_INVENTARIO = ? AND STOCK_ACT + ? >= 0");
            $ps->execute([$delta, $id, $delta]);
            if ($ps->rowCount() === 0) {
                throw new \RuntimeException('No hay stock suficiente para esa salida');
            }

            $cn->prepare("INSERT INTO dbo.MOVIMIENTO_INVENTARIO "
                . "(ID_USUARIO, ID_INVENTARIO, FECHA_MOVIMIENTO, TIPO_MOVIMIENTO, CANTIDAD) "
                . "VALUES (?, ?, SYSUTCDATETIME(), ?, ?)")->execute([
                    $idUsuario, $id, $tipoMov, $cantidad
                ]);
            $cn->commit();
        } catch (Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            throw $e;
        }
    }

    private function modeloCompleto(InventarioItem $item): bool
    {
        foreach (['idCategoria', 'estado', 'idMaterialVinc',
                  'idProducto', 'idMaterial', 'idArreglo'] as $propiedad) {
            if (!property_exists($item, $propiedad)) {
                return false;
            }
        }
        return true;
    }

    private function fallar(string $mensaje): never
    {
        flash('error', $mensaje, true);
        $this->ir('/inventario');
    }

    private function enteroOpcional(string $txt): ?int
    {
        $v = trim($txt);
        return preg_match('/^-?\d+$/', $v) === 1 ? (int) $v : null;
    }

    private function decimalOpcional(string $txt): ?float
    {
        $v = str_replace(',', '.', trim($txt));
        if ($v === '' || preg_match('/^\d+(?:\.\d+)?$/', $v) !== 1) {
            return null;
        }
        $numero = (float) $v;
        return $numero <= self::MAX_PRECIO ? $numero : null;
    }

    /** Evita propiedades dinámicas si el modelo aún no declara el campo. */
    private function asignar(object $destino, string $propiedad, mixed $valor): void
    {
        if (property_exists($destino, $propiedad)) {
            $destino->{$propiedad} = $valor;
        }
    }
}
