/*
 * Lista de Inventario: modal de ajuste de stock.
 * Reemplaza el script en línea de inventario.jsp (CSP sin unsafe-inline).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('modalAjuste');
        if (!modal) { return; }

        modal.addEventListener('show.bs.modal', function (ev) {
            var btn = ev.relatedTarget;
            if (!btn) { return; }
            document.getElementById('ajusteId').value = btn.getAttribute('data-id') || '';
            document.getElementById('ajusteNombre').textContent = btn.getAttribute('data-nombre') || '';
            document.getElementById('ajusteCantidad').value = '1';
            document.getElementById('ajusteMotivo').value = '';
            document.getElementById('ajusteSumar').checked = true;
            document.getElementById('ajusteDelta').value = '1';
        });

        var form = document.getElementById('formAjuste');
        if (form) {
            form.addEventListener('submit', function () {
                var signo = document.querySelector('input[name="signo"]:checked');
                var cant = parseInt(document.getElementById('ajusteCantidad').value, 10) || 0;
                var s = signo ? parseInt(signo.value, 10) : 1;
                document.getElementById('ajusteDelta').value = String(s * cant);
            });
        }
    });
})();
