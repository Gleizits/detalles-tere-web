<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<%@taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <h1 class="titulo-seccion mb-0"><i class="bi bi-truck me-2"></i>Ingresos</h1>
    <a class="btn btn-outline-secondary" href="${ctx}/panel">
        <i class="bi bi-arrow-left me-1"></i>Inicio
    </a>
</div>

<c:if test="${not empty mensaje}">
    <div class="alerta-exito mb-4"><i class="bi bi-check-circle me-2"></i><c:out value="${mensaje}"/></div>
</c:if>
<c:if test="${not empty error}">
    <div class="alerta-error mb-4"><i class="bi bi-exclamation-triangle me-2"></i><c:out value="${error}"/></div>
</c:if>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="app-card p-4">
            <h2 class="h4 mb-3">Registrar entrada de mercadería</h2>
            <form method="post" action="${ctx}/ingresos">
                <input type="hidden" name="accion" value="registrar">
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-7">
                        <label class="form-label" for="idProveedor">Proveedor *</label>
                        <select class="form-select" id="idProveedor" name="idProveedor" required>
                            <option value="">— Selecciona un proveedor —</option>
                            <c:forEach var="p" items="${proveedores}">
                                <option value="${p.idProveedor}">
                                    <c:out value="${p.nombre}"/> · <c:out value="${p.numero}"/>
                                </option>
                            </c:forEach>
                        </select>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="fechaIngreso">Fecha *</label>
                        <input class="form-control" type="date" id="fechaIngreso"
                               name="fechaIngreso" value="${fechaActual}" required>
                    </div>
                </div>

                <p class="form-label mb-2">Artículos recibidos</p>
                <div id="lineasIngreso">
                    <c:forEach begin="1" end="6">
                        <div class="row g-2 align-items-end mb-2 linea-ingreso">
                            <div class="col-12 col-md-5">
                                <label class="visually-hidden">Artículo</label>
                                <select class="form-select" name="idInventario">
                                    <option value="">— Artículo —</option>
                                    <c:forEach var="it" items="${inventario}">
                                        <option value="${it.idInventario}">
                                            <c:out value="${it.nombre}"/> · <c:out value="${it.clase}"/>
                                        </option>
                                    </c:forEach>
                                </select>
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="visually-hidden">Cantidad</label>
                                <input class="form-control" type="number" name="cantidad"
                                       min="1" step="1" placeholder="Cantidad">
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="visually-hidden">Precio de compra</label>
                                <input class="form-control" type="text" name="precioCompra"
                                       inputmode="decimal" placeholder="Costo S/">
                            </div>
                            <div class="col-4 col-md-3">
                                <label class="visually-hidden">Observaciones</label>
                                <input class="form-control" type="text" name="observaciones"
                                       maxlength="100" placeholder="Observación">
                            </div>
                        </div>
                    </c:forEach>
                </div>
                <small class="text-muted d-block mb-3">
                    Las filas vacías se ignoran. El costo registrado reemplaza el costo vigente del artículo.
                </small>
                <button class="btn btn-app btn-app-primary" type="submit">
                    <i class="bi bi-box-arrow-in-down me-1"></i>Registrar ingreso
                </button>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="app-card p-4 mb-4">
            <h2 class="h4 mb-3">Nuevo proveedor</h2>
            <form method="post" action="${ctx}/ingresos">
                <input type="hidden" name="accion" value="crearProveedor">
                <div class="mb-3">
                    <label class="form-label" for="nombreProveedor">Nombre *</label>
                    <input class="form-control" id="nombreProveedor" name="nombreProveedor"
                           maxlength="50" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="numeroProveedor">Número *</label>
                    <input class="form-control" id="numeroProveedor" name="numeroProveedor"
                           maxlength="12" required>
                </div>
                <button class="btn btn-outline-primary" type="submit">
                    <i class="bi bi-person-plus me-1"></i>Guardar proveedor
                </button>
            </form>
        </div>

        <div class="app-card p-4">
            <h2 class="h4 mb-3">Últimos ingresos</h2>
            <c:choose>
                <c:when test="${empty ingresos}">
                    <p class="text-muted mb-0">Todavía no hay ingresos registrados.</p>
                </c:when>
                <c:otherwise>
                    <div class="table-responsive">
                        <table class="tabla-app">
                            <thead><tr><th>Proveedor · artículo</th><th>Cant.</th><th>Total</th></tr></thead>
                            <tbody>
                                <c:forEach var="linea" items="${ingresos}">
                                    <tr>
                                        <td><c:out value="${linea.nombre}"/></td>
                                        <td>${linea.cantidad}</td>
                                        <td>S/ <fmt:formatNumber value="${linea.subtotal}"
                                            minFractionDigits="2" maxFractionDigits="2"/></td>
                                    </tr>
                                </c:forEach>
                            </tbody>
                        </table>
                    </div>
                </c:otherwise>
            </c:choose>
        </div>
    </div>
</div>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
