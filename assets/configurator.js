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
  var MINQ = (CFG.minQty != null ? CFG.minQty : 5); // minimum total units per order

  // Print positions (front / back / sleeves / chest) and print sizes (A7..A3).
  var POSITIONS = (CFG.positions && CFG.positions.length) ? CFG.positions : [
    { code: "frente", label: "Frente", defaultSize: "A4", sizes: [] },
    { code: "costas", label: "Costas", defaultSize: "A3", sizes: [] },
    { code: "peito_esq", label: "Peito (lado coração)", defaultSize: "A7", sizes: ["A7", "A6"] },
    { code: "manga_esq", label: "Manga esquerda", defaultSize: "A7", sizes: ["A7", "A6"] },
    { code: "manga_dta", label: "Manga direita", defaultSize: "A7", sizes: ["A7", "A6"] }
  ];
  var PRINT_SIZES = (CFG.printSizes && CFG.printSizes.length) ? CFG.printSizes : [
    { code: "A7", label: "A7", price: 1.5 }, { code: "A6", label: "A6", price: 2.0 },
    { code: "A5", label: "A5", price: 3.0 }, { code: "A4", label: "A4", price: 4.5 },
    { code: "A3", label: "A3", price: 7.0 }
  ];
  var MEASURES = CFG.measurements || [];
  var EXTRAS = CFG.extras || [];
  var PRODUCTION = CFG.production || [];

  // Preview geometry per position (as % of the mock box). view = front | back.
  var ZONES = {
    frente:    { view: "front", top: 27, cx: 50,   base: 42, ar: "1 / 1.32" },
    peito_esq: { view: "front", top: 30, cx: 36,   base: 12, ar: "1 / 1.15" },
    manga_esq: { view: "front", top: 19, cx: 17.5, base: 14, ar: "1 / 1.15" },
    manga_dta: { view: "front", top: 19, cx: 82.5, base: 14, ar: "1 / 1.15" },
    costas:    { view: "back",  top: 24, cx: 50,   base: 44, ar: "1 / 1.32" }
  };
  var SIZE_FRAC = { A7: 0.5, A6: 0.62, A5: 0.75, A4: 0.88, A3: 1.0 };
  // Print-zone geometry tuned for the real product PHOTOS, per mockup set.
  var ZONES_BY_SET = {
    cotton: {
      frente:    { view: 'front', top: 24, cx: 50,   base: 38, ar: '1 / 1.35' },
      peito_esq: { view: 'front', top: 26, cx: 63,   base: 14, ar: '1 / 1.15' },
      manga_esq: { view: 'front', top: 22, cx: 15,   base: 14, ar: '1 / 1.15' },
      manga_dta: { view: 'front', top: 22, cx: 85,   base: 14, ar: '1 / 1.15' },
      costas:    { view: 'back',  top: 20, cx: 50,   base: 38, ar: '1 / 1.35' }
    },
    sport: {
      frente:    { view: 'front', top: 25, cx: 50, base: 34, ar: '1 / 1.35' },
      peito_esq: { view: 'front', top: 27, cx: 61, base: 13, ar: '1 / 1.15' },
      manga_esq: { view: 'front', top: 25, cx: 16, base: 13, ar: '1 / 1.15' },
      manga_dta: { view: 'front', top: 25, cx: 84, base: 13, ar: '1 / 1.15' },
      costas:    { view: 'back',  top: 21, cx: 50, base: 34, ar: '1 / 1.35' }
    }
  };
  function zoneSetFor(prod) {
    var set = prod && prod.mockupSet;
    if (set && ZONES_BY_SET[set]) return ZONES_BY_SET[set];
    return null;
  }
  function colorImg(view) { var c = state.color; return (c && c[view]) ? c[view] : null; }
  function repImg(prod) {
    var cols = colorsFor(prod), i;
    for (i = 0; i < cols.length; i++) { if (cols[i].front && /branco|white/i.test(cols[i].name)) return cols[i].front; }
    for (i = 0; i < cols.length; i++) { if (cols[i].front) return cols[i].front; }
    return null;
  }

  function t(k, d) { return i18n[k] || d; }
  function r2(v) { return Math.round((Number(v) || 0) * 100) / 100; }
  function money(v) { return CURR + (Number(v) || 0).toFixed(2).replace(".", ","); }
  function sizeByCode(code) { for (var i = 0; i < PRINT_SIZES.length; i++) if (PRINT_SIZES[i].code === code) return PRINT_SIZES[i]; return null; }
  function sizePrice(code) { var s = sizeByCode(code); return s ? Number(s.price) || 0 : 0; }
  function allowedSizesFor(pos) {
    if (!pos.sizes || !pos.sizes.length) return PRINT_SIZES.slice();
    return PRINT_SIZES.filter(function (ps) { return pos.sizes.indexOf(ps.code) >= 0; });
  }
  // Each product can have its own colour palette; fall back to the master list.
  function colorsFor(prod) {
    return (prod && prod.colors && prod.colors.length) ? prod.colors : colors;
  }
  // Quantity-tier (bulk) pricing helpers.
  function productTiers(prod) { return (prod && prod.tiers && prod.tiers.length) ? prod.tiers : null; }
  function activeTier(prod, qty) {
    var tiers = productTiers(prod); if (!tiers) return null;
    var q = Math.max(qty || 0, 0);
    for (var i = 0; i < tiers.length; i++) { var t = tiers[i]; var mx = (t.max && t.max > 0) ? t.max : Infinity; if (q >= t.min && q <= mx) return t; }
    return tiers[0];
  }
  function unitPriceFor(prod, qty) {
    if (!prod) return 0;
    var t = activeTier(prod, qty);
    if (t) return Number(t.price) || 0;
    return Number(prod.price) || 0;
  }
  function lowestTierPrice(prod) {
    var tiers = productTiers(prod); if (!tiers) return Number(prod.price) || 0;
    var mn = Infinity; tiers.forEach(function (t) { var p = Number(t.price) || 0; if (p < mn) mn = p; });
    return isFinite(mn) ? mn : (Number(prod.price) || 0);
  }
  function tierRange(t) { return t.min + (t.max && t.max > 0 ? '-' + t.max : '+') + ' un.'; }

  // ---- state ----
  var state = {
    step: 0,
    product: products[0] || null,
    color: colorsFor(products[0] || null)[0] || null,
    qty: {},               // size -> qty
    pos: {},               // code -> { size, artDataUrl, artName, rawFile }
    extras: {},            // extra code -> bool
    production: null,      // production code
    notes: ""
  };
  POSITIONS.forEach(function (p) {
    var allowed = allowedSizesFor(p);
    var codes = allowed.map(function (a) { return a.code; });
    var def = (p.defaultSize && codes.indexOf(p.defaultSize) >= 0) ? p.defaultSize : (codes[0] || "A4");
    state.pos[p.code] = { size: def, artDataUrl: null, artName: null, rawFile: null };
  });
  (function () { // default production = flagged default or first
    var d = PRODUCTION.filter(function (p) { return p.default; })[0] || PRODUCTION[0];
    state.production = d ? d.code : null;
  })();

  // ---- t-shirt SVG (recolourable) ----
  function teeBody(fill, stroke) {
    stroke = stroke || "#d7dce6";
    var d = "M210 70 L150 50 L40 140 L95 240 L150 205 L150 640 Q150 660 170 660 L430 660 Q450 660 450 640 L450 205 L505 240 L560 140 L450 50 L390 70 Q300 130 210 70 Z";
    return '<path fill="' + fill + '" stroke="' + stroke + '" stroke-width="2" d="' + d + '"/>' +
      '<path fill="url(#fdtfShade)" d="' + d + '"/>';
  }
  function teeSVG(fill, back) {
    var neck = back
      ? '<path fill="none" stroke="rgba(0,0,0,.16)" stroke-width="2" stroke-linecap="round" d="M214 66 Q300 96 386 66"/>'
      : '<path fill="none" stroke="rgba(0,0,0,.18)" stroke-width="2" stroke-linecap="round" d="M212 72 Q300 132 388 72"/>';
    return '<svg class="tee" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 700">' +
      '<defs><linearGradient id="fdtfShade" x1="0" y1="0" x2="0" y2="1">' +
      '<stop offset="0" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.12"/></linearGradient></defs>' +
      teeBody(fill) + neck + '</svg>';
  }

  function el(html) { var d = document.createElement("div"); d.innerHTML = html.trim(); return d.firstChild; }
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]; }); }

  // ---- pricing ----
  function totalQty() { var n = 0; for (var k in state.qty) n += state.qty[k] || 0; return n; }
  function activePositions() { return POSITIONS.filter(function (p) { var st = state.pos[p.code]; return st && st.artName; }); }
  function persoPerUnit() { var s = 0; activePositions().forEach(function (p) { s += sizePrice(state.pos[p.code].size); }); return s; }
  function selectedExtras() { return EXTRAS.filter(function (e) { return state.extras[e.code]; }); }
  function selectedProduction() { for (var i = 0; i < PRODUCTION.length; i++) if (PRODUCTION[i].code === state.production) return PRODUCTION[i]; return PRODUCTION[0] || null; }
  function pricing() {
    var q = totalQty();
    var base = unitPriceFor(state.product, q);
    var perso = persoPerUnit();
    var exU = 0, exO = 0;
    selectedExtras().forEach(function (e) { if (e.per === "order") exO += Number(e.price) || 0; else exU += Number(e.price) || 0; });
    var prod = selectedProduction();
    var pct = prod ? Number(prod.pct) || 0 : 0;
    var pu = prod ? Number(prod.unit) || 0 : 0;
    var prodUnit = r2((base + perso + exU) * pct / 100 + pu);
    var netUnit = base + perso + exU + prodUnit;
    var net = r2(netUnit * q + exO);
    var vat = r2(net * VAT);
    return { base: base, perso: perso, exU: exU, exO: exO, prodUnit: prodUnit, prodPct: pct, netUnit: netUnit, q: q, net: net, vat: vat, total: r2(net + vat), tier: activeTier(state.product, q) };
  }

  // ---- render ----
  var root, viewHost, summaryHost;
  function render() { renderSteps(); renderView(); renderSummary(); }

  function renderSteps() {
    var labels = [t("step_model", "Modelo & Cor"), t("step_size", "Tamanhos"),
                  t("step_perso", "Personalização"), t("step_review", "Resumo")];
    var host = root.querySelector(".fdtf-steps");
    host.innerHTML = "";
    labels.forEach(function (lb, i) {
      var cls = "fdtf-step" + (i === state.step ? " active" : "") + (i < state.step ? " done" : "");
      host.appendChild(el('<div class="' + cls + '"><div class="dot">' + (i < state.step ? "✓" : (i + 1)) + '</div><div class="lbl">' + lb + '</div></div>'));
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
      // Selected model shows the chosen colour's photo live; others show a representative one.
      var thumbSrc = (sel && colorImg('front')) ? colorImg('front') : repImg(prod);
      var feats = "";
      if (prod.features && prod.features.length) {
        feats = '<ul class="fdtf-feat">' + prod.features.map(function (f) { return '<li>' + esc(f) + '</li>'; }).join("") + '</ul>';
      }
      c.innerHTML =
        '<div class="tick">✓</div>' +
        (prod.badge ? '<span class="fdtf-badge ' + (prod.badgeHot ? "hot" : "") + '">' + esc(prod.badge) + '</span>' : '') +
        '<div class="thumb">' + (thumbSrc ? '<img class="fdtf-tee-img" src="' + thumbSrc + '" alt="">' : teeSVG(state.color ? state.color.hex : "#ffffff")) + '</div>' +
        '<h3>' + esc(prod.name) + '</h3>' +
        (prod.desc ? '<p class="fdtf-desc">' + esc(prod.desc) + '</p>' : '') +
        feats +
        '<div class="price">' + t("from", "desde") + ' <b>' + money(lowestTierPrice(prod)) + '</b> /un.</div>';
      c.addEventListener("click", function () {
        state.product = prod;
        var pc = colorsFor(prod);
        if (!state.color || !pc.some(function (x) { return x.name === state.color.name; })) {
          state.color = pc[0] || null;
        }
        render();
      });
      grid.appendChild(c);
    });
    p.appendChild(grid);

    p.appendChild(el('<div class="fdtf-label">' + t("color", "Cor da t-shirt") + '</div>'));
    var cwrap = el('<div class="fdtf-colors"></div>');
    colorsFor(state.product).forEach(function (col) {
      var sel = state.color && state.color.name === col.name;
      var c = el('<div class="fdtf-color' + (sel ? " sel" : "") + '" title="' + esc(col.name) + '"><span class="swatch" style="background:' + col.hex + '"></span></div>');
      c.addEventListener("click", function () { state.color = col; render(); });
      cwrap.appendChild(c);
    });
    p.appendChild(cwrap);
    p.appendChild(el('<p class="fdtf-note">' + t("color_note", "A cor escolhida é aplicada em tempo real na pré-visualização.") + '</p>'));
    return p;
  }

  // STEP 2 — sizes / quantities + measurements table
  function stepSizes() {
    var p = el('<div class="fdtf-panel fdtf-stepview active"></div>');
    p.appendChild(el('<h2 class="fdtf-h2">' + t("h_size", "Tamanhos e quantidades") + '</h2>'));
    p.appendChild(el('<p class="fdtf-sub">' + t("s_size", "Indica quantas unidades queres em cada tamanho.") + '</p>'));
    var grid = el('<div class="fdtf-sizes"></div>');
    sizes.forEach(function (sz) {
      var q = state.qty[sz] || 0;
      var row = el('<div class="fdtf-size"></div>');
      row.appendChild(el('<span class="sz">' + esc(sz) + '</span>'));
      var qty = el('<div class="fdtf-qty"></div>');
      var minus = el('<button type="button" aria-label="menos">−</button>');
      var inp = el('<input type="number" min="0" inputmode="numeric" value="' + q + '">');
      var plus = el('<button type="button" aria-label="mais">+</button>');
      function set(v) { v = Math.max(0, parseInt(v || 0, 10)); state.qty[sz] = v; inp.value = v; renderSummary(); updateTierHighlight(); }
      minus.addEventListener("click", function () { set((state.qty[sz] || 0) - 1); });
      plus.addEventListener("click", function () { set((state.qty[sz] || 0) + 1); });
      inp.addEventListener("input", function () { set(inp.value); });
      qty.appendChild(minus); qty.appendChild(inp); qty.appendChild(plus);
      row.appendChild(qty); grid.appendChild(row);
    });
    p.appendChild(grid);

    // measurements table (collapsible, informative)
    if (MEASURES.length) {
      var det = el('<details class="fdtf-measures"></details>');
      det.appendChild(el('<summary>📏 ' + t("measures", "Medidas por tamanho (Largura / Altura)") + '</summary>'));
      var tbl = '<table><thead><tr><th>' + t("m_size", "Tamanho") + '</th><th>' + t("m_w", "Largura (cm)") + '</th><th>' + t("m_h", "Altura (cm)") + '</th></tr></thead><tbody>';
      MEASURES.forEach(function (m) {
        tbl += '<tr><td>' + esc(m.size) + '</td><td>' + esc(m.width) + '</td><td>' + esc(m.height) + '</td></tr>';
      });
      tbl += '</tbody></table>';
      det.appendChild(el(tbl));
      p.appendChild(det);
    }

    // Escal\u00f5es por quantidade (bulk pricing) table
    var _tiers = productTiers(state.product);
    if (_tiers) {
      var _at = activeTier(state.product, totalQty());
      var det2 = el('<details class="fdtf-tiers" open></details>');
      det2.appendChild(el('<summary>\ud83d\udcb6 ' + t("tiers_h", "Escal\u00f5es por quantidade (pre\u00e7o/un., sem IVA)") + '</summary>'));
      var _tt = '<table><thead><tr><th>' + t("qtty", "Quantidade") + '</th><th>' + t("unit", "Pre\u00e7o unit\u00e1rio") + '</th></tr></thead><tbody>';
      _tiers.forEach(function (tr) {
        var on = (_at && _at.min === tr.min && _at.max === tr.max) ? ' class="on"' : '';
        _tt += '<tr data-min="' + tr.min + '" data-max="' + tr.max + '"' + on + '><td>' + esc(tierRange(tr)) + '</td><td>' + money(tr.price) + '</td></tr>';
      });
      _tt += '</tbody></table>';
      det2.appendChild(el(_tt));
      p.appendChild(det2);
    }

    p.appendChild(el('<p class="fdtf-note">' + t("size_note", "Quantidade mínima de " + MINQ + " unidades por encomenda.") + '</p>'));
    return p;
  }

  // ---- preview stages ----
  function buildStage(view, label) {
    var stage = el('<div class="fdtf-stage"><span class="fdtf-view-tag">' + label + '</span></div>');
    var img = colorImg(view);
    var mock = el('<div class="fdtf-mock"></div>');
    if (img) { mock.appendChild(el('<img class="fdtf-tee-img" src="' + img + '" alt="">')); }
    else { mock.innerHTML = teeSVG(state.color ? state.color.hex : "#ffffff", view === "back"); }
    var zones = zoneSetFor(state.product) || ZONES;
    POSITIONS.forEach(function (p) {
      var z = zones[p.code];
      if (!z || z.view !== view) return;
      var st = state.pos[p.code];
      var frac = SIZE_FRAC[st.size] != null ? SIZE_FRAC[st.size] : 0.8;
      var w = z.base * frac;
      var box = el('<div class="fdtf-print' + (st.artName ? "" : " empty") + '"></div>');
      box.style.width = w + "%";
      box.style.left = (z.cx - w / 2) + "%";
      box.style.top = z.top + "%";
      box.style.aspectRatio = z.ar;
      if (st.artDataUrl) box.appendChild(el('<img src="' + st.artDataUrl + '">'));
      else if (z.base >= 30) box.appendChild(el('<div class="hint">' + esc(p.label) + '</div>'));
      mock.appendChild(box);
    });
    stage.appendChild(mock);
    return stage;
  }
  function buildPreview() {
    var wrap = el('<div class="fdtf-stages"></div>');
    wrap.appendChild(buildStage("front", t("view_front", "Frente")));
    wrap.appendChild(buildStage("back", t("view_back", "Costas")));
    return wrap;
  }

  // STEP 3 — per-position art upload
  function stepPerso() {
    var p = el('<div class="fdtf-panel fdtf-stepview active"></div>');
    p.appendChild(el('<h2 class="fdtf-h2">' + t("h_perso", "Personalização") + '</h2>'));
    p.appendChild(el('<p class="fdtf-sub">' + t("s_perso", "Escolhe onde queres imprimir, o tamanho da impressão e envia a arte. A pré-visualização atualiza em tempo real.") + '</p>'));

    var perso = el('<div class="fdtf-perso"></div>');
    perso.appendChild(buildPreview());
    var ctrl = el('<div class="fdtf-pos-list"></div>');
    POSITIONS.forEach(function (p2) { ctrl.appendChild(positionCard(p2)); });
    ctrl.appendChild(el('<p class="fdtf-note">' + t("perso_note", "Podes imprimir em várias posições. Para melhor qualidade, envia um PDF pronto a imprimir ou um PNG em alta resolução com fundo transparente.") + '</p>'));
    perso.appendChild(ctrl);
    p.appendChild(perso);
    return p;
  }

  function positionCard(pos) {
    var st = state.pos[pos.code];
    var active = !!st.artName;
    var card = el('<div class="fdtf-pos' + (active ? " on" : "") + '"></div>');

    var head = el('<div class="fdtf-pos-head"></div>');
    head.appendChild(el('<b>' + esc(pos.label) + '</b>'));
    if (active) head.appendChild(el('<span class="fdtf-chip">' + esc(st.size) + ' · ' + money(sizePrice(st.size)) + '/un.</span>'));
    card.appendChild(head);

    card.appendChild(el('<div class="fdtf-pos-size-lbl">' + t("print_size", "Tamanho da impressão") + '</div>'));
    var opts = el('<div class="fdtf-size-opts"></div>');
    allowedSizesFor(pos).forEach(function (ps) {
      var o = el('<button type="button" class="fdtf-size-opt' + (ps.code === st.size ? " sel" : "") + '"><span class="sz">' + esc(ps.label) + '</span><span class="pr">' + money(ps.price) + '</span></button>');
      o.addEventListener("click", function () { st.size = ps.code; renderView(); renderSummary(); });
      opts.appendChild(o);
    });
    card.appendChild(opts);

    if (!active) {
      var drop = el('<div class="fdtf-drop"><div class="ic">🎨</div><b>' + t("drop", "Arrasta a imagem ou clica para carregar") + '</b><small>' + (CFG.acceptLabel || "PNG ou PDF · máx 25 MB") + '</small></div>');
      var file = el('<input type="file" accept="' + (CFG.accept || ".png,.pdf") + '" style="display:none">');
      drop.addEventListener("click", function () { file.click(); });
      ["dragover", "dragenter"].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.add("drag"); }); });
      ["dragleave", "drop"].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.remove("drag"); }); });
      drop.addEventListener("drop", function (ev) { if (ev.dataTransfer.files[0]) handleFile(pos.code, ev.dataTransfer.files[0]); });
      file.addEventListener("change", function () { if (file.files[0]) handleFile(pos.code, file.files[0]); });
      card.appendChild(drop); card.appendChild(file);
    } else {
      var frow = el('<div class="fdtf-file-row">🖼️ <span>' + esc(st.artName) + '</span><button class="rm" type="button">' + t("remove", "remover") + '</button></div>');
      frow.querySelector(".rm").addEventListener("click", function () {
        st.artDataUrl = null; st.artName = null; st.rawFile = null; renderView(); renderSummary();
      });
      card.appendChild(frow);
    }
    return card;
  }

  function handleFile(code, f) {
    var max = (CFG.maxMB || 25) * 1024 * 1024;
    if (f.size > max) { alert(t("too_big", "Ficheiro demasiado grande (máx ") + (CFG.maxMB || 25) + " MB)."); return; }
    var st = state.pos[code];
    st.artName = f.name; st.rawFile = f;
    if (/pdf$/i.test(f.name)) { st.artDataUrl = pdfPreview(); renderView(); renderSummary(); return; }
    var r = new FileReader();
    r.onload = function () { st.artDataUrl = r.result; renderView(); renderSummary(); };
    r.readAsDataURL(f);
  }
  function pdfPreview() {
    return "data:image/svg+xml;utf8," + encodeURIComponent(
      '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="240"><rect width="200" height="240" rx="10" fill="#eef1fd"/><text x="100" y="120" font-family="Arial" font-size="26" fill="#0b1a5b" text-anchor="middle" font-weight="bold">PDF</text><text x="100" y="150" font-family="Arial" font-size="12" fill="#6b7392" text-anchor="middle">pronto para impressão</text></svg>');
  }

  // envio previsto (delivery estimate) — today + N calendar days
  function estimatedDate(days) {
    try {
      var d = new Date(); d.setDate(d.getDate() + (parseInt(days, 10) || 0));
      var dd = ("0" + d.getDate()).slice(-2), mm = ("0" + (d.getMonth() + 1)).slice(-2);
      return dd + "/" + mm + "/" + d.getFullYear();
    } catch (e) { return ""; }
  }

  // STEP 4 — extras, production, notes, review
  function stepReview() {
    var p = el('<div class="fdtf-panel fdtf-stepview active"></div>');
    p.appendChild(el('<h2 class="fdtf-h2">' + t("h_review", "Resumo do pedido") + '</h2>'));
    p.appendChild(el('<p class="fdtf-sub">' + t("s_review", "Escolhe os extras e confirma os detalhes antes de adicionar ao carrinho.") + '</p>'));

    // Extras opcionais
    if (EXTRAS.length) {
      p.appendChild(el('<div class="fdtf-label">' + t("extras", "Extras opcionais") + '</div>'));
      var exWrap = el('<div class="fdtf-extras"></div>');
      EXTRAS.forEach(function (e) {
        var on = !!state.extras[e.code];
        var row = el('<label class="fdtf-extra' + (on ? " on" : "") + '"></label>');
        var cb = el('<input type="checkbox"' + (on ? " checked" : "") + '>');
        cb.addEventListener("change", function () { state.extras[e.code] = cb.checked; row.classList.toggle("on", cb.checked); renderView(); renderSummary(); });
        row.appendChild(cb);
        row.appendChild(el('<span class="fdtf-extra-info"><b>' + esc(e.label) + '</b>' + (e.desc ? '<small>' + esc(e.desc) + '</small>' : '') + '</span>'));
        row.appendChild(el('<span class="fdtf-extra-price">+' + money(e.price) + (e.per === "order" ? "" : "/un.") + '</span>'));
        exWrap.appendChild(row);
      });
      p.appendChild(exWrap);
    }

    // Tempo de produção
    if (PRODUCTION.length) {
      p.appendChild(el('<div class="fdtf-label">' + t("prod_time", "Tempo de produção") + '</div>'));
      var prWrap = el('<div class="fdtf-prod-opts"></div>');
      PRODUCTION.forEach(function (pr) {
        var on = state.production === pr.code;
        var card = el('<label class="fdtf-prod-opt' + (on ? " on" : "") + '"></label>');
        var rb = el('<input type="radio" name="fdtf-prod"' + (on ? " checked" : "") + '>');
        rb.addEventListener("change", function () { state.production = pr.code; renderView(); renderSummary(); });
        var extra = pr.pct ? ("+" + pr.pct + "%") : (pr.unit ? "+" + money(pr.unit) + "/un." : t("no_extra", "sem custo extra"));
        card.appendChild(rb);
        card.appendChild(el('<span class="fdtf-prod-info"><b>' + esc(pr.label) + '</b><small>' + (pr.days ? pr.days + ' ' + t("days", "dias") + ' · ' : '') + extra + '</small></span>'));
        prWrap.appendChild(card);
      });
      p.appendChild(prWrap);
      var prod = selectedProduction();
      if (prod && prod.days) {
        p.appendChild(el('<p class="fdtf-ship"><span>' + t("ship_est", "Envio previsto") + '</span> <b>' + estimatedDate(prod.days) + '</b> <small>(' + t("ship_calc", "estimativa em dias corridos") + ')</small></p>'));
      }
    }

    // Notas
    p.appendChild(el('<div class="fdtf-label">' + t("notes", "Notas adicionais") + '</div>'));
    var ta = el('<textarea class="fdtf-notes" rows="3" placeholder="' + t("notes_ph", "Alguma indicação especial? Ex: instruções de impressão, cores, etc.") + '">' + esc(state.notes) + '</textarea>');
    ta.addEventListener("input", function () { state.notes = ta.value; });
    p.appendChild(ta);

    // Review details + preview
    p.appendChild(el('<div class="fdtf-label">' + t("order_detail", "Detalhes do pedido") + '</div>'));
    var perso = el('<div class="fdtf-perso"></div>');
    perso.appendChild(buildPreview());
    var det = el('<div></div>');
    var rows = [
      [t("model", "Modelo"), state.product ? state.product.name : "—"],
      [t("color", "Cor"), state.color ? state.color.name : "—"]
    ];
    var qsum = [];
    sizes.forEach(function (s) { if (state.qty[s]) qsum.push(state.qty[s] + "× " + s); });
    rows.push([t("sizes_lbl", "Tamanhos"), qsum.length ? qsum.join(", ") : "—"]);
    rows.forEach(function (r) { det.appendChild(el('<div class="fdtf-row"><span>' + r[0] + '</span><b>' + esc(r[1]) + '</b></div>')); });

    det.appendChild(el('<div class="fdtf-mini-lbl">' + t("perso_lbl", "Personalização") + '</div>'));
    var act = activePositions();
    if (act.length) {
      act.forEach(function (p2) {
        var st = state.pos[p2.code];
        det.appendChild(el('<div class="fdtf-row"><span>' + esc(p2.label) + ' · ' + esc(st.size) + ' <small>(' + esc(st.artName || "") + ')</small></span><b>' + money(sizePrice(st.size)) + '/un.</b></div>'));
      });
    } else {
      det.appendChild(el('<div class="fdtf-row"><span>' + t("none", "Sem arte") + '</span><b>—</b></div>'));
    }
    det.appendChild(el('<p class="fdtf-note">' + t("review_note", "Ao adicionar ao carrinho, o pagamento e envio seguem o checkout habitual do site.") + '</p>'));
    perso.appendChild(det);
    p.appendChild(perso);
    return p;
  }

  function navButtons() {
    var wrap = el('<div class="fdtf-actions"></div>');
    var back = el('<button class="fdtf-btn ghost" type="button">‹ ' + t("back", "Voltar") + '</button>');
    back.disabled = state.step === 0;
    back.addEventListener("click", function () { if (state.step > 0) { state.step--; render(); scrollTop(); } });
    wrap.appendChild(back);
    if (state.step < 3) {
      var next = el('<button class="fdtf-btn primary" type="button">' + t("next", "Continuar") + ' ›</button>');
      next.addEventListener("click", function () { if (!validate()) return; state.step++; render(); scrollTop(); });
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
    if (state.step === 1 && totalQty() < MINQ) { alert(t("v_size", "Quantidade mínima de " + MINQ + " unidades por encomenda.")); return false; }
    return true;
  }
  function scrollTop() { try { root.scrollIntoView({ behavior: "smooth", block: "start" }); } catch (e) {} }

  // Refresh the active-tier highlight in the escalões table without a full re-render.
  function updateTierHighlight() {
    if (!root) return;
    var at = activeTier(state.product, totalQty());
    var rows = root.querySelectorAll('.fdtf-tiers tbody tr');
    rows.forEach(function (tr) {
      var mn = parseInt(tr.getAttribute('data-min'), 10);
      var mx = parseInt(tr.getAttribute('data-max'), 10);
      var isOn = at && at.min === mn && at.max === mx;
      tr.classList.toggle('on', !!isOn);
    });
  }

  // summary sidebar
  function renderSummary() {
    var pr = pricing();
    summaryHost.innerHTML = "";
    summaryHost.appendChild(el('<h3>' + t("budget", "Resumo do orçamento") + '</h3>'));
    summaryHost.appendChild(el('<div class="fdtf-sum-prod"><div class="mini">' + (colorImg('front') ? '<img class="fdtf-tee-img" src="' + colorImg('front') + '" alt="">' : teeSVG(state.color ? state.color.hex : "#ffffff")) + '</div>' +
      '<div><div class="nm">' + esc(state.product ? state.product.name : "—") + '</div>' +
      '<div class="meta">' + esc(state.color ? state.color.name : "") + (pr.q ? " · " + pr.q + " un." : "") + '</div></div></div>'));
    var _unitLbl = t("unit", "Pre\u00e7o unit\u00e1rio") + (pr.tier ? ' <small>(' + esc(tierRange(pr.tier)) + ')</small>' : '');
    summaryHost.appendChild(el('<div class="fdtf-row"><span>' + _unitLbl + '</span><b>' + money(pr.base) + '</b></div>'));
    activePositions().forEach(function (p2) {
      var st = state.pos[p2.code];
      summaryHost.appendChild(el('<div class="fdtf-row sub"><span>+ ' + esc(p2.label) + ' · ' + esc(st.size) + '</span><b>' + money(sizePrice(st.size)) + '</b></div>'));
    });
    summaryHost.appendChild(el('<div class="fdtf-row"><span>' + t("perso_price", "Personalização/un.") + '</span><b>' + money(pr.perso) + '</b></div>'));
    selectedExtras().forEach(function (e) {
      summaryHost.appendChild(el('<div class="fdtf-row sub"><span>+ ' + esc(e.label) + '</span><b>' + money(e.price) + (e.per === "order" ? "" : "/un.") + '</b></div>'));
    });
    if (pr.prodUnit > 0) {
      summaryHost.appendChild(el('<div class="fdtf-row sub"><span>+ ' + t("prod_time", "Tempo de produção") + '</span><b>' + money(pr.prodUnit) + '/un.</b></div>'));
    }
    summaryHost.appendChild(el('<div class="fdtf-row"><span>' + t("qtty", "Quantidade") + '</span><b>' + pr.q + '</b></div>'));
    summaryHost.appendChild(el('<div class="fdtf-row"><span>' + t("net", "Total sem IVA") + '</span><b>' + money(pr.net) + '</b></div>'));
    summaryHost.appendChild(el('<div class="fdtf-row"><span>' + t("vat", "IVA (" + Math.round(VAT * 100) + "%)") + '</span><b>' + money(pr.vat) + '</b></div>'));
    summaryHost.appendChild(el('<div class="fdtf-row tot"><span>' + t("total", "Total") + '</span><b>' + money(pr.total) + '</b></div>'));
    if (state.step === 3) {
      var add = el('<button class="fdtf-btn cart" type="button">🛒 ' + t("add", "Adicionar ao carrinho") + '</button>');
      add.addEventListener("click", addToCart);
      summaryHost.appendChild(add);
    }
  }

  // add to cart
  function addToCart() {
    if (totalQty() < MINQ) { alert(t("v_size", "Quantidade mínima de " + MINQ + " unidades por encomenda.")); return; }
    var act = activePositions();
    var payload = {
      product: state.product ? state.product.id : null,
      productName: state.product ? state.product.name : null,
      color: state.color ? state.color.name : null,
      colorHex: state.color ? state.color.hex : null,
      sizes: state.qty,
      positions: act.map(function (p2) { var st = state.pos[p2.code]; return { code: p2.code, label: p2.label, size: st.size, artName: st.artName }; }),
      extras: selectedExtras().map(function (e) { return e.code; }),
      production: state.production,
      notes: state.notes,
      price: pricing().net
    };
    if (typeof CFG.onAddToCart === "function") { CFG.onAddToCart(payload, state); return; }
    if (CFG.ajaxUrl) {
      var fd = new FormData();
      fd.append("action", "fdtf_add_to_cart");
      fd.append("nonce", CFG.nonce || "");
      fd.append("data", JSON.stringify(payload));
      act.forEach(function (p2) { var st = state.pos[p2.code]; if (st.rawFile) fd.append("art_" + p2.code, st.rawFile); });
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
      '<div class="fdtf-wrap"><div class="fdtf-steps"></div>' +
      '<div class="fdtf-grid"><div class="fdtf-main"></div><aside class="fdtf-summary"></aside></div></div>';
    viewHost = root.querySelector(".fdtf-main");
    summaryHost = root.querySelector(".fdtf-summary");
    render();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();
})();
