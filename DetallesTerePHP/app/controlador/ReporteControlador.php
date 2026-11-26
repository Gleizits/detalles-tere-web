<?php

namespace Controlador;

use Dao\ReporteDAO;
use DateTime;
use InvalidArgumentException;
use Throwable;

/**
 * Reportes diarios y mensuales (solo admin).
 *
 * Nota de migración: el original generaba un PDF con iText. En PHP puro no
 * hay librería PDF instalada y no se permite composer, así que la descarga
 * es CSV con BOM UTF-8: se abre en Excel/Calc y conserva TODOS los datos
 * (resumen, ventas, ingresos y cajas) sin dependencias externas.
 */
final class ReporteControlador extends ControladorBase
{
    /** GET /reportes (?tipo=dia|mes&periodo=...&formato=csv) */
    public function index(): never
    {
        $tipo = valorParam('tipo') === 'mes' ? 'mes' : 'dia';
        $valor = valorParam('periodo');
        if ($valor === '') {
            $valor = $tipo === 'mes' ? date('Y-m') : date('Y-m-d');
        }

        try {
            [$desde, $hasta] = $this->periodo($tipo, $valor);
            $reporte = (new ReporteDAO())->generar($desde, $hasta);
        } catch (InvalidArgumentException $e) {
            $this->ver('reportes', 'Reportes', '/reportes', [
                'error'        => $e->getMessage(),
                'reporte'      => null,
                'tipoPeriodo'  => $tipo,
                'valorPeriodo' => $valor,
            ]);
        } catch (Throwable $e) {
            error_log('Error al generar reporte: ' . $e->getMessage());
            $this->ver('reportes', 'Reportes', '/reportes', [
                'error'        => 'No se pudo generar el reporte. Intenta de nuevo.',
                'reporte'      => null,
                'tipoPeriodo'  => $tipo,
                'valorPeriodo' => $valor,
            ]);
        }

        // "pdf" se acepta para no romper enlaces antiguos; ahora entrega CSV.
        if (in_array(valorParam('formato'), ['csv', 'pdf'], true)) {
            $this->descargarCsv($reporte, $valor);
        }

        $this->ver('reportes', 'Reportes', '/reportes', [
            'error'        => null,
            'reporte'      => $reporte,
            'tipoPeriodo'  => $tipo,
            'valorPeriodo' => $valor,
        ]);
    }

    /** POST /reportes: normaliza los filtros y vuelve a la vista. */
    public function procesar(): never
    {
        $tipo = valorParam('tipo') === 'mes' ? 'mes' : 'dia';
        $periodo = valorParam('periodo');
        if ($periodo === '') {
            $periodo = $tipo === 'mes' ? date('Y-m') : date('Y-m-d');
        }

        $consulta = http_build_query(['tipo' => $tipo, 'periodo' => $periodo]);
        $formato = valorParam('formato');
        if ($formato !== '') {
            $consulta .= '&formato=' . rawurlencode($formato);
        }

        $this->ir('/reportes?' . $consulta);
    }

