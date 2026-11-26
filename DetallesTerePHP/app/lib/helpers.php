<?php

/**
 * Helpers globales: escape, formateo de números/fechas, mensajes flash y
 * redirecciones. Sustituyen a JSTL (c:out, fmt:formatNumber, fmt:formatDate).
 */

/** Escapa texto para HTML (equivale a <c:out>). */
function e(mixed $valor): string
{
    return htmlspecialchars($valor === null ? '' : (string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL absoluta respetando la base (equivale a ${pageContext.request.contextPath}). */
function url(string $ruta = ''): string
{
    return BASE_URL . $ruta;
}

/**
 * Número con 2 decimales y separador de miles (fmt:formatNumber type=number).
 * Devuelve '' si el valor es null (también en fmt:formatNumber).
 */
function num($valor, int $decimales = 2, bool $agrupar = true): string
{
    if ($valor === null || $valor === '') {
        return '';
    }
    return number_format((float) $valor, $decimales, '.', $agrupar ? ',' : '');
}

/** Formato compacto sin agrupación, para JavaScript (groupingUsed=false). */
function numJs($valor): string
{
    return num($valor, 2, false);
}

/**
 * Fecha u hora desde un string de SQL Server (datetime2 / date).
 * Formatos equivalentes a los pattern de fmt:formatDate:
 *   fecha($v)             -> dd/MM/yyyy
 *   fecha($v, 'H:i')      -> solo hora
 *   fecha($v, 'd/m H:mm') -> dd/MM HH:mm
 */
function fecha(?string $valor, string $formato = 'd/m/Y'): string
{
    if ($valor === null || trim($valor) === '') {
        return '';
    }
    // Recorta los 7 decimales de datetime2: 2026-11-24 21:51:37.1234567
    $valor = preg_replace('/\.\d+$/', '', trim($valor));
    $ts = strtotime($valor);
    return $ts === false ? '' : date($formato, $ts);
}

/** Redirección 302 y fin del script (resp.sendRedirect). */
function redirigir(string $ruta): never
{
    header('Location: ' . $ruta);
    exit;
}

// ------------------------------------------------------------------
// Mensajes flash (equivalente a los atributos de sesión de los servlets)
// ------------------------------------------------------------------

/**
 * Guarda un mensaje para la siguiente petición.
 *
 * @param string      $clave  Identificador corto ('aviso', 'error'...).
 * @param string      $mensaje Texto a mostrar.
 * @param bool        $error  true para pintarlo como error.
 * @param string      $titulo Opcional, encabezado del aviso.
 */
function flash(string $clave, string $mensaje, bool $error = false, string $titulo = ''): void
{
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }

    $_SESSION['_flash'][] = [
        'clave'    => $clave,
        'mensaje'  => $mensaje,
        'error'    => $error,
        'titulo'   => $titulo,
    ];
}

// ------------------------------------------------------------------
// Parámetros de petición (como valor()/entero()/decimal() en los servlets)
// ------------------------------------------------------------------

function valorParam(string $clave, string $defecto = ''): string
{
    $v = $_POST[$clave] ?? $_GET[$clave] ?? $defecto;
    return is_string($v) ? trim($v) : $defecto;
}

function enteroParam(string $clave, int $defecto = 0): int
{
    $v = valorParam($clave);
    return preg_match('/^-?\d+$/', $v) === 1 ? (int) $v : $defecto;
}

function decimalParam(string $clave): ?float
{
    $v = str_replace(',', '.', valorParam($clave));
    if ($v === '' || !is_numeric($v)) {
        return null;
    }
    return (float) $v;
}

/** Saludo según la hora (PanelServlet.saludoPorHorario). */
function saludoPorHorario(): string
{
    $hora = (int) date('G');
    if ($hora < 12) {
        return 'Buenos días';
    }
    if ($hora < 19) {
        return 'Buenas tardes';
    }
    return 'Buenas noches';
}

/**
 * Mensaje amigable a partir de una excepción (PedidoServlet.mensajeDe).
 * Recorre la cadena de causas como hacía el original.
 */
function mensajeDe(Throwable $e): string
{
    $t = $e;
    while ($t->getPrevious() !== null) {
        $t = $t->getPrevious();
    }
    $m = $t->getMessage();

    if ($m !== null && stripos($m, 'stock') !== false) {
        return 'No hay stock suficiente para apartar ese ítem. Quita o reduce la cantidad.';
    }
    if ($m !== null && str_contains($m, 'pedido (revísalo')) {
        return 'No se pudo actualizar el pedido. Puede que ya no esté en ese estado.';
    }
    return 'Ocurrió un error. Revisa los datos e inténtalo de nuevo.';
}
