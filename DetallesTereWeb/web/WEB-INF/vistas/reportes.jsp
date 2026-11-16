<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<%@taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="titulo-seccion mb-1"><i class="bi bi-graph-up-arrow me-2"></i>Reportes</h1>
        <p class="text-muted mb-0">Ventas, entradas y margen estimado para tomar decisiones.</p>
    </div>
    <form class="reporte-filtros" method="get" action="${ctx}/reportes">
        <label class="visually-hidden" for="tipo">Periodo</label>
        <select class="form-select" id="tipo" name="tipo">
            <option value="dia" ${tipoPeriodo == 'dia' ? 'selected' : ''}>Un día</option>
            <option value="mes" ${tipoPeriodo == 'mes' ? 'selected' : ''}>Un mes</option>
        </select>
        <label class="visually-hidden" for="periodo">Fecha</label>
        <input class="form-control" id="periodo" name="periodo"
               type="${tipoPeriodo == 'mes' ? 'month' : 'date'}" value="${valorPeriodo}" required>
        <button class="btn btn-app btn-app-primary" type="submit">Ver reporte</button>
        <a class="btn btn-outline-secondary" href="${ctx}/reportes?tipo=${tipoPeriodo}&periodo=${valorPeriodo}&formato=pdf">
            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
    </form>
</div>

<c:if test="${not empty error}">
    <div class="alerta-error mb-4"><i class="bi bi-exclamation-triangle me-2"></i><c:out value="${error}"/></div>
</c:if>

<c:if test="${not empty reporte}">
    <div class="reporte-periodo mb-4">
        <span class="reporte-periodo-marca"></span>
        <div>
            <strong><c:out value="${reporte.etiquetaPeriodo}"/></strong>
            <span>Solo visible para administradores</span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="reporte-metrica">
                <span>Ventas</span>
                <strong>S/ <fmt:formatNumber value="${reporte.ventasTotales}" minFractionDigits="2" maxFractionDigits="2"/></strong>
                <small>${reporte.cantidadVentas} operación(es) · ${reporte.unidadesVendidas} unidad(es)</small>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="reporte-metrica">
                <span>Entradas al inventario</span>
                <strong>S/ <fmt:formatNumber value="${reporte.ingresosTotales}" minFractionDigits="2" maxFractionDigits="2"/></strong>
                <small>${reporte.unidadesIngresadas} unidad(es) recibida(s)</small>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="reporte-metrica reporte-metrica-acento">
                <span>Margen estimado</span>
                <strong>S/ <fmt:formatNumber value="${reporte.margenEstimado}" minFractionDigits="2" maxFractionDigits="2"/></strong>
                <small>Usa el costo vigente del artículo</small>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="reporte-metrica">
                <span>Formas de pago</span>
                <strong>S/ <fmt:formatNumber value="${reporte.efectivo}" minFractionDigits="2" maxFractionDigits="2"/></strong>
                <small>Efectivo · Yape S/ <fmt:formatNumber value="${reporte.yape}" minFractionDigits="2" maxFractionDigits="2"/></small>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <div class="app-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-baseline mb-3">
                    <h2 class="h4 mb-0">Ventas del periodo</h2>
                    <span class="text-muted">${reporte.ventas.size()} línea(s)</span>
                </div>
                <div class="tabla-envoltorio">
                    <table class="tabla-app">
                        <thead><tr><th>Fecha</th><th>Artículo</th><th class="num">Cant.</th><th class="num">Venta</th><th class="num">Margen</th></tr></thead>
                        <tbody>
                            <c:forEach var="linea" items="${reporte.ventas}">
                                <tr>
                                    <td>${linea.fechaTexto}</td>
                                    <td class="nombre-item"><c:out value="${linea.articulo}"/></td>
                                    <td class="num">${linea.cantidad}</td>
                                    <td class="num">S/ <fmt:formatNumber value="${linea.subtotal}" minFractionDigits="2" maxFractionDigits="2"/></td>
                                    <td class="num">S/ <fmt:formatNumber value="${linea.margen}" minFractionDigits="2" maxFractionDigits="2"/></td>
                                </tr>
                            </c:forEach>
                        </tbody>
                    </table>
                    <c:if test="${empty reporte.ventas}">
                        <p class="text-muted mb-0 mt-3">No hubo ventas en este periodo.</p>
                    </c:if>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="app-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-baseline mb-3">
                    <h2 class="h4 mb-0">Entradas registradas</h2>
                    <span class="text-muted">${reporte.ingresos.size()} línea(s)</span>
                </div>
                <div class="tabla-envoltorio">
                    <table class="tabla-app">
                        <thead><tr><th>Proveedor</th><th>Artículo</th><th class="num">Total</th></tr></thead>
                        <tbody>
                            <c:forEach var="linea" items="${reporte.ingresos}">
                                <tr>
                                    <td><c:out value="${linea.proveedor}"/></td>
                                    <td><c:out value="${linea.articulo}"/></td>
                                    <td class="num">S/ <fmt:formatNumber value="${linea.subtotal}" minFractionDigits="2" maxFractionDigits="2"/></td>
                                </tr>
                            </c:forEach>
                        </tbody>
                    </table>
                    <c:if test="${empty reporte.ingresos}">
                        <p class="text-muted mb-0 mt-3">No hubo entradas en este periodo.</p>
                    </c:if>
                </div>
            </div>
        </div>
    </div>
</c:if>

<script>
    (function () {
        var tipo = document.getElementById('tipo');
        var periodo = document.getElementById('periodo');
        tipo.addEventListener('change', function () {
            periodo.type = tipo.value === 'mes' ? 'month' : 'date';
            periodo.value = '';
        });
    })();
</script>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
