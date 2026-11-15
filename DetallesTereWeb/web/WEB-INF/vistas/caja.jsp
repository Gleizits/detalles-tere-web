<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<%@taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

    <h1 class="titulo-seccion mb-4">
        <i class="bi bi-cash-coin me-2"></i>Caja
    </h1>

    <c:if test="${not empty error}">
        <div class="alerta-error p-3 mb-3" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <c:out value="${error}"/>
        </div>
    </c:if>

    <!-- Resumen tras cerrar -->
    <c:if test="${not empty resumen}">
        <div class="app-card p-4 mb-4">
            <h2 class="h5 mb-3">
                <i class="bi bi-check-circle text-success me-1"></i> Caja cerrada
            </h2>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="dato-etiqueta">Monto inicial</div>
                    <div class="dato-valor">S/ <fmt:formatNumber value="${resumen.montoInicial}" type="number" minFractionDigits="2" maxFractionDigits="2"/></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="dato-etiqueta">Ventas en efectivo</div>
                    <div class="dato-valor">S/ <fmt:formatNumber value="${resumen.montoFinal - resumen.montoInicial}" type="number" minFractionDigits="2" maxFractionDigits="2"/></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="dato-etiqueta">Esperado en caja</div>
                    <div class="dato-valor">S/ <fmt:formatNumber value="${resumen.montoFinal}" type="number" minFractionDigits="2" maxFractionDigits="2"/></div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="dato-etiqueta">Contado</div>
                    <div class="dato-valor">S/ <fmt:formatNumber value="${resumen.montoReal}" type="number" minFractionDigits="2" maxFractionDigits="2"/></div>
                </div>
            </div>
            <div class="mt-3">
                <span class="dato-etiqueta">Diferencia:</span>
                <c:choose>
                    <c:when test="${resumen.diferencia > 0}">
                        <span class="badge-diferencia positivo">+ S/ <fmt:formatNumber value="${resumen.diferencia}" type="number" minFractionDigits="2" maxFractionDigits="2"/> (sobra)</span>
                    </c:when>
                    <c:when test="${resumen.diferencia < 0}">
                        <span class="badge-diferencia negativo">- S/ <fmt:formatNumber value="${-resumen.diferencia}" type="number" minFractionDigits="2" maxFractionDigits="2"/> (falta)</span>
                    </c:when>
                    <c:otherwise>
                        <span class="badge-diferencia exacto">S/ 0.00 (cuadra)</span>
                    </c:otherwise>
                </c:choose>
            </div>
        </div>
    </c:if>

    <c:choose>
        <c:when test="${not empty caja}">
            <div class="app-card p-4 mb-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge-punto-abierto">Caja abierta</span>
                    <span class="text-muted">desde <fmt:formatDate value="${caja.fechaAperturaDate}" pattern="dd/MM/yyyy HH:mm"/></span>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="dato-etiqueta">Monto inicial</div>
                        <div class="dato-valor">S/ <fmt:formatNumber value="${caja.montoInicial}" type="number" minFractionDigits="2" maxFractionDigits="2"/></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="dato-etiqueta">Ventas de productos</div>
                        <div class="dato-valor">S/ <fmt:formatNumber value="${ventasProductos}" type="number" minFractionDigits="2" maxFractionDigits="2"/></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="dato-etiqueta">Pagos de reservas</div>
                        <div class="dato-valor" style="color:var(--c-acento)">S/ <fmt:formatNumber value="${pagosReservas}" type="number" minFractionDigits="2" maxFractionDigits="2"/></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="dato-etiqueta">Esperado en caja</div>
                        <div class="dato-valor">S/ <fmt:formatNumber value="${esperado}" type="number" minFractionDigits="2" maxFractionDigits="2"/></div>
                    </div>
                </div>

                <form action="${ctx}/caja" method="post" autocomplete="off">
                    <input type="hidden" name="accion" value="cerrar">
                    <input type="hidden" name="idCaja" value="${caja.idCaja}">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="montoReal">Cuéntalo: dinero físico en caja</label>
                            <div class="input-group">
                                <span class="input-group-text">S/</span>
                                <input type="number" step="0.01" min="0" class="form-control"
                                       id="montoReal" name="montoReal" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-5">
                            <button type="submit" class="btn btn-app btn-app-primary w-100"
                                    onclick="return confirm('¿Cerrar la caja? Esta acción no se puede deshacer.');">
                                <i class="bi bi-lock me-1"></i> Cerrar caja
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </c:when>
        <c:otherwise>
            <c:if test="${empty resumen}">
                <div class="app-card p-4 mb-4">
                    <p class="text-muted mb-3">No tienes una caja abierta. Abre una para empezar a registrar ventas.</p>
                    <form action="${ctx}/caja" method="post" autocomplete="off">
                        <input type="hidden" name="accion" value="abrir">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-5">
                                <label class="form-label" for="montoInicial">Dinero inicial en caja</label>
                                <div class="input-group">
                                    <span class="input-group-text">S/</span>
                                    <input type="number" step="0.01" min="0" class="form-control"
                                           id="montoInicial" name="montoInicial" required autofocus>
                                </div>
                            </div>
                            <div class="col-12 col-md-5">
                                <button type="submit" class="btn btn-app btn-app-primary w-100">
                                    <i class="bi bi-unlock me-1"></i> Abrir caja
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </c:if>
        </c:otherwise>

    </c:choose>

    <!-- Historial reciente -->
    <c:if test="${not empty historial}">
        <h2 class="h5 mt-4 mb-3">Cajas recientes</h2>
        <div class="app-card tabla-envoltorio">
            <table class="tabla-app">
                <thead>
                    <tr>
                        <th>Apertura</th>
                        <th>Cierre</th>
                        <th class="num">Inicial</th>
                        <th class="num">Esperado</th>
                        <th class="num">Contado</th>
                        <th class="num">Diferencia</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <c:forEach var="ch" items="${historial}">
                        <tr>
                            <td><fmt:formatDate value="${ch.fechaAperturaDate}" pattern="dd/MM HH:mm"/></td>
                            <td>
                                <c:choose>
                                    <c:when test="${ch.abierta}">—</c:when>
                                    <c:otherwise><fmt:formatDate value="${ch.fechaCierreDate}" pattern="dd/MM HH:mm"/></c:otherwise>
                                </c:choose>
                            </td>
                            <td class="num">S/ <fmt:formatNumber value="${ch.montoInicial}" type="number" minFractionDigits="2" maxFractionDigits="2"/></td>
                            <td class="num">
                                <c:choose>
                                    <c:when test="${ch.abierta}">—</c:when>
                                    <c:otherwise>S/ <fmt:formatNumber value="${ch.montoFinal}" type="number" minFractionDigits="2" maxFractionDigits="2"/></c:otherwise>
                                </c:choose>
                            </td>
                            <td class="num">
                                <c:choose>
                                    <c:when test="${ch.abierta}">—</c:when>
                                    <c:otherwise>S/ <fmt:formatNumber value="${ch.montoReal}" type="number" minFractionDigits="2" maxFractionDigits="2"/></c:otherwise>
                                </c:choose>
                            </td>
                            <td class="num">
                                <c:choose>
                                    <c:when test="${ch.abierta}">—</c:when>
                                    <c:otherwise>
                                        <c:choose>
                                            <c:when test="${ch.diferencia < 0}"><span class="texto-negativo">- S/ <fmt:formatNumber value="${-ch.diferencia}" type="number" minFractionDigits="2" maxFractionDigits="2"/></span></c:when>
                                            <c:otherwise>S/ <fmt:formatNumber value="${ch.diferencia}" type="number" minFractionDigits="2" maxFractionDigits="2"/></c:otherwise>
                                        </c:choose>
                                    </c:otherwise>
                                </c:choose>
                            </td>
                            <td>
                                <c:choose>
                                    <c:when test="${ch.abierta}"><span class="badge-estado abierto">Abierta</span></c:when>
                                    <c:otherwise><span class="badge-estado cerrado">Cerrada</span></c:otherwise>
                                </c:choose>
                            </td>
                        </tr>
                    </c:forEach>
                </tbody>
            </table>
        </div>
    </c:if>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
