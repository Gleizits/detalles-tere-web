<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <h1 class="titulo-seccion mb-0"><i class="bi bi-tags me-2"></i>Términos de búsqueda</h1>
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
    <div class="col-12 col-lg-5">
        <div class="app-card p-4">
            <h2 class="h4 mb-3">Agregar término</h2>
            <form method="post" action="${ctx}/terminos" class="mb-4">
                <input type="hidden" name="csrfToken" value="${csrfToken}">
                <input type="hidden" name="accion" value="crear">
                <label class="form-label" for="palabra">Palabra o sinónimo *</label>
                <div class="input-group">
                    <input class="form-control" id="palabra" name="palabra"
                           maxlength="75" placeholder="Ej: limpiapipas" required>
                    <button class="btn btn-app btn-app-primary" type="submit">Guardar</button>
                </div>
            </form>

            <h2 class="h4 mb-3">Asociar artículo</h2>
            <form method="post" action="${ctx}/terminos">
                <input type="hidden" name="csrfToken" value="${csrfToken}">
                <input type="hidden" name="accion" value="asociar">
                <div class="mb-3">
                    <label class="form-label" for="idTermino">Término *</label>
                    <select class="form-select" id="idTermino" name="idTermino" required>
                        <option value="">— Selecciona un término —</option>
                        <c:forEach var="termino" items="${terminos}">
                            <option value="${termino.idTermino}"><c:out value="${termino.palabra}"/></option>
                        </c:forEach>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="idInventario">Artículo *</label>
                    <select class="form-select" id="idInventario" name="idInventario" required>
                        <option value="">— Selecciona un artículo —</option>
                        <c:forEach var="it" items="${inventario}">
                            <option value="${it.idInventario}">
                                <c:out value="${it.nombre}"/> · <c:out value="${it.clase}"/>
                            </option>
                        </c:forEach>
                    </select>
                </div>
                <button class="btn btn-outline-primary" type="submit">
                    <i class="bi bi-link-45deg me-1"></i>Asociar
                </button>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="app-card p-4">
            <h2 class="h4 mb-3">Términos registrados</h2>
            <c:choose>
                <c:when test="${empty terminos}">
                    <p class="text-muted mb-0">Todavía no hay términos registrados.</p>
                </c:when>
                <c:otherwise>
                    <div class="table-responsive">
                        <table class="tabla-app">
                            <thead><tr><th>Término</th><th>Artículos asociados</th></tr></thead>
                            <tbody>
                                <c:forEach var="termino" items="${terminos}">
                                    <tr>
                                        <td class="nombre-item"><c:out value="${termino.palabra}"/></td>
                                        <td><c:out value="${termino.asociaciones}"/></td>
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
