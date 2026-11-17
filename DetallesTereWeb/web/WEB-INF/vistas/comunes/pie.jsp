        </main>
    </div><!-- /app-cuerpo -->

    <script>
        (function () {
            var barra = document.getElementById('appSidebar');
            var botonMovil = document.getElementById('sidebarToggle');   // solo celular
            var botonColapsar = document.getElementById('sidebarCollapse'); // solo escritorio
            var fondo = document.getElementById('sidebarOverlay');
            var anchoMovil = window.matchMedia('(max-width: 991.98px)');

            function esMovil() { return anchoMovil.matches; }
            function reducido() { return document.body.classList.contains('menu-reducido'); }
            function abierto() { return document.body.classList.contains('menu-abierto'); }

            function guardar() {
                try {
                    localStorage.setItem('menuLateral', reducido() ? 'reducido' : 'completo');
                } catch (e) { /* sin almacenamiento disponible */ }
            }

            function sincronizar() {
                if (botonMovil) {
                    botonMovil.setAttribute('aria-expanded', String(esMovil() && abierto()));
                }
                if (botonColapsar) {
                    var visible = !reducido();
                    botonColapsar.setAttribute('aria-expanded', String(visible));
                    botonColapsar.setAttribute('aria-label',
                        visible ? 'Contraer el menú lateral' : 'Expandir el menú lateral');
                }
                fondo.setAttribute('aria-hidden', String(!(esMovil() && abierto())));
            }

            function abrirCerrarMovil() {
                document.body.classList.toggle('menu-abierto');
                sincronizar();
            }

            function contraerExpandir() {
                document.body.classList.toggle('menu-reducido');
                guardar();
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
                if (localStorage.getItem('menuLateral') === 'reducido') {
                    document.body.classList.add('menu-reducido');
                }
            } catch (e) { /* sin almacenamiento disponible */ }

            if (botonMovil) { botonMovil.addEventListener('click', abrirCerrarMovil); }
            if (botonColapsar) { botonColapsar.addEventListener('click', contraerExpandir); }
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
