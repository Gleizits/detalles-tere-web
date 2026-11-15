<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

    <!-- Saludo -->
    <div class="mb-4">
        <h1 class="saludo">
            <c:out value="${saludo}"/>, <c:out value="${usuario.nombre}"/>.
        </h1>
        <p class="saludo-sub mb-0">¿Qué vamos a hacer hoy?</p>
    </div>

    <!-- Accesos rápidos -->
    <div class="row g-3">

        <div class="col-12 col-md-6 col-xl-4">
            <a class="app-card seccion-tarjeta" href="${ctx}/caja">
                <div class="ico"><i class="bi bi-cash-coin"></i></div>
                <h3>Caja</h3>
                <p>Abrir caja, registrar ventas y cobrar en yape o efectivo.</p>
            </a>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <a class="app-card seccion-tarjeta" href="${ctx}/inventario">
                <div class="ico"><i class="bi bi-box-seam"></i></div>
                <h3>Inventario</h3>
                <p>Ver productos, materiales y arreglos con su stock actual.</p>
            </a>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <a class="app-card seccion-tarjeta" href="${ctx}/panel">
                <div class="ico"><i class="bi bi-calendar-check"></i></div>
                <h3>Pedidos</h3>
                <p>Gestionar pedidos reservados, adelantos y recogidas.</p>
            </a>
        </div>

        <!-- Reportes: solo administrador -->
        <c:if test="${usuario.admin}">
            <div class="col-12 col-md-6 col-xl-4">
                <a class="app-card seccion-tarjeta" href="${ctx}/panel">
                    <div class="ico"><i class="bi bi-graph-up"></i></div>
                    <h3>Reportes</h3>
                    <p>Resumen de ingresos al inventario, ventas y cajas del día.</p>
                </a>
            </div>
        </c:if>

    </div>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
