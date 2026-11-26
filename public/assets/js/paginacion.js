/* La CSP prohíbe manejadores en línea: el cambio de tamaño se enlaza aquí. */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('select[data-paginacion]').forEach(function (select) {
    select.addEventListener('change', function () {
      if (select.form) select.form.submit();
    });
  });
});
