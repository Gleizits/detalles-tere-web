<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@page isErrorPage="true" %>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error · DetallesTere</title>
    <link rel="stylesheet" href="${pageContext.request.contextPath}/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="${pageContext.request.contextPath}/assets/css/app.css">
</head>
<body>
    <div class="login-envoltorio">
        <div class="app-card login-tarjeta text-center">
            <div class="mb-3" style="font-size:3rem;color:var(--c-error)">
                <i class="bi bi-exclamation-octagon"></i>
            </div>
            <h1 class="h4 mb-2">Algo salió mal</h1>
            <p class="text-muted mb-4">
                No pudimos completar la operación. Intenta de nuevo o vuelve al inicio.
            </p>
            <a class="btn btn-app btn-app-primary" href="${pageContext.request.contextPath}/panel">
                Volver al inicio
            </a>
        </div>
    </div>
</body>
</html>
