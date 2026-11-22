<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<%@taglib prefix="fn" uri="http://java.sun.com/jsp/jstl/functions"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>
<%--
    Sección activa del menú lateral: los servlets hacen forward al JSP de la
    vista, por lo que la ruta disponible es la del JSP (p. ej.
    /WEB-INF/vistas/caja.jsp). Se reduce al nombre base de la vista para
    obtener la sección (/caja) y marcar el enlace activo.
--%>
<c:set var="origen" value="${pageContext.request.getAttribute('javax.servlet.forward.requestURI')}"/>
<c:if test="${empty origen}">
    <c:set var="origen" value="${pageContext.request.requestURI}"/>
</c:if>
<c:set var="ruta" value="${fn:replace(origen, ctx, '')}"/>
<c:set var="jsp" value="${fn:substringAfter(ruta, '/vistas/')}"/>
<c:set var="base" value="${fn:substringBefore(jsp, '.jsp')}"/>
<c:if test="${fn:contains(base, '_')}">
    <c:set var="base" value="${fn:substringBefore(base, '_')}"/>
</c:if>
<c:choose>
    <c:when test="${empty base}"><c:set var="seccion" value="${ruta}"/></c:when>
    <c:when test="${base == 'panel'}"><c:set var="seccion" value="/panel"/></c:when>
    <c:when test="${base == 'pedido'}"><c:set var="seccion" value="/pedidos"/></c:when>
    <c:otherwise><c:set var="seccion" value="/${base}"/></c:otherwise>
</c:choose>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${titulo} · DetallesTere</title>
    <link rel="icon" type="image/png" href="${ctx}/assets/img/logo.png">
    <link rel="stylesheet" href="${ctx}/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="${ctx}/assets/css/bootstrap-icons.min.css">
    <!-- ?v= fuerza al navegador a descargar el CSS tras cada cambio -->
    <link rel="stylesheet" href="${ctx}/assets/css/app.css?v=2026-11-22d">
</head>
<body>

    <!-- Menú lateral: cajón superpuesto que se abre con la hamburguesa
         (en celular Y en escritorio). Está cerrado por defecto. -->
    <aside class="app-sidebar" id="appSidebar" aria-label="Menú de secciones">
        <div class="sidebar-marca">
            <a class="marca marca-lateral" href="${ctx}/panel">
                <img src="${ctx}/assets/img/logo.png" alt="Logo" class="marca-logo">
                <span class="marca-completa">Detalles<span class="marca-punto">Tere</span></span>
            </a>
            <button type="button" class="sidebar-cerrar" id="sidebarClose"
                    aria-label="Cerrar el menú de secciones">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="${ctx}/panel" title="Inicio"
               class="nav-app-link ${seccion == '/panel' || seccion == '/' ? 'activo' : ''}"
               ${seccion == '/panel' || seccion == '/' ? 'aria-current="page"' : ''}>
                <i class="bi bi-house-door" aria-hidden="true"></i>
                <span class="sidebar-texto">Inicio</span>
            </a>
            <a href="${ctx}/caja" title="Caja"
               class="nav-app-link ${seccion == '/caja' ? 'activo' : ''}"
               ${seccion == '/caja' ? 'aria-current="page"' : ''}>
                <i class="bi bi-cash-coin" aria-hidden="true"></i>
                <span class="sidebar-texto">Caja</span>
            </a>
            <a href="${ctx}/venta" title="Venta"
               class="nav-app-link ${seccion == '/venta' ? 'activo' : ''}"
               ${seccion == '/venta' ? 'aria-current="page"' : ''}>
                <i class="bi bi-cart-check" aria-hidden="true"></i>
                <span class="sidebar-texto">Venta</span>
            </a>
            <a href="${ctx}/pedidos" title="Pedidos"
               class="nav-app-link ${seccion == '/pedidos' ? 'activo' : ''}"
               ${seccion == '/pedidos' ? 'aria-current="page"' : ''}>
                <i class="bi bi-calendar2-check" aria-hidden="true"></i>
                <span class="sidebar-texto">Pedidos</span>
            </a>
            <a href="${ctx}/inventario" title="Inventario"
               class="nav-app-link ${seccion == '/inventario' ? 'activo' : ''}"
               ${seccion == '/inventario' ? 'aria-current="page"' : ''}>
                <i class="bi bi-box-seam" aria-hidden="true"></i>
                <span class="sidebar-texto">Inventario</span>
            </a>
            <c:if test="${usuario.admin}">
                <div class="sidebar-separador" role="separator"></div>
                <a href="${ctx}/ingresos" title="Ingresos"
                   class="nav-app-link ${seccion == '/ingresos' ? 'activo' : ''}"
                   ${seccion == '/ingresos' ? 'aria-current="page"' : ''}>
                    <i class="bi bi-truck" aria-hidden="true"></i>
                    <span class="sidebar-texto">Ingresos</span>
                </a>
                <a href="${ctx}/terminos" title="Términos"
                   class="nav-app-link ${seccion == '/terminos' ? 'activo' : ''}"
                   ${seccion == '/terminos' ? 'aria-current="page"' : ''}>
                    <i class="bi bi-tags" aria-hidden="true"></i>
                    <span class="sidebar-texto">Términos</span>
                </a>
                <a href="${ctx}/reportes" title="Reportes"
                   class="nav-app-link ${seccion == '/reportes' ? 'activo' : ''}"
                   ${seccion == '/reportes' ? 'aria-current="page"' : ''}>
                    <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                    <span class="sidebar-texto">Reportes</span>
                </a>
                <a href="${ctx}/usuarios" title="Usuarios"
                   class="nav-app-link ${seccion == '/usuarios' ? 'activo' : ''}"
                   ${seccion == '/usuarios' ? 'aria-current="page"' : ''}>
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <span class="sidebar-texto">Usuarios</span>
                </a>
            </c:if>
        </nav>
    </aside>

    <!-- Fondo al abrir el menú (celular y escritorio) -->
    <div class="app-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <div class="app-cuerpo">

        <!-- Barra superior: botón de menú (abre el cajón) + usuario -->
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="nav-menu-btn" id="sidebarToggle"
                        aria-controls="appSidebar" aria-expanded="false"
                        aria-label="Abrir el menú de secciones">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <a class="marca marca-top" href="${ctx}/panel">
                    <img src="${ctx}/assets/img/logo.png" alt="Logo" class="marca-logo">
                    <span>Detalles<span class="marca-punto">Tere</span></span>
                </a>
            </div>

            <div class="d-flex align-items-center gap-3">
                <span class="text-muted usuario-chip">
                    <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                    <span class="usuario-nombre"><c:out value="${usuario.nombreCompleto}"/></span>
                </span>
                <form method="post" action="${ctx}/logout" class="m-0">
                    <input type="hidden" name="csrfToken" value="${csrfToken}">
                    <button type="submit" class="salir border-0 bg-transparent p-0">
                        <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Salir
                    </button>
                </form>
            </div>
        </header>

        <main class="container py-4">
