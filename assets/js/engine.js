/*
 * Solar Estimator calculation engine — no DOM dependency.
 * Prices and the area-per-kW factors come from the site settings; all texts come from cfg.t (translations).
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.SolEstEngine = factory();
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  /** Digits (and decimal separator) for numbers placed inside the "why" sentences */
  function faDigits(n) { return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }).replace('.', '٫'); }
  function latinDigits(n) { return String(n); }

  var STD_TRANS = [100, 160, 200, 250, 315, 400, 500, 630, 800, 1000, 1250, 1600, 2000, 2500];

  /** Annual horizontal irradiation (kWh/m²): lower on the Caspian coast; elsewhere it decreases with latitude */
  function baseGhi(lat, lng) {
    var cas = lat > 36.2 && lat < 38 && lng > 48.8 && lng < 54.5;
    var g = cas ? 1380 : Math.round(2250 - (lat - 25) * 28);
    return Math.max(1350, Math.min(2220, g));
  }

  /** Cable choice: manual (index), custom (nearest table size) or automatic */
  function pickCable(tbl, v, customMm, reqAmp, lim, pctFn, skipCu) {
    var row = null, custom = false;
    if (v === 'custom') {
      var mm = parseFloat(customMm) || tbl[1].mm;
      row = tbl.filter(function (c) { return c.mm >= mm; })[0] || tbl[tbl.length - 1];
      custom = true;
    } else if (v !== 'auto' && v !== undefined && v !== null) row = tbl[+v];
    if (!row) {
      row = tbl.filter(function (c) { return c.amp >= reqAmp && pctFn(c) <= lim && !(skipCu && c.type === 'Cu'); })[0] || tbl[tbl.length - 1];
    }
    var pct = pctFn(row);
    return { row: row, pct: pct, custom: custom, ok: row.amp >= reqAmp && pct <= lim };
  }

  /**
   * input: {lat, climate:{ghi,wind,temp}, installType, mode, value, sel:'auto'|'manual',
   *         manual:{panel,cpName,cpW, inv,ciName,ciKw, dc,cdName,cdMm, ac,caName,caMm}}
   * cfg:   {price:{ground,roof,usdRate,satba,greenMult}, areaPerKw:{ground,roof}, genFactor, panels, inverters, dc, ac,
   *         t: function (key, vars) → text, digits: 'fa' | 'en'}
   * The "why" sentences are returned as {k: key, v: vars} so the UI can translate and format them.
   */
  function calculate(input, cfg) {
    var T = cfg.t || function (k) { return k; };
    var fd = cfg.digits === 'fa' ? faDigits : latinDigits;
    var type = input.installType, mode = input.mode, lat = input.lat, clim = input.climate;
    var manual = input.sel === 'manual', m = input.manual || {};
    var val = parseFloat(input.value) || 100;
    var roof = type !== 'ground';
    var P = cfg.panels, I = cfg.inverters, DC = cfg.dc, AC = cfg.ac;

    var tmin = lat > 34 ? -12 : (lat > 29 ? -6 : 2);
    var cellT = Math.round(clim.temp + 26 - 1.1 * clim.wind);
    var areaPerKw = roof ? cfg.areaPerKw.roof : cfg.areaPerKw.ground;
    var tilt = roof ? (type === 'shed' ? 12 : Math.round(lat - 3)) : Math.round(lat - 2);
    var tiltF = roof ? (type === 'shed' ? 0.96 : 1.02) : 1.05;
    var priceUsd = roof ? cfg.price.roof : cfg.price.ground;

    /* panel */
    var panel, pReason, customP = false;
    if (manual && m.panel === 'custom') {
      var w = parseFloat(m.cpW) || 585;
      panel = { brand: T('custom'), name: (m.cpName || '').trim() || T('custom_panel', { w: w }), pmax: w, vmp: 43.86, imp: w / 43.86, voc: 52.5, isc: w / 43.86 * 1.055, tcP: -0.29, tcV: -0.25, bif: 1.0 };
      pReason = { k: 'r_panel_custom', v: { name: panel.name, w: w } }; customP = true;
    } else if (manual) {
      panel = P[+m.panel] || P[0];
      pReason = { k: 'r_panel_manual', v: { name: panel.name } };
    } else {
      panel = roof ? P[0] : P[1];
      pReason = roof
        ? { k: 'r_panel_roof', v: { area: fd(cfg.areaPerKw.roof), name: panel.name } }
        : { k: 'r_panel_ground', v: { area: fd(cfg.areaPerKw.ground), name: panel.name } };
    }

    var raw = mode === 'area' ? Math.floor(val / areaPerKw / (panel.pmax / 1000)) : Math.ceil(val * 1000 / panel.pmax);
    var vocMin = panel.voc * (1 + (panel.tcV / 100) * (tmin - 25));
    var maxSer = Math.floor((1100 - 35) / vocMin);
    var ser = Math.max(1, Math.min(maxSer, Math.max(14, Math.round(600 / panel.vmp) + 4)));
    var stVmp = Math.round(ser * panel.vmp);
    var nStr = Math.max(1, Math.round(raw / ser));
    var nPanels = nStr * ser;
    var dcKwp = nPanels * panel.pmax / 1000;

    /* inverter */
    var inv, iReason, customI = false;
    if (manual && m.inv === 'custom') {
      var k = parseFloat(m.ciKw) || 100;
      inv = { brand: T('custom'), name: (m.ciName || '').trim() || T('custom_inv', { kw: k }), pac: k, vmax: 1100, strs: Math.max(2, Math.ceil(k / 6.25)), eff: 98.4 };
      iReason = { k: 'r_inv_custom', v: { name: inv.name, kw: k } }; customI = true;
    } else if (manual) {
      inv = I[+m.inv] || I[0];
      iReason = { k: 'r_inv_manual', v: { name: inv.name } };
    } else {
      inv = dcKwp <= 55 ? I[2] : (dcKwp >= 500 ? I[5] : I[4]);
      iReason = { k: 'r_inv_auto', v: { kwp: dcKwp.toFixed(1), name: inv.name } };
    }
    var nInv = Math.max(1, Math.ceil(dcKwp / 1.25 / inv.pac), Math.ceil(nStr / inv.strs));
    var acKw = nInv * inv.pac;
    var ratio = (dcKwp / acKw).toFixed(2);

    /* cable runs */
    var effArea = mode === 'area' ? val : val * areaPerKw;
    var side = Math.sqrt(effArea);
    var dcLen = Math.max(25, Math.min(140, Math.round(side * 0.65)));
    var acLen = Math.max(20, Math.min(120, Math.round(side * 0.45)));

    /* DC cable */
    var reqDc = panel.isc * 1.25;
    var dcPick = pickCable(DC, manual ? m.dc : 'auto', m.cdMm, reqDc, 1.35, function (c) { return (2 * dcLen * panel.imp * c.r / 1000) / stVmp * 100; }, false);
    var dcRow = dcPick.row, dcPct = dcPick.pct, dcV = dcPct * stVmp / 100;
    var dcMeters = Math.round(nStr * dcLen * 2);
    var dcLabel = dcPick.custom ? ((m.cdName || '').trim() || T('custom_dc')) + ' (' + T('calc_with', { label: dcRow.label }) + ')' : dcRow.label;

    /* AC cable */
    var cosP = 0.99, sinP = Math.sqrt(1 - cosP * cosP);
    var iA = inv.pac * 1000 / (Math.sqrt(3) * 400 * cosP);
    var reqAc = iA * 1.25;
    var acPick = pickCable(AC, manual ? m.ac : 'auto', m.caMm, reqAc, 1.5, function (c) { return (Math.sqrt(3) * iA * acLen * (c.r * cosP + c.x * sinP) / 1000) / 400 * 100; }, acKw > 150);
    var acRow = acPick.row, acPct = acPick.pct, acV = acPct * 4;
    var acMeters = Math.round(nInv * acLen);
    var acLabel = acPick.custom ? ((m.caName || '').trim() || T('custom_ac')) + ' (' + T('calc_with', { label: acRow.label }) + ')' : acRow.label;

    /* generation */
    var tLoss = Math.max(0, (cellT - 25) * Math.abs(panel.tcP));
    var pr = ((100 - tLoss) / 100) * (inv.eff / 100) * 0.93 * panel.bif;
    var genKwh = dcKwp * clim.ghi * tiltF * pr * (cfg.genFactor || 1);

    /* transformer and breaker */
    var plantA = Math.round(iA * nInv);
    var trans = { needed: false, kva: 0 };
    if (acKw >= 100) { trans.needed = true; trans.kva = STD_TRANS.filter(function (t) { return t >= acKw * 1.15; })[0] || 2500; }
    var mainBreakerA = Math.ceil(plantA * 1.25 / 50) * 50;

    /* finance */
    var capexUsd = dcKwp * priceUsd;
    var capexM = capexUsd * cfg.price.usdRate / 1e6;
    var revS = genKwh * cfg.price.satba / 1e6;
    var revG = genKwh * cfg.price.satba * cfg.price.greenMult / 1e6;

    return {
      panel: panel, panelCustom: customP, nPanels: nPanels, nStr: nStr, ser: ser, stVmp: stVmp, pReason: pReason,
      inv: inv, invCustom: customI, nInv: nInv, iReason: iReason, dcKwp: dcKwp, acKw: acKw, ratio: ratio,
      dc: { row: dcRow, pct: dcPct, v: dcV, ok: dcPick.ok, custom: dcPick.custom, label: dcLabel, meters: dcMeters, len: dcLen },
      ac: { row: acRow, pct: acPct, v: acV, ok: acPick.ok, custom: acPick.custom, label: acLabel, meters: acMeters, len: acLen, currentA: iA },
      trans: trans, plantA: plantA, mainBreakerA: mainBreakerA,
      pr: pr, genKwh: genKwh, genMwh: (genKwh / 1000).toFixed(1), tilt: tilt, cellT: cellT,
      usd: cfg.price.usdRate, capexUsd: capexUsd, capexM: capexM, revS: revS, revG: revG,
      areaPerKw: areaPerKw, effArea: effArea, manual: manual
    };
  }

  return { calculate: calculate, baseGhi: baseGhi, AUTO_DEFAULT: { temp: 24, wind: 4.2 } };
});
