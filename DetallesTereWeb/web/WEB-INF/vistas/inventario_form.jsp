<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<%@taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<%-- esEdicion = true solo cuando el artículo ya existe en BD --%>
<c:set var="esEdicion" value="${not empty item && item.idInventario > 0}"/>
<c:set var="claseActual" value="${empty item ? '' : item.clase}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="titulo-seccion mb-0">
            <i class="bi bi-box-seam me-2"></i><c:out value="${titulo}"/>
        </h1>
        <a class="btn btn-outline-secondary" href="${ctx}/inventario">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    <c:if test="${not empty mensaje}">
        <div class="${mensajeError ? 'alerta-error' : 'alerta-exito'} mb-4">
            <i class="bi ${mensajeError ? 'bi-exclamation-triangle' : 'bi-check-circle'} me-2"></i>
            <c:out value="${mensaje}"/>
        </div>
    </c:if>

    <div class="app-card p-4">
        <form method="post" action="${ctx}/inventario" autocomplete="off" id="formArticulo">
            <input type="hidden" name="csrfToken" value="${csrfToken}">
            <input type="hidden" name="accion" value="${esEdicion ? 'editar' : 'crear'}">
            <c:if test="${esEdicion}">
                <input type="hidden" name="idInventario" value="${item.idInventario}">
            </c:if>

            <!-- ============ TIPO DE ARTÍCULO ============ -->
            <c:choose>
                <c:when test="${esEdicion && not empty claseActual}">
                    <%-- La clase NUNCA cambia después del alta --%>
                    <div class="mb-3">
                        <span class="form-label d-block">Tipo de artículo</span>
                        <span class="badge-clase">${claseActual}</span>
                        <small class="text-muted d-block mt-1">El tipo de artículo no se puede cambiar.</small>
                    </div>
                </c:when>
                <c:otherwise>
                    <div class="mb-3">
                        <span class="form-label d-block">¿Qué tipo de artículo es? *</span>
                        <div class="d-flex gap-4 flex-wrap mt-1">
                            <div class="form-check fs-5">
                                <input class="form-check-input" type="radio" name="clase"
                                       id="claseProducto" value="PRODUCTO"
                                       <c:if test="${empty claseActual || claseActual == 'PRODUCTO'}">checked</c:if>
                                       onchange="alternarClase()">
                                <label class="form-check-label" for="claseProducto">Producto</label>
                            </div>
                            <div class="form-check fs-5">
                                <input class="form-check-input" type="radio" name="clase"
                                       id="claseMaterial" value="MATERIAL"
                                       <c:if test="${claseActual == 'MATERIAL'}">checked</c:if>
                                       onchange="alternarClase()">
                                <label class="form-check-label" for="claseMaterial">Material</label>
                            </div>
                            <div class="form-check fs-5">
                                <input class="form-check-input" type="radio" name="clase"
                                       id="claseArreglo" value="ARREGLO"
                                       <c:if test="${claseActual == 'ARREGLO'}">checked</c:if>
                                       onchange="alternarClase()">
                                <label class="form-check-label" for="claseArreglo">Arreglo</label>
                            </div>
                        </div>
                    </div>
                </c:otherwise>
            </c:choose>

            <!-- ============ NOMBRE Y TIPO ============ -->
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="nombre">Nombre del artículo *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre"
                           maxlength="100" required
                           value="<c:out value='${item.nombre}'/>"
                           placeholder="Ej: Marco de madera 20×30">
                </div>
                <div class="col-12 col-md-6" id="zonaTipo"
                     style="display:${claseActual == 'ARREGLO' ? 'none' : ''}">
                    <label class="form-label" for="tipo">Tipo o material *</label>
                    <input type="text" class="form-control" id="tipo" name="tipo"
                           maxlength="50"
                           value="<c:out value='${item.tipo}'/>"
                           placeholder="Ej: madera, acrílico, tela…">
                    <small class="text-muted">Los arreglos no llevan tipo.</small>
                </div>
            </div>

            <!-- ============ CATEGORÍA ============ -->
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="idCategoria">Categoría *</label>
                    <select class="form-select" id="idCategoria" name="idCategoria">
                        <option value="" ${empty item || item.idCategoria == 0 ? 'selected' : ''}>
                            — Elige una categoría —
                        </option>
                        <c:forEach var="c" items="${categorias}">
                            <option value="${c.idCategoria}"
                                    ${item.idCategoria == c.idCategoria ? 'selected' : ''}>
                                <c:out value="${c.nombre}"/>
                            </option>
                        </c:forEach>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="categoriaNueva">…o escribe una categoría nueva</label>
                    <input type="text" class="form-control" id="categoriaNueva" name="categoriaNueva"
                           maxlength="100" placeholder="Opcional si eliges una arriba">
                </div>
            </div>

            <!-- ============ UNIDAD, ALMACÉN Y STOCK ============ -->
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="unidadMedida">Unidad de medida o empaque *</label>
                    <input type="text" class="form-control" id="unidadMedida" name="unidadMedida"
                           maxlength="50" required
                           value="<c:out value='${item.unidadMedida}'/>"
                           placeholder="Ej: paquete de 12, unidad">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="tipoAlmacen">Cómo se guarda *</label>
                    <input type="text" class="form-control" id="tipoAlmacen" name="tipoAlmacen"
                           maxlength="35" required
                           value="<c:out value='${item.tipoAlmacen}'/>"
                           placeholder="Ej: costal, caja, estante">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="stockMin">Stock mínimo *</label>
                    <input type="number" class="form-control" id="stockMin" name="stockMin"
                           min="0" step="1" required inputmode="numeric"
                           value="${empty item ? '' : item.stockMin}">
                </div>
                <c:if test="${not esEdicion}">
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="stockAct">Stock inicial *</label>
                        <input type="number" class="form-control" id="stockAct" name="stockAct"
                               min="0" step="1" required inputmode="numeric"
                               value="${empty item ? 0 : item.stockAct}">
                    </div>
                </c:if>
            </div>

            <!-- ============ PRECIOS ============ -->
            <%-- Precio de venta: siempre en el alta; en edición solo lo ve el admin --%>
            <c:if test="${not esEdicion || usuario.admin}">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="precioVenta">
                            Precio de venta (S/) ${esEdicion ? '' : '*'}
                        </label>
                        <input type="text" class="form-control" id="precioVenta" name="precioVenta"
                               inputmode="decimal" maxlength="12"
                               value="<fmt:formatNumber value='${item.precioVenta}' minFractionDigits='2' maxFractionDigits='2' groupingUsed='false'/>"
                               placeholder="Ej: 25.00">
                        <small class="text-muted" id="notaVenta">
                            <c:if test="${esEdicion}">Déjalo vacío para no cambiarlo.</c:if>
                        </small>
                    </div>
                    <c:if test="${usuario.admin}">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="precioCompra">Precio de compra / costo (S/)</label>
                            <input type="text" class="form-control" id="precioCompra" name="precioCompra"
                                   inputmode="decimal" maxlength="12"
                                   value="<fmt:formatNumber value='${item.precioCompra}' minFractionDigits='2' maxFractionDigits='2' groupingUsed='false'/>"
                                   placeholder="Ej: 15.00">
                            <small class="text-muted" id="notaCompra">Solo lo ve el administrador.</small>
                        </div>
                        <div class="col-12 col-md-4">
                            <span class="form-label d-block">Precio sugerido</span>
                            <div class="alerta-info mb-0" id="sugerencia">
                                Escribe el precio de compra y te calculo el precio de venta (costo × 1.50).
                            </div>
                        </div>
                    </c:if>
                </div>
            </c:if>
            <c:if test="${esEdicion && !usuario.admin}">
                <div class="alerta-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    Los precios de este artículo solo los puede cambiar el administrador.
                </div>
            </c:if>

            <!-- ============ MATERIAL RELACIONADO (SOLO PRODUCTO) ============ -->
            <div class="mb-3" id="zonaMaterial"
                 style="display:${(not empty claseActual && claseActual != 'PRODUCTO') ? 'none' : ''}">
                <label class="form-label" for="idMaterialVinc">Material del que está hecho (opcional)</label>
                <select class="form-select" id="idMaterialVinc" name="idMaterialVinc">
                    <option value="0" ${empty item || item.idMaterialVinc == 0 ? 'selected' : ''}>
                        — Ninguno —
                    </option>
                    <c:forEach var="m" items="${materiales}">
                        <option value="${m.idCategoria}"
                                ${item.idMaterialVinc == m.idCategoria ? 'selected' : ''}>
                            <c:out value="${m.nombre}"/>
                        </option>
                    </c:forEach>
                </select>
                <small class="text-muted">
                    Útil cuando el mismo artículo también se usa como material (ej.: limpiapipas).
                </small>
            </div>

            <!-- ============ ESTADO (SOLO ADMIN, SOLO EDICIÓN) ============ -->
            <c:if test="${usuario.admin && esEdicion}">
                <div class="mb-3">
                    <div class="form-check form-switch fs-5">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="estado" name="estado" <c:if test="${item.estado}">checked</c:if>>
                        <label class="form-check-label" for="estado">
                            Artículo activo (desmárcalo para dar de baja sin borrarlo)
                        </label>
                    </div>
                </div>
            </c:if>

            <div class="d-flex gap-2 flex-wrap mt-4">
                <button type="submit" class="btn btn-app btn-app-primary py-2 px-4">
                    <i class="bi bi-save me-1"></i>
                    <c:choose>
                        <c:when test="${esEdicion}">Guardar cambios</c:when>
                        <c:otherwise>Guardar artículo</c:otherwise>
                    </c:choose>
                </button>
                <a class="btn btn-outline-secondary py-2 px-4" href="${ctx}/inventario">Cancelar</a>
            </div>
        </form>
    </div>

