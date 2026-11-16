<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%@taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core"%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>
<c:set var="editando" value="${not empty usuarioEditar}"/>

<jsp:include page="/WEB-INF/vistas/comunes/cabecera.jsp"/>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="titulo-seccion mb-1"><i class="bi bi-people me-2"></i>Usuarios</h1>
        <p class="text-muted mb-0">Controla quién puede entrar y qué puede administrar.</p>
    </div>
    <a class="btn btn-app btn-app-primary" href="${ctx}/usuarios?nuevo=1">
        <i class="bi bi-person-plus me-1"></i>Nuevo usuario
    </a>
</div>

<c:if test="${not empty mensaje}">
    <div class="alerta-exito mb-4"><i class="bi bi-check-circle me-2"></i><c:out value="${mensaje}"/></div>
</c:if>
<c:if test="${not empty error}">
    <div class="alerta-error mb-4"><i class="bi bi-exclamation-triangle me-2"></i><c:out value="${error}"/></div>
</c:if>

<div class="usuarios-layout">
    <div class="app-card tabla-envoltorio">
        <table class="tabla-app">
            <thead><tr><th>Persona</th><th>Usuario</th><th>Rol</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                <c:forEach var="u" items="${usuarios}">
                    <tr>
                        <td class="nombre-item"><c:out value="${u.nombre}"/> <c:out value="${u.apellido}"/></td>
                        <td><c:out value="${u.username}"/></td>
                        <td><span class="badge-clase"><c:out value="${u.rol}"/></span></td>
                        <td><span class="badge-estado ${u.estado ? 'abierto' : 'cerrado'}">${u.estado ? 'Activo' : 'Baja'}</span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="${ctx}/usuarios?editar=${u.id}">
                                <i class="bi bi-pencil-square me-1"></i>Editar
                            </a>
                        </td>
                    </tr>
                </c:forEach>
            </tbody>
        </table>
    </div>

    <c:if test="${editando || nuevo}">
        <div class="app-card p-4">
            <h2 class="h4 mb-3">${editando ? 'Editar usuario' : 'Nuevo usuario'}</h2>
            <form method="post" action="${ctx}/usuarios" autocomplete="off">
                <input type="hidden" name="csrfToken" value="${csrfToken}">
                <input type="hidden" name="accion" value="${editando ? 'editar' : 'crear'}">
                <c:if test="${editando}"><input type="hidden" name="id" value="${usuarioEditar.id}"></c:if>
                <div class="mb-3">
                    <label class="form-label" for="username">Usuario *</label>
                    <input class="form-control" id="username" name="username" maxlength="50"
                           autocomplete="username"
                           value="<c:out value='${usuarioEditar.username}'/>" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="nombre">Nombre *</label>
                        <input class="form-control" id="nombre" name="nombre" maxlength="50"
                               value="<c:out value='${usuarioEditar.nombre}'/>" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="apellido">Apellido *</label>
                        <input class="form-control" id="apellido" name="apellido" maxlength="50"
                               value="<c:out value='${usuarioEditar.apellido}'/>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="correo">Correo</label>
                    <input class="form-control" id="correo" name="correo" type="email" maxlength="75"
                           value="<c:out value='${usuarioEditar.correo}'/>">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="rol">Rol *</label>
                        <select class="form-select" id="rol" name="rol" required>
                            <option value="empleado" ${usuarioEditar.rol == 'empleado' ? 'selected' : ''}>Empleado</option>
                            <option value="admin" ${usuarioEditar.rol == 'admin' ? 'selected' : ''}>Administrador</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="password">${editando ? 'Nueva contraseña' : 'Contraseña *'}</label>
                        <input class="form-control" id="password" name="password" type="password"
                               minlength="6" maxlength="100" autocomplete="new-password"
                               ${editando ? '' : 'required'}>
                    </div>
                </div>
                <c:if test="${editando}">
                    <div class="form-check form-switch fs-5 mb-4">
                        <input class="form-check-input" type="checkbox" id="estado" name="estado"
                               <c:if test="${usuarioEditar.estado}">checked</c:if>>
                        <label class="form-check-label" for="estado">Usuario activo</label>
                    </div>
                </c:if>
                <div class="d-flex gap-2">
                    <button class="btn btn-app btn-app-primary" type="submit">Guardar usuario</button>
                    <a class="btn btn-outline-secondary" href="${ctx}/usuarios">Cancelar</a>
                </div>
            </form>
        </div>
    </c:if>
</div>

<jsp:include page="/WEB-INF/vistas/comunes/pie.jsp"/>
