        </main>
    </div><!-- /app-cuerpo -->

    <script>
        (function () {
            var barra = document.getElementById('appSidebar');
            var boton = document.getElementById('sidebarToggle');
            var fondo = document.getElementById('sidebarOverlay');
            var anchoMovil = window.matchMedia('(max-width: 991.98px)');

            function esMovil() { return anchoMovil.matches; }
            function reducido() { return document.body.classList.contains('menu-reducido'); }
            function abierto() { return document.body.classList.contains('menu-abierto'); }

            function sincronizar() {
                var visible = esMovil() ? abierto() : !reducido();
                boton.setAttribute('aria-expanded', String(visible));
                fondo.setAttribute('aria-hidden', String(!(esMovil() && abierto())));
            }

            function alternar() {
                if (esMovil()) {
                    document.body.classList.toggle('menu-abierto');
                } else {
                    document.body.classList.toggle('menu-reducido');
                    try { localStorage.setItem('menuLateral', reducido() ? 'reducido' : 'completo'); }
                    catch (e) { /* sin almacenamiento disponible */ }
                }
                sincronizar();
            }

            function cerrarMovil() {
                if (abierto()) {
                    document.body.classList.remove('menu-abierto');
                    sincronizar();
                }
            }

            // Estado guardado del menú plegado en escritorio
            try {
                if (localStorage.getItem('menuLateral') === 'reducido' && !esMovil()) {
                    document.body.classList.add('menu-reducido');
                }
            } catch (e) { /* sin almacenamiento disponible */ }

            boton.addEventListener('click', alternar);
            fondo.addEventListener('click', cerrarMovil);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { cerrarMovil(); }
            });
            anchoMovil.addEventListener('change', function () {
                document.body.classList.remove('menu-abierto');
                sincronizar();
            });
            sincronizar();
        })();
    </script>

    <script src="${pageContext.request.contextPath}/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
