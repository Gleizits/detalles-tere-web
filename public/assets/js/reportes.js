/*
 * Reportes: alterna el selector de periodo entre día (date) y mes (month).
 * Script externo para cumplir la CSP (sin scripts en línea).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var tipo = document.getElementById('tipo');
        var periodo = document.getElementById('periodo');
        if (!tipo || !periodo) { return; }

        tipo.addEventListener('change', function () {
            periodo.type = (tipo.value === 'mes') ? 'month' : 'date';
            periodo.value = '';
        });
    });
})();
