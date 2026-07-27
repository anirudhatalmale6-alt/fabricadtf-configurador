/* Fábrica DTF homepage — hero slider only (rest is static server-rendered HTML). */
(function () {
  function initSlider(root) {
    var slides = [].slice.call(root.querySelectorAll(".fh-slide"));
    var dots = [].slice.call(root.querySelectorAll(".fh-dots button"));
    if (slides.length < 2) return;
    var i = 0, timer = null;
    function show(n) {
      i = (n + slides.length) % slides.length;
      slides.forEach(function (s, k) { s.classList.toggle("on", k === i); });
      dots.forEach(function (d, k) { d.classList.toggle("on", k === i); });
    }
    function next() { show(i + 1); }
    function play() { stop(); timer = setInterval(next, 6000); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    dots.forEach(function (d, k) { d.addEventListener("click", function () { show(k); play(); }); });
    var pv = root.querySelector(".fh-arrow.prev"), nx = root.querySelector(".fh-arrow.next");
    if (pv) pv.addEventListener("click", function () { show(i - 1); play(); });
    if (nx) nx.addEventListener("click", function () { show(i + 1); play(); });
    root.addEventListener("mouseenter", stop);
    root.addEventListener("mouseleave", play);
    show(0); play();
  }
  function boot() {
    document.querySelectorAll(".fdtf-home .fh-hero").forEach(initSlider);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();
})();
