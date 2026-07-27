/**
 * Fábrica DTF — "DTF a Metro" ordering page.
 * Renders from window.FDTF_DTF_CONFIG and posts to the WooCommerce cart.
 * Quantity is in linear metres; price is per-metre by tier (server-authoritative).
 */
(function () {
  var CFG = window.FDTF_DTF_CONFIG || {};
  var TIERS = (CFG.tiers && CFG.tiers.length) ? CFG.tiers : [{ label: "1 metro", min: 1, max: 0, price: 0 }];
  var CUR = CFG.currency || "€";
  var MINM = CFG.minM != null ? Math.max(1, parseInt(CFG.minM, 10)) : 1;

  var root, state = { tier: 0, meters: MINM, file: null };

  function el(html) { var d = document.createElement("div"); d.innerHTML = html.trim(); return d.firstChild; }
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) { return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]; }); }
  function money(n) { return (Number(n) || 0).toFixed(2).replace(".", ",") + CUR; }

  function tierForMeters(m) {
    for (var i = 0; i < TIERS.length; i++) {
      var t = TIERS[i], hi = t.max > 0 ? t.max : Infinity;
      if (m >= t.min && m <= hi) return i;
    }
    return TIERS.length - 1;
  }

  function build() {
    root.innerHTML = "";
    var grid = el('<div class="fd-grid"></div>');

    // ---- LEFT: gallery + accordions ----
    var left = el('<div class="fd-gallery"></div>');
    var imgs = CFG.images || [];
    var main = el('<div class="fd-main"></div>');
    if (CFG.badge) main.appendChild(el('<span class="fd-badge">✦ ' + esc(CFG.badge) + '</span>'));
    var mainImg;
    if (imgs.length) {
      mainImg = el('<img alt="' + esc(CFG.title) + '" src="' + esc(imgs[0]) + '">');
      main.appendChild(mainImg);
    } else {
      main.appendChild(el('<div class="fd-ph">' + esc(CFG.title || "Transferência DTF") + '</div>'));
    }
    left.appendChild(main);

    if (imgs.length > 1) {
      var thumbs = el('<div class="fd-thumbs"></div>');
      imgs.forEach(function (src, i) {
        var b = el('<button' + (i === 0 ? ' class="sel"' : '') + '><img src="' + esc(src) + '" alt=""></button>');
        b.addEventListener("click", function () {
          if (mainImg) mainImg.src = src;
          [].forEach.call(thumbs.children, function (c) { c.className = ""; });
          b.className = "sel";
        });
        thumbs.appendChild(b);
      });
      left.appendChild(thumbs);
    }

    if (CFG.detailsHtml || CFG.shippingHtml || CFG.requirementsHtml) {
      var acc = el('<div class="fd-acc"></div>');
      if (CFG.detailsHtml) acc.appendChild(el('<details open><summary>Detalhes do Produto</summary><div class="fd-body">' + CFG.detailsHtml + '</div></details>'));
      if (CFG.shippingHtml) acc.appendChild(el('<details><summary>Envio</summary><div class="fd-body">' + CFG.shippingHtml + '</div></details>'));
      if (CFG.requirementsHtml) acc.appendChild(el('<details><summary>Requisitos para o DTF</summary><div class="fd-body">' + CFG.requirementsHtml + '</div></details>'));
      left.appendChild(acc);
    }
    grid.appendChild(left);

    // ---- RIGHT: buy box ----
    var buy = el('<div class="fd-buy"></div>');
    buy.appendChild(el('<h2 class="fd-ptitle">' + esc(CFG.title || "DTF a Metro") + '</h2>'));

    var full = Math.round(CFG.rating || 5);
    var stars = ""; for (var i = 0; i < 5; i++) stars += (i < full ? "★" : "☆");
    var rate = el('<div class="fd-rating"><span class="fd-stars">' + stars + '</span> <span>' + (CFG.reviews ? esc(CFG.reviews) + " avaliações" : "") + '</span></div>');
    buy.appendChild(rate);

    buy.appendChild(el('<div class="fd-price" data-role="unit">' + money(TIERS[0].price) + ' <span>' + esc(CFG.unitLabel || "/metro") + '</span></div>'));
    buy.appendChild(el('<div class="fd-ship-note">Portes calculados no checkout</div>'));
    if (CFG.desc) buy.appendChild(el('<p class="fd-desc">' + esc(CFG.desc) + '</p>'));

    if (CFG.features && CFG.features.length) {
      var fe = el('<div class="fd-features"></div>');
      CFG.features.forEach(function (f) { fe.appendChild(el('<div><span class="fd-ic">✔</span> ' + esc(f) + '</div>')); });
      buy.appendChild(fe);
    }

    if (CFG.guidelines && CFG.guidelines.length) {
      var g = el('<div class="fd-guide"></div>');
      g.appendChild(el('<div class="fd-gtitle">⚠ Diretrizes Importantes do Design</div>'));
      CFG.guidelines.forEach(function (row) {
        var tone = (row.tone === "red") ? "red" : "blue";
        g.appendChild(el('<div class="fd-row ' + tone + '">' + (row.html || "") + '</div>'));
      });
      buy.appendChild(g);
    }

    // step 1 — upload (optional)
    buy.appendChild(el('<div class="fd-step"><span class="fd-n">1</span> Envie o seu design <span class="fd-optional">(opcional)</span></div>'));
    var drop = el('<div class="fd-drop"><div class="fd-upic">⬆️</div><b>Arraste e largue aqui o seu design</b><small>ou clique para escolher · ' + esc(CFG.acceptLabel || "PNG, JPG, PDF") + '</small></div>');
    var file = el('<input type="file" accept="' + esc(CFG.accept || ".png,.jpg,.jpeg,.pdf") + '" style="display:none">');
    var filerow = el('<div class="fd-filerow" style="display:none">🖼️ <span data-role="fname"></span><button type="button" class="fd-rm">remover</button></div>');
    drop.addEventListener("click", function () { file.click(); });
    ["dragover", "dragenter"].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.add("drag"); }); });
    ["dragleave", "drop"].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.remove("drag"); }); });
    drop.addEventListener("drop", function (ev) { if (ev.dataTransfer.files[0]) pick(ev.dataTransfer.files[0]); });
    file.addEventListener("change", function () { if (file.files[0]) pick(file.files[0]); });
    function pick(f) {
      var max = (CFG.maxMB || 40) * 1024 * 1024;
      if (f.size > max) { alert("Ficheiro demasiado grande (máx " + (CFG.maxMB || 40) + " MB)."); return; }
      state.file = f;
      filerow.querySelector('[data-role="fname"]').textContent = f.name;
      filerow.style.display = "flex"; drop.style.display = "none";
    }
    filerow.querySelector(".fd-rm").addEventListener("click", function () {
      state.file = null; file.value = ""; filerow.style.display = "none"; drop.style.display = "block";
    });
    buy.appendChild(drop); buy.appendChild(file); buy.appendChild(filerow);
    if (CFG.uploadNote) buy.appendChild(el('<div class="fd-uploadnote">' + esc(CFG.uploadNote) + '</div>'));

    // step 2 — tiers
    buy.appendChild(el('<div class="fd-step"><span class="fd-n">2</span> Escolha o tamanho (metros)</div>'));
    var tiersEl = el('<div class="fd-tiers"></div>');
    buy.appendChild(tiersEl);
    var tierPrice = el('<div class="fd-tierprice"><span data-role="tlabel"></span><span data-role="tprice"></span></div>');
    buy.appendChild(tierPrice);

    // step 3 — quantity
    buy.appendChild(el('<div class="fd-step"><span class="fd-n">3</span> ' + esc(CFG.qtyLabel || "Quantidade (metros lineares)") + '</div>'));
    var qty = el('<div class="fd-qty"></div>');
    var minus = el('<button type="button" aria-label="menos">−</button>');
    var inp = el('<input type="number" min="' + MINM + '" inputmode="numeric" value="' + state.meters + '">');
    var plus = el('<button type="button" aria-label="mais">+</button>');
    qty.appendChild(minus); qty.appendChild(inp); qty.appendChild(plus);
    buy.appendChild(qty);
    buy.appendChild(el('<div class="fd-qtynote">Indique quantos metros lineares pretende. O preço por metro ajusta-se ao escalão.</div>'));

    buy.appendChild(el('<div class="fd-step"><span class="fd-n">4</span> ' + esc(CFG.notesLabel || "Notas (opcional)") + '</div>'));
    var notes = el('<textarea class="fd-notes" rows="3"></textarea>');
    notes.setAttribute("placeholder", CFG.notesPh || "Notas para a encomenda (opcional).");
    buy.appendChild(notes);

    // summary + cta
    var summary = el(
      '<div class="fd-summary">' +
      '<div class="fd-l"><span>Tamanho (<span data-role="stier"></span>)</span><span data-role="sunit"></span></div>' +
      '<div class="fd-l"><span>Metros</span><span data-role="sqty"></span></div>' +
      '<div class="fd-total"><span>Total</span><span data-role="stotal"></span></div>' +
      '</div>');
    buy.appendChild(summary);
    var cta = el('<button type="button" class="fd-cta">🛒 Adicionar ao carrinho</button>');
    buy.appendChild(cta);

    grid.appendChild(buy);
    root.appendChild(grid);

    // ---- wiring ----
    function renderTiers() {
      tiersEl.innerHTML = "";
      TIERS.forEach(function (t, i) {
        var d = el('<div class="fd-tier' + (i === state.tier ? " sel" : "") + '">' + esc(t.label) + '<small>' + money(t.price) + " / metro</small></div>");
        d.addEventListener("click", function () {
          state.tier = i;
          if (state.meters < t.min) state.meters = t.min;
          if (t.max > 0 && state.meters > t.max) state.meters = t.max;
          inp.value = state.meters;
          update();
        });
        tiersEl.appendChild(d);
      });
    }
    function set(sel, txt) { var e = root.querySelector('[data-role="' + sel + '"]'); if (e) e.textContent = txt; }
    function update() {
      var t = TIERS[state.tier];
      var total = t.price * state.meters;
      var unitEl = root.querySelector('[data-role="unit"]');
      if (unitEl) unitEl.innerHTML = money(t.price) + ' <span>' + esc(CFG.unitLabel || "/metro") + '</span>';
      set("tlabel", t.label); set("tprice", money(t.price) + " / metro");
      set("stier", t.label); set("sunit", money(t.price) + " / metro");
      set("sqty", "× " + state.meters); set("stotal", money(total));
      renderTiers();
    }
    function setMeters(v) {
      v = Math.max(MINM, parseInt(v || MINM, 10));
      state.meters = v; state.tier = tierForMeters(v); inp.value = v; update();
    }
    minus.addEventListener("click", function () { setMeters(state.meters - 1); });
    plus.addEventListener("click", function () { setMeters(state.meters + 1); });
    inp.addEventListener("input", function () { setMeters(inp.value); });

    cta.addEventListener("click", function () {
      if (state.meters < MINM) { alert("Quantidade mínima de " + MINM + " metro(s)."); return; }
      var fd = new FormData();
      fd.append("action", "fdtf_add_dtf");
      fd.append("nonce", CFG.nonce || "");
      fd.append("meters", state.meters);
      fd.append("notes", notes.value || "");
      if (state.file) fd.append("art", state.file); // upload is optional
      cta.disabled = true;
      postToCart(fd, true, cta);
    });

    renderTiers(); update();
  }

  // ---- cart POST with stale-nonce auto-retry (same approach as the configurador) ----
  function postToCart(fd, allowRetry, cta) {
    fetch(CFG.ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success && res.data && res.data.redirect) { window.location = res.data.redirect; return; }
        if (allowRetry && res && res.data && res.data.code === "bad_nonce") {
          refreshNonce(function (ok) {
            if (ok) { fd.set("nonce", CFG.nonce || ""); postToCart(fd, false, cta); }
            else fail(cta);
          });
          return;
        }
        if (cta) cta.disabled = false;
        alert((res && res.data && res.data.message) || "Adicionado ao carrinho!");
      })
      .catch(function () {
        if (allowRetry) {
          refreshNonce(function (ok) {
            if (ok) { fd.set("nonce", CFG.nonce || ""); postToCart(fd, false, cta); }
            else fail(cta);
          });
        } else { fail(cta); }
      });
  }
  function fail(cta) { if (cta) cta.disabled = false; alert("Ocorreu um erro. Tente novamente."); }

  function refreshNonce(cb) {
    if (!CFG.ajaxUrl) { if (cb) cb(false); return; }
    var url = CFG.ajaxUrl + (CFG.ajaxUrl.indexOf("?") === -1 ? "?" : "&") + "action=fdtf_nonce";
    fetch(url, { method: "GET", credentials: "same-origin", cache: "no-store" })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success && res.data && res.data.nonce) { CFG.nonce = res.data.nonce; if (cb) cb(true); }
        else if (cb) cb(false);
      })
      .catch(function () { if (cb) cb(false); });
  }

  function boot() {
    root = document.querySelector(".fdtf-dtf");
    if (!root) return;
    refreshNonce();
    build();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();
})();
