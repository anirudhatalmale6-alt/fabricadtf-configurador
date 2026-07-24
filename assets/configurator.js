/* Fábrica DTF – Configurador de T-shirts (frontend)
 * Reads config from window.FDTF_CONFIG (injected by the WP plugin, or the demo page).
 * No external dependencies. */
(function () {
  "use strict";

  var CFG = window.FDTF_CONFIG || {};
  var products = CFG.products || [];
  var colors = CFG.colors || [];
  var sizes = CFG.sizes || ["S", "M", "L", "XL", "XXL"];
  var VAT = (CFG.vat != null ? CFG.vat : 23) / 100;
  var CURR = CFG.currency || "€";
  var i18n = CFG.i18n || {};

  function t(k, d) { return i18n[k] || d; }
  function money(v) { return CURR + v.toFixed(2).replace(".", ","); }

  // ---- state ----
  var state = {
    step: 0,
    product: products[0] || null,
    color: colors[0] || null,
    qty: {},               // size -> qty
    artDataUrl: null,
    artName: null,
    artScale: 0.7,
  };

  // ---- t-shirt SVG (recolourable) ----
  function teeSVG(fill, stroke) {
    stroke = stroke || "#d7dce6";
    return '<svg class="tee" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 700">' +
      '<defs><linearGradient id="fdtfShade" x1="0" y1="0" x2="0" y2="1">' +
      '<stop offset="0" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.12"/></linearGradient></defs>' +
      '<path fill="' + fill + '" stroke="' + stroke + '" stroke-width="2" d="M210 70 L150 50 L40 140 L95 240 L150 205 L150 640 Q150 660 170 660 L430 660 Q450 660 450 640 L450 205 L505 240 L560 140 L450 50 L390 70 Q300 130 210 70 Z"/>' +
      '<path fill="url(#fdtfShade)" d="M210 70 L150 50 L40 140 L95 240 L150 205 L150 640 Q150 660 170 660 L430 660 Q450 660 450 640 L450 205 L505 240 L560 140 L450 50 L390 70 Q300 130 210 70 Z"/>' +
      '<path fill="none" stroke="rgba(0,0,0,.18)" stroke-width="2" stroke-linecap="round" d="M212 72 Q300 132 388 72"/>' +
      '</svg>';
  }

  function el(html) { var d = document.createElement("div"); d.innerHTML = html.trim(); return d.firstChild; }

  // ---- pricing ----
  function totalQty() {
    var n = 0; for (var k in state.qty) n += state.qty[k] || 0; return n;
  }
  function pricing() {
    var unit = state.product ? state.product.price : 0;
    var perso = state.artDataUrl ? (CFG.printPrice != null ? CFG.printPrice : 2.5) : 0;
    var q = totalQty();
    var netUnit = unit + perso;
    var net = netUnit * q;
    var vat = net * VAT;
    return { unit: unit, perso: perso, q: q, net: net, vat: vat, total: net + vat };
  }

  // ---- render helpers ----
  var root, viewHost, summaryHost;

  function render() {
    renderSteps();
    renderView();
    renderSummary();
  }

  function renderSteps() {
    var labels = [t("step_model", "Modelo & Cor"), t("step_size", "Tamanhos"),
                  t("step_perso", "Personalização"), t("step_review", "Resumo")];
    var host = root.querySelector(".fdtf-steps");
    host.innerHTML = "";
    labels.forEach(function (lb, i) {
      var cls = "fdtf-step" + (i === state.step ? " active" : "") + (i < state.step ? " done" : "");
      var s = el('<div class="' + cls + '"><div class="dot">' + (i < state.step ? "✓" : (i + 1)) + '</div><div class="lbl">' + lb + '</div></div>');
      host.appendChild(s);
      if (i < labels.length - 1) host.appendChild(el('<div class="bar"></div>'));
    });
  }

  function renderView() {
    viewHost.innerHTML = "";
    if (state.step === 0) viewHost.appendChild(stepModel());
    else if (state.step === 1) viewHost.appendChild(stepSizes());
    else if (state.step === 2) viewHost.appendChild(stepPerso());
    else viewHost.appendChild(stepReview());
    viewHost.appendChild(navButtons());
  }

  // STEP 1 — product + color
  function stepModel() {
    var p = el('<div class="fdtf-panel fdtf-stepview active"></div>');
    p.appendChild(el('<h2 class="fdtf-h2">' + t("h_model", "Escolhe o modelo e a cor") + '</h2>'));
    p.appendChild(el('<p class="fdtf-sub">' + t("s_model", "Seleciona a base em branco que melhor se adapta ao teu projeto.") + '</p>'));

    var grid = el('<div class="fdtf-products"></div>');
    products.forEach(function (prod) {
      var sel = state.product && state.product.id === prod.id;
      var c = el('<div class="fdtf-prod' + (sel ? " sel" : "") + '"></div>');
      c.innerHTML =
        '<div class="tick">✓</div>' +
        (prod.badge ? '<span class="fdtf-badge ' + (prod.badgeHot ? "hot" : "") + '">' + prod.badge + '</span>' : '') +
        '<div class="thumb">' + teeSVG(state.color ? state.color.hex : "#ffffff") + '</div>' +
        '<h3>' + prod.name + '</h3>' +
        '<div class="price">' + t("from", "desde") + ' <b>' + money(prod.price) + '</b> /un.</div>';
      c.addEventListener("click", function () { state.product = prod; render(); });
      grid.appendChild(c);
    });
    p.appendChild(grid);

    p.appendChild(el('<div class="fdtf-label">' + t("color", "Cor da t-shirt") + '</div>'));
    var cwrap = el('<div class="fdtf-colors"></div>');
    colors.forEach(function (col) {
      var sel = state.color && state.color.name === col.name;
      var c = el('<div class="fdtf-color' + (sel ? " sel" : "") + '" title="' + col.name + '"><span class="swatch" style="background:' + col.hex + '"></span></div>');
      c.addEventListener("click", function () { state.color = col; render(); });
      cwrap.appendChild(c);
    });
    p.appendChild(cwrap);
    p.appendChild(el('<p class="fdtf-note">' + t("color_note", "A cor escolhida é aplicada em tempo real na pré-visualização.") + '</p>'));
    return p;
  }

  // STEP 2 — sizes / quantities
  function stepSizes() {
    var p = el('<div class="fdtf-panel fdtf-stepview active"></div>');
    p.appendChild(el('<h2 class="fdtf-h2">' + t("h_size", "Tamanhos e quantidades") + '</h2>'));
    p.appendChild(el('<p class="fdtf-sub">' + t("s_size", "Indica quantas unidades queres em cada tamanho.") + '</p>'));
    var grid = el('<div class="fdtf-sizes"></div>');
    sizes.forEach(function (sz) {
      var q = state.qty[sz] || 0;
      var row = el('<div class="fdtf-size"><span class="sz">' + sz + '</span></div>');
      var qty = el('<div class="fdtf-qty"></div>');
      var minus = el('<button type="button">−</button>');
      var inp = el('<input type="number" min="0" value="' + q + '">');
      var plus = el('<button type="button">+</button>');
      function set(v) { v = Math.max(0, parseInt(v || 0, 10)); state.qty[sz] = v; inp.value = v; renderSummary(); }
      minus.addEventListener("click", function () { set((state.qty[sz] || 0) - 1); });
      plus.addEventListener("click", function () { set((state.qty[sz] || 0) + 1); });
      inp.addEventListener("input", function () { set(inp.value); });
      qty.appendChild(minus); qty.appendChild(inp); qty.appendChild(plus);
      row.appendChild(qty); grid.appendChild(row);
    });
    p.appendChild(grid);
    p.appendChild(el('<p class="fdtf-note">' + t("size_note", "Total mínimo de 1 unidade para avançar.") + '</p>'));
    return p;
  }

  // STEP 3 — art upload + live preview
  function stepPerso() {
    var p = el('<div class="fdtf-panel fdtf-stepview active"></div>');
    p.appendChild(el('<h2 class="fdtf-h2">' + t("h_perso", "Personalização") + '</h2>'));
    p.appendChild(el('<p class="fdtf-sub">' + t("s_perso", "Faz upload da tua arte e vê a pré-visualização em tempo real.") + '</p>'));

    var perso = el('<div class="fdtf-perso"></div>');

    // stage / mockup
    var stage = el('<div class="fdtf-stage"></div>');
    var mock = el('<div class="fdtf-mock">' + teeSVG(state.color ? state.color.hex : "#ffffff") + '</div>');
    var print = el('<div class="fdtf-print' + (state.artDataUrl ? "" : " empty") + '"></div>');
    // print area geometry (as % of the mock box) — chest zone
    var w = state.artScale * 46;        // width %
    print.style.width = w + "%";
    print.style.height = (w * 1.25) + "%";
    print.style.left = (50 - w / 2) + "%";
    print.style.top = "26%";
    if (state.artDataUrl) print.appendChild(el('<img src="' + state.artDataUrl + '">'));
    else print.appendChild(el('<div class="hint">' + t("print_hint", "A tua arte aparece aqui") + '</div>'));
    mock.appendChild(print);
    stage.appendChild(mock);
    perso.appendChild(stage);

    // controls
    var ctrl = el('<div></div>');
    var drop = el('<div class="fdtf-drop"><div class="ic">🎨</div><b>' + t("drop", "Arrasta a imagem ou clica para carregar") + '</b><small>' + (CFG.acceptLabel || "PNG ou PDF · máx 25 MB") + '</small></div>');
    var file = el('<input type="file" accept="' + (CFG.accept || ".png,.pdf") + '" style="display:none">');
    drop.addEventListener("click", function () { file.click(); });
    ["dragover", "dragenter"].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.add("drag"); }); });
    ["dragleave", "drop"].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.remove("drag"); }); });
    drop.addEventListener("drop", function (ev) { if (ev.dataTransfer.files[0]) handleFile(ev.dataTransfer.files[0]); });
    file.addEventListener("change", function () { if (file.files[0]) handleFile(file.files[0]); });
    ctrl.appendChild(drop); ctrl.appendChild(file);

    if (state.artName) {
      var frow = el('<div class="fdtf-file-row">🖼️ <span>' + state.artName + '</span><button class="rm" type="button">' + t("remove", "remover") + '</button></div>');
      frow.querySelector(".rm").addEventListener("click", function () {
        state.artDataUrl = null; state.artName = null; renderView(); renderSummary();
      });
      ctrl.appendChild(frow);

      var sl = el('<div class="fdtf-slider"><label>' + t("size_art", "Tamanho da arte") + '<span></span></label></div>');
      var rng = el('<input type="range" min="30" max="100" value="' + Math.round(state.artScale * 100) + '">');
      rng.addEventListener("input", function () { state.artScale = rng.value / 100; renderView(); });
      sl.appendChild(rng); ctrl.appendChild(sl);
    }
    ctrl.appendChild(el('<p class="fdtf-note">' + t("perso_note", "Dica: para melhor qualidade de impressão, envia um PDF pronto para impressão ou um PNG em alta resolução com fundo transparente.") + '</p>'));
    perso.appendChild(ctrl);

    p.appendChild(perso);
    return p;
  }

  function handleFile(f) {
    var max = (CFG.maxMB || 25) * 1024 * 1024;
    if (f.size > max) { alert(t("too_big", "Ficheiro demasiado grande (máx ") + (CFG.maxMB || 25) + " MB)."); return; }
    state.artName = f.name;
    if (/pdf$/i.test(f.name)) {
      // PDFs can't preview inline without a renderer — show a placeholder card, keep the file
      state.artDataUrl = pdfPreview();
      renderView(); renderSummary();
      // keep raw file for upload
      state._rawFile = f; return;
    }
    var r = new FileReader();
    r.onload = function () { state.artDataUrl = r.result; state._rawFile = f; renderView(); renderSummary(); };
    r.readAsDataURL(f);
  }
  function pdfPreview() {
    return "data:image/svg+xml;utf8," + encodeURIComponent(
      '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="240"><rect width="200" height="240" rx="10" fill="#eef1fd"/><text x="100" y="120" font-family="Arial" font-size="26" fill="#0b1a5b" text-anchor="middle" font-weight="bold">PDF</text><text x="100" y="150" font-family="Arial" font-size="12" fill="#6b7392" text-anchor="middle">pronto para impressão</text></svg>');
  }

  // STEP 4 — review
  function stepReview() {
    var p = el('<div class="fdtf-panel fdtf-stepview active"></div>');
    p.appendChild(el('<h2 class="fdtf-h2">' + t("h_review", "Resumo do pedido") + '</h2>'));
    p.appendChild(el('<p class="fdtf-sub">' + t("s_review", "Confirma os detalhes antes de adicionar ao carrinho.") + '</p>'));

    var perso = el('<div class="fdtf-perso"></div>');
    var stage = el('<div class="fdtf-stage"></div>');
    var mock = el('<div class="fdtf-mock">' + teeSVG(state.color ? state.color.hex : "#ffffff") + '</div>');
    if (state.artDataUrl) {
      var w = state.artScale * 46;
      var print = el('<div class="fdtf-print"></div>');
      print.style.width = w + "%"; print.style.height = (w * 1.25) + "%";
      print.style.left = (50 - w / 2) + "%"; print.style.top = "26%";
      print.appendChild(el('<img src="' + state.artDataUrl + '">'));
      mock.appendChild(print);
    }
    stage.appendChild(mock); perso.appendChild(stage);

    var det = el('<div></div>');
    var rows = [
      [t("model", "Modelo"), state.product ? state.product.name : "—"],
      [t("color", "Cor"), state.color ? state.color.name : "—"],
      [t("art", "Arte"), state.artName || t("none", "Sem arte")],
    ];
    var qsum = [];
    sizes.forEach(function (s) { if (state.qty[s]) qsum.push(state.qty[s] + "× " + s); });
    rows.push([t("sizes_lbl", "Tamanhos"), qsum.length ? qsum.join(", ") : "—"]);
    rows.forEach(function (r) { det.appendChild(el('<div class="fdtf-row"><span>' + r[0] + '</span><b>' + r[1] + '</b></div>')); });
    det.appendChild(el('<p class="fdtf-note">' + t("review_note", "Ao adicionar ao carrinho, o pagamento e envio seguem o checkout habitual do site.") + '</p>'));
    perso.appendChild(det);
    p.appendChild(perso);
    return p;
  }

  // nav buttons
  function navButtons() {
    var wrap = el('<div class="fdtf-actions"></div>');
    var back = el('<button class="fdtf-btn ghost" type="button">‹ ' + t("back", "Voltar") + '</button>');
    back.disabled = state.step === 0;
    back.addEventListener("click", function () { if (state.step > 0) { state.step--; render(); scrollTop(); } });
    wrap.appendChild(back);

    if (state.step < 3) {
      var next = el('<button class="fdtf-btn primary" type="button">' + t("next", "Continuar") + ' ›</button>');
      next.addEventListener("click", function () {
        if (!validate()) return;
        state.step++; render(); scrollTop();
      });
      wrap.appendChild(next);
    } else {
      var add = el('<button class="fdtf-btn primary" type="button">🛒 ' + t("add", "Adicionar ao carrinho") + '</button>');
      add.addEventListener("click", addToCart);
      wrap.appendChild(add);
    }
    return wrap;
  }

  function validate() {
    if (state.step === 0 && (!state.product || !state.color)) { alert(t("v_model", "Escolhe um modelo e uma cor.")); return false; }
    if (state.step === 1 && totalQty() < 1) { alert(t("v_size", "Escolhe pelo menos 1 unidade.")); return false; }
    return true;
  }
  function scrollTop() { try { root.scrollIntoView({ behavior: "smooth", block: "start" }); } catch (e) {} }

  // summary (sidebar)
  function renderSummary() {
    var pr = pricing();
    summaryHost.innerHTML = "";
    summaryHost.appendChild(el('<h3>' + t("budget", "Resumo do orçamento") + '</h3>'));
    var sp = el('<div class="fdtf-sum-prod"><div class="mini">' + teeSVG(state.color ? state.color.hex : "#ffffff") + '</div>' +
      '<div><div class="nm">' + (state.product ? state.product.name : "—") + '</div>' +
      '<div class="meta">' + (state.color ? state.color.name : "") + (pr.q ? " · " + pr.q + " un." : "") + '</div></div></div>');
    summaryHost.appendChild(sp);
    summaryHost.appendChild(el('<div class="fdtf-row"><span>' + t("unit", "Preço unitário") + '</span><b>' + money(pr.unit) + '</b></div>'));
    summaryHost.appendChild(el('<div class="fdtf-row"><span>' + t("perso_price", "Personalização/un.") + '</span><b>' + money(pr.perso) + '</b></div>'));
    summaryHost.appendChild(el('<div class="fdtf-row"><span>' + t("qtty", "Quantidade") + '</span><b>' + pr.q + '</b></div>'));
    summaryHost.appendChild(el('<div class="fdtf-row"><span>' + t("net", "Total sem IVA") + '</span><b>' + money(pr.net) + '</b></div>'));
    summaryHost.appendChild(el('<div class="fdtf-row"><span>' + t("vat", "IVA (23%)") + '</span><b>' + money(pr.vat) + '</b></div>'));
    summaryHost.appendChild(el('<div class="fdtf-row tot"><span>' + t("total", "Total") + '</span><b>' + money(pr.total) + '</b></div>'));
    if (state.step === 3) {
      var add = el('<button class="fdtf-btn cart" type="button">🛒 ' + t("add", "Adicionar ao carrinho") + '</button>');
      add.addEventListener("click", addToCart);
      summaryHost.appendChild(add);
    }
  }

  // add to cart -> hands off to WooCommerce via the plugin's AJAX endpoint
  function addToCart() {
    if (totalQty() < 1) { alert(t("v_size", "Escolhe pelo menos 1 unidade.")); return; }
    var payload = {
      product: state.product ? state.product.id : null,
      productName: state.product ? state.product.name : null,
      color: state.color ? state.color.name : null,
      colorHex: state.color ? state.color.hex : null,
      sizes: state.qty,
      artName: state.artName,
      price: pricing().net,
    };
    if (typeof CFG.onAddToCart === "function") { CFG.onAddToCart(payload, state); return; }
    if (CFG.ajaxUrl) {
      var fd = new FormData();
      fd.append("action", "fdtf_add_to_cart");
      fd.append("nonce", CFG.nonce || "");
      fd.append("data", JSON.stringify(payload));
      if (state._rawFile) fd.append("art", state._rawFile);
      fetch(CFG.ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.success && res.data && res.data.redirect) window.location = res.data.redirect;
          else alert((res && res.data && res.data.message) || t("add_ok", "Adicionado ao carrinho!"));
        })
        .catch(function () { alert(t("add_err", "Ocorreu um erro. Tenta novamente.")); });
    } else {
      alert(t("demo_add", "Demonstração: t-shirt configurada e pronta a adicionar ao carrinho!\n\n") + JSON.stringify(payload, null, 2));
    }
  }

  // ---- boot ----
  function boot() {
    root = document.querySelector(".fdtf-config");
    if (!root) return;
    root.innerHTML =
      '<div class="fdtf-wrap">' +
      '<div class="fdtf-steps"></div>' +
      '<div class="fdtf-grid">' +
      '<div class="fdtf-main"></div>' +
      '<aside class="fdtf-summary"></aside>' +
      '</div></div>';
    viewHost = root.querySelector(".fdtf-main");
    summaryHost = root.querySelector(".fdtf-summary");
    render();
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();
})();