<script>
    function alternarClase() {
        var sel = document.querySelector('input[name="clase"]:checked');
        if (!sel) return;
        var clase = sel.value;
        document.getElementById('zonaTipo').style.display =
            (clase === 'ARREGLO') ? 'none' : '';
        document.getElementById('zonaMaterial').style.display =
            (clase === 'PRODUCTO') ? '' : 'none';
        var nombre = document.getElementById('nombre');
        if (nombre) nombre.maxLength = (clase === 'ARREGLO') ? 100 : 50;
    }

    // Precio sugerido: costo × 1.50 (solo si el precio de venta está vacío
    // o fue puesto por la sugerencia, para no pisar lo que escribió la usuaria).
    function calcularSugerencia() {
        var pc = document.getElementById('precioCompra');
        var pv = document.getElementById('precioVenta');
        var nota = document.getElementById('sugerencia');
        if (!pc || !pv || !nota) return;
        var costo = parseFloat(pc.value.replace(',', '.'));
        if (isNaN(costo) || costo <= 0) {
            nota.textContent = 'Escribe el precio de compra y te calculo el precio de venta (costo × 1.50).';
            return;
        }
        var sug = (costo * 1.5).toFixed(2);
        nota.innerHTML = 'Precio sugerido: <strong>S/ ' + sug + '</strong> — ' +
            '<a href="#" id="usarSugerido">usar este precio</a>';
        document.getElementById('usarSugerido').addEventListener('click', function (e) {
            e.preventDefault();
            pv.value = sug;
            pv.dataset.sugerido = '1';
        });
        if (pv.value.trim() === '' || pv.dataset.sugerido === '1') {
            pv.value = sug;
            pv.dataset.sugerido = '1';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        alternarClase();
        var pc = document.getElementById('precioCompra');
        if (pc) {
            pc.addEventListener('input', calcularSugerencia);
            calcularSugerencia();
        }
        var pv = document.getElementById('precioVenta');
        if (pv) {
            pv.addEventListener('input', function () { pv.dataset.sugerido = '0'; });
        }
    });
</script>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
