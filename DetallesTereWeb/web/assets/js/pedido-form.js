/*
 * Formulario de nuevo pedido reservado: tipo de reserva (adelanto/completo),
 * monto mínimo del 50% y total a cobrar.
 * Reemplaza el script en línea y los onchange de pedido_form.jsp (CSP sin
 * unsafe-inline). El total llega en data-total del formulario de pago
 * (lo pinta el JSP con fmt:formatNumber, siempre numérico).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('form[data-total]');
        if (!form) { return; }

        var TOTAL = parseFloat(form.getAttribute('data-total'));
        if (isNaN(TOTAL)) { TOTAL = 0; }
        var MINIMO = TOTAL * 0.5;

        var monto = document.getElementById('monto');
        if (!monto) { return; }

        function actualizarTipo() {
            var completo = document.getElementById('tipoCompleto').checked;
            document.getElementById('zonaAdelanto').style.display = completo ? 'none' : '';
            document.getElementById('zonaCompleto').style.display = completo ? '' : 'none';
            if (completo) {
                monto.removeAttribute('required');
            } else {
                monto.setAttribute('required', 'required');
            }
        }

        var radios = document.querySelectorAll('input[name="tipoReserva"]');
        for (var i = 0; i < radios.length; i++) {
            radios[i].addEventListener('change', actualizarTipo);
        }

        monto.value = MINIMO.toFixed(2);
        document.getElementById('minNota').textContent =
            'Mínimo S/ ' + MINIMO.toFixed(2) + ' (50% del total).';
        document.getElementById('totalCompleto').textContent = TOTAL.toFixed(2);
        actualizarTipo();
    });
})();
