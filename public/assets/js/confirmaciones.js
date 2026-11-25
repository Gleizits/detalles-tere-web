/*
 * Confirmaciones de acciones irreversibles (data-confirm).
 * Reemplaza los onclick="return confirm(...)" de los JSP (CSP sin unsafe-inline).
 * Uso: <button type="submit" data-confirm="¿Seguro?">…</button>
 */
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var objetivo = e.target;
        while (objetivo && objetivo !== document) {
            if (objetivo.nodeType === 1 && objetivo.hasAttribute('data-confirm')) {
                if (!window.confirm(objetivo.getAttribute('data-confirm'))) {
                    e.preventDefault();
                }
                return;
            }
            objetivo = objetivo.parentNode;
        }
    });
})();
