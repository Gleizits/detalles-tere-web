/*
 * Punto de venta (Venta): método de pago, zona de crédito y vuelto.
 * Reemplaza los onchange/oninput de la vista (CSP sin unsafe-inline).
 * El total llega en data-total del formulario de pago (lo pinta la vista
 * con numJs(), siempre con punto decimal y sin agrupación de miles).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('form[data-total]');
        if (!form) { return; }

        var TOTAL = parseFloat(form.getAttribute('data-total'));
        if (isNaN(TOTAL)) { TOTAL = 0; }

        var monto = document.getElementById('montoRecibido');
        if (!monto) { return; }

        function calcularVuelto() {
            var recibido = parseFloat(monto.value || '0');
            if (isNaN(recibido)) { recibido = 0; }
            var v = recibido - TOTAL;
            document.getElementById('vuelto').textContent =
                'S/ ' + (v > 0 ? v.toFixed(2) : '0.00');
        }

        function actualizarPago() {
            var yape = document.getElementById('pagoYape').checked;
            var credito = document.getElementById('pagoCredito').checked;
            var zona = document.getElementById('zonaEfectivo');
            var zonaCredito = document.getElementById('zonaCredito');
            if (yape || credito) {
                zona.style.display = 'none';
                monto.value = '';
            } else {
                zona.style.display = '';
            }
            zonaCredito.style.display = credito ? '' : 'none';
            calcularVuelto();
        }

        var radios = document.querySelectorAll('input[name="tipoPago"]');
        for (var i = 0; i < radios.length; i++) {
            radios[i].addEventListener('change', actualizarPago);
        }
        monto.addEventListener('input', calcularVuelto);

        actualizarPago();
    });
})();
