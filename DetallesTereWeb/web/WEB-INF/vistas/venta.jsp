<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<%@taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="titulo-seccion mb-0">
            <i class="bi bi-cart-check me-2"></i>Venta
        </h1>
        <c:choose>
            <c:when test="${not empty caja}">
                <span class="badge-punto-abierto">Caja abierta</span>
            </c:when>
            <c:otherwise>
                <span class="badge-punto-cerrado">Caja cerrada</span>
            </c:otherwise>
        </c:choose>
    </div>

    <c:if test="${not empty mensaje}">
        <div class="${mensajeError ? 'alerta-error' : 'alerta-exito'} mb-4">
            <i class="bi ${mensajeError ? 'bi-exclamation-triangle' : 'bi-check-circle'} me-2"></i>
            <c:out value="${mensaje}"/>
        </div>
    </c:if>

    <c:if test="${empty caja}">
        <div class="alerta-error mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>
                <i class="bi bi-exclamation-triangle me-2"></i>
                No tienes la caja abierta. Ábrela para poder cobrar.
            </span>
            <a class="btn btn-app btn-app-primary" href="${ctx}/caja">Ir a Caja</a>
        </div>
    </c:if>

    <div class="venta-grid">

        <!-- ===================== CATÁLOGO ===================== -->
        <c:if test="${empty cotizacionId}">
            <div class="app-card p-3">
                <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">Agregar productos</h2>

                <form action="${ctx}/venta" method="get" class="mb-3" role="search">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control" name="q" value="${q}"
                               placeholder="Buscar producto o término…" aria-label="Buscar productos">
                        <button class="btn btn-app btn-app-primary" type="submit">Buscar</button>
                    </div>
                </form>

                <c:choose>
                    <c:when test="${empty items}">
                        <p class="text-muted mb-0">Sin resultados en el inventario.</p>
                    </c:when>
                    <c:otherwise>
                        <div class="tabla-envoltorio">
                            <table class="tabla-app">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="num">P. venta</th>
                                        <th class="num">Stock</th>
                                        <th>Agregar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <c:forEach var="it" items="${items}">
                                        <tr>
                                            <td class="nombre-item"><c:out value="${it.nombre}"/></td>
                                            <td class="num">
                                                <c:choose>
                                                    <c:when test="${not empty it.precioVenta}">
                                                        S/ <fmt:formatNumber value="${it.precioVenta}" minFractionDigits="2"/>
                                                    </c:when>
                                                    <c:otherwise>—</c:otherwise>
                                                </c:choose>
                                            </td>
                                            <td class="num">
                                                <c:choose>
                                                    <c:when test="${it.stockAct <= it.stockMin}">
                                                        <span class="badge-estado bajo"><c:out value="${it.stockAct}"/></span>
                                                    </c:when>
                                                    <c:otherwise><c:out value="${it.stockAct}"/></c:otherwise>
                                                </c:choose>
                                            </td>
                                            <td>
                                                <form class="catalogo-form" method="post" action="${ctx}/venta">
                                                    <input type="hidden" name="accion" value="agregar">
                                                    <input type="hidden" name="idInventario" value="${it.idInventario}">
                                                    <input type="number" class="form-control cant-input"
                                                           name="cantidad" value="1" min="1"
                                                           <c:if test="${it.stockAct > 0}">max="${it.stockAct}"</c:if>
                                                           aria-label="Cantidad">
                                                    <button class="btn btn-app btn-app-primary btn-sm"
                                                            <c:if test="${it.stockAct == 0 || empty it.precioVenta}">disabled</c:if>>
                                                        Agregar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    </c:forEach>
                                </tbody>
                            </table>
                        </div>
                    </c:otherwise>
                </c:choose>
            </div>
        </c:if>

        <!-- ===================== CARRITO ===================== -->
        <div class="app-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="titulo-seccion mb-0" style="font-size:1.25rem">Carrito</h2>
                <c:if test="${not empty cotizacionId}">
                    <span class="badge-estado abierto">Cotización #${cotizacionId}</span>
                </c:if>
            </div>

            <c:if test="${not empty cotizacionId}">
                <div class="alerta-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    Cotización cargada. Al cobrar se convertirá en venta y se descontará su stock.
                </div>
            </c:if>

            <c:choose>
                <c:when test="${empty carrito}">
                    <p class="text-muted mb-0">El carrito está vacío. Agrega productos desde el catálogo.</p>
                </c:when>
                <c:otherwise>
                    <div class="tabla-envoltorio">
                        <table class="tabla-app">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="num">Cant.</th>
                                    <th class="num">P. unit.</th>
                                    <th class="num">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <c:forEach var="l" items="${carrito}" varStatus="st">
                                    <tr>
                                        <td><c:out value="${l.nombre}"/></td>
                                        <td class="num">${l.cantidad}</td>
                                        <td class="num">S/ <fmt:formatNumber value="${l.costoUnitario}" minFractionDigits="2"/></td>
                                        <td class="num">S/ <fmt:formatNumber value="${l.subtotal}" minFractionDigits="2"/></td>
                                        <td class="text-end">
                                            <c:if test="${empty cotizacionId}">
                                                <form method="post" action="${ctx}/venta">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="indice" value="${st.index}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                            title="Quitar del carrito" aria-label="Quitar del carrito">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </c:if>
                                        </td>
                                    </tr>
                                </c:forEach>
                            </tbody>
                        </table>
                    </div>

                    <div class="carrito-total">
                        <span class="etq">Total</span>
                        <span class="val">S/ <fmt:formatNumber value="${totalCarrito}" minFractionDigits="2"/></span>
                    </div>

                    <!-- ====== PAGO ====== -->
                    <form method="post" action="${ctx}/venta" class="pago-zona mt-3">
                        <input type="hidden" name="accion" value="cobrar">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Método de pago</label>
                            <div class="d-flex gap-4 mt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipoPago"
                                           id="pagoEfectivo" value="EFECTIVO" checked onchange="actualizarPago()">
                                    <label class="form-check-label fs-5" for="pagoEfectivo">
                                        <i class="bi bi-cash me-1"></i>Efectivo
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipoPago"
                                           id="pagoYape" value="YAPE" onchange="actualizarPago()">
                                    <label class="form-check-label fs-5" for="pagoYape">
                                        <i class="bi bi-phone me-1"></i>Yape
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipoPago"
                                           id="pagoCredito" value="CREDITO" onchange="actualizarPago()">
                                    <label class="form-check-label fs-5" for="pagoCredito">
                                        <i class="bi bi-wallet2 me-1"></i>Saldo a favor
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3" id="zonaCredito" style="display:none">
                            <label class="form-label fw-bold" for="celularCredito">
                                Celular del cliente con saldo a favor
                            </label>
                            <input type="tel" class="form-control" name="celularCredito"
                                   id="celularCredito" maxlength="12" inputmode="tel"
                                   placeholder="Ej: 999888777">
                            <small class="text-muted">
                                El total debe coincidir exactamente con todo su saldo vigente.
                            </small>
                        </div>

                        <div class="mb-3" id="zonaEfectivo">
                            <label class="form-label fw-bold" for="montoRecibido">Monto recibido (S/)</label>
                            <input type="number" step="0.01" min="0" inputmode="decimal"
                                   class="form-control" name="montoRecibido" id="montoRecibido"
                                   placeholder="Ej: 50" oninput="calcularVuelto()">
                            <div class="vuelto-linea mt-2">
                                <span>Vuelto</span>
                                <span id="vuelto">S/ 0.00</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-app btn-app-primary w-100 py-2">
                            <i class="bi bi-cash-stack me-1"></i>
                            Cobrar S/ <span id="totalVisible"><fmt:formatNumber value="${totalCarrito}" minFractionDigits="2" groupingUsed="false"/></span>
                        </button>
                    </form>

                    <!-- ====== ACCIONES ====== -->
                    <c:choose>
                        <c:when test="${not empty cotizacionId}">
                            <form method="post" action="${ctx}/venta" class="mt-2">
                                <input type="hidden" name="accion" value="cancelar_carga">
                                <button type="submit" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-x-circle me-1"></i>Cancelar carga de cotización
                                </button>
                            </form>
                        </c:when>
                        <c:otherwise>
                            <div class="d-flex gap-2 mt-2 flex-wrap">
                                <form method="post" action="${ctx}/venta" class="flex-fill">
                                    <input type="hidden" name="accion" value="cotizar">
                                    <button type="submit" class="btn btn-outline-secondary w-100">
                                        <i class="bi bi-file-earmark-text me-1"></i>Guardar cotización (24 h)
                                    </button>
                                </form>
                                <form method="post" action="${ctx}/venta" class="flex-fill">
                                    <input type="hidden" name="accion" value="vaciar">
                                    <button type="submit" class="btn btn-outline-danger w-100">
                                        <i class="bi bi-trash me-1"></i>Vaciar
                                    </button>
                                </form>
                            </div>
                        </c:otherwise>
                    </c:choose>
                </c:otherwise>
            </c:choose>
        </div>
    </div>

    <!-- ===================== COTIZACIONES ===================== -->
    <div class="app-card mt-4">
        <div class="p-3 pb-2">
            <h2 class="titulo-seccion mb-1" style="font-size:1.25rem">
                <i class="bi bi-clock-history me-2"></i>Cotizaciones activas (24 h)
            </h2>
            <p class="text-muted mb-0">Se borran solas al vencer. Carga una para cobrarla.</p>
        </div>

        <c:choose>
            <c:when test="${empty cotizaciones}">
                <p class="text-muted p-3 mb-0">No hay cotizaciones activas.</p>
            </c:when>
            <c:otherwise>
                <c:forEach var="co" items="${cotizaciones}">
                    <div class="cotiz-tarjeta">
                        <div>
                            <strong>Cotización #${co.idVenta}</strong>
                            <div class="cotiz-resumen"><c:out value="${co.resumen}"/></div>
                            <small class="text-muted">
                                Creada: <fmt:formatDate value="${co.fechaVentaDate}" pattern="dd/MM/yyyy HH:mm"/>
                                · ${co.numLineas} producto(s)
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <strong class="fs-5">S/ <fmt:formatNumber value="${co.total}" minFractionDigits="2"/></strong>
                            <form method="post" action="${ctx}/venta">
                                <input type="hidden" name="accion" value="cargar_cotizacion">
                                <input type="hidden" name="idVenta" value="${co.idVenta}">
                                <button type="submit" class="btn btn-app btn-app-primary btn-sm">Cargar</button>
                            </form>
                        </div>
                    </div>
                </c:forEach>
            </c:otherwise>
        </c:choose>
    </div>

<script>
    var TOTAL = parseFloat('<fmt:formatNumber value="${totalCarrito}" minFractionDigits="2" groupingUsed="false"/>');

    function actualizarPago() {
        var yape = document.getElementById('pagoYape').checked;
        var credito = document.getElementById('pagoCredito').checked;
        var zona = document.getElementById('zonaEfectivo');
        var zonaCredito = document.getElementById('zonaCredito');
        var monto = document.getElementById('montoRecibido');
        if (yape || credito) {
            zona.style.display = 'none';
            monto.value = '';
        } else {
            zona.style.display = '';
        }
        zonaCredito.style.display = credito ? '' : 'none';
        calcularVuelto();
    }

    function calcularVuelto() {
        var monto = parseFloat(document.getElementById('montoRecibido').value || '0');
        if (isNaN(monto)) monto = 0;
        var v = monto - TOTAL;
        document.getElementById('vuelto').textContent = 'S/ ' + (v > 0 ? v.toFixed(2) : '0.00');
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('montoRecibido')) {
            actualizarPago();
        }
    });
</script>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
