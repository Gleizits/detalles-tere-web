<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>
<c:set var="ruta" value="${pageContext.request.servletPath}"/>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${titulo} · DetallesTere</title>
    <link rel="stylesheet" href="${ctx}/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="${ctx}/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="${ctx}/assets/css/app.css">
</head>
<body>

    <!-- Barra superior con navegación principal -->
    <nav class="app-navbar">
        <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">

            <a class="marca" href="${ctx}/panel">
                Detalles<span class="marca-punto">Tere</span>
            </a>

            <!-- Secciones -->
            <div class="nav-app">
                <a href="${ctx}/panel"
                   class="nav-app-link ${ruta == '/panel.jsp' || ruta == '/' ? 'activo' : ''}">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
                <a href="${ctx}/caja"
                   class="nav-app-link ${ruta.startsWith('/caja') ? 'activo' : ''}">
                    <i class="bi bi-cash-coin"></i> Caja
                </a>
                <a href="${ctx}/venta"
                   class="nav-app-link ${ruta.startsWith('/venta') ? 'activo' : ''}">
                    <i class="bi bi-cart-check"></i> Venta
                </a>
                <a href="${ctx}/pedidos"
                   class="nav-app-link ${ruta.startsWith('/pedidos') ? 'activo' : ''}">
                    <i class="bi bi-calendar2-check"></i> Pedidos
                </a>
                <a href="${ctx}/inventario"
                   class="nav-app-link ${ruta.startsWith('/inventario') ? 'activo' : ''}">
                    <i class="bi bi-box-seam"></i> Inventario
                </a>
                <c:if test="${usuario.admin}">
                     <a href="${ctx}/ingresos"
                        class="nav-app-link ${ruta.startsWith('/ingresos') ? 'activo' : ''}">
                        <i class="bi bi-truck"></i> Ingresos
                     </a>
                     <a href="${ctx}/terminos"
                        class="nav-app-link ${ruta.startsWith('/terminos') ? 'activo' : ''}">
                        <i class="bi bi-tags"></i> Términos
                     </a>
                     <a href="${ctx}/reportes"
                        class="nav-app-link ${ruta.startsWith('/reportes') ? 'activo' : ''}">
                        <i class="bi bi-graph-up-arrow"></i> Reportes
                     </a>
                     <a href="${ctx}/usuarios"
                        class="nav-app-link ${ruta.startsWith('/usuarios') ? 'activo' : ''}">
                        <i class="bi bi-people"></i> Usuarios
                     </a>
                </c:if>
            </div>

            <!-- Usuario + salir -->
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted usuario-chip">
                    <i class="bi bi-person-circle me-1"></i>
                    <c:out value="${usuario.nombreCompleto}"/>
                </span>
                <form method="post" action="${ctx}/logout" class="m-0">
                    <input type="hidden" name="csrfToken" value="${csrfToken}">
                    <button type="submit" class="salir border-0 bg-transparent p-0">
                        <i class="bi bi-box-arrow-right me-1"></i>Salir
                    </button>
                </form>
            </div>

        </div>
    </nav>

    <main class="container py-4">
