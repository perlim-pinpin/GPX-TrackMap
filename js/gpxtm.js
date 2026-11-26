/**
 * GPX-TrackMap : moteur d'affichage des traces GPX sur une carte Leaflet.
 * Code original du plugin (licence GNU/GPL v2 ou ultérieure), écrit pour GPX-TrackMap ; il utilise la bibliothèque Leaflet (licence BSD-2).
 *
 * Chaque carte est une division « .gpxtm-map » dont les réglages sont des attributs data-*.
 * Après la lecture des fichiers GPX, l'événement « gpxtm:tracks » est envoyé sur la division (voir les modèles).
 */
(function (global) {
	'use strict';

	var L = global.L;
	if (!L) { return; }

	// La configuration (langue, textes) est écrite dans la page après le chargement du script : relue au démarrage
	var CFG = {}, T = {};
	function loadCfg() { CFG = global.GPXTM_CONFIG || {}; T = CFG.t || {}; }
	loadCfg();

	// ---------------------------------------------------------------------------------------------
	// Outils
	// ---------------------------------------------------------------------------------------------

	function tr(key, fallback) { return T[key] !== undefined ? T[key] : (fallback !== undefined ? fallback : key); }
	function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function num(v, d) { var n = parseFloat(v); return isFinite(n) ? n : d; }
	function bool(v, d) {
		if (v === undefined || v === null || v === '') { return d; }
		v = String(v).toLowerCase();
		return v === 'true' || v === '1' || v === 'yes' || v === 'oui' || v === 'on';
	}
	function rad(d) { return d * Math.PI / 180; }

	// Distance en km entre deux points (formule de haversine)
	function haversine(a, b) {
		var dLat = rad(b.lat - a.lat), dLon = rad(b.lon - a.lon);
		var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
		return 12742 * Math.asin(Math.min(1, Math.sqrt(h)));
	}

	// Durée « 1 h 05 min » à partir de secondes
	function fmtDur(sec) {
		sec = Math.round(sec);
		var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
		if (h > 0) { return h + ' h ' + (m < 10 ? '0' : '') + m + ' min'; }
		if (m > 0) { return m + ' min ' + (s < 10 ? '0' : '') + s + ' s'; }
		return s + ' s';
	}
	function fmtDate(ms) {
		try { return new Date(ms).toLocaleString(CFG.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' }); } catch (e) { return new Date(ms).toISOString(); }
	}
	function fmtNum(v, d) { return Number(v).toLocaleString(CFG.lang || undefined, { maximumFractionDigits: d, minimumFractionDigits: 0 }); }

	// Échelle de couleurs bleu -> vert -> rouge (valeur de 0 à 1), la même que l'aperçu de l'administration
	function scaleColor(t) {
		t = Math.max(0, Math.min(1, t));
		function g(pos) { var x = (t - pos) * 3; return Math.min(255, Math.round(255 * Math.exp(-x * x))); }
		return 'rgb(' + g(0.75) + ',' + g(0.5) + ',' + g(0.25) + ')';
	}

	// ---------------------------------------------------------------------------------------------
	// Lecture d'un fichier GPX
	// ---------------------------------------------------------------------------------------------

	function childText(node, name) {
		var list = node.getElementsByTagNameNS('*', name);
		for (var i = 0; i < list.length; i++) { if (list[i].parentNode === node) { return (list[i].textContent || '').trim(); } }
		return '';
	}
	function extValue(node, name) {
		var list = node.getElementsByTagNameNS('*', name);
		if (!list.length) { return NaN; }
		return parseFloat(list[0].textContent);
	}

	function readPoint(node, withExt) {
		var p = { lat: parseFloat(node.getAttribute('lat')), lon: parseFloat(node.getAttribute('lon')) };
		if (!isFinite(p.lat) || !isFinite(p.lon)) { return null; }
		var ele = childText(node, 'ele');
		if (ele !== '' && isFinite(parseFloat(ele))) { p.ele = parseFloat(ele); }
		var time = childText(node, 'time');
		if (time) { var ms = Date.parse(time); if (isFinite(ms)) { p.time = ms; } }
		if (withExt) {
			var hr = extValue(node, 'hr'), cad = extValue(node, 'cad'), at = extValue(node, 'atemp');
			if (isFinite(hr)) { p.hr = hr; }
			if (isFinite(cad)) { p.cad = cad; }
			if (isFinite(at)) { p.atemp = at; }
		}
		return p;
	}

	function parseGpx(text, file) {
		var doc = new DOMParser().parseFromString(text, 'application/xml');
		if (doc.getElementsByTagName('parsererror').length) { return null; }
		var out = { tracks: [], routes: [], waypoints: [] };

		var trks = doc.getElementsByTagNameNS('*', 'trk');
		for (var i = 0; i < trks.length; i++) {
			var segs = trks[i].getElementsByTagNameNS('*', 'trkseg'), pts = [], segStarts = [];
			for (var s = 0; s < segs.length; s++) {
				var nodes = segs[s].getElementsByTagNameNS('*', 'trkpt');
				segStarts.push(pts.length);
				for (var k = 0; k < nodes.length; k++) { var p = readPoint(nodes[k], true); if (p) { pts.push(p); } }
			}
			if (pts.length > 1) {
				out.tracks.push({ name: childText(trks[i], 'name'), cmt: childText(trks[i], 'cmt'), desc: childText(trks[i], 'desc'), pts: pts, file: file });
			}
		}
		var rtes = doc.getElementsByTagNameNS('*', 'rte');
		for (var r = 0; r < rtes.length; r++) {
			var rn = rtes[r].getElementsByTagNameNS('*', 'rtept'), rp = [];
			for (var q = 0; q < rn.length; q++) { var pp = readPoint(rn[q], false); if (pp) { rp.push(pp); } }
			if (rp.length > 1) { out.routes.push({ name: childText(rtes[r], 'name'), cmt: childText(rtes[r], 'cmt'), desc: childText(rtes[r], 'desc'), pts: rp, file: file }); }
		}
		var wpts = doc.getElementsByTagNameNS('*', 'wpt');
		for (var w = 0; w < wpts.length; w++) {
			var wp = readPoint(wpts[w], false);
			if (wp) { wp.name = childText(wpts[w], 'name'); wp.cmt = childText(wpts[w], 'cmt'); wp.desc = childText(wpts[w], 'desc'); wp.sym = childText(wpts[w], 'sym'); wp.type = childText(wpts[w], 'type'); out.waypoints.push(wp); }
		}
		return out;
	}

	// ---------------------------------------------------------------------------------------------
	// Calculs sur une trace : distance, altitude lissée, dénivelé, pente, vitesse, durées
	// ---------------------------------------------------------------------------------------------

	function computeTrack(track) {
		var pts = track.pts, n = pts.length, i, j;

		// Distance cumulée (km)
		pts[0].x = 0;
		for (i = 1; i < n; i++) { pts[i].x = pts[i - 1].x + haversine(pts[i - 1], pts[i]); }

		var hasEle = pts.filter(function (p) { return p.ele !== undefined; }).length > n * 0.5;
		var hasTime = pts.filter(function (p) { return p.time !== undefined; }).length > n * 0.5;
		var st = { dist: pts[n - 1].x, gain: 0, loss: 0 };

		// Altitude lissée sur 5 points, puis dénivelé avec un seuil de 3 m (évite de compter le bruit du GPS)
		if (hasEle) {
			var last = 0;
			for (i = 0; i < n; i++) { if (pts[i].ele === undefined) { pts[i].ele = last; } last = pts[i].ele; }
			for (i = 0; i < n; i++) {
				var sum = 0, c = 0;
				for (j = Math.max(0, i - 2); j <= Math.min(n - 1, i + 2); j++) { sum += pts[j].ele; c++; }
				pts[i].h = sum / c;
			}
			var ref = pts[0].h;
			st.hmin = Infinity; st.hmax = -Infinity;
			for (i = 0; i < n; i++) {
				var h = pts[i].h;
				if (h < st.hmin) { st.hmin = h; }
				if (h > st.hmax) { st.hmax = h; }
				if (h - ref >= 3) { st.gain += h - ref; ref = h; } else if (ref - h >= 3) { st.loss += ref - h; ref = h; }
			}
			// Pente en %, calculée sur au moins 40 m
			st.smin = Infinity; st.smax = -Infinity;
			for (i = 0; i < n; i++) {
				var a = i, b = i;
				while (a > 0 && pts[i].x - pts[a].x < 0.02) { a--; }
				while (b < n - 1 && pts[b].x - pts[i].x < 0.02) { b++; }
				var dx = (pts[b].x - pts[a].x) * 1000;
				if (dx >= 10) {
					var s = Math.max(-60, Math.min(60, (pts[b].h - pts[a].h) / dx * 100));
					pts[i].s = s;
					if (s < st.smin) { st.smin = s; }
					if (s > st.smax) { st.smax = s; }
				}
			}
		}

		// Vitesse (km/h) sur au moins 20 m et 5 s, durée totale et durée en mouvement
		if (hasTime) {
			var t0 = null, t1 = null;
			for (i = 0; i < n; i++) { if (pts[i].time !== undefined) { if (t0 === null) { t0 = pts[i].time; } t1 = pts[i].time; } }
			st.t0 = t0;
			st.dur = (t1 - t0) / 1000;
			st.vmax = 0;
			for (i = 0; i < n; i++) {
				if (pts[i].time === undefined) { continue; }
				var a2 = i, b2 = i;
				while (a2 > 0 && (pts[i].x - pts[a2].x < 0.02 || pts[i].time - pts[a2].time < 5000)) { a2--; }
				while (b2 < n - 1 && (pts[b2].x - pts[i].x < 0.02 || pts[b2].time - pts[i].time < 5000)) { b2++; }
				var dt = (pts[b2].time - pts[a2].time) / 3600000;
				if (dt > 0 && isFinite(dt)) {
					var v = (pts[b2].x - pts[a2].x) / dt;
					if (v >= 0 && v < 150) { pts[i].v = v; }
				}
			}
			var mov = 0;
			for (i = 1; i < n; i++) {
				if (pts[i].time === undefined || pts[i - 1].time === undefined) { continue; }
				var d = (pts[i].time - pts[i - 1].time) / 1000;
				if (d > 0 && (pts[i].x - pts[i - 1].x) * 1000 / d >= 0.5) { mov += d; }
			}
			st.durMoving = mov;
			if (st.dur > 0) { st.vavg = st.dist / (st.dur / 3600); }
			if (mov > 0) { st.vavgMoving = st.dist / (mov / 3600); }
		}

		// Pouls, cadence et température : moyennes
		['hr', 'cad', 'atemp'].forEach(function (k) {
			var vals = pts.filter(function (p) { return p[k] !== undefined; }).map(function (p) { return p[k]; });
			if (vals.length) { st[k + 'Avg'] = vals.reduce(function (x, y) { return x + y; }, 0) / vals.length; }
		});

		track.stats = st;
		track.has = { h: hasEle, s: hasEle, v: hasTime, hr: st.hrAvg !== undefined, cad: st.cadAvg !== undefined, atemp: st.atempAvg !== undefined };
		return track;
	}

	// ---------------------------------------------------------------------------------------------
	// Fonds de carte
	// ---------------------------------------------------------------------------------------------

	var OSM_ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
	function ign(layer, fmt) {
		return 'https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=' + layer + '&STYLE=normal&FORMAT=' + fmt + '&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}';
	}
	var PROVIDERS = {
		osm: { label: 'OpenStreetMap', url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', o: { maxZoom: 19, attribution: OSM_ATTR } },
		osmde: { label: 'OSM Deutschland', url: 'https://tile.openstreetmap.de/{z}/{x}/{y}.png', o: { maxZoom: 19, attribution: OSM_ATTR } },
		opentopo: { label: 'OpenTopoMap', url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', o: { maxZoom: 17, subdomains: 'abc', attribution: OSM_ATTR + ', SRTM | &copy; <a href="https://opentopomap.org" target="_blank" rel="noopener">OpenTopoMap</a> (CC-BY-SA)' } },
		topplusopen: { label: 'TopPlusOpen', url: 'https://sgx.geodatenzentrum.de/wmts_topplus_open/tile/1.0.0/web/default/WEBMERCATOR/{z}/{y}/{x}.png', o: { maxZoom: 18, attribution: '&copy; <a href="https://www.bkg.bund.de" target="_blank" rel="noopener">BKG</a> (dl-de/by-2-0)' } },
		satellit: { label: 'Satellite (Esri)', url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', o: { maxZoom: 19, attribution: 'Tiles &copy; <a href="https://www.esri.com" target="_blank" rel="noopener">Esri</a> &mdash; Source: Esri, Maxar, Earthstar Geographics' } },
		ignsatellite: { label: 'IGN Satellite', url: ign('ORTHOIMAGERY.ORTHOPHOTOS', 'image/jpeg'), o: { maxZoom: 19, attribution: '&copy; IGN / Geoplateforme' } },
		ignplan: { label: 'IGN Plan', url: ign('GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2', 'image/png'), o: { maxZoom: 19, attribution: '&copy; IGN / Geoplateforme' } },
		igntopo: { label: 'IGN Topo', url: ign('GEOGRAPHICALGRIDSYSTEMS.MAPS', 'image/jpeg'), o: { maxZoom: 18, attribution: '&copy; IGN / Geoplateforme' } },
		nomap: { label: '—', url: null, o: {} }
	};
	var OVERLAYS = {
		opensea: { label: 'OpenSeaMap', url: 'https://tiles.openseamap.org/seamark/{z}/{x}/{y}.png', o: { maxZoom: 18, attribution: '&copy; <a href="https://www.openseamap.org" target="_blank" rel="noopener">OpenSeaMap</a> contributors' } },
		hiking: { label: 'Waymarked Trails (hiking)', url: 'https://tile.waymarkedtrails.org/hiking/{z}/{x}/{y}.png', o: { maxZoom: 18, attribution: '&copy; <a href="https://waymarkedtrails.org" target="_blank" rel="noopener">Waymarked Trails</a> (CC-BY-SA)' } },
		cycling: { label: 'Waymarked Trails (cycling)', url: 'https://tile.waymarkedtrails.org/cycling/{z}/{x}/{y}.png', o: { maxZoom: 18, attribution: '&copy; <a href="https://waymarkedtrails.org" target="_blank" rel="noopener">Waymarked Trails</a> (CC-BY-SA)' } }
	};
	function layerFor(def) { return def.url ? L.tileLayer(def.url, def.o) : L.layerGroup(); }

	// ---------------------------------------------------------------------------------------------
	// Accord du visiteur avant le chargement des cartes (licences des fonds de carte)
	// ---------------------------------------------------------------------------------------------

	var consent = null;
	function consentOk() {
		if (consent !== null) { return consent; }
		if (!CFG.consent || global.location.protocol === 'file:') { consent = true; return true; }
		try { if (global.localStorage && global.localStorage.getItem('GPXTM_OK')) { consent = true; return true; } } catch (e) { /* stockage bloqué */ }
		consent = !!global.confirm(tr('consentQ'));
		if (consent) { try { global.localStorage.setItem('GPXTM_OK', '1'); } catch (e) { /* mémorisation impossible */ } }
		return consent;
	}

	// ---------------------------------------------------------------------------------------------
	// Carte
	// ---------------------------------------------------------------------------------------------

	function options(el) {
		var d = el.dataset, o = {};
		o.files = (d.files || '').split(',').filter(Boolean);
		o.base = d.gpxpath || '';
		o.imgBase = d.imgpath || '';
		o.style = (d.style || 'osm').toLowerCase().replace(/[^a-z]/g, '');
		o.maplist = d.maplist ? d.maplist.split(',').map(function (s) { return s.trim().toLowerCase(); }) : null;
		o.tcols = (d.tcols || '#ff0000,#00ff00,#0000ff,#eeee00,#ff00ff,#00ffff,#000000').split(',');
		o.twidth = num(d.twidth, 3);
		o.topac = num(d.topac, 0.8);
		o.trcolmod = (d.trcolmod || '').toLowerCase();
		o.rcols = (d.rcols || '#0000ff').split(',');
		o.rwidth = num(d.rwidth, 2);
		o.ropac = num(d.ropac, 0.7);
		o.tracks = bool(d.gpxtracks, true);
		o.routes = bool(d.gpxroutes, true);
		o.wpts = bool(d.gpxwaypoints, true);
		o.scrollzoom = bool(d.scrollzoom, true);
		o.layerctl = bool(d.layercontrol, true);
		o.fullscreen = bool(d.fullscreenbutton, true);
		o.locate = bool(d.locatebutton, false);
		o.recenter = bool(d.recenter, true);
		o.arrow = bool(d.arrowtrack, false);
		o.arrowcol = d.arrowtrackcol || '';
		o.arrowsym = d.arrowsymbol || '➤';
		o.legend = bool(d.legend, true);
		o.legendTrk = bool(d.legend_trk, true);
		o.legendRte = bool(d.legend_rte, true);
		o.legendWpt = bool(d.legend_wpt, true);
		o.legendInfo = bool(d.legend_info, false);
		o.start = bool(d.shtrstart, true);
		o.finish = bool(d.shtrfinish, true);
		o.trackover = bool(d.trackover, true);
		o.trackclick = bool(d.trackclick, true);
		o.center = d.center ? d.center.split(',').map(parseFloat) : null;
		o.thumb = num(d.thumbsize, 200);
		o.defaulticon = (d.defaulticon || '').toLowerCase();
		o.sw = {};
		Object.keys(d).forEach(function (k) { if (k.indexOf('shtr') === 0 || k.indexOf('shwp') === 0) { o.sw[k] = bool(d[k], false); } });
		return o;
	}

	// Ligne « libellé : valeur » des informations d'un point de trace (bulle au clic)
	function pointInfo(trk, p, o) {
		var sw = o.sw, st = trk.stats, rows = [];
		function row(label, val) { rows.push('<tr><td>' + esc(label) + '</td><td>' + esc(val) + '</td></tr>'); }
		var head = '';
		if (sw.shtrname && trk.name) { head += '<strong>' + esc(trk.name) + '</strong><br>'; }
		if (sw.shtrcmt && trk.cmt) { head += esc(trk.cmt) + '<br>'; }
		if (sw.shtrdesc && trk.desc) { head += esc(trk.desc) + '<br>'; }
		if (sw.shtrx) { row(tr('dist'), fmtNum(p.x, 2) + ' km'); }
		if (sw.shtrh && p.h !== undefined) { row(tr('alt'), Math.round(p.h) + ' m'); }
		if (sw.shtrt && p.time !== undefined) { row(tr('time'), fmtDate(p.time)); }
		if (sw.shtrtwob && p.time !== undefined && st.t0 !== undefined) { row(tr('elapsed'), fmtDur((p.time - st.t0) / 1000)); }
		if (sw.shtrrr && st.gain !== undefined) { row(tr('gain'), '+' + Math.round(st.gain) + ' / -' + Math.round(st.loss) + ' m'); }
		if (sw.shtrs && p.s !== undefined) { row(tr('slope'), fmtNum(p.s, 1) + ' %'); }
		if (sw.shtrv && p.v !== undefined) { row(tr('speed'), fmtNum(p.v, 1) + ' km/h'); }
		if (sw.shtrpace && p.v > 0.3) { row(tr('pace'), fmtNum(60 / p.v, 1) + ' min/km'); }
		if (sw.shtrhr && p.hr !== undefined) { row(tr('hr'), Math.round(p.hr) + ' bpm'); }
		if (sw.shtrcad && p.cad !== undefined) { row(tr('cad'), Math.round(p.cad) + ' rpm'); }
		if (sw.shtratemp && p.atemp !== undefined) { row(tr('temp'), Math.round(p.atemp) + ' °C'); }
		if (sw.shtrlatlon) { row(tr('coord'), p.lat.toFixed(5) + ', ' + p.lon.toFixed(5)); }
		if (sw.shtrtges && st.dur !== undefined) { row(tr('dur'), fmtDur(st.dur)); }
		if (sw.shtrtgeswob && st.durMoving !== undefined) { row(tr('moving'), fmtDur(st.durMoving)); }
		if (sw.shtrvmitt && st.vavg !== undefined) { row(tr('avgspeed'), fmtNum(st.vavg, 1) + ' km/h'); }
		if (sw.shtrvmittwob && st.vavgMoving !== undefined) { row(tr('avgspeedmov'), fmtNum(st.vavgMoving, 1) + ' km/h'); }
		if (sw.shtrvmittpace && st.vavg > 0) { row(tr('avgpace'), fmtNum(60 / st.vavg, 1) + ' min/km'); }
		if (sw.shtrvmittpacewob && st.vavgMoving > 0) { row(tr('avgpacemov'), fmtNum(60 / st.vavgMoving, 1) + ' min/km'); }
		return head + (rows.length ? '<table class="gpxtm-pop">' + rows.join('') + '</table>' : '');
	}

	function nearest(trk, latlng, map) {
		var best = null, bd = Infinity, pt = map.latLngToLayerPoint(latlng);
		trk.pts.forEach(function (p) {
			var q = map.latLngToLayerPoint([p.lat, p.lon]), d = (q.x - pt.x) * (q.x - pt.x) + (q.y - pt.y) * (q.y - pt.y);
			if (d < bd) { bd = d; best = p; }
		});
		return best;
	}

	function icon(cls, html) { return L.divIcon({ className: 'gpxtm-icon ' + cls, html: html || '', iconSize: [22, 22], iconAnchor: [11, 11], popupAnchor: [0, -10] }); }
	function pin(color) {
		return L.divIcon({ className: 'gpxtm-pin', html: '<svg viewBox="0 0 24 34" width="24" height="34"><path d="M12 1C6 1 1.5 5.5 1.5 11.2 1.5 19 12 33 12 33s10.5-14 10.5-21.8C22.5 5.5 18 1 12 1z" fill="' + color + '" stroke="#fff" stroke-width="1.5"/><circle cx="12" cy="11" r="4" fill="#fff"/></svg>', iconSize: [24, 34], iconAnchor: [12, 33], popupAnchor: [0, -30] });
	}

	// Icône d'un waypoint : nom d'icône, clé=valeur d'OpenStreetMap ou mot courant, trouvé dans la balise <sym> puis <type>, sinon icône par défaut
	function iconName(w, def) {
		var I = global.GPXTM_ICONS;
		if (!I) { return null; }
		var found = null;
		[w.sym, w.type, def].forEach(function (raw) {
			if (found || !raw) { return; }
			var k = String(raw).toLowerCase().trim(), u = k.replace(/[\s\-]+/g, '_');
			if (I.names.indexOf(u) !== -1) { found = u; return; }
			found = I.osm[k] || I.words[k] || I.osm[u] || I.words[u.replace(/_/g, ' ')] || null;
			if (!found && k.indexOf('=') !== -1) { found = I.words[k.split('=')[1].trim()] || null; }
		});
		return found;
	}
	function wptIcon(w, def) {
		var n = iconName(w, def), base = CFG.iconUrl;
		if (!n || !base) { return pin('#d9534f'); }
		return L.icon({ iconUrl: base + n + '.png', iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -14], tooltipAnchor: [14, 0], className: 'gpxtm-wpt' });
	}

	function fullscreenControl(map, el) {
		var Ctl = L.Control.extend({
			options: { position: 'topleft' },
			onAdd: function () {
				var box = L.DomUtil.create('div', 'leaflet-bar'), a = L.DomUtil.create('a', 'gpxtm-fs', box);
				a.href = '#'; a.setAttribute('role', 'button'); a.title = tr('fullscreen'); a.setAttribute('aria-label', tr('fullscreen'));
				a.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M3 9V3h6M15 3h6v6M21 15v6h-6M9 21H3v-6" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
				L.DomEvent.disableClickPropagation(box);
				L.DomEvent.on(a, 'click', function (e) {
					L.DomEvent.preventDefault(e);
					var wrap = el, on = wrap.classList.toggle('gpxtm-full');
					if (on && wrap.requestFullscreen) { wrap.requestFullscreen().catch(function () { /* mode plein écran CSS seulement */ }); }
					else if (!on && document.fullscreenElement && document.exitFullscreen) { document.exitFullscreen(); }
					setTimeout(function () { map.invalidateSize(); }, 150);
				});
				return box;
			}
		});
		document.addEventListener('fullscreenchange', function () {
			if (!document.fullscreenElement) { el.classList.remove('gpxtm-full'); }
			setTimeout(function () { map.invalidateSize(); }, 150);
		});
		new Ctl().addTo(map);
	}

	function buttonControl(map, pos, label, svg, onClick) {
		var Ctl = L.Control.extend({
			options: { position: pos },
			onAdd: function () {
				var box = L.DomUtil.create('div', 'leaflet-bar'), a = L.DomUtil.create('a', '', box);
				a.href = '#'; a.title = label; a.setAttribute('role', 'button'); a.setAttribute('aria-label', label); a.innerHTML = svg;
				L.DomEvent.disableClickPropagation(box);
				L.DomEvent.on(a, 'click', function (e) { L.DomEvent.preventDefault(e); onClick(); });
				return box;
			}
		});
		return new Ctl().addTo(map);
	}

	function build(el) {
		if (el._gpxtm) { return; }
		var o = options(el);
		if (!o.files.length) { return; }
		if (!consentOk()) { el.innerHTML = '<p class="gpxtm-refused">' + esc(tr('consentNo')) + '</p>'; return; }

		var inst = { el: el, options: o, L: L, tracks: [], routes: [], waypoints: [] };
		el._gpxtm = inst;
		el.classList.add('gpxtm-ready');

		// Lecture de tous les fichiers GPX
		var jobs = o.files.map(function (f) {
			var url = o.base + f.split('/').map(encodeURIComponent).join('/');
			return fetch(url).then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.text(); }).then(function (t) { return parseGpx(t, f); }).catch(function () { return null; });
		});

		Promise.all(jobs).then(function (results) {
			var ci = 0;
			results.forEach(function (g) {
				if (!g) { return; }
				if (o.tracks) { g.tracks.forEach(function (t) { t.color = o.tcols[ci % o.tcols.length].trim(); ci++; computeTrack(t); inst.tracks.push(t); }); }
				if (o.routes) { g.routes.forEach(function (r, i) { r.color = o.rcols[i % o.rcols.length].trim(); inst.routes.push(r); }); }
				if (o.wpts) { g.waypoints.forEach(function (w) { inst.waypoints.push(w); }); }
			});
			if (!inst.tracks.length && !inst.routes.length && !inst.waypoints.length) { el.innerHTML = '<p class="gpxtm-refused">' + esc(tr('nodata')) + '</p>'; return; }
			draw(inst);
		});
	}

	function draw(inst) {
		var el = inst.el, o = inst.options;
		el.innerHTML = '';
		var map = L.map(el, { scrollWheelZoom: o.scrollzoom, zoomSnap: 0.25, zoomControl: false });
		L.control.zoom({ zoomInTitle: tr('zoomIn', 'Zoom in'), zoomOutTitle: tr('zoomOut', 'Zoom out') }).addTo(map);
		inst.map = map;
		L.control.scale({ imperial: false }).addTo(map);

		// Fonds de carte et calques
		var styleName = o.style, base = PROVIDERS[styleName] ? styleName : 'osm', baseLayers = {}, overlays = {}, firstOverlay = OVERLAYS[styleName] ? styleName : null;
		var allowed = function (k) { return !o.maplist || o.maplist.indexOf(k) !== -1 || k === base; };
		Object.keys(PROVIDERS).forEach(function (k) { if (allowed(k)) { baseLayers[PROVIDERS[k].label === '—' ? tr('nomap') : PROVIDERS[k].label] = k; } });
		var made = {};
		Object.keys(baseLayers).forEach(function (label) { var k = baseLayers[label]; made[k] = layerFor(PROVIDERS[k]); baseLayers[label] = made[k]; });
		var ovMade = {};
		Object.keys(OVERLAYS).forEach(function (k) { if (allowed(k)) { ovMade[k] = layerFor(OVERLAYS[k]); overlays[OVERLAYS[k].label] = ovMade[k]; } });
		(made[base] || made.osm || layerFor(PROVIDERS.osm)).addTo(map);
		if (firstOverlay && ovMade[firstOverlay]) { ovMade[firstOverlay].addTo(map); }
		if (o.layerctl && (Object.keys(baseLayers).length > 1 || Object.keys(overlays).length)) { L.control.layers(baseLayers, overlays, { collapsed: true }).addTo(map); }

		// Traces
		var all = L.featureGroup().addTo(map), groups = { tracks: [], routes: [], wpts: L.layerGroup().addTo(map) };
		var scale = null, arrowSets = [];
		if (o.trcolmod && ['h', 's', 'v', 'hr', 'cad', 'atemp'].indexOf(o.trcolmod) !== -1) {
			var mn = Infinity, mx = -Infinity;
			inst.tracks.forEach(function (t) { t.pts.forEach(function (p) { var v = p[o.trcolmod]; if (v !== undefined) { if (v < mn) { mn = v; } if (v > mx) { mx = v; } } }); });
			if (mn < mx) { scale = { key: o.trcolmod, min: mn, max: mx }; }
		}

		inst.tracks.forEach(function (t, idx) {
			var layer = L.featureGroup(), latlngs = t.pts.map(function (p) { return [p.lat, p.lon]; }), lines = [];
			if (scale) {
				for (var i = 1; i < t.pts.length; i++) {
					var v = t.pts[i][scale.key], c = v === undefined ? t.color : scaleColor((v - scale.min) / (scale.max - scale.min));
					lines.push(L.polyline([latlngs[i - 1], latlngs[i]], { color: c, weight: o.twidth, opacity: o.topac, lineCap: 'butt' }));
				}
			} else {
				lines.push(L.polyline(latlngs, { color: t.color, weight: o.twidth, opacity: o.topac }));
			}
			lines.forEach(function (ln) {
				ln.addTo(layer);
				if (o.trackover && (t.name || t.file)) { ln.bindTooltip(esc(t.name || t.file), { sticky: true }); }
				if (o.trackclick) {
					ln.on('click', function (e) {
						var p = nearest(t, e.latlng, map);
						if (!p) { return; }
						var html = pointInfo(t, p, o);
						if (html) { L.popup().setLatLng([p.lat, p.lon]).setContent(html).openOn(map); }
					});
				}
			});
			if (o.start) { L.marker(latlngs[0], { icon: icon('gpxtm-start', ''), title: tr('start'), keyboard: false }).bindTooltip(esc(tr('start'))).addTo(layer); }
			if (o.finish) { L.marker(latlngs[latlngs.length - 1], { icon: icon('gpxtm-finish', ''), title: tr('finish'), keyboard: false }).bindTooltip(esc(tr('finish'))).addTo(layer); }
			layer.addTo(all);
			if (o.arrow) { arrowSets.push({ pts: latlngs, color: o.arrowcol || t.color, group: L.featureGroup().addTo(layer) }); }
			t.layer = layer;
			groups.tracks.push(layer);
		});

		inst.routes.forEach(function (r) {
			var rl = r.pts.map(function (p) { return [p.lat, p.lon]; });
			var layer = L.polyline(rl, { color: r.color, weight: o.rwidth, opacity: o.ropac, dashArray: '8 6' }).addTo(all);
			if (o.arrow) { arrowSets.push({ pts: rl, color: o.arrowcol || r.color, group: L.layerGroup().addTo(map) }); }
			if (r.name) { layer.bindTooltip(esc(r.name), { sticky: true }); }
			r.layer = layer;
			groups.routes.push(layer);
		});

		inst.waypoints.forEach(function (w) {
			var m = L.marker([w.lat, w.lon], { icon: wptIcon(w, o.defaulticon), keyboard: false });
			var sw = o.sw, html = '';
			if (sw.shwpname !== false && w.name) { html += '<strong>' + esc(w.name) + '</strong><br>'; }
			if (sw.shwpcmt && w.cmt) { html += esc(w.cmt) + '<br>'; }
			if (sw.shwpdesc && w.desc) { html += esc(w.desc) + '<br>'; }
			if (sw.shwptime && w.time !== undefined) { html += esc(fmtDate(w.time)) + '<br>'; }
			if (sw.shwplatlon) { html += w.lat.toFixed(5) + ', ' + w.lon.toFixed(5); }
			if (html) { m.bindPopup(html); }
			if (sw.shwptooltip && w.name) { m.bindTooltip(esc(w.name)); }
			m.addTo(groups.wpts);
		});

		// Photos géolocalisées (liens <a data-geo="lat:..,lon:..">)
		var imgBox = document.getElementById(el.id + '_img');
		if (imgBox) {
			Array.prototype.forEach.call(imgBox.querySelectorAll('a[data-geo]'), function (a) {
				var m = /lat:\s*(-?[\d.]+)\s*,\s*lon:\s*(-?[\d.]+)/.exec(a.getAttribute('data-geo') || '');
				if (!m) { return; }
				var html = '<a href="' + esc(a.href) + '" target="_blank" rel="noopener"><img src="' + esc(a.href) + '" alt="" style="max-width:' + o.thumb + 'px;height:auto;display:block"></a>' + (a.textContent ? '<div>' + esc(a.textContent) + '</div>' : '');
				L.marker([parseFloat(m[1]), parseFloat(m[2])], { icon: icon('gpxtm-photo', '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="#fff" d="M9 4 7.2 6H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-3.2L15 4zm3 5a4 4 0 1 1 0 8 4 4 0 0 1 0-8z"/></svg>') }).bindPopup(html, { minWidth: 120 }).addTo(groups.wpts);
			});
		}

		// Vue initiale
		function fit() {
			if (o.center && isFinite(o.center[0]) && isFinite(o.center[1])) {
				var r = (o.center[2] || 1) * 1000;
				map.fitBounds(L.latLng(o.center[0], o.center[1]).toBounds(r * 2), { animate: false });
				return;
			}
			var b = all.getBounds();
			if (!b.isValid() && groups.wpts.getLayers().length) { b = L.featureGroup(groups.wpts.getLayers()).getBounds(); }
			if (b.isValid()) { map.fitBounds(b, { padding: [20, 20], animate: false }); } else { map.setView([46.6, 2.4], 5); }
		}
		fit();
		inst.fit = fit;

		// Flèches de direction : une flèche tous les ~90 pixels, recalculée à chaque zoom
		function drawArrows() {
			arrowSets.forEach(function (s) {
				s.group.clearLayers();
				var acc = 0, step = 90, prev = map.latLngToLayerPoint(s.pts[0]);
				for (var i = 1; i < s.pts.length; i++) {
					var cur = map.latLngToLayerPoint(s.pts[i]), dx = cur.x - prev.x, dy = cur.y - prev.y, seg = Math.sqrt(dx * dx + dy * dy);
					if (seg < 1) { continue; }
					acc += seg;
					if (acc >= step) {
						var ang = Math.atan2(dy, dx) * 180 / Math.PI;
						L.marker(s.pts[i], { interactive: false, keyboard: false, icon: L.divIcon({ className: 'gpxtm-arrow', html: '<span style="color:' + esc(s.color) + ';transform:rotate(' + ang.toFixed(0) + 'deg)">' + esc(o.arrowsym) + '</span>', iconSize: [16, 16], iconAnchor: [8, 8] }) }).addTo(s.group);
						acc = 0;
					}
					prev = cur;
				}
			});
		}
		if (arrowSets.length) { map.on('zoomend', drawArrows); drawArrows(); }

		// Boutons
		if (o.fullscreen) { fullscreenControl(map, el); }
		if (o.recenter) {
			buttonControl(map, 'topleft', tr('recenter'), '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="12" cy="12" r="6" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 1v5M12 18v5M1 12h5M18 12h5" stroke="currentColor" stroke-width="2" fill="none"/></svg>', function () { all.getLayers().length || groups.wpts.getLayers().length ? fit() : null; });
		}
		if (o.locate && navigator.geolocation) {
			var here = null;
			buttonControl(map, 'topleft', tr('locate'), '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="currentColor"/><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/></svg>', function () {
				navigator.geolocation.getCurrentPosition(function (pos) {
					var ll = [pos.coords.latitude, pos.coords.longitude];
					if (here) { here.remove(); }
					here = L.circleMarker(ll, { radius: 8, color: '#fff', weight: 2, fillColor: '#1a73e8', fillOpacity: 1 }).addTo(map);
					map.setView(ll, Math.max(map.getZoom(), 14));
				}, function () { /* localisation refusée */ });
			});
		}

		// Légende : liste des traces, routes et waypoints avec cases à cocher
		if (o.legend) { legend(inst, map, groups, o); }

		// Barre de couleurs (variable Trcolmod)
		if (scale) { colourBar(map, scale); }

		// Marqueur de suivi (graphiques)
		var follow = null;
		inst.showAt = function (p, html) {
			if (!follow) { follow = L.circleMarker([p.lat, p.lon], { radius: 7, color: '#fff', weight: 2, fillColor: '#111', fillOpacity: 1, interactive: false }).addTo(map); }
			follow.setLatLng([p.lat, p.lon]);
			follow.unbindTooltip();
			if (html) { follow.bindTooltip(html, { permanent: true, direction: 'top', offset: [0, -8], className: 'gpxtm-follow' }).openTooltip(); }
			if (!map.getBounds().contains([p.lat, p.lon])) { map.panTo([p.lat, p.lon], { animate: false }); }
		};
		inst.hideMarker = function () { if (follow) { follow.remove(); follow = null; } };

		// Graphiques
		if (global.GPXTMCharts) { global.GPXTMCharts.initAll(inst); }

		el.dispatchEvent(new CustomEvent('gpxtm:tracks', { bubbles: true, detail: { tracks: inst.tracks, routes: inst.routes, waypoints: inst.waypoints, map: map, instance: inst, options: o } }));
		setTimeout(function () { map.invalidateSize(); }, 100);
	}

	function legend(inst, map, groups, o) {
		var items = [];
		if (o.legendTrk) { inst.tracks.forEach(function (t, i) { items.push({ label: t.name || t.file || ('#' + (i + 1)), color: t.color, layer: t.layer, info: o.legendInfo ? t : null }); }); }
		if (o.legendRte) { inst.routes.forEach(function (r, i) { items.push({ label: r.name || r.file || ('#' + (i + 1)), color: r.color, layer: r.layer, dashed: true }); }); }
		if (o.legendWpt && inst.waypoints.length) { items.push({ label: tr('waypoints'), color: '#d9534f', layer: groups.wpts }); }
		if (!items.length) { return; }
		var Ctl = L.Control.extend({
			options: { position: 'bottomleft' },
			onAdd: function () {
				var box = L.DomUtil.create('div', 'gpxtm-legend');
				items.forEach(function (it) {
					var lab = L.DomUtil.create('label', '', box), cb = L.DomUtil.create('input', '', lab), sw = L.DomUtil.create('span', 'gpxtm-sw', lab), tx = L.DomUtil.create('span', '', lab);
					cb.type = 'checkbox'; cb.checked = true;
					sw.style.background = it.dashed ? 'repeating-linear-gradient(90deg,' + it.color + ' 0 4px,transparent 4px 7px)' : it.color;
					tx.textContent = it.label;
					if (it.info) {
						var s = it.info.stats, bits = [fmtNum(s.dist, 2) + ' km'];
						if (s.gain !== undefined) { bits.push('+' + Math.round(s.gain) + ' m'); }
						if (s.dur !== undefined) { bits.push(fmtDur(s.dur)); }
						var sm = L.DomUtil.create('small', '', lab); sm.textContent = ' ' + bits.join(' · ');
					}
					cb.addEventListener('change', function () { if (cb.checked) { it.layer.addTo(map); } else { it.layer.remove(); } });
				});
				L.DomEvent.disableClickPropagation(box);
				return box;
			}
		});
		new Ctl().addTo(map);
	}

	function colourBar(map, sc) {
		var labels = { h: tr('alt') + ' (m)', s: tr('slope') + ' (%)', v: tr('speed') + ' (km/h)', hr: tr('hr'), cad: tr('cad'), atemp: tr('temp') + ' (°C)' };
		var Ctl = L.Control.extend({
			options: { position: 'topright' },
			onAdd: function () {
				var box = L.DomUtil.create('div', 'gpxtm-colbar'), st = [], k;
				for (k = 0; k <= 20; k++) { st.push(scaleColor(k / 20) + ' ' + (k * 5) + '%'); }
				box.innerHTML = '<div class="gpxtm-colbar-max">' + fmtNum(sc.max, 0) + '</div><div class="gpxtm-colbar-bar" style="background:linear-gradient(to top,' + st.join(',') + ')"></div><div class="gpxtm-colbar-min">' + fmtNum(sc.min, 0) + '</div><div class="gpxtm-colbar-t">' + esc(labels[sc.key] || '') + '</div>';
				return box;
			}
		});
		new Ctl().addTo(map);
	}

	// ---------------------------------------------------------------------------------------------
	// Démarrage
	// ---------------------------------------------------------------------------------------------

	function start() {
		loadCfg();
		Array.prototype.forEach.call(document.querySelectorAll('.gpxtm-map'), build);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }

	global.GPXTM = { build: build, start: start, parseGpx: parseGpx, computeTrack: computeTrack, scaleColor: scaleColor, fmtDur: fmtDur, fmtDate: fmtDate, fmtNum: fmtNum, tr: tr, esc: esc };
})(window);
