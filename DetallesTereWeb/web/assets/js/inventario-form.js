/*
 * Formulario de artículo (alta/edited) de Inventario.
 * Reemplaza el script en línea y los atributos onchange de los JSP
 * (la CSP no permite scripts en línea). Se carga desde inventario_form.jsp.
 */
(function () {
    'use strict';

    function alternarClase() {
        var sel = document.querySelector('input[name="clase"]:checked');
        if (!sel) { return; }
        var clase = sel.value;
        var zonaTipo = document.getElementById('zonaTipo');
        var zonaMaterial = document.getElementById('zonaMaterial');
        if (zonaTipo) {
            zonaTipo.style.display = (clase === 'ARREGLO') ? 'none' : '';
        }
        if (zonaMaterial) {
            zonaMaterial.style.display = (clase === 'PRODUCTO') ? '' : 'none';
        }
        var nombre = document.getElementById('nombre');
        if (nombre) { nombre.maxLength = (clase === 'ARREGLO') ? 100 : 50; }
    }

    // Precio sugerido: costo × 1.50 (solo si el precio de venta está vacío
    // o fue puesto por la sugerencia, para no pisar lo que escribió la usuaria).
    function calcularSugerencia() {
        var pc = document.getElementById('precioCompra');
        var pv = document.getElementById('precioVenta');
        var nota = document.getElementById('sugerencia');
        if (!pc || !pv || !nota) { return; }
        var costo = parseFloat(pc.value.replace(',', '.'));
        if (isNaN(costo) || costo <= 0) {
            nota.textContent = 'Escribe el precio de compra y te calculo el precio de venta (costo × 1.50).';
            return;
        }
        var sug = (costo * 1.5).toFixed(2);
        nota.innerHTML = 'Precio sugerido: <strong>S/ ' + sug + '</strong> — ' +
            '<a href="#" id="usarSugerido">usar este precio</a>';
        document.getElementById('usarSugerido').addEventListener('click', function (e) {
            e.preventDefault();
            pv.value = sug;
            pv.dataset.sugerido = '1';
        });
        if (pv.value.trim() === '' || pv.dataset.sugerido === '1') {
            pv.value = sug;
            pv.dataset.sugerido = '1';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var radios = document.querySelectorAll('input[name="clase"]');
        for (var i = 0; i < radios.length; i++) {
            radios[i].addEventListener('change', alternarClase);
        }
        alternarClase();

        var pc = document.getElementById('precioCompra');
        if (pc) {
            pc.addEventListener('input', calcularSugerencia);
            calcularSugerencia();
        }
        var pv = document.getElementById('precioVenta');
        if (pv) {
            pv.addEventListener('input', function () { pv.dataset.sugerido = '0'; });
        }
    });
})();
