/*
 * Menú lateral tipo cajón (igual en celular y escritorio).
 * El cajón está cerrado por defecto; lo abre el botón hamburguesa de la
 * barra superior y lo cierran: el fondo oscuro, la tecla Escape o la
 * navegación a otra página (cada página nueva arranca cerrada).
 * Se carga desde comunes/pie.jsp. Sin scripts en línea (CSP).
 */
(function () {
    'use strict';

    var boton = document.getElementById('sidebarToggle');
    var botonCerrar = document.getElementById('sidebarClose');
    var fondo = document.getElementById('sidebarOverlay');
    if (!boton || !fondo) { return; }

    function abierto() {
        return document.body.classList.contains('menu-abierto');
    }

    function sincronizar() {
        boton.setAttribute('aria-expanded', String(abierto()));
        fondo.setAttribute('aria-hidden', String(!abierto()));
    }

    function alternar() {
        document.body.classList.toggle('menu-abierto');
        sincronizar();
    }

    function cerrar() {
        if (abierto()) {
            document.body.classList.remove('menu-abierto');
            sincronizar();
        }
    }

    boton.addEventListener('click', alternar);
    if (botonCerrar) {
        botonCerrar.addEventListener('click', cerrar);
    }
    fondo.addEventListener('click', cerrar);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { cerrar(); }
    });
    // Si el usuario redimensiona a escritorio/celular, se cierra el cajón
    // para no dejar contenido tapado.
    var ancho = window.matchMedia('(max-width: 991.98px)');
    ancho.addEventListener('change', cerrar);

    sincronizar();
})();
