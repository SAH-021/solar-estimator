/* Solar Estimator — interface, Neshan map, boundary drawing, quote and print. Bilingual (fa / en). */
(function () {
  'use strict';

  var Engine = window.SolEstEngine;
  var NL = window.SOLEST_L; // Neshan's Leaflet (kept in SOLEST_L so it does not clash with other plugins)

  var RUN_INPUTS = ['val', 'notes', 'install', 'cfg-ground', 'cfg-roof', 'cfg-usd', 'cfg-satba', 'cp-name', 'cp-w', 'ci-name', 'ci-kw', 'cd-name', 'cd-mm', 'ca-name', 'ca-mm'];

  /* ---------- helpers ---------- */
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ltr(x) { return '<span class="ltr-num">' + esc(x) + '</span>'; }
  /** Digits typed by the user (Persian / Arabic-Indic) → Latin, separators removed */
  function latin(s) {
    return String(s).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }).replace(/[٬,،\s]/g, '').replace(/٫/g, '.');
  }
  function mean(a) { var s = 0, n = 0; (a || []).forEach(function (v) { if (typeof v === 'number' && isFinite(v)) { s += v; n++; } }); return n ? s / n : NaN; }

  /** Polygon area (m²) using a local flat projection */
  function polygonArea(pts) {
    if (pts.length < 3) return 0;
    var R = 6378137, rad = Math.PI / 180, lat0 = 0, lng0 = 0, i;
    for (i = 0; i < pts.length; i++) { lat0 += pts[i].lat; lng0 += pts[i].lng; }
    lat0 /= pts.length; lng0 /= pts.length;
    var k = Math.cos(lat0 * rad), xy = pts.map(function (p) { return [(p.lng - lng0) * rad * R * k, (p.lat - lat0) * rad * R]; }), a = 0;
    for (i = 0; i < xy.length; i++) { var p = xy[i], q = xy[(i + 1) % xy.length]; a += p[0] * q[1] - q[0] * p[1]; }
    return Math.round(Math.abs(a) / 2);
  }

  var climCache = {};
  function getClimate(lat, lng, source) {
    var key = source + lat.toFixed(1) + ',' + lng.toFixed(1);
    if (climCache[key]) return Promise.resolve(climCache[key]);
    var def = { ghi: Engine.baseGhi(lat, lng), temp: Engine.AUTO_DEFAULT.temp, wind: Engine.AUTO_DEFAULT.wind, live: false };
    var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = setTimeout(function () { if (ctrl) ctrl.abort(); }, 7000);
    var ll = 'latitude=' + lat.toFixed(4) + '&longitude=' + lng.toFixed(4), y = new Date().getFullYear() - 1;
    var url = source === 'annual'
      ? 'https://archive-api.open-meteo.com/v1/archive?' + ll + '&start_date=' + y + '-01-01&end_date=' + y + '-12-31&daily=temperature_2m_mean,wind_speed_10m_mean&wind_speed_unit=ms&timezone=auto'
      : 'https://api.open-meteo.com/v1/forecast?' + ll + '&current=temperature_2m,wind_speed_10m&timezone=auto';
    return fetch(url, ctrl ? { signal: ctrl.signal } : {})
      .then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); })
      .then(function (d) {
        var temp, wind;
        if (source === 'annual') { temp = mean(d.daily.temperature_2m_mean); wind = mean(d.daily.wind_speed_10m_mean); }
        else { temp = d.current.temperature_2m; wind = d.current.wind_speed_10m / 3.6; }
        if (!isFinite(temp) || !isFinite(wind)) throw new Error('nan');
        return (climCache[key] = { ghi: def.ghi, temp: Math.round(temp * 10) / 10, wind: Math.round(wind * 10) / 10, live: true });
      })
      .catch(function () { return def; })
      .then(function (v) { clearTimeout(timer); return v; });
  }

  /* ---------- app ---------- */
  function App(root) {
    this.root = root;
    this.uid = root.getAttribute('data-uid');
    this.C = (window.SOLEST_CFG || {})[this.uid];
    if (!this.C) return;
    this.isFa = this.C.ui.lang === 'fa';
    this.rtl = !!this.C.ui.rtl;
    var c = this.C.map.center;
    this.s = { lat: c[0], lng: c[1], clim: { ghi: 1980, wind: 4.2, temp: 24 }, sel: 'auto', drawing: false, pts: [], last: null, lead: null, satIdx: 0, token: 0 };
    this.vmarks = [];
    this.bind();
    this.fillSelects();
    this.initMap();
    this.run();
    this.weather();
  }

  /* ----- language helpers ----- */
  /** plain text: t('key', {name: value}) */
  App.prototype.t = function (k, v) {
    var s = (this.C.i18n && this.C.i18n[k] !== undefined) ? this.C.i18n[k] : k;
    return v ? s.replace(/\{(\w+)\}/g, function (m, n) { return n in v ? v[n] : m; }) : s;
  };
  /** HTML: the template and every value are escaped, except the keys listed in raw (already HTML) */
  App.prototype.th = function (k, v, raw) {
    var s = esc(this.t(k));
    return s.replace(/\{(\w+)\}/g, function (m, n) {
      if (!v || !(n in v)) return m;
      return raw && raw.indexOf(n) > -1 ? v[n] : esc(v[n]);
    });
  };
  /** number in the interface language (Persian digits for fa) */
  App.prototype.n = function (x) { return Number(x).toLocaleString(this.isFa ? 'fa-IR' : 'en-US'); };
  /** a number that was already formatted with a Latin decimal point */
  App.prototype.d = function (x) {
    return this.isFa ? String(x).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }).replace('.', '٫') : String(x);
  };
  App.prototype.money = function (m) {
    return m >= 1000 ? this.n((m / 1000).toFixed(2)) + ' ' + this.t('billion_toman') : this.n(Math.round(m)) + ' ' + this.t('million_toman');
  };
  App.prototype.usd = function (x) { return '$' + Math.round(x).toLocaleString('en-US'); };
  /** the "why" sentence returned by the engine: {k, v} with the name in bold */
  App.prototype.reason = function (r) {
    var v = {};
    for (var p in r.v) if (Object.prototype.hasOwnProperty.call(r.v, p)) v[p] = r.v[p];
    v.name = '<strong>' + esc(v.name) + '</strong>';
    return this.th(r.k, v, ['name']);
  };

  App.prototype.el = function (n) { return this.root.querySelector('[data-el="' + n + '"]'); };
  App.prototype.api = function (t) { this.el('api').textContent = t; };

  App.prototype.bind = function () {
    var self = this, root = this.root;
    root.addEventListener('click', function (e) {
      var b = e.target.closest('[data-act]');
      if (!b || !root.contains(b)) return;
      var fn = self.acts[b.getAttribute('data-act')];
      if (fn) fn.call(self, b, e);
    });
    root.addEventListener('input', function (e) {
      var n = e.target.getAttribute('data-el');
      if (RUN_INPUTS.indexOf(n) > -1) self.run();
    });
    root.addEventListener('change', function (e) {
      var t = e.target, n = t.getAttribute('data-el');
      if (t.hasAttribute('data-sel')) self.onSel(t.getAttribute('data-sel'));
      else if (n === 'mode') self.toggleMode();
      else if (RUN_INPUTS.indexOf(n) > -1) self.run();
    });
    this.el('q').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); self.searchPlace(); } });
    this.el('leadform').addEventListener('submit', function (e) { e.preventDefault(); self.submitLead(); });
    this.el('lead').addEventListener('click', function (e) { if (e.target === self.el('lead')) self.closeLead(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !self.el('lead').hidden) self.closeLead(); });
  };

  App.prototype.acts = {
    search: function () { this.searchPlace(); },
    locate: function () { this.locateMe(); },
    street: function () { this.switchMap('street'); },
    sat: function () { this.switchMap('sat'); },
    nextsat: function () { this.nextSat(); },
    draw: function () { this.toggleDraw(); },
    clearpoly: function () { this.clearPoly(); },
    selauto: function () { this.setSel('auto'); },
    selmanual: function () { this.setSel('manual'); },
    openlead: function (b) { this.lastTrigger = b; this.openLead(); },
    closelead: function () { this.closeLead(); },
    print: function () { this.print(); },
    saveprices: function () { this.savePrices(); }
  };

  /** URL of a plugin REST route; the language is sent so server messages match the interface */
  App.prototype.url = function (route, query) {
    return this.C.rest + route + (this.C.rest.indexOf('?') > -1 ? '&' : '?') + (query ? query + '&' : '') + 'lang=' + this.C.ui.lang;
  };

  /* ----- form ----- */
  App.prototype.fillSelects = function () {
    var E = this.C.engine, custom = '<option value="custom">' + esc(this.t('opt_custom')) + '</option>', auto = '<option value="auto">' + esc(this.t('opt_auto')) + '</option>';
    var list = function (a, f) { return a.map(function (x, i) { return '<option value="' + i + '">' + esc(f(x)) + '</option>'; }).join(''); };
    this.el('m-panel').innerHTML = list(E.panels, function (p) { return p.name; }) + custom;
    this.el('m-inv').innerHTML = list(E.inverters, function (p) { return p.name; }) + custom;
    this.el('m-dc').innerHTML = auto + list(E.dc, function (c) { return c.label; }) + custom;
    this.el('m-ac').innerHTML = auto + list(E.ac, function (c) { return c.label; }) + custom;
  };

  App.prototype.onSel = function (k) {
    var ids = { panel: 'm-panel', inv: 'm-inv', dc: 'm-dc', ac: 'm-ac' };
    this.el('c-' + k).hidden = this.el(ids[k]).value !== 'custom';
    this.run();
  };

  App.prototype.setSel = function (m) {
    this.s.sel = m;
    this.el('s-auto').classList.toggle('on', m === 'auto');
    this.el('s-manual').classList.toggle('on', m === 'manual');
    this.el('manual').hidden = m !== 'manual';
    this.el('sel-hint').textContent = this.t(m === 'auto' ? 'hint_auto' : 'hint_manual');
    this.run();
  };

  App.prototype.toggleMode = function () {
    this.el('lbl').textContent = this.t(this.el('mode').value === 'area' ? 'lbl_area' : 'lbl_power');
    this.run();
  };

  /* ----- Neshan map ----- */
  App.prototype.noMap = function (html) {
    this.map = null;
    var n = this.el('mapnote'); n.innerHTML = html; n.hidden = false; this.el('map').hidden = true;
    ['street', 'sat', 'nextsat', 'draw', 'clearpoly'].forEach(function (a) { var b = this.root.querySelector('[data-act="' + a + '"]'); if (b) b.hidden = true; }, this);
  };

  App.prototype.initMap = function () {
    var m = this.C.map, self = this, s = this.s;
    if (!m.key) {
      this.noMap(m.canConfigure
        ? '<strong>' + esc(this.t('map_nokey_title')) + '</strong><br>' + this.th('map_nokey_body', { link: '<a href="' + esc(m.settingsUrl) + '">' + esc(this.t('map_settings_link')) + '</a>' }, ['link'])
        : esc(this.t('map_unavailable')));
      return;
    }
    if (!NL || !NL.Map) { this.noMap(esc(this.t('map_load_failed'))); return; }
    try {
      this.map = new NL.Map(this.el('map'), { key: m.key, maptype: m.type, poi: true, traffic: false, center: m.center, zoom: m.zoom });
    } catch (err) { this.noMap(esc(this.t('map_init_failed'))); return; }
    this.accent = getComputedStyle(this.root).getPropertyValue('--sun').trim() || '#f0a500';
    this.map.createPane('solestSat').style.zIndex = 250; // above Neshan's layers (which sit in tilePane with a negative z)
    this.pinIcon = NL.divIcon({ className: 'sd-pin-wrap', html: '<span class="sd-pin"></span>', iconSize: [30, 38], iconAnchor: [15, 36] });
    this.vtxIcon = NL.divIcon({ className: 'sd-vtx-wrap', html: '<span class="sd-vtx"></span>', iconSize: [18, 18], iconAnchor: [9, 9] });
    this.marker = NL.marker([s.lat, s.lng], { draggable: true, icon: this.pinIcon, keyboard: true, title: this.t('pin_title') }).addTo(this.map);
    this.marker.on('dragend', function () { var p = self.marker.getLatLng(); self.setPos(p.lat, p.lng, true); });
    this.map.on('click', function (e) {
      if (s.drawing) self.addVertex(e.latlng); else self.setPos(e.latlng.lat, e.latlng.lng);
    });
    if (m.sats && m.sats.length) this.buildSat();
    else ['sat', 'nextsat'].forEach(function (a) { this.root.querySelector('[data-act="' + a + '"]').hidden = true; }, this);
    setTimeout(function () { if (self.map) self.map.invalidateSize(); }, 400);
    window.addEventListener('resize', function () { if (self.map) self.map.invalidateSize(); });
  };

  App.prototype.buildSat = function () {
    var self = this, src = this.C.map.sats[this.s.satIdx];
    if (!src) return;
    var o = { pane: 'solestSat', maxZoom: 19, maxNativeZoom: src.maxZoom, attribution: '' };
    if (src.sub) o.subdomains = src.sub;
    this.sat = NL.tileLayer(src.url, o);
    this.satOk = 0; this.satErr = 0;
    this.sat.on('tileload', function () { self.satOk++; });
    this.sat.on('tileerror', function () {
      self.satErr++;
      if (self.satOk === 0 && self.satErr === 4 && self.map && self.map.hasLayer(self.sat)) {
        var sats = self.C.map.sats;
        if (self.s.satIdx < sats.length - 1) {
          self.map.removeLayer(self.sat); self.s.satIdx++; self.buildSat(); self.map.addLayer(self.sat);
          self.api(self.t('sat_switched', { name: sats[self.s.satIdx].name })); self.attribText();
        } else self.api(self.t('sat_blocked'));
      }
    });
  };

  App.prototype.attribText = function () {
    if (!this.attrib) { this.attrib = document.createElement('div'); this.attrib.className = 'sd-attrib'; this.el('map').parentNode.appendChild(this.attrib); }
    this.attrib.textContent = this.t('sat_credit', { name: this.C.map.sats[this.s.satIdx].name });
    this.attrib.hidden = false;
  };

  App.prototype.nextSat = function () {
    var sats = this.C.map.sats;
    if (!this.map || !sats.length) return;
    if (this.map.hasLayer(this.sat)) this.map.removeLayer(this.sat);
    this.s.satIdx = (this.s.satIdx + 1) % sats.length;
    this.buildSat(); this.map.addLayer(this.sat);
    this.el('b-sat').classList.add('on'); this.el('b-street').classList.remove('on');
    this.attribText();
    this.api(this.t('sat_source', { name: sats[this.s.satIdx].name }));
  };

  App.prototype.switchMap = function (t) {
    this.el('b-sat').classList.toggle('on', t === 'sat');
    this.el('b-street').classList.toggle('on', t !== 'sat');
    if (!this.map || !this.sat) return;
    if (t === 'sat') { this.map.addLayer(this.sat); this.attribText(); }
    else { this.map.removeLayer(this.sat); if (this.attrib) this.attrib.hidden = true; }
  };

  App.prototype.setPos = function (a, b, fromMarker) {
    this.s.lat = a; this.s.lng = b;
    if (this.marker && !fromMarker) this.marker.setLatLng([a, b]);
    this.weather();
  };

  App.prototype.weather = function () {
    var s = this.s, self = this, t = ++s.token;
    this.el('coord').textContent = this.t('coord', { lat: this.d(s.lat.toFixed(3)), lng: this.d(s.lng.toFixed(3)) });
    this.api(this.t('st_climate_fetch'));
    getClimate(s.lat, s.lng, this.C.climateSource).then(function (c) {
      if (t !== s.token) return;
      s.clim = { ghi: c.ghi, wind: c.wind, temp: c.temp };
      self.api(self.t(c.live ? 'st_climate_ok' : 'st_atlas'));
      self.run();
    });
  };

  App.prototype.locateMe = function () {
    var self = this;
    if (!navigator.geolocation) { this.api(this.t('geo_unsupported')); return; }
    navigator.geolocation.getCurrentPosition(function (p) {
      if (self.map) self.map.setView([p.coords.latitude, p.coords.longitude], 16);
      self.setPos(p.coords.latitude, p.coords.longitude);
    }, function () { self.api(self.t('geo_denied')); }, { enableHighAccuracy: true, timeout: 10000 });
  };

  /* Search: Neshan first (through the server, when a service key is set), otherwise / when empty OpenStreetMap */
  App.prototype.searchPlace = function () {
    var self = this, q = this.el('q').value.trim();
    if (!q) return;
    this.api(this.t('st_searching'));
    var c = this.map ? this.map.getCenter() : { lat: this.s.lat, lng: this.s.lng };
    var viaNeshan = this.C.map.hasService
      ? fetch(this.url('search', 'q=' + encodeURIComponent(q) + '&lat=' + c.lat.toFixed(3) + '&lng=' + c.lng.toFixed(3)))
          .then(function (r) { return r.ok ? r.json() : []; }).catch(function () { return []; })
      : Promise.resolve([]);
    viaNeshan.then(function (list) {
      if (list && list.length) return { lat: +list[0].lat, lng: +list[0].lng };
      return fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (d) { return d.length ? { lat: +d[0].lat, lng: +d[0].lon } : null; });
    }).then(function (pt) {
      if (!pt) { self.api(self.t('st_not_found')); return; }
      if (self.map) self.map.setView([pt.lat, pt.lng], 15);
      self.setPos(pt.lat, pt.lng);
    }).catch(function () { self.api(self.t('st_search_failed')); });
  };

  /* ----- boundary drawing ----- */
  App.prototype.toggleDraw = function () {
    var s = this.s, b = this.el('b-draw');
    if (!this.map) return;
    s.drawing = !s.drawing;
    b.classList.toggle('rec', s.drawing);
    this.root.classList.toggle('is-drawing', s.drawing);
    b.textContent = this.t(s.drawing ? 'draw_stop' : 'draw_start');
    if (s.drawing) {
      if (this.sat) this.switchMap('sat');
      this.clearPoly();
      this.map.doubleClickZoom.disable();
      this.el('map').parentNode.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
      this.map.doubleClickZoom.enable();
      if (s.pts.length >= 3) {
        var lat = 0, lng = 0;
        s.pts.forEach(function (p) { lat += p.lat; lng += p.lng; });
        this.map.fitBounds(this.poly.getBounds(), { padding: [40, 40], maxZoom: 18 });
        this.setPos(lat / s.pts.length, lng / s.pts.length);
      }
    }
  };

  App.prototype.addVertex = function (ll) {
    var self = this, s = this.s;
    var mk = NL.marker(ll, { draggable: true, icon: this.vtxIcon, zIndexOffset: 800, keyboard: false }).addTo(this.map);
    mk.on('drag', function () { s.pts[self.vmarks.indexOf(mk)] = mk.getLatLng(); self.redraw(); });
    this.vmarks.push(mk);
    s.pts.push(ll);
    this.redraw();
    if (s.pts.length === 1) this.setPos(ll.lat, ll.lng);
  };

  App.prototype.redraw = function () {
    var s = this.s;
    if (!this.poly) this.poly = NL.polygon(s.pts, { color: this.accent, weight: 2.5, fillColor: this.accent, fillOpacity: 0.35, interactive: false }).addTo(this.map);
    else this.poly.setLatLngs(s.pts);
    if (s.pts.length >= 3) {
      var a = polygonArea(s.pts);
      this.el('mode').value = 'area';
      this.el('lbl').textContent = this.t('lbl_area');
      this.el('val').value = a;
      this.el('map-hint').textContent = this.t('area_hint', { area: this.n(a) });
      this.run();
    }
  };

  App.prototype.clearPoly = function () {
    var self = this;
    if (this.map) { if (this.poly) this.map.removeLayer(this.poly); this.vmarks.forEach(function (m) { self.map.removeLayer(m); }); }
    this.s.pts = []; this.poly = null; this.vmarks = [];
  };

  /* ----- calculation and display ----- */
  App.prototype.manualInput = function () {
    var g = function (n) { return this.el(n).value; }.bind(this);
    return { panel: g('m-panel'), cpName: g('cp-name'), cpW: latin(g('cp-w')), inv: g('m-inv'), ciName: g('ci-name'), ciKw: latin(g('ci-kw')), dc: g('m-dc'), cdName: g('cd-name'), cdMm: latin(g('cd-mm')), ac: g('m-ac'), caName: g('ca-name'), caMm: latin(g('ca-mm')) };
  };

  App.prototype.run = function () {
    var s = this.s, C = this.C, msg = this.el('valmsg'), self = this;
    var val = parseFloat(latin(this.el('val').value));
    if (!(val > 0)) { msg.textContent = this.t('val_invalid'); msg.hidden = false; return; }
    msg.hidden = true;

    var price = { ground: C.engine.price.ground, roof: C.engine.price.roof, usdRate: C.engine.price.usdRate, satba: C.engine.price.satba, greenMult: C.engine.price.greenMult };
    if (C.isAdmin) {
      price.ground = parseFloat(this.el('cfg-ground').value) || price.ground;
      price.roof = parseFloat(this.el('cfg-roof').value) || price.roof;
      price.usdRate = parseFloat(this.el('cfg-usd').value) || price.usdRate;
      price.satba = parseFloat(this.el('cfg-satba').value) || price.satba;
    }
    var cfg = {
      price: price, areaPerKw: C.engine.areaPerKw, genFactor: C.engine.genFactor, panels: C.engine.panels, inverters: C.engine.inverters, dc: C.engine.dc, ac: C.engine.ac,
      t: function (k, v) { return self.t(k, v); }, digits: this.isFa ? 'fa' : 'en'
    };
    var type = this.el('install').value, mode = this.el('mode').value;
    var r = Engine.calculate({ lat: s.lat, climate: s.clim, installType: type, mode: mode, value: val, sel: s.sel, manual: this.manualInput() }, cfg);

    if (s.sel === 'auto') {
      this.el('m-panel').value = C.engine.panels.indexOf(r.panel);
      this.el('m-inv').value = C.engine.inverters.indexOf(r.inv);
      this.el('c-panel').hidden = this.el('c-inv').hidden = true;
    }
    var cReason = s.sel === 'manual'
      ? this.th('why_cables_manual', { dc: ltr(r.dc.label), ac: ltr(r.ac.label) }, ['dc', 'ac'])
      : this.th('why_cables_auto', { dc: ltr(r.dc.row.label), ac: ltr(r.ac.row.label) }, ['dc', 'ac']);
    this.el('why').innerHTML = '<li>' + this.reason(r.pReason) + '</li><li>' + this.reason(r.iReason) + '</li><li>' + cReason + '</li>';
    this.el('sp-panel').textContent = r.panelCustom ? '' : r.panel.pmax + 'W | Vmp ' + r.panel.vmp + 'V | Voc ' + r.panel.voc + 'V | Isc ' + r.panel.isc + 'A';
    this.el('sp-inv').textContent = this.el('m-inv').value === 'custom' ? '' : this.t('spec_inv', { kw: r.inv.pac, v: r.inv.vmax, eff: r.inv.eff });

    var trans = r.trans.needed ? this.th('trans_yes', { kva: ltr(r.trans.kva + ' kVA') }, ['kva']) : esc(this.t('trans_no'));
    var $ = this.el.bind(this), set = function (n, h) { $(n).innerHTML = h; };
    var th = function (k, v, raw) { return self.th(k, v, raw); };
    var inline = function (ok, limitPct) {
      return ok ? '<span class="ok">' + esc(self.t('ok')) + '</span>'
        : '<span class="bad">' + esc(self.t('bad', { max: self.t('max_pct', { pct: self.d(limitPct) }) })) + '</span>';
    };
    set('h-cap', ltr(r.dcKwp.toFixed(1) + ' kWp') + '<span style="font-size:.85rem;font-weight:400;display:block">' + th('inverter_label', { kw: ltr(r.acKw + ' kW') }, ['kw']) + '</span>');
    set('h-gen', th('annual_gen', { mwh: this.n(r.genMwh) }));
    set('h-capex', esc(this.money(r.capexM)));
    set('h-usd', th('usd_note', { usd: ltr(this.usd(r.capexUsd)), rate: r.usd.toLocaleString(this.isFa ? 'fa-IR' : 'en-US') }, ['usd']));
    set('h-roi', th('roi_range', { min: this.n((r.capexM / r.revG).toFixed(1)), max: this.n((r.capexM / r.revS).toFixed(1)) }));
    set('h-tilt', th('tilt_val', { n: this.n(r.tilt) }));
    set('h-ghi', ltr(s.clim.ghi + ' kWh/m²'));
    set('h-pr', ltr((r.pr * 100).toFixed(1) + '%'));
    set('o-panel', th('panel_line', { n: this.n(r.nPanels), w: ltr(r.panel.pmax + 'W') }, ['w']));
    set('o-str', th('string_line', { n: this.n(r.nStr), ser: this.n(r.ser), v: ltr(r.stVmp + ' V') }, ['v']));
    set('o-inv', th('inv_line', { n: this.n(r.nInv), kw: ltr(r.inv.pac + ' kW') }, ['kw']));
    set('o-invi', th('inv_sub', { brand: ltr(r.inv.brand), eff: ltr(r.inv.eff + '%') }, ['brand', 'eff']));
    set('o-trans', trans);
    set('o-brk', th('brk_line', { a: ltr(r.plantA + ' A'), b: ltr('MCCB ' + r.mainBreakerA + 'A') }, ['a', 'b']));
    set('o-ratio', ltr(r.ratio));
    set('o-dc', ltr(r.dc.label));
    set('o-dcd', th('dc_detail', { drop: ltr(r.dc.v.toFixed(2) + ' V (' + r.dc.pct.toFixed(2) + '%)'), status: inline(r.dc.ok, '1.35'), m: this.n(r.dc.meters), len: this.n(r.dc.len) }, ['drop', 'status']));
    set('o-ac', ltr(r.ac.label));
    set('o-acd', th('ac_detail', { a: ltr(r.ac.currentA.toFixed(1) + ' A'), drop: ltr(r.ac.v.toFixed(2) + ' V (' + r.ac.pct.toFixed(2) + '%)'), status: inline(r.ac.ok, '1.5'), m: this.n(r.ac.meters) }, ['a', 'drop', 'status']));
    set('o-satba', esc(this.money(r.revS)));
    set('o-green', esc(this.money(r.revG)));

    s.last = { r: r, type: type, mode: mode, value: val, trans: trans, notes: this.el('notes').value.trim(), manual: s.sel === 'manual' };
    if (!this.el('bom').hidden) this.renderBom(false);
  };

  /* ----- quote ----- */
  App.prototype.openLead = function () {
    if (!this.s.last) return;
    if (this.C.leadRequired && !this.s.lead) {
      this.leadAt = Date.now();
      this.el('formerr').hidden = true;
      this.el('lead').hidden = false;
      document.documentElement.classList.add('sd-lock');
      this.root.querySelector('input[name="name"]').focus();
    } else this.renderBom(true);
  };

  App.prototype.closeLead = function () {
    this.el('lead').hidden = true;
    document.documentElement.classList.remove('sd-lock');
    if (this.lastTrigger) this.lastTrigger.focus();
  };

  App.prototype.payload = function () {
    var L = this.s.last, r = L.r, s = this.s, rd = function (v, d) { return Math.round(v * Math.pow(10, d)) / Math.pow(10, d); };
    return {
      lat: rd(s.lat, 5), lng: rd(s.lng, 5), installType: L.type, mode: L.mode, value: L.value, areaM2: Math.round(r.effArea),
      dcKwp: rd(r.dcKwp, 1), acKw: r.acKw, panels: r.nPanels, panelName: r.panel.name, inverters: r.nInv, invName: r.inv.name,
      capexUsd: Math.round(r.capexUsd), capexMillion: Math.round(r.capexM), genMwh: rd(parseFloat(r.genMwh), 1),
      paybackMin: rd(r.capexM / r.revG, 1), paybackMax: rd(r.capexM / r.revS, 1), notes: L.notes
    };
  };

  App.prototype.submitLead = function () {
    var self = this, f = this.el('leadform'), E = f.elements, err = this.el('formerr'), btn = this.el('submit');
    var body = {
      name: E.namedItem('name').value.trim(), company: E.namedItem('company').value.trim(), phone: latin(E.namedItem('phone').value),
      website: E.namedItem('website').value, elapsed: Date.now() - (this.leadAt || Date.now()), estimate: this.payload()
    };
    var fail = function (m) { err.textContent = m; err.hidden = false; btn.disabled = false; btn.textContent = self.t('lead_submit'); };
    if (body.name.length < 2) { fail(this.t('lead_name_short')); E.namedItem('name').focus(); return; }
    if (!/^(\+?98|0)?9\d{9}$/.test(body.phone)) { fail(this.t('lead_phone_bad')); E.namedItem('phone').focus(); return; }
    err.hidden = true; btn.disabled = true; btn.textContent = this.t('lead_saving');
    fetch(this.url('lead'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (x) {
        if (!x.ok) { fail((x.j && x.j.message) || self.t('lead_save_failed')); return; }
        self.s.lead = { name: body.name, company: body.company, phone: body.phone };
        btn.disabled = false; btn.textContent = self.t('lead_submit');
        self.closeLead();
        self.renderBom(true);
      })
      .catch(function () { fail(self.t('lead_net_failed')); });
  };

  /** Table rows of the quote. Cells are HTML: every value is escaped here. */
  App.prototype.rows = function () {
    var L = this.s.last, r = L.r, self = this;
    var rows = [
      [this.t('bom_panel'), this.th('bom_panel_qty', { n: this.n(r.nPanels), name: r.panel.name })],
      [this.t('bom_inv'), this.th('bom_inv_qty', { n: this.n(r.nInv), name: r.inv.name })],
      [this.t('bom_struct'), this.th('bom_struct_val', { kwp: this.d(r.dcKwp.toFixed(1)) })],
      [this.t('bom_earth'), this.th('bom_earth_val')],
      [this.t('bom_eng'), this.th('bom_eng_val')]
    ];
    if (L.notes) rows.push([this.t('bom_notes'), esc(L.notes)]);
    return rows.map(function (x) { return [esc(x[0]), x[1]]; });
  };

  /** The quote sheet (HTML), used both on the page and for printing */
  App.prototype.sheetHtml = function () {
    var D = this.C.doc, L = this.s.last, r = L.r, lead = this.s.lead, self = this;
    var page = D.lhMode === 'page' && D.lhHeader;
    var date = new Date().toLocaleDateString(this.isFa ? 'fa-IR' : 'en-US');
    var client = lead
      ? this.th('client_line', { name: lead.name, company: lead.company || this.t('not_given'), phone: lead.phone, date: date })
      : this.th('date_only', { date: date });
    var top = '';
    if (D.lhHeader) top = page ? '<img class="lh-bg" src="' + esc(D.lhHeader) + '" alt="">' : '<img class="lh-top" src="' + esc(D.lhHeader) + '" alt="' + esc(this.t('letterhead')) + '">';
    var head = D.lhHeader ? '' : '<div class="head"><strong>' + esc(D.company || '') + '</strong>' + (D.phone ? ' — ' + this.th('phone_line', { phone: ltr(D.phone) }, ['phone']) : '') + '</div>';
    var pad = page ? ' style="padding-top:' + (D.lhTop / 210 * 100).toFixed(3) + '%;padding-bottom:' + (D.lhBottom / 210 * 100).toFixed(3) + '%"' : '';
    var rows = this.rows().map(function (x, i) { return '<tr><td>' + self.n(i + 1) + '</td><td><strong>' + x[0] + '</strong></td><td>' + x[1] + '</td></tr>'; }).join('');
    var total = D.showPrice ? '<p class="total">' + this.th('total_line', { cost: this.money(r.capexM), usd: ltr(this.usd(r.capexUsd)) }, ['usd']) + '</p>' : '';
    var disc = D.disclaimer ? '<p class="disc">' + esc(D.disclaimer) + '</p>' : '';
    var foot = (!page && D.lhFooter) ? '<img class="lh-foot" src="' + esc(D.lhFooter) + '" alt="">' : '';
    return '<div class="sd-sheet' + (page ? ' is-page' : '') + '" dir="' + (this.rtl ? 'rtl' : 'ltr') + '" lang="' + this.C.ui.lang + '" style="--sk:' + esc(D.ink) + ';--sa:' + esc(D.accent) + '">' + top +
      '<div class="body"' + pad + '>' + head + '<h3>' + esc(this.t('sheet_title')) + '</h3><p class="client">' + client + '</p>' +
      '<table><thead><tr><th>' + esc(this.t('th_no')) + '</th><th>' + esc(this.t('th_desc')) + '</th><th>' + esc(this.t('th_spec')) + '</th></tr></thead><tbody>' + rows + '</tbody></table>' + total + disc + '</div>' + foot + '</div>';
  };

  App.prototype.renderBom = function (scroll) {
    if (!this.s.last) return;
    this.el('sheet').innerHTML = this.sheetHtml();
    this.el('bom').hidden = false;
    if (scroll) this.el('bom').scrollIntoView({ behavior: 'smooth' });
  };

  /** Print from a hidden iframe: independent of the site theme and not blocked by pop-up blockers */
  App.prototype.print = function () {
    if (!this.s.last) return;
    var D = this.C.doc, page = D.lhMode === 'page' && D.lhHeader;
    var css = '<style>html,body{margin:0;background:#fff}*{-webkit-print-color-adjust:exact;print-color-adjust:exact}.sd-sheet{border:0;max-width:none;overflow:visible;' +
      (page ? 'width:210mm;aspect-ratio:auto;min-height:297mm;' : '') + '}@page{size:A4;margin:' + (page ? '0' : '10mm') + '}</style>';
    var font = this.root.classList.contains('sd-vazir') ? '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">' : '';
    var html = '<!doctype html><html lang="' + this.C.ui.lang + '" dir="' + (this.rtl ? 'rtl' : 'ltr') + '"><head><meta charset="utf-8"><title>' + esc(this.t('quote_doc_title')) + '</title>' + font + '<link rel="stylesheet" href="' + esc(D.sheetCss) + '">' + css + '</head><body>' + this.sheetHtml() + '</body></html>';
    var f = document.createElement('iframe');
    f.setAttribute('aria-hidden', 'true');
    f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
    f.onload = function () {
      var d = f.contentDocument, ready = d && d.fonts && d.fonts.ready ? d.fonts.ready : Promise.resolve();
      ready.then(function () {
        setTimeout(function () { try { f.contentWindow.focus(); f.contentWindow.print(); } catch (e) { /* the browser refused to print */ } setTimeout(function () { if (f.parentNode) f.parentNode.removeChild(f); }, 60000); }, 200);
      });
    };
    document.body.appendChild(f);
    f.srcdoc = html;
  };

  /* ----- daily prices (site admin only) ----- */
  App.prototype.savePrices = function () {
    var self = this, C = this.C, msg = this.el('pricemsg');
    var body = { ground: parseFloat(this.el('cfg-ground').value), roof: parseFloat(this.el('cfg-roof').value), usd: parseFloat(this.el('cfg-usd').value), satba: parseFloat(this.el('cfg-satba').value) };
    msg.textContent = this.t('saving');
    fetch(this.url('prices'), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (x) {
        if (!x.ok) { msg.textContent = (x.j && x.j.message) || self.t('save_failed'); return; }
        C.engine.price.ground = body.ground; C.engine.price.roof = body.roof; C.engine.price.usdRate = body.usd; C.engine.price.satba = body.satba;
        msg.textContent = self.t('saved'); self.run();
      }).catch(function () { msg.textContent = self.t('net_failed'); });
  };

  /* ---------- boot ---------- */
  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('.sd-app'), function (el) { if (!el.__sd) el.__sd = new App(el); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
  window.SolEstApp = App;
})();
