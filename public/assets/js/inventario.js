/*
 * Inventario: modal de ajuste de stock.
 * Script externo para cumplir la CSP (script-src 'self', sin inline).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('modalAjuste');
        if (!modal) { return; }

        modal.addEventListener('show.bs.modal', function (ev) {
            var btn = ev.relatedTarget;
            if (!btn) { return; }

            var id = document.getElementById('ajusteId');
            var nombre = document.getElementById('ajusteNombre');
            var cantidad = document.getElementById('ajusteCantidad');
            var motivo = document.getElementById('ajusteMotivo');
            var sumar = document.getElementById('ajusteSumar');
            var delta = document.getElementById('ajusteDelta');

            if (id) { id.value = btn.getAttribute('data-id') || ''; }
            if (nombre) { nombre.textContent = btn.getAttribute('data-nombre') || ''; }
            if (cantidad) { cantidad.value = '1'; }
            if (motivo) { motivo.value = ''; }
            if (sumar) { sumar.checked = true; }
            if (delta) { delta.value = '1'; }
        });

        var form = document.getElementById('formAjuste');
        if (form) {
            form.addEventListener('submit', function () {
                var delta = document.getElementById('ajusteDelta');
                var cantidad = document.getElementById('ajusteCantidad');
                if (!delta || !cantidad) { return; }

                var marcado = document.querySelector('input[name="signo"]:checked');
                var signo = marcado ? parseInt(marcado.value, 10) : 1;
                var n = parseInt(cantidad.value, 10);
                if (isNaN(n)) { n = 0; }
                delta.value = String(signo * n);
            });
        }
    });
})();