    /** @return array{0:string,1:string} */
    private function periodo(string $tipo, string $valor): array
    {
        if ($tipo === 'mes') {
            $fecha = DateTime::createFromFormat('!Y-m', $valor);
            if ($fecha === false || $fecha->format('Y-m') !== $valor) {
                throw new InvalidArgumentException('Selecciona un periodo válido.');
            }
            return [$valor . '-01', date('Y-m-t', $fecha->getTimestamp())];
        }

        $fecha = DateTime::createFromFormat('!Y-m-d', $valor);
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException('Selecciona un periodo válido.');
        }
        return [$valor, $valor];
    }

    private function descargarCsv(object $reporte, string $valor): never
    {
        $nombre = 'reporte-detallestere-' . preg_replace('/[^0-9]/', '', $valor) . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $salida = fopen('php://output', 'w');
        // BOM para que Excel reconozca los acentos.
        fwrite($salida, "\xEF\xBB\xBF");
        foreach ($this->filasCsv($reporte) as $fila) {
            fputcsv($salida, $fila, ',', '"', '');
        }
        fclose($salida);
        exit;
    }

    /** Todas las secciones del reporte, en filas de CSV. */
    private function filasCsv(object $reporte): array
    {
        $filas = [
            ['DetallesTere · Reporte administrativo'],
            [$this->prop($reporte, 'etiquetaPeriodo', '')],
            [],
            ['RESUMEN'],
            ['Desde', $this->prop($reporte, 'desde', '')],
            ['Hasta', $this->prop($reporte, 'hasta', '')],
            ['Ventas totales (S/)', $this->dinero($this->prop($reporte, 'ventasTotales', 0))],
            ['Operaciones de venta', $this->prop($reporte, 'cantidadVentas', 0)],
            ['Unidades vendidas', $this->prop($reporte, 'unidadesVendidas', 0)],
            ['Ingresos totales (S/)', $this->dinero($this->prop($reporte, 'ingresosTotales', 0))],
            ['Unidades ingresadas', $this->prop($reporte, 'unidadesIngresadas', 0)],
            ['Margen estimado (S/)', $this->dinero($this->prop($reporte, 'margenEstimado', 0))],
            ['Efectivo (S/)', $this->dinero($this->prop($reporte, 'efectivo', 0))],
            ['Yape (S/)', $this->dinero($this->prop($reporte, 'yape', 0))],
            ['Crédito (S/)', $this->dinero($this->prop($reporte, 'credito', 0))],
        ];

        if (property_exists($reporte, 'cajasTotales')) {
            $filas[] = ['Cajas (S/)', $this->dinero($reporte->cajasTotales)];
        }

        $filas[] = [];
        $filas[] = ['DETALLE DE VENTAS'];
        $filas[] = ['Fecha', 'Artículo', 'Cantidad', 'Precio venta (S/)',
            'Subtotal (S/)', 'Costo vigente (S/)', 'Margen (S/)'];
        foreach ($this->lista($reporte, 'ventas') as $linea) {
            $filas[] = [
                $this->fechaLinea($linea),
                $this->prop($linea, 'articulo', ''),
                $this->prop($linea, 'cantidad', 0),
                $this->dinero($this->prop($linea, 'precioVenta', 0)),
                $this->dinero($this->prop($linea, 'subtotal', 0)),
                $this->dinero($this->prop($linea, 'costeVigente', 0)),
                $this->dinero($this->prop($linea, 'margen', 0)),
            ];
        }

        $filas[] = [];
        $filas[] = ['DETALLE DE INGRESOS'];
        $filas[] = ['Fecha', 'Proveedor', 'Artículo', 'Cantidad',
            'Costo unitario (S/)', 'Subtotal (S/)'];
        foreach ($this->lista($reporte, 'ingresos') as $linea) {
            $filas[] = [
                $this->fechaProp($linea, 'fecha', 'd/m/Y'),
                $this->prop($linea, 'proveedor', ''),
                $this->prop($linea, 'articulo', ''),
                $this->prop($linea, 'cantidad', 0),
                $this->dinero($this->prop($linea, 'precioCompra', 0)),
                $this->dinero($this->prop($linea, 'subtotal', 0)),
            ];
        }

        $cajas = $this->lista($reporte, 'cajas');
        if ($cajas !== []) {
            $filas[] = [];
            $filas[] = ['CAJAS'];
            $filas[] = ['Usuario', 'Apertura', 'Cierre', 'Monto inicial (S/)',
                'Monto final (S/)', 'Monto real (S/)', 'Diferencia (S/)'];
            foreach ($cajas as $caja) {
                $filas[] = [
                    $this->prop($caja, 'nombreUsuario', ''),
                    $this->fechaProp($caja, 'fechaApertura', 'd/m/Y H:i'),
                    $this->fechaProp($caja, 'fechaCierre', 'd/m/Y H:i'),
                    $this->dinero($this->prop($caja, 'montoInicial', 0)),
                    $this->dinero($this->prop($caja, 'montoFinal', 0)),
                    $this->dinero($this->prop($caja, 'montoReal', 0)),
                    $this->dinero($this->prop($caja, 'diferencia', 0)),
                ];
            }
        }

        return $filas;
    }

    private function fechaLinea(object $linea): string
    {
        $texto = $this->prop($linea, 'fechaTexto', '');
        return $texto !== '' ? (string) $texto
            : $this->fechaProp($linea, 'fecha', 'd/m/Y H:i');
    }

    private function fechaProp(object $origen, string $nombre, string $formato): string
    {
        $valor = $this->prop($origen, $nombre, null);
        return fecha($valor === null || $valor === '' ? null : (string) $valor, $formato);
    }

    private function dinero(mixed $valor): string
    {
        return num($valor, 2, false);
    }

    private function prop(object $origen, string $nombre, mixed $defecto): mixed
    {
        return property_exists($origen, $nombre) ? $origen->{$nombre} : $defecto;
    }

    /** @return array<int, object> */
    private function lista(object $origen, string $nombre): array
    {
        $valor = $this->prop($origen, $nombre, []);
        return is_array($valor) ? $valor : [];
    }
}
