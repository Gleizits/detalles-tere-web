<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<%@taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="titulo-seccion mb-0">
            <i class="bi bi-calendar2-check me-2"></i>Pedido #${pedido.idPedido}
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

    <!-- ====== ESTADOS ====== -->
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <c:choose>
            <c:when test="${pedido.estado == 'PENDIENTE'}"><span class="badge-pedido pendiente">Pendiente de pago</span></c:when>
            <c:when test="${pedido.estado == 'COMPLETO'}"><span class="badge-pedido completo">Pagado por completo</span></c:when>
            <c:when test="${pedido.estado == 'RECOGIDO'}"><span class="badge-pedido recogido">Recogido</span></c:when>
            <c:when test="${pedido.estado == 'CANCELADO'}"><span class="badge-pedido cancelado">Cancelado</span></c:when>
        </c:choose>
        <c:choose>
            <c:when test="${pedido.estadoProduccion == 'HECHO'}"><span class="badge-pedido hecho">Producción: HECHO</span></c:when>
            <c:when test="${pedido.estadoProduccion == 'CANCELADO'}"><span class="badge-pedido cancelado">Producción: cancelada</span></c:when>
            <c:otherwise><span class="badge-pedido pendiente">Producción: pendiente</span></c:otherwise>
        </c:choose>
    </div>

    <!-- ====== DINERO ====== -->
    <div class="app-card p-4 mb-4">
        <div class="row g-3 text-center">
            <div class="col-4">
                <div class="dato-etiqueta">Total del pedido</div>
                <div class="dato-valor">S/ <fmt:formatNumber value="${pedido.total}" type="number" minFractionDigits="2" maxFractionDigits="2"/></div>
            </div>
            <div class="col-4">
                <div class="dato-etiqueta">Pagado</div>
                <div class="dato-valor">S/ <fmt:formatNumber value="${pedido.adelanto}" type="number" minFractionDigits="2" maxFractionDigits="2"/></div>
            </div>
            <div class="col-4">
                <div class="dato-etiqueta">Falta por pagar</div>
                <c:choose>
                    <c:when test="${pedido.faltante > 0}">
                        <div class="dato-valor texto-negativo">S/ <fmt:formatNumber value="${pedido.faltante}" type="number" minFractionDigits="2" maxFractionDigits="2"/></div>
                    </c:when>
                    <c:otherwise>
                        <div class="dato-valor" style="color:var(--c-exito)">S/ 0.00</div>
                    </c:otherwise>
                </c:choose>
            </div>
        </div>
        <c:if test="${pedido.faltante > 0 && pedido.estado != 'CANCELADO'}">
            <div class="alerta-info mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Falta cobrar <strong>S/ <fmt:formatNumber value="${pedido.faltante}" minFractionDigits="2"/></strong>.
                No se entrega hasta pagar el total.
            </div>
        </c:if>
    </div>

    <div class="row g-4">
        <!-- ====== CLIENTE ====== -->
        <div class="col-12 col-lg-6">
            <div class="app-card p-4 h-100">
                <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">
                    <i class="bi bi-person-vcard me-2"></i>Cliente
                </h2>
                <dl class="row mb-0 detalle-lista">
                    <dt>Quien paga</dt>
                    <dd><c:out value="${pedido.nombrePaga}"/></dd>
                    <dt>Celular</dt>
                    <dd><c:out value="${pedido.celular}"/></dd>
                    <dt>Quien recoge</dt>
                    <dd>
                        <c:choose>
                            <c:when test="${not empty pedido.nombreRecoge}"><c:out value="${pedido.nombreRecoge}"/></c:when>
                            <c:otherwise>—</c:otherwise>
                        </c:choose>
                    </dd>
                    <dt>Día de recojo</dt>
                    <dd>
                        <fmt:formatDate value="${pedido.fechaRecojoDate}" pattern="dd/MM/yyyy"/>
                        <c:if test="${not empty pedido.horaRecojo}">
                            a las <c:out value="${pedido.horaRecojo}"/>
                        </c:if>
                    </dd>
                </dl>
            </div>
        </div>

        <!-- ====== PLAZOS ====== -->
        <div class="col-12 col-lg-6">
            <div class="app-card p-4 h-100">
                <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">
                    <i class="bi bi-hourglass-split me-2"></i>Plazos
                </h2>
                <dl class="row mb-0 detalle-lista">
                    <dt>Recoger antes de</dt>
                    <dd><fmt:formatDate value="${pedido.fechaLimiteRecojoDate}" pattern="dd/MM/yyyy"/></dd>
                    <dt>Pagar el total antes de</dt>
                    <dd><fmt:formatDate value="${pedido.fechaLimitePagoDate}" pattern="dd/MM/yyyy"/></dd>
                    <dt>Canjear antes de</dt>
                    <dd><fmt:formatDate value="${pedido.fechaLimiteCanjeDate}" pattern="dd/MM/yyyy"/></dd>
                    <dt>Creado</dt>
                    <dd><fmt:formatDate value="${pedido.fechaCreacionDate}" pattern="dd/MM/yyyy HH:mm"/></dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- ====== PRODUCTOS ====== -->
    <div class="app-card tabla-envoltorio mt-4">
        <table class="tabla-app">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="num">Cant.</th>
                    <th class="num">P. unit.</th>
                    <th class="num">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <c:forEach var="l" items="${pedido.lineas}">
                    <tr>
                        <td><c:out value="${l.nombre}"/></td>
                        <td class="num">${l.cantidad}</td>
                        <td class="num">S/ <fmt:formatNumber value="${l.costoUnitario}" minFractionDigits="2"/></td>
                        <td class="num">S/ <fmt:formatNumber value="${l.subtotal}" minFractionDigits="2"/></td>
                    </tr>
                </c:forEach>
            </tbody>
        </table>
    </div>

    <!-- ====== ACCIONES ====== -->
    <c:if test="${pedido.estado != 'CANCELADO' && pedido.estado != 'RECOGIDO'}">

        <!-- Abono -->
        <c:if test="${pedido.faltante > 0}">
            <div class="app-card p-4 mt-4">
                <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">
                    <i class="bi bi-cash-coin me-2"></i>Registrar abono
                </h2>
                <c:choose>
                    <c:when test="${empty caja}">
                        <div class="alerta-error mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Abre la caja para registrar pagos.
                        </div>
                    </c:when>
                    <c:otherwise>
                        <form method="post" action="${ctx}/pedidos" class="row g-3 align-items-end">
                            <input type="hidden" name="accion" value="abono">
                            <input type="hidden" name="idPedido" value="${pedido.idPedido}">
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="abonoMonto">Monto a abonar (S/)</label>
                                <input type="number" step="0.01" min="0.01" max="${pedido.faltante}"
                                       inputmode="decimal" class="form-control" name="monto"
                                       id="abonoMonto" required placeholder="Máx. ${pedido.faltante}">
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label fw-bold mb-1">Método</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check fs-5">
                                        <input class="form-check-input" type="radio" name="metodo"
                                               id="aEfectivo" value="EFECTIVO" checked>
                                        <label class="form-check-label" for="aEfectivo">Efectivo</label>
                                    </div>
                                    <div class="form-check fs-5">
                                        <input class="form-check-input" type="radio" name="metodo"
                                               id="aYape" value="YAPE">
                                        <label class="form-check-label" for="aYape">Yape</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-3">
                                <button type="submit" class="btn btn-app btn-app-primary w-100">
                                    <i class="bi bi-plus-lg me-1"></i>Abonar
                                </button>
                            </div>
                        </form>
                    </c:otherwise>
                </c:choose>
            </div>
        </c:if>

        <!-- Marcar hecho / Recoger -->
        <div class="app-card p-4 mt-4">
            <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">
                <i class="bi bi-gear me-2"></i>Acciones
            </h2>
            <div class="d-flex gap-2 flex-wrap">
                <c:if test="${pedido.estadoProduccion == 'PENDIENTE'}">
                    <form method="post" action="${ctx}/pedidos" class="flex-fill">
                        <input type="hidden" name="accion" value="hecho">
                        <input type="hidden" name="idPedido" value="${pedido.idPedido}">
                        <button type="submit" class="btn btn-app btn-app-primary w-100"
                                onclick="return confirm('¿Marcar como HECHO? A partir de este punto no se puede cancelar: se cobrará el total.');">
                            <i class="bi bi-check2-circle me-1"></i>Marcar HECHO
                        </button>
                    </form>
                </c:if>

                <c:if test="${pedido.estado == 'COMPLETO'}">
                    <form method="post" action="${ctx}/pedidos" class="flex-fill">
                        <input type="hidden" name="accion" value="recoger">
                        <input type="hidden" name="idPedido" value="${pedido.idPedido}">
                        <button type="submit" class="btn btn-app w-100"
                                style="background:var(--c-exito);border-color:var(--c-exito);color:#fff"
                                onclick="return confirm('¿Entregar el pedido al cliente?');">
                            <i class="bi bi-box-seam me-1"></i>Entregar (recogido)
                        </button>
                    </form>
                </c:if>

                <c:if test="${pedido.estadoProduccion == 'PENDIENTE' && pedido.faltante > 0}">
                    <div class="flex-fill">
                        <a class="btn btn-outline-danger w-100" href="${ctx}/pedidos?id=${pedido.idPedido}"
                           style="pointer-events:none;opacity:.5" aria-disabled="true">
                            <i class="bi bi-x-circle me-1"></i>Cancelar (próximamente)
                        </a>
                    </div>
                </c:if>
            </div>
        </div>
    </c:if>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
