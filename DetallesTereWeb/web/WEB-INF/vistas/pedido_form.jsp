<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<%@taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="titulo-seccion mb-0">
            <i class="bi bi-calendar2-plus me-2"></i>Nuevo pedido reservado
        </h1>
        <a class="btn btn-outline-secondary" href="${ctx}/pedidos">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    <c:if test="${not empty mensaje}">
        <div class="${mensajeError ? 'alerta-error' : 'alerta-exito'} mb-4">
            <i class="bi ${mensajeError ? 'bi-exclamation-triangle' : 'bi-check-circle'} me-2"></i>
            <c:out value="${mensaje}"/>
        </div>
    </c:if>

    <c:if test="${empty caja}">
        <div class="alerta-error mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-exclamation-triangle me-2"></i>No tienes la caja abierta. Ábrela para cobrar el adelanto.</span>
            <a class="btn btn-app btn-app-primary" href="${ctx}/caja">Ir a Caja</a>
        </div>
    </c:if>

    <div class="venta-grid">

        <!-- ===================== CATÁLOGO ===================== -->
        <div class="app-card p-3">
            <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">Agregar productos</h2>

            <form action="${ctx}/pedidos" method="get" class="mb-3" role="search">
                <input type="hidden" name="nuevo" value="1">
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
                                            <form class="catalogo-form" method="post" action="${ctx}/pedidos">
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

        <!-- ===================== CARRITO ===================== -->
        <div class="app-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="titulo-seccion mb-0" style="font-size:1.25rem">Carrito de la reserva</h2>
                <c:if test="${not empty carrito}">
                    <form method="post" action="${ctx}/pedidos">
                        <input type="hidden" name="accion" value="vaciar">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash me-1"></i>Vaciar
                        </button>
                    </form>
                </c:if>
            </div>

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
                                    <th class="num">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <c:forEach var="l" items="${carrito}" varStatus="st">
                                    <tr>
                                        <td><c:out value="${l.nombre}"/></td>
                                        <td class="num">${l.cantidad}</td>
                                        <td class="num">S/ <fmt:formatNumber value="${l.subtotal}" minFractionDigits="2"/></td>
                                        <td class="text-end">
                                            <form method="post" action="${ctx}/pedidos">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="indice" value="${st.index}">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        title="Quitar del carrito" aria-label="Quitar del carrito">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
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
                </c:otherwise>
            </c:choose>
        </div>
    </div>

    <!-- ===================== DATOS DEL CLIENTE Y PAGO ===================== -->
    <c:if test="${not empty carrito}">
        <div class="app-card p-4 mt-4">
            <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">
                <i class="bi bi-person-vcard me-2"></i>Datos del cliente y pago
            </h2>

            <form method="post" action="${ctx}/pedidos" autocomplete="off">
                <input type="hidden" name="accion" value="crear">

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="celular">Celular del cliente *</label>
                        <input type="tel" class="form-control" id="celular" name="celular"
                               required maxlength="12" placeholder="Ej: 987654321">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="nombrePaga">Nombre de quien paga *</label>
                        <input type="text" class="form-control" id="nombrePaga" name="nombrePaga"
                               required maxlength="90" placeholder="Ej: María López">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="nombreRecoge">Nombre de quien recoge</label>
                        <input type="text" class="form-control" id="nombreRecoge" name="nombreRecoge"
                               maxlength="90" placeholder="Opcional">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="fechaRecojo">Día de recojo *</label>
                        <input type="date" class="form-control" id="fechaRecojo" name="fechaRecojo"
                               required min="${hoy}" value="${hoy}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="horaRecojo">Hora</label>
                        <input type="time" class="form-control" id="horaRecojo" name="horaRecojo">
                    </div>
                </div>

                <div class="pago-zona">
                    <div class="row g-4">
                        <!-- Tipo de reserva -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">¿Cuánto se paga ahora?</label>
                            <div class="d-flex flex-column gap-2 mt-1">
                                <div class="form-check fs-5">
                                    <input class="form-check-input" type="radio" name="tipoReserva"
                                           id="tipoAdelanto" value="adelanto" checked onchange="actualizarTipo()">
                                    <label class="form-check-label" for="tipoAdelanto">
                                        A cuenta / adelanto (mínimo 50%)
                                    </label>
                                </div>
                                <div class="form-check fs-5">
                                    <input class="form-check-input" type="radio" name="tipoReserva"
                                           id="tipoCompleto" value="completo" onchange="actualizarTipo()">
                                    <label class="form-check-label" for="tipoCompleto">
                                        Pago completo
                                    </label>
                                </div>
                            </div>

                            <div id="zonaAdelanto" class="mt-3">
                                <label class="form-label" for="monto">Monto a pagar ahora (S/)</label>
                                <input type="number" step="0.01" min="0" inputmode="decimal"
                                       class="form-control" name="monto" id="monto">
                                <small class="text-muted" id="minNota"></small>
                            </div>
                            <div id="zonaCompleto" class="mt-3 alerta-info" style="display:none">
                                Se cobra el total: S/ <span id="totalCompleto"></span>
                            </div>
                        </div>

                        <!-- Método de pago -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Método de pago</label>
                            <div class="d-flex gap-4 mt-1">
                                <div class="form-check fs-5">
                                    <input class="form-check-input" type="radio" name="metodo"
                                           id="mEfectivo" value="EFECTIVO" checked>
                                    <label class="form-check-label" for="mEfectivo">
                                        <i class="bi bi-cash me-1"></i>Efectivo
                                    </label>
                                </div>
                                <div class="form-check fs-5">
                                    <input class="form-check-input" type="radio" name="metodo"
                                           id="mYape" value="YAPE">
                                    <label class="form-check-label" for="mYape">
                                        <i class="bi bi-phone me-1"></i>Yape
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-app btn-app-primary w-100 py-2 mt-4"
                            <c:if test="${empty caja}">disabled</c:if>>
                        <i class="bi bi-calendar2-check me-1"></i>
                        Reservar pedido (apartar stock)
                    </button>
                    <p class="text-muted text-center mt-2 mb-0">
                        Al reservar se aparta el stock y el pago entra a la caja.
                    </p>
                </div>
            </form>
        </div>
    </c:if>

<script>
    var TOTAL = parseFloat('<fmt:formatNumber value="${totalCarrito}" minFractionDigits="2" groupingUsed="false"/>');
    if (isNaN(TOTAL)) TOTAL = 0;
    var MINIMO = TOTAL * 0.5;

    function actualizarTipo() {
        var completo = document.getElementById('tipoCompleto').checked;
        document.getElementById('zonaAdelanto').style.display = completo ? 'none' : '';
        document.getElementById('zonaCompleto').style.display = completo ? '' : 'none';
        if (completo) {
            document.getElementById('monto').removeAttribute('required');
        } else {
            document.getElementById('monto').setAttribute('required', 'required');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var monto = document.getElementById('monto');
        if (monto) {
            monto.value = MINIMO.toFixed(2);
            document.getElementById('minNota').textContent =
                'Mínimo S/ ' + MINIMO.toFixed(2) + ' (50% del total).';
            document.getElementById('totalCompleto').textContent = TOTAL.toFixed(2);
            actualizarTipo();
        }
    });
</script>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
