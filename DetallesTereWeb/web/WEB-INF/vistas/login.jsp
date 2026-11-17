<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · DetallesTere</title>
    <link rel="stylesheet" href="${pageContext.request.contextPath}/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="${pageContext.request.contextPath}/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="${pageContext.request.contextPath}/assets/css/app.css">
</head>
<body>
    <div class="login-envoltorio">
        <div class="app-card login-tarjeta">

            <div class="text-center mb-4">
                <span class="marca">Detalles<span class="marca-punto">Tere</span></span>
                <p class="text-muted mt-2 mb-0">Sistema de ventas e inventario</p>
            </div>

            <c:if test="${not empty error}">
                <div class="alerta-error p-3 mb-3 text-center" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <c:out value="${error}"/>
                </div>
            </c:if>

            <form action="${pageContext.request.contextPath}/login" method="post" autocomplete="off">
                <input type="hidden" name="csrfToken" value="${csrfToken}">
                <input type="hidden" name="destino"
                       value="<c:out value='${param.destino}'/>">

                <div class="mb-3">
                    <label for="username" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<c:out value='${username}'/>" autocomplete="username" required autofocus>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password"
                           autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn btn-app btn-app-primary w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar sesión
                </button>
            </form>

        </div>
    </div>
</body>
</html>
