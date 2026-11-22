<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<%@taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="titulo-seccion mb-0">
            <i class="bi bi-calendar2-check me-2"></i>Pedidos reservados
        </h1>
        <a class="btn btn-app btn-app-primary" href="${ctx}/pedidos?nuevo=1">
            <i class="bi bi-plus-lg me-1"></i>Nuevo pedido
        </a>
    </div>

    <c:if test="${not empty mensaje}">
        <div class="${mensajeError ? 'alerta-error' : 'alerta-exito'} mb-4">
            <i class="bi ${mensajeError ? 'bi-exclamation-triangle' : 'bi-check-circle'} me-2"></i>
            <c:out value="${mensaje}"/>
        </div>
    </c:if>

    <form action="${ctx}/pedidos" method="get" class="mb-4" role="search">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" name="q" value="<c:out value='${q}'/>"
                   placeholder="Buscar por nombre, celular o quién recoge…" aria-label="Buscar pedidos">
            <button class="btn btn-app btn-app-primary" type="submit">Buscar</button>
        </div>
    </form>

    <c:choose>
        <c:when test="${empty pedidos}">
            <div class="app-card p-4 text-center">
                <p class="text-muted mb-3">No hay pedidos reservados<c:if test="${not empty q}"> que coincidan con «<c:out value="${q}"/>»</c:if>.</p>
                <a class="btn btn-app btn-app-primary" href="${ctx}/pedidos?nuevo=1">
                    <i class="bi bi-plus-lg me-1"></i>Crear el primer pedido
                </a>
            </div>
        </c:when>
        <c:otherwise>
            <div class="app-card tabla-envoltorio">
                <table class="tabla-app">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Recojo</th>
                            <th class="num">Total</th>
                            <th class="num">Pagado</th>
                            <th class="num">Falta</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <c:forEach var="p" items="${pedidos}">
                            <tr>
                                <td><strong>${p.idPedido}</strong></td>
                                <td>
                                    <div class="nombre-item"><c:out value="${p.nombrePaga}"/></div>
                                    <small class="text-muted"><c:out value="${p.celular}"/></small>
                                </td>
                                <td>
                                    <fmt:formatDate value="${p.fechaRecojoDate}" pattern="dd/MM/yyyy"/>
                                    <c:if test="${not empty p.horaRecojo}">
                                        <small class="text-muted"><c:out value="${p.horaRecojo}"/></small>
                                    </c:if>
                                </td>
                                <td class="num">S/ <fmt:formatNumber value="${p.total}" minFractionDigits="2"/></td>
                                <td class="num">S/ <fmt:formatNumber value="${p.adelanto}" minFractionDigits="2"/></td>
                                <td class="num">
                                    <c:choose>
                                        <c:when test="${p.faltante > 0}">
                                            <span class="texto-negativo">S/ <fmt:formatNumber value="${p.faltante}" minFractionDigits="2"/></span>
                                        </c:when>
                                        <c:otherwise><span class="text-success fw-bold">S/ 0.00</span></c:otherwise>
                                    </c:choose>
                                </td>
                                <td>
                                    <c:choose>
                                        <c:when test="${p.estado == 'PENDIENTE'}"><span class="badge-pedido pendiente">Pendiente</span></c:when>
                                        <c:when test="${p.estado == 'COMPLETO'}"><span class="badge-pedido completo">Listo</span></c:when>
                                        <c:when test="${p.estado == 'RECOGIDO'}"><span class="badge-pedido recogido">Recogido</span></c:when>
                                        <c:when test="${p.estado == 'CANCELADO'}"><span class="badge-pedido cancelado">Cancelado</span></c:when>
                                    </c:choose>
                                    <c:if test="${p.estadoProduccion == 'HECHO'}">
                                        <span class="badge-pedido hecho">Hecho</span>
                                    </c:if>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-app btn-app-primary" href="${ctx}/pedidos?id=${p.idPedido}">Ver</a>
                                </td>
                            </tr>
                        </c:forEach>
                    </tbody>
                </table>
            </div>
        </c:otherwise>
    </c:choose>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
