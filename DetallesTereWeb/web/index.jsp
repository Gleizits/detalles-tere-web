<%@page contentType="text/html" pageEncoding="UTF-8"%>
<%-- Punto de entrada: redirige al login --%>
<%
    response.sendRedirect(request.getContextPath() + "/login");
%>
