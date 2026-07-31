/* Fábrica DTF — pop-up de subscrição (mostra, valida, subscreve, mostra código) */
(function () {
	function init() {
	var CFG = window.FDTF_POPUP || {};
	var pop = document.getElementById('fdtfPopup');
	if (!pop) { return; }

	var DONE_COOKIE = 'fdtf_popup_done';
	var preview = pop.getAttribute('data-preview') === '1';

	function getCookie(name) {
		return document.cookie.split('; ').reduce(function (r, c) {
			var p = c.split('=');
			return p[0] === name ? decodeURIComponent(p.slice(1).join('=')) : r;
		}, '');
	}
	function setCookie(name, value, days) {
		var d = new Date();
		d.setTime(d.getTime() + days * 864e5);
		document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
	}

	var opened = false;
	function open() {
		if (opened) { return; }
		opened = true;
		pop.classList.add('is-open');
		pop.setAttribute('aria-hidden', 'false');
		var input = pop.querySelector('.fdtf-pop-email');
		if (input) { setTimeout(function () { input.focus(); }, 120); }
	}
	function close(markDone) {
		pop.classList.remove('is-open');
		pop.setAttribute('aria-hidden', 'true');
		if (markDone && !preview) { setCookie(DONE_COOKIE, '1', CFG.cookieDays || 30); }
	}

	// Decide whether/when to show.
	if (preview) {
		open();
	} else if (!getCookie(DONE_COOKIE)) {
		var shown = false;
		var timer = setTimeout(function () { shown = true; open(); }, (CFG.delay || 6) * 1000);
		// Exit-intent: show sooner if the mouse leaves the top of the viewport.
		document.addEventListener('mouseout', function onOut(e) {
			if (!shown && e.clientY <= 0) {
				shown = true; clearTimeout(timer); open();
				document.removeEventListener('mouseout', onOut);
			}
		});
	}

	// Close interactions.
	pop.addEventListener('click', function (e) {
		if (e.target.getAttribute('data-close') === '1') { close(true); }
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && pop.classList.contains('is-open')) { close(true); }
	});

	// Submit.
	var form = pop.querySelector('.fdtf-pop-form');
	var input = pop.querySelector('.fdtf-pop-email');
	var btn = pop.querySelector('.fdtf-pop-btn');
	var msg = pop.querySelector('.fdtf-pop-msg');

	function showMsg(text, kind, codeHtml) {
		msg.className = 'fdtf-pop-msg ' + (kind === 'ok' ? 'is-ok' : 'is-error');
		msg.innerHTML = text + (codeHtml ? '<br><span class="fdtf-pop-code">' + codeHtml + '</span>' : '');
	}

	function refreshNonce() {
		return fetch(CFG.ajaxUrl + '?action=fdtf_popup_nonce', { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (j) { if (j && j.success && j.data && j.data.nonce) { CFG.nonce = j.data.nonce; } })
			.catch(function () {});
	}

	function submit(retry) {
		var email = (input.value || '').trim();
		var valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
		if (!valid) {
			input.classList.add('is-invalid');
			showMsg('Por favor introduza um email válido.', 'error');
			input.focus();
			return;
		}
		input.classList.remove('is-invalid');
		btn.disabled = true;
		var body = new URLSearchParams();
		body.set('action', 'fdtf_popup');
		body.set('nonce', CFG.nonce || '');
		body.set('email', email);
		fetch(CFG.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (j && j.success) {
					if (j.data && j.data.cookie && j.data.code) {
						setCookie(j.data.cookie, j.data.code, CFG.cookieDays || 30);
					}
					showMsg(CFG.success || (j.data && j.data.message) || 'Obrigado!', 'ok', j.data && j.data.code);
					form.style.display = 'none';
					if (!preview) { setCookie(DONE_COOKIE, '1', CFG.cookieDays || 30); }
					btn.disabled = false;
				} else {
					var err = j && j.data ? j.data : {};
					if (err.code === 'bad_nonce' && !retry) {
						refreshNonce().then(function () { submit(true); });
						return;
					}
					showMsg(err.message || 'Ocorreu um erro. Tente novamente.', 'error');
					btn.disabled = false;
				}
			})
			.catch(function () {
				showMsg('Não foi possível ligar ao servidor. Tente novamente.', 'error');
				btn.disabled = false;
			});
	}

	if (form) {
		form.addEventListener('submit', function (e) { e.preventDefault(); submit(false); });
	}

	// Get a fresh nonce on load (pages may be served from cache with a stale one).
	refreshNonce();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
