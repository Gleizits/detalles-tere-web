<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<%@taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="titulo-seccion mb-0">
            <i class="bi bi-box-seam me-2"></i>Inventario
        </h1>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="text-muted"><c:out value="${total}"/> artículo(s)</span>
            <a class="btn btn-app btn-app-primary" href="${ctx}/inventario?nuevo=1">
                <i class="bi bi-plus-lg me-1"></i>Nuevo artículo
            </a>
        </div>
    </div>

    <c:if test="${not empty mensaje}">
        <div class="${mensajeError ? 'alerta-error' : 'alerta-exito'} mb-4">
            <i class="bi ${mensajeError ? 'bi-exclamation-triangle' : 'bi-check-circle'} me-2"></i>
            <c:out value="${mensaje}"/>
        </div>
    </c:if>

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
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <c:forEach var="it" items="${items}">
                            <tr${it.estado ? '' : ' class="text-muted"'}>
                                <td class="nombre-item">
                                    <c:out value="${it.nombre}"/>
                                    <c:if test="${not empty it.tipo}">
                                        <span class="text-muted tipo-item"><c:out value="${it.tipo}"/></span>
                                    </c:if>
                                    <c:if test="${!it.estado}">
                                        <span class="badge-estado cerrado ms-1" title="Baja lógica: no se vende pero se conserva el historial">BAJA</span>
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
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="${ctx}/inventario?editar=${it.idInventario}"
                                       title="Editar <c:out value='${it.nombre}'/>">
                                        <i class="bi bi-pencil-square me-1"></i>Editar
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#modalAjuste"
                                            data-id="${it.idInventario}"
                                            data-nombre="<c:out value='${it.nombre}'/>"
                                            title="Ajustar el stock de <c:out value='${it.nombre}'/>">
                                        <i class="bi bi-arrow-left-right me-1"></i>Ajustar
                                    </button>
                                </td>
                            </tr>
                        </c:forEach>
                    </tbody>
                </table>
            </div>
        </c:otherwise>
    </c:choose>

    <!-- ===================== MODAL: AJUSTE DE STOCK ===================== -->
    <div class="modal fade" id="modalAjuste" tabindex="-1" aria-labelledby="modalAjusteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="${ctx}/inventario" id="formAjuste">
                    <input type="hidden" name="csrfToken" value="${csrfToken}">
                    <input type="hidden" name="accion" value="ajustar">
                    <input type="hidden" name="idInventario" id="ajusteId" value="">

                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="modalAjusteLabel">
                            <i class="bi bi-arrow-left-right me-2"></i>Ajustar stock
                        </h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-3">
                            Artículo: <strong id="ajusteNombre"></strong>
                        </p>

                        <label class="form-label">¿Qué hacemos? *</label>
                        <div class="d-flex gap-4 mb-3">
                            <div class="form-check fs-5">
                                <input class="form-check-input" type="radio" name="signo"
                                       id="ajusteSumar" value="1" checked>
                                <label class="form-check-label" for="ajusteSumar">
                                    <i class="bi bi-plus-circle text-success me-1"></i>Sumar (entró mercadería)
                                </label>
                            </div>
                            <div class="form-check fs-5">
                                <input class="form-check-input" type="radio" name="signo"
                                       id="ajusteRestar" value="-1">
                                <label class="form-check-label" for="ajusteRestar">
                                    <i class="bi bi-dash-circle text-danger me-1"></i>Restar (salió o se dañó)
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="ajusteCantidad">Cantidad de unidades *</label>
                            <input type="number" class="form-control" id="ajusteCantidad"
                                   name="cantidad" min="1" step="1" value="1" required inputmode="numeric">
                        </div>

                        <div class="mb-1">
                            <label class="form-label" for="ajusteMotivo">Motivo (opcional)</label>
                            <input type="text" class="form-control" id="ajusteMotivo"
                                   name="motivo" maxlength="100"
                                   placeholder="Ej: merma, conteo físico, reposición">
                        </div>
                        <small class="text-muted">Quedará registrado quién hizo el ajuste y cuándo.</small>

                        <%-- El delta firmado lo calcula el JS al enviar --%>
                        <input type="hidden" name="delta" id="ajusteDelta" value="1">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-app btn-app-primary">
                            <i class="bi bi-check-lg me-1"></i>Guardar ajuste
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('modalAjuste');
            if (!modal) return;

            modal.addEventListener('show.bs.modal', function (ev) {
                var btn = ev.relatedTarget;
                if (!btn) return;
                document.getElementById('ajusteId').value = btn.getAttribute('data-id') || '';
                document.getElementById('ajusteNombre').textContent = btn.getAttribute('data-nombre') || '';
                document.getElementById('ajusteCantidad').value = '1';
                document.getElementById('ajusteMotivo').value = '';
                document.getElementById('ajusteSumar').checked = true;
                document.getElementById('ajusteDelta').value = '1';
            });

            document.getElementById('formAjuste').addEventListener('submit', function () {
                var signo = document.querySelector('input[name="signo"]:checked');
                var cant = parseInt(document.getElementById('ajusteCantidad').value, 10) || 0;
                var s = signo ? parseInt(signo.value, 10) : 1;
                document.getElementById('ajusteDelta').value = String(s * cant);
            });
        })();
    </script>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
