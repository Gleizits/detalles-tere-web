(function () {
  var slider = document.getElementById('lpSlider');
  if (!slider) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var radios = Array.prototype.slice.call(slider.querySelectorAll('.lp-slider-radio'));
  if (radios.length < 2) return;

  window.setInterval(function () {
    var actual = radios.findIndex(function (radio) { return radio.checked; });
    var siguiente = (actual + 1) % radios.length;
    radios[siguiente].checked = true;
  }, 6000);
})();
