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
  function initCountdown() {
    var el = document.getElementById("fhCd");
    if (!el) return;
    var boxes = el.querySelectorAll("b");
    if (boxes.length < 3) return;
    function pad(n) { return (n < 10 ? "0" : "") + n; }
    function tick() {
      var now = new Date();
      var end = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);
      var s = Math.max(0, Math.floor((end - now) / 1000));
      boxes[0].textContent = pad(Math.floor(s / 3600));
      boxes[1].textContent = pad(Math.floor((s % 3600) / 60));
      boxes[2].textContent = pad(s % 60);
    }
    tick();
    setInterval(tick, 1000);
  }
  function boot() {
    document.querySelectorAll(".fdtf-home .fh-hero").forEach(initSlider);
    initCountdown();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();
})();
