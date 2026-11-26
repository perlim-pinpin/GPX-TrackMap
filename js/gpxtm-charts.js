/**
 * GPX-TrackMap : graphiques (dénivelé, pente, vitesse et graphique combiné) dessinés sur un canevas.
 * Code original du plugin (licence GNU/GPL v2 ou ultérieure).
 *
 * Une division « id_hp », « id_sp », « id_vp » ou « id_cp » (id = identifiant de la carte) devient un graphique
 * si elle porte l'attribut data-gpxtm-chart (réglages en JSON). Le pointeur déplace un marqueur sur la carte.
 */
(function (global) {
	'use strict';

	// La configuration (langue, textes) est écrite dans la page après le chargement du script : relue au démarrage
	var CFG = {}, T = {};
	function loadCfg() { CFG = global.GPXTM_CONFIG || {}; T = CFG.t || {}; }
	loadCfg();
	var KEYS = ['h', 's', 'v'];
	var LINES = { solid: [], dashed: [8, 4], dotted: [2, 3], dashdot: [10, 3, 2, 3], longdash: [16, 6] };
	var DEFAULT_LINE = { h: 'solid', s: 'dashed', v: 'dotted' };
	var UNITS = { h: 'm', s: '%', v: 'km/h', x: 'km' };
	var all = [];

	function label(k) { return T['curve' + k.toUpperCase()] || k; }
	function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

	// Graduations « rondes » couvrant toute l'étendue de l'axe
	function niceTicks(min, max, n) {
		var span = max - min;
		if (!(span > 0)) { span = Math.abs(max) || 1; min -= span / 2; max += span / 2; span = max - min; }
		var raw = span / n, mag = Math.pow(10, Math.floor(Math.log(raw) / Math.LN10)), norm = raw / mag;
		var step = (norm < 1.5 ? 1 : norm < 3 ? 2 : norm < 7 ? 5 : 10) * mag, ticks = [], t = Math.floor(min / step) * step;
		for (var i = 0; i < 200; i++) {
			ticks.push(Number(t.toFixed(10)));
			if (t >= max - step * 1e-9) { break; }
			t += step;
		}
		return ticks;
	}

	function styleOf(st, k) {
		var o = (st.o.styles && st.o.styles[k]) || {};
		return { color: o.color || '', dash: LINES[o.line] || LINES[DEFAULT_LINE[k]] };
	}
	function trackColor(st, tr, i) {
		var g = st.o.gcols;
		return (g && g.length && g[i % g.length]) || tr.color || '#f00';
	}
	function has(tr, k) { return !!(tr.has && tr.has[k]); }
	function name(st, tr, i) { return tr.name || tr.file || ('#' + (i + 1)); }

	function init(box, inst, o) {
		var st = { box: box, inst: inst, tracks: inst.tracks, o: o, follow: -1, on: {}, avail: {} };
		var curves = (o.curves && o.curves.length) ? o.curves : ['h'];
		KEYS.forEach(function (k) {
			st.avail[k] = st.tracks.some(function (t) { return has(t, k); });
			st.on[k] = curves.indexOf(k) !== -1;
		});
		box.innerHTML = '';
		box.style.position = 'relative';
		box.style.overflow = 'hidden';

		var bar = document.createElement('div');
		bar.className = 'gpxtm-chartbar';

		// Courbes à cocher (graphique combiné)
		if (o.selectable) {
			KEYS.forEach(function (k) {
				var lab = document.createElement('label'), cb = document.createElement('input'), sw = document.createElement('canvas');
				cb.type = 'checkbox'; cb.checked = st.on[k] && st.avail[k]; cb.disabled = !st.avail[k];
				cb.addEventListener('change', function () { st.on[k] = cb.checked; draw(st); });
				sw.width = 30; sw.height = 10; sw.style.cssText = 'width:30px;height:10px';
				var c = sw.getContext('2d');
				if (c) {
					var sty = styleOf(st, k);
					c.strokeStyle = sty.color || global.getComputedStyle(box).color || '#000'; c.lineWidth = 2; c.setLineDash(sty.dash);
					c.beginPath(); c.moveTo(0, 5); c.lineTo(30, 5); c.stroke();
				}
				lab.appendChild(cb); lab.appendChild(sw); lab.appendChild(document.createTextNode(label(k)));
				bar.appendChild(lab);
			});
		}

		// Plusieurs traces : fichier suivi sur la carte et légende des couleurs
		if (st.tracks.length > 1) {
			if (o.selectable) {
				var sl = document.createElement('label');
				sl.appendChild(document.createTextNode((T.follow || 'Follow') + ' :'));
				var sel = document.createElement('select');
				var o0 = document.createElement('option'); o0.value = '-1'; o0.textContent = T.auto || 'auto'; sel.appendChild(o0);
				st.tracks.forEach(function (t, i) { var op = document.createElement('option'); op.value = String(i); op.textContent = name(st, t, i); sel.appendChild(op); });
				sel.addEventListener('change', function () { st.follow = parseInt(sel.value, 10); });
				sl.appendChild(sel); bar.appendChild(sl);
			}
			st.tracks.forEach(function (t, i) {
				var n = document.createElement('span'), c = document.createElement('span');
				c.className = 'gpxtm-chartsw'; c.style.background = trackColor(st, t, i);
				n.appendChild(c); n.appendChild(document.createTextNode(name(st, t, i)));
				bar.appendChild(n);
			});
		}
		if (bar.childNodes.length) { box.appendChild(bar); }

		var cv = document.createElement('canvas'), tip = document.createElement('div');
		cv.style.cssText = 'display:block;width:100%;touch-action:pan-y';
		tip.className = 'gpxtm-charttip';
		box.appendChild(cv); box.appendChild(tip);
		st.bar = bar; st.cv = cv; st.tip = tip;

		var ev = global.PointerEvent ? ['pointermove', 'pointerleave'] : ['mousemove', 'mouseleave'];
		cv.addEventListener(ev[0], function (e) { hover(st, e); });
		cv.addEventListener(ev[1], function () { tip.style.display = 'none'; if (inst.hideMarker) { inst.hideMarker(); } });

		all = all.filter(function (s) { return s.box !== box; });
		all.push(st);
		draw(st);
	}

	function draw(st) {
		var cv = st.cv, box = st.box, w = cv.clientWidth || box.clientWidth || 600;
		var h = box.clientHeight - (st.bar.childNodes.length ? st.bar.offsetHeight : 0);
		if (!(h > 80)) { h = 200; }
		cv.style.height = h + 'px';
		var dpr = global.devicePixelRatio || 1;
		cv.width = Math.round(w * dpr); cv.height = Math.round(h * dpr);
		var ctx = cv.getContext('2d');
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		ctx.clearRect(0, 0, w, h);
		var fg = global.getComputedStyle(box).color || '#000';
		ctx.font = '11px sans-serif';

		var active = KEYS.filter(function (k) { return st.on[k] && st.avail[k]; });
		st.layout = null;
		if (!active.length) { ctx.fillStyle = fg; ctx.textAlign = 'center'; ctx.fillText(T.none || '', w / 2, h / 2); return; }

		var rng = {}, xmax = 0;
		active.forEach(function (k) { rng[k] = { min: Infinity, max: -Infinity }; });
		st.tracks.forEach(function (t) {
			t.pts.forEach(function (p) {
				if (p.x > xmax) { xmax = p.x; }
				active.forEach(function (k) {
					if (!has(t, k)) { return; }
					var y = p[k];
					if (typeof y === 'number' && isFinite(y)) { if (y < rng[k].min) { rng[k].min = y; } if (y > rng[k].max) { rng[k].max = y; } }
				});
			});
		});

		var nRight = active.length - 1, AX = 46, padL = 48, padR = 12 + AX * nRight, padT = 18, padB = 24;
		var pw = w - padL - padR, ph = h - padT - padB;
		if (pw < 40 || ph < 40) { return; }

		var axes = {};
		active.forEach(function (k, i) {
			if (!(rng[k].min <= rng[k].max)) { rng[k] = { min: 0, max: 1 }; }
			var t = niceTicks(rng[k].min, rng[k].max, 5);
			axes[k] = { ticks: t, min: t[0], max: t[t.length - 1], side: i === 0 ? 'L' : 'R', pos: i === 0 ? padL : w - padR + AX * (i - 1) };
		});
		var xt = niceTicks(0, xmax || 1, 8), xs = xt[xt.length - 1] || 1;
		function X(v) { return padL + (v / xs) * pw; }
		function Y(k, v) { return padT + ph - ((v - axes[k].min) / ((axes[k].max - axes[k].min) || 1)) * ph; }

		ctx.lineWidth = 1; ctx.strokeStyle = 'rgba(128,128,128,.3)'; ctx.fillStyle = fg; ctx.textAlign = 'center';
		xt.forEach(function (t) { ctx.beginPath(); ctx.moveTo(X(t), padT); ctx.lineTo(X(t), padT + ph); ctx.stroke(); ctx.fillText(String(t), X(t), padT + ph + 14); });
		ctx.textAlign = 'right'; ctx.fillText(UNITS.x, w - 4, h - 4);
		axes[active[0]].ticks.forEach(function (t) { var y = Y(active[0], t); ctx.beginPath(); ctx.moveTo(padL, y); ctx.lineTo(padL + pw, y); ctx.stroke(); });
		active.forEach(function (k) {
			var a = axes[k];
			ctx.fillStyle = fg; ctx.textAlign = a.side === 'L' ? 'right' : 'left';
			a.ticks.forEach(function (t) { ctx.fillText(String(t), a.side === 'L' ? padL - 4 : a.pos + 4, Y(k, t) + 3); });
			ctx.fillText(UNITS[k], a.side === 'L' ? padL - 4 : a.pos + 4, padT - 6);
		});

		ctx.save(); ctx.beginPath(); ctx.rect(padL, padT, pw, ph); ctx.clip();
		st.tracks.forEach(function (t, ti) {
			active.forEach(function (k) {
				if (!has(t, k)) { return; }
				var sty = styleOf(st, k), col = sty.color || trackColor(st, t, ti), pen = false, lastX = 0;
				ctx.beginPath(); ctx.strokeStyle = col; ctx.lineWidth = k === 'h' ? 2 : 1.5; ctx.setLineDash(sty.dash);
				t.pts.forEach(function (p) {
					if (typeof p[k] !== 'number' || !isFinite(p[k])) { pen = false; return; }
					if (pen) { ctx.lineTo(X(p.x), Y(k, p[k])); } else { ctx.moveTo(X(p.x), Y(k, p[k])); pen = true; }
					lastX = p.x;
				});
				ctx.stroke();
				// Remplissage sous la courbe d'altitude (graphique de dénivelé seul)
				if (st.o.fill > 0 && k === 'h' && active.length === 1) {
					ctx.lineTo(X(lastX), padT + ph); ctx.lineTo(X(0), padT + ph); ctx.closePath();
					ctx.globalAlpha = Math.min(1, st.o.fill); ctx.fillStyle = col; ctx.fill(); ctx.globalAlpha = 1;
				}
			});
		});
		ctx.restore(); ctx.setLineDash([]);
		st.layout = { padL: padL, pw: pw, xs: xs, active: active, Y: Y };
	}

	function hover(st, e) {
		var Lt = st.layout;
		if (!Lt) { return; }
		var r = st.cv.getBoundingClientRect(), x = e.clientX - r.left, y = e.clientY - r.top;
		if (x < Lt.padL || x > Lt.padL + Lt.pw) { st.tip.style.display = 'none'; return; }
		var dist = (x - Lt.padL) / Lt.pw * Lt.xs, k0 = Lt.active[0], lines = [dist.toFixed(2) + ' ' + UNITS.x], pick = null, gapBest = Infinity, forced = null;

		st.tracks.forEach(function (t, i) {
			var best = null, bd = Infinity;
			t.pts.forEach(function (p) { var d = Math.abs(p.x - dist); if (d < bd) { bd = d; best = p; } });
			if (!best) { return; }
			var parts = [];
			Lt.active.forEach(function (k) { if (has(t, k) && typeof best[k] === 'number') { parts.push(label(k) + ' ' + best[k].toFixed(k === 'h' ? 0 : 1) + ' ' + UNITS[k]); } });
			if (parts.length) { lines.push((st.tracks.length > 1 ? name(st, t, i) + ' : ' : '') + parts.join(' | ')); }
			var gap = (has(t, k0) && typeof best[k0] === 'number') ? Math.abs(Lt.Y(k0, best[k0]) - y) : Infinity;
			var cand = { point: best, name: name(st, t, i), parts: parts };
			if (st.follow === i) { forced = cand; }
			if (pick === null || gap < gapBest) { pick = cand; gapBest = gap; }
		});

		st.tip.textContent = lines.join('\n'); st.tip.style.display = 'block';
		var bx = st.box.getBoundingClientRect(), left = e.clientX - bx.left + 12;
		if (left + st.tip.offsetWidth > bx.width) { left = e.clientX - bx.left - st.tip.offsetWidth - 12; }
		st.tip.style.left = Math.max(0, left) + 'px'; st.tip.style.top = (e.clientY - bx.top + 12) + 'px';

		if (forced) { pick = forced; }
		if (pick && st.inst.showAt) {
			st.inst.showAt(pick.point, '<strong>' + esc(pick.name) + '</strong><br>' + dist.toFixed(2) + ' km' + (pick.parts.length ? '<br>' + pick.parts.map(esc).join('<br>') : ''));
		}
	}

	// Crée tous les graphiques demandés pour une carte
	function initAll(inst) {
		loadCfg();
		['hp', 'sp', 'vp', 'cp'].forEach(function (s) {
			var box = document.getElementById(inst.el.id + '_' + s);
			if (!box || !box.getAttribute('data-gpxtm-chart')) { return; }
			var o = {};
			try { o = JSON.parse(box.getAttribute('data-gpxtm-chart')) || {}; } catch (e) { o = {}; }
			init(box, inst, o);
		});
	}

	global.addEventListener('resize', function () { all.forEach(function (st) { if (document.body.contains(st.box)) { draw(st); } }); });
	global.GPXTMCharts = { initAll: initAll, init: init };
})(window);
