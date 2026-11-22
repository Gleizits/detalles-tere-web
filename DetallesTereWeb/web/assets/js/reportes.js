/*
 * Reportes: cambia el input de periodo entre fecha (día) y mes.
 * Reemplaza el script en línea de reportes.jsp (CSP sin unsafe-inline).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var tipo = document.getElementById('tipo');
        var periodo = document.getElementById('periodo');
        if (!tipo || !periodo) { return; }
        tipo.addEventListener('change', function () {
            periodo.type = tipo.value === 'mes' ? 'month' : 'date';
            periodo.value = '';
        });
    });
})();
