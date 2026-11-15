<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<%@taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="titulo-seccion mb-0">
            <i class="bi bi-box-seam me-2"></i>Inventario
        </h1>
        <span class="text-muted"><c:out value="${total}"/> artículo(s)</span>
    </div>

    <!-- Búsqueda por nombre o término -->
    <form action="${ctx}/inventario" method="get" class="mb-4" role="search">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" name="q" value="${q}"
                   placeholder="Buscar por nombre o término…" aria-label="Buscar en inventario"
                   autofocus>
            <button class="btn btn-app btn-app-primary" type="submit">Buscar</button>
            <c:if test="${not empty q}">
                <a class="btn btn-outline-secondary" href="${ctx}/inventario">Limpiar</a>
            </c:if>
        </div>
    </form>

    <c:choose>
        <c:when test="${empty items}">
            <div class="app-card p-5 text-center">
                <div style="font-size:2.5rem;color:var(--c-suave)"><i class="bi bi-inbox"></i></div>
                <p class="text-muted mt-2 mb-0">
                    <c:choose>
                        <c:when test="${not empty q}">
                            No encontramos nada para “<c:out value="${q}"/>”.
                        </c:when>
                        <c:otherwise>
                            Aún no hay artículos en el inventario.
                        </c:otherwise>
                    </c:choose>
                </p>
            </div>
        </c:when>
        <c:otherwise>
            <div class="app-card tabla-envoltorio">
                <table class="tabla-app">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Clase</th>
                            <th>Categoría</th>
                            <th>Almacén</th>
                            <th>Unidad</th>
                            <th class="num">Stock</th>
                            <th class="num">Mín.</th>
                            <th class="num">P. venta</th>
                            <c:if test="${usuario.admin}">
                                <th class="num">P. costo</th>
                            </c:if>
                        </tr>
                    </thead>
                    <tbody>
                        <c:forEach var="it" items="${items}">
                            <tr>
                                <td class="nombre-item">
                                    <c:out value="${it.nombre}"/>
                                    <c:if test="${not empty it.tipo}">
                                        <span class="text-muted tipo-item"><c:out value="${it.tipo}"/></span>
                                    </c:if>
                                </td>
                                <td><span class="badge-clase">${it.clase}</span></td>
                                <td><c:out value="${it.categoria}"/></td>
                                <td><c:out value="${it.tipoAlmacen}"/></td>
                                <td><c:out value="${it.unidadMedida}"/></td>
                                <td class="num">
                                    <c:choose>
                                        <c:when test="${it.stockBajo}">
                                            <span class="badge-estado bajo">${it.stockAct}</span>
                                        </c:when>
                                        <c:otherwise>${it.stockAct}</c:otherwise>
                                    </c:choose>
                                </td>
                                <td class="num text-muted">${it.stockMin}</td>
                                <td class="num">
                                    <c:choose>
                                        <c:when test="${not empty it.precioVenta}">
                                            S/ <fmt:formatNumber value="${it.precioVenta}" type="number" minFractionDigits="2" maxFractionDigits="2"/>
                                        </c:when>
                                        <c:otherwise>—</c:otherwise>
                                    </c:choose>
                                </td>
                                <c:if test="${usuario.admin}">
                                    <td class="num text-muted">
                                        <c:choose>
                                            <c:when test="${not empty it.precioCompra}">
                                                S/ <fmt:formatNumber value="${it.precioCompra}" type="number" minFractionDigits="2" maxFractionDigits="2"/>
                                            </c:when>
                                            <c:otherwise>—</c:otherwise>
                                        </c:choose>
                                    </td>
                                </c:if>
                            </tr>
                        </c:forEach>
                    </tbody>
                </table>
            </div>
        </c:otherwise>
    </c:choose>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
