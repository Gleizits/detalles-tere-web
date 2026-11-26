/*
 * Formulario de artículo (alta/edición) de Inventario.
 * Reemplaza los scripts en línea y los atributos onchange del JSP original:
 * la CSP no permite scripts en línea.
 */
(function () {
    'use strict';

    function alternarClase() {
        var seleccionada = document.querySelector('input[name="clase"]:checked');
        if (!seleccionada) { return; } // En edición la clase no se puede cambiar.

        var clase = seleccionada.value;
        var zonaTipo = document.getElementById('zonaTipo');
        var zonaMaterial = document.getElementById('zonaMaterial');
        var nombre = document.getElementById('nombre');

        if (zonaTipo) { zonaTipo.style.display = (clase === 'ARREGLO') ? 'none' : ''; }
        if (zonaMaterial) { zonaMaterial.style.display = (clase === 'PRODUCTO') ? '' : 'none'; }
        if (nombre) { nombre.maxLength = (clase === 'ARREGLO') ? 100 : 50; }
    }

    function escribirSugerencia(nota, texto, sugerido) {
        while (nota.firstChild) { nota.removeChild(nota.firstChild); }
        nota.appendChild(document.createTextNode(texto));

        if (sugerido) {
            var fuerte = document.createElement('strong');
            fuerte.textContent = 'S/ ' + sugerido;
            nota.appendChild(document.createTextNode(' '));
            nota.appendChild(fuerte);
            nota.appendChild(document.createTextNode(' — '));

            var enlace = document.createElement('a');
            enlace.href = '#';
            enlace.id = 'usarSugerido';
            enlace.textContent = 'usar este precio';
            nota.appendChild(enlace);

            enlace.addEventListener('click', function (e) {
                e.preventDefault();
                var venta = document.getElementById('precioVenta');
                if (venta) {
                    venta.value = sugerido;
                    venta.dataset.sugerido = '1';
                }
            });
        }
    }

    // Costo × 1.50, sin pisar un precio escrito por la usuaria.
    function calcularSugerencia() {
        var compra = document.getElementById('precioCompra');
        var venta = document.getElementById('precioVenta');
        var nota = document.getElementById('sugerencia');
        if (!compra || !venta || !nota) { return; }

        var costo = parseFloat(compra.value.replace(',', '.'));
        if (isNaN(costo) || costo <= 0) {
            escribirSugerencia(nota,
                'Escribe el precio de compra y te calculo el precio de venta (costo × 1.50).',
                null);
            return;
        }

        var sugerido = (costo * 1.5).toFixed(2);
        escribirSugerencia(nota, 'Precio sugerido:', sugerido);

        if (venta.value.trim() === '' || venta.dataset.sugerido === '1') {
            venta.value = sugerido;
            venta.dataset.sugerido = '1';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var radios = document.querySelectorAll('input[name="clase"]');
        for (var i = 0; i < radios.length; i++) {
            radios[i].addEventListener('change', alternarClase);
        }
        alternarClase();

        var compra = document.getElementById('precioCompra');
        if (compra) {
            compra.addEventListener('input', calcularSugerencia);
            calcularSugerencia();
        }

        var venta = document.getElementById('precioVenta');
        if (venta) {
            venta.addEventListener('input', function () { venta.dataset.sugerido = '0'; });
        }
    });
})();
