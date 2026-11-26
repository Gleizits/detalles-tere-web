/*
 * Formulario de nuevo pedido reservado: tipo de reserva (adelanto/completo),
 * monto mínimo del 50% y total a cobrar.
 * Reemplaza los scripts en línea de pedido_form.jsp (CSP sin unsafe-inline).
 * El total llega en data-total del formulario de pago, pintado por PHP con
 * numJs() (dos decimales, sin separador de miles).
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
        var completo = document.getElementById('tipoCompleto');
        var zonaAdelanto = document.getElementById('zonaAdelanto');
        var zonaCompleto = document.getElementById('zonaCompleto');
        var minNota = document.getElementById('minNota');
        var totalCompleto = document.getElementById('totalCompleto');
        if (!monto || !completo || !zonaAdelanto || !zonaCompleto) { return; }

        function actualizarTipo() {
            var esCompleto = completo.checked;
            zonaAdelanto.style.display = esCompleto ? 'none' : '';
            zonaCompleto.style.display = esCompleto ? '' : 'none';
            if (esCompleto) {
                monto.removeAttribute('required');
            } else {
                monto.setAttribute('required', 'required');
            }
        }

        var radios = document.querySelectorAll('input[name="tipoReserva"]');
        for (var i = 0; i < radios.length; i++) {
            radios[i].addEventListener('change', actualizarTipo);
        }

        monto.max = TOTAL.toFixed(2);
        if (monto.value === '') {
            monto.value = MINIMO.toFixed(2);
        }
        if (minNota) {
            minNota.textContent = 'Mínimo S/ ' + MINIMO.toFixed(2) + ' (50% del total).';
        }
        if (totalCompleto) {
            totalCompleto.textContent = TOTAL.toFixed(2);
        }
        actualizarTipo();
    });
})();
