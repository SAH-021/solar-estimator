<?php
/** Tool template. Variables: $uid $style $o $ui $vazir $fa $num */
if (!defined('ABSPATH')) exit;
$admin = current_user_can('manage_options');
$e = function ($n) { echo 'data-el="' . esc_attr($n) . '"'; };
?>
<div class="sd-app<?php echo $vazir ? ' sd-vazir' : ''; ?>" id="<?php echo esc_attr($uid); ?>" dir="<?php echo $ui['rtl'] ? 'rtl' : 'ltr'; ?>" lang="<?php echo esc_attr($ui['lang']); ?>" data-uid="<?php echo esc_attr($uid); ?>" style="<?php echo esc_attr($style); ?>">
<noscript><p class="sd-notice"><?php esc_html_e('JavaScript must be enabled in your browser to use this tool.', 'solar-estimator'); ?></p></noscript>

<header class="sd-header">
  <div>
    <h1><?php echo esc_html($o['title']); ?></h1>
    <p><?php echo esc_html($o['subtitle']); ?></p>
  </div>
  <?php if ($admin) : ?>
  <details class="sd-admin">
    <summary><?php esc_html_e('Daily price settings (site admin)', 'solar-estimator'); ?></summary>
    <div class="sd-admin-grid">
      <div><label><?php esc_html_e('Ground (USD per kW)', 'solar-estimator'); ?></label><input class="sd-fc" type="number" <?php $e('cfg-ground'); ?> value="<?php echo esc_attr($o['price_ground']); ?>"></div>
      <div><label><?php esc_html_e('Rooftop and shed (USD per kW)', 'solar-estimator'); ?></label><input class="sd-fc" type="number" <?php $e('cfg-roof'); ?> value="<?php echo esc_attr($o['price_roof']); ?>"></div>
      <div><label><?php esc_html_e('USD rate (Toman)', 'solar-estimator'); ?></label><input class="sd-fc" type="number" step="1000" <?php $e('cfg-usd'); ?> value="<?php echo esc_attr($o['usd_rate']); ?>"></div>
      <div><label><?php esc_html_e('SATBA purchase (Toman per kWh)', 'solar-estimator'); ?></label><input class="sd-fc" type="number" step="100" <?php $e('cfg-satba'); ?> value="<?php echo esc_attr($o['satba_rate']); ?>"></div>
    </div>
    <div class="sd-admin-save"><button type="button" class="sd-bt" data-act="saveprices"><?php esc_html_e('Save prices for all visitors', 'solar-estimator'); ?></button><span <?php $e('pricemsg'); ?> aria-live="polite"></span></div>
  </details>
  <?php endif; ?>
</header>

<div class="sd-grid">
  <!-- inputs column -->
  <div>
    <div class="sd-card">
      <div class="sd-step"><b><?php echo esc_html($fa('1')); ?></b><h2><?php esc_html_e('Mark the project location on the map', 'solar-estimator'); ?><small><?php esc_html_e('Click, drag the marker, or search for an address', 'solar-estimator'); ?></small></h2></div>
      <div class="sd-search">
        <input class="sd-fc" <?php $e('q'); ?> placeholder="<?php esc_attr_e('Search an address or city, e.g. Yazd', 'solar-estimator'); ?>" aria-label="<?php esc_attr_e('Search an address or city', 'solar-estimator'); ?>">
        <button type="button" class="sd-bt" data-act="search"><?php esc_html_e('Search', 'solar-estimator'); ?></button>
        <button type="button" class="sd-bt" data-act="locate"><?php esc_html_e('My location', 'solar-estimator'); ?></button>
      </div>
      <div class="sd-bar">
        <button type="button" class="sd-bt on" <?php $e('b-street'); ?> data-act="street"><?php esc_html_e('Map', 'solar-estimator'); ?></button>
        <button type="button" class="sd-bt" <?php $e('b-sat'); ?> data-act="sat"><?php esc_html_e('Satellite', 'solar-estimator'); ?></button>
        <button type="button" class="sd-bt" <?php $e('b-nextsat'); ?> data-act="nextsat" title="<?php esc_attr_e('If the satellite image did not load', 'solar-estimator'); ?>"><?php esc_html_e('Other satellite source', 'solar-estimator'); ?></button>
        <button type="button" class="sd-bt" <?php $e('b-draw'); ?> data-act="draw"><?php esc_html_e('Draw land or roof boundary', 'solar-estimator'); ?></button>
        <button type="button" class="sd-bt" data-act="clearpoly"><?php esc_html_e('Clear boundary', 'solar-estimator'); ?></button>
      </div>
      <div class="sd-hint" <?php $e('map-hint'); ?>><?php esc_html_e('To calculate from an area, press “Draw land or roof boundary” and click the corners of the land (at least 3 points). The area is filled in automatically.', 'solar-estimator'); ?></div>
      <div class="sd-mapbox">
        <div class="sd-map" <?php $e('map'); ?> role="application" aria-label="<?php esc_attr_e('Map for choosing the project location', 'solar-estimator'); ?>"></div>
        <div class="sd-mapnote" <?php $e('mapnote'); ?> hidden></div>
      </div>
      <div class="sd-mapinfo"><span <?php $e('coord'); ?>><?php esc_html_e('Lat / Lng: —', 'solar-estimator'); ?></span><span <?php $e('api'); ?> aria-live="polite"><?php esc_html_e('Ready to calculate', 'solar-estimator'); ?></span></div>
    </div>

    <div class="sd-card">
      <div class="sd-step"><b><?php echo esc_html($fa('2')); ?></b><h2><?php esc_html_e('Installation type and project size', 'solar-estimator'); ?></h2></div>
      <div class="sd-fg"><label for="<?php echo esc_attr($uid); ?>-install"><?php esc_html_e('Installation site', 'solar-estimator'); ?></label>
        <select id="<?php echo esc_attr($uid); ?>-install" class="sd-fc" <?php $e('install'); ?>>
          <option value="ground"><?php echo esc_html(sprintf(
              /* translators: %s: required area per kW */
              __('Ground-mount plant (%s m² per kW)', 'solar-estimator'), $num($o['area_ground']))); ?></option>
          <option value="shed"><?php echo esc_html(sprintf(
              /* translators: %s: required area per kW */
              __('Industrial shed roof (%s m² per kW)', 'solar-estimator'), $num($o['area_roof']))); ?></option>
          <option value="rooftop"><?php echo esc_html(sprintf(
              /* translators: %s: required area per kW */
              __('Flat rooftop (%s m² per kW)', 'solar-estimator'), $num($o['area_roof']))); ?></option>
        </select></div>
      <div class="sd-row">
        <div class="sd-fg"><label for="<?php echo esc_attr($uid); ?>-mode"><?php esc_html_e('What should the calculation be based on?', 'solar-estimator'); ?></label>
          <select id="<?php echo esc_attr($uid); ?>-mode" class="sd-fc" <?php $e('mode'); ?>>
            <option value="area"><?php esc_html_e('Area (m²)', 'solar-estimator'); ?></option>
            <option value="power"><?php esc_html_e('Capacity (kW)', 'solar-estimator'); ?></option>
          </select></div>
        <div class="sd-fg"><label for="<?php echo esc_attr($uid); ?>-val" <?php $e('lbl'); ?>><?php esc_html_e('Available area (m²)', 'solar-estimator'); ?></label>
          <input type="text" inputmode="decimal" id="<?php echo esc_attr($uid); ?>-val" class="sd-fc" <?php $e('val'); ?> value="5000" autocomplete="off">
          <div class="sd-valmsg" <?php $e('valmsg'); ?> hidden></div></div>
      </div>
    </div>

    <div class="sd-card">
      <div class="sd-step"><b><?php echo esc_html($fa('3')); ?></b><h2><?php esc_html_e('Choose the equipment', 'solar-estimator'); ?><small><?php esc_html_e('If you are not sure, keep “Automatic”', 'solar-estimator'); ?></small></h2></div>
      <div class="sd-seg" role="group" aria-label="<?php esc_attr_e('Equipment selection method', 'solar-estimator'); ?>">
        <button type="button" class="on" <?php $e('s-auto'); ?> data-act="selauto"><?php esc_html_e('Automatic (system suggestion)', 'solar-estimator'); ?></button>
        <button type="button" <?php $e('s-manual'); ?> data-act="selmanual"><?php esc_html_e('Manual (I choose myself)', 'solar-estimator'); ?></button>
      </div>
      <div class="sd-hint" <?php $e('sel-hint'); ?>><?php esc_html_e('In automatic mode the panel, inverter and cables are chosen from the installation type and capacity. The reason for each choice is shown in the results.', 'solar-estimator'); ?></div>

      <div class="sd-manual" <?php $e('manual'); ?> hidden>
        <div class="sd-grp"><label for="<?php echo esc_attr($uid); ?>-mp"><?php esc_html_e('Solar panel', 'solar-estimator'); ?></label>
          <select id="<?php echo esc_attr($uid); ?>-mp" class="sd-fc" <?php $e('m-panel'); ?> data-sel="panel"></select>
          <div class="sd-spec" <?php $e('sp-panel'); ?>></div>
          <div class="sd-custom" <?php $e('c-panel'); ?> hidden>
            <input class="sd-fc" <?php $e('cp-name'); ?> placeholder="<?php esc_attr_e('Panel name and model (write anything you like)', 'solar-estimator'); ?>" aria-label="<?php esc_attr_e('Panel name and model', 'solar-estimator'); ?>">
            <div class="sd-row"><input class="sd-fc" inputmode="decimal" <?php $e('cp-w'); ?> placeholder="<?php esc_attr_e('Panel power (W)', 'solar-estimator'); ?>" aria-label="<?php esc_attr_e('Panel power (W)', 'solar-estimator'); ?>"><span class="sd-spec"><?php esc_html_e('Other electrical specifications are assumed to be standard.', 'solar-estimator'); ?></span></div>
          </div></div>
        <div class="sd-grp"><label for="<?php echo esc_attr($uid); ?>-mi"><?php esc_html_e('Inverter', 'solar-estimator'); ?></label>
          <select id="<?php echo esc_attr($uid); ?>-mi" class="sd-fc" <?php $e('m-inv'); ?> data-sel="inv"></select>
          <div class="sd-spec" <?php $e('sp-inv'); ?>></div>
          <div class="sd-custom" <?php $e('c-inv'); ?> hidden>
            <input class="sd-fc" <?php $e('ci-name'); ?> placeholder="<?php esc_attr_e('Inverter name and model', 'solar-estimator'); ?>" aria-label="<?php esc_attr_e('Inverter name and model', 'solar-estimator'); ?>">
            <div class="sd-row"><input class="sd-fc" inputmode="decimal" <?php $e('ci-kw'); ?> placeholder="<?php esc_attr_e('Inverter power (kW)', 'solar-estimator'); ?>" aria-label="<?php esc_attr_e('Inverter power (kW)', 'solar-estimator'); ?>"><span class="sd-spec"><?php echo esc_html(sprintf(
                /* translators: 1: efficiency, 2: input voltage */
                __('An efficiency of %1$s%% and a %2$s V input are assumed.', 'solar-estimator'), $num(98.4), $fa(1100))); ?></span></div>
          </div></div>
        <div class="sd-grp"><label for="<?php echo esc_attr($uid); ?>-md"><?php esc_html_e('DC cable (panel side)', 'solar-estimator'); ?></label>
          <select id="<?php echo esc_attr($uid); ?>-md" class="sd-fc" <?php $e('m-dc'); ?> data-sel="dc"></select>
          <div class="sd-custom" <?php $e('c-dc'); ?> hidden>
            <input class="sd-fc" <?php $e('cd-name'); ?> placeholder="<?php esc_attr_e('Custom cable specification', 'solar-estimator'); ?>" aria-label="<?php esc_attr_e('Custom DC cable specification', 'solar-estimator'); ?>">
            <div class="sd-row"><input class="sd-fc" inputmode="decimal" <?php $e('cd-mm'); ?> placeholder="<?php esc_attr_e('Cross-section (mm²)', 'solar-estimator'); ?>" aria-label="<?php esc_attr_e('DC cable cross-section', 'solar-estimator'); ?>"><span class="sd-spec"><?php esc_html_e('The voltage drop is calculated with the nearest size in the table.', 'solar-estimator'); ?></span></div>
          </div></div>
        <div class="sd-grp"><label for="<?php echo esc_attr($uid); ?>-ma"><?php esc_html_e('AC cable (inverter output)', 'solar-estimator'); ?></label>
          <select id="<?php echo esc_attr($uid); ?>-ma" class="sd-fc" <?php $e('m-ac'); ?> data-sel="ac"></select>
          <div class="sd-custom" <?php $e('c-ac'); ?> hidden>
            <input class="sd-fc" <?php $e('ca-name'); ?> placeholder="<?php esc_attr_e('Custom cable specification (copper or aluminum)', 'solar-estimator'); ?>" aria-label="<?php esc_attr_e('Custom AC cable specification', 'solar-estimator'); ?>">
            <div class="sd-row"><input class="sd-fc" inputmode="decimal" <?php $e('ca-mm'); ?> placeholder="<?php esc_attr_e('Cross-section (mm²)', 'solar-estimator'); ?>" aria-label="<?php esc_attr_e('AC cable cross-section', 'solar-estimator'); ?>"><span class="sd-spec"><?php esc_html_e('The voltage drop is calculated with the nearest size in the table.', 'solar-estimator'); ?></span></div>
          </div></div>
        <div class="sd-grp" style="margin-bottom:0"><label for="<?php echo esc_attr($uid); ?>-notes"><?php esc_html_e('Your notes or special requests', 'solar-estimator'); ?></label>
          <textarea id="<?php echo esc_attr($uid); ?>-notes" class="sd-fc" rows="3" <?php $e('notes'); ?> maxlength="600" placeholder="<?php esc_attr_e('E.g. a specific brand, cable route length, special installation conditions… (included in the quote)', 'solar-estimator'); ?>"></textarea></div>
      </div>
    </div>
  </div>

  <!-- results column -->
  <div>
    <div class="sd-card sd-hero sd-sticky">
      <div class="sd-hero-main">
        <div class="sd-big"><span><?php esc_html_e('Panel / inverter capacity', 'solar-estimator'); ?></span><strong <?php $e('h-cap'); ?>>—</strong><em <?php $e('h-gen'); ?>></em></div>
        <div class="sd-big alt"><span><?php esc_html_e('Total project cost', 'solar-estimator'); ?></span><strong <?php $e('h-capex'); ?>>—</strong><em <?php $e('h-usd'); ?>></em></div>
      </div>
      <div class="sd-chips">
        <div class="sd-chip"><span><?php esc_html_e('Payback', 'solar-estimator'); ?></span><strong <?php $e('h-roi'); ?>>—</strong></div>
        <div class="sd-chip"><span><?php esc_html_e('Tilt angle', 'solar-estimator'); ?></span><strong <?php $e('h-tilt'); ?>>—</strong></div>
        <div class="sd-chip"><span><?php esc_html_e('Annual irradiation', 'solar-estimator'); ?></span><strong <?php $e('h-ghi'); ?>>—</strong></div>
        <div class="sd-chip"><span><?php esc_html_e('Performance ratio (PR)', 'solar-estimator'); ?></span><strong <?php $e('h-pr'); ?>>—</strong></div>
      </div>
    </div>

    <div class="sd-card">
      <div class="sd-step"><b class="blue">?</b><h2><?php esc_html_e('Why this equipment?', 'solar-estimator'); ?></h2></div>
      <ul class="sd-why" <?php $e('why'); ?>></ul>
    </div>

    <div class="sd-card">
      <div class="sd-step"><b class="blue">⚡</b><h2><?php esc_html_e('Technical specifications', 'solar-estimator'); ?></h2></div>
      <div class="sd-kpis">
        <div class="sd-kpi"><span><?php esc_html_e('Panel and string layout', 'solar-estimator'); ?></span><strong <?php $e('o-panel'); ?>></strong><small <?php $e('o-str'); ?>></small></div>
        <div class="sd-kpi"><span><?php esc_html_e('Inverter', 'solar-estimator'); ?></span><strong <?php $e('o-inv'); ?>></strong><small <?php $e('o-invi'); ?>></small></div>
        <div class="sd-kpi"><span><?php esc_html_e('Transformer', 'solar-estimator'); ?></span><strong <?php $e('o-trans'); ?>></strong><small <?php $e('o-brk'); ?>></small></div>
        <div class="sd-kpi"><span><?php esc_html_e('DC-to-AC ratio', 'solar-estimator'); ?></span><strong <?php $e('o-ratio'); ?>></strong><small><?php echo esc_html(sprintf(
            /* translators: %s: maximum DC/AC ratio */
            __('Allowed overload up to %s', 'solar-estimator'), $num(1.25))); ?></small></div>
      </div>
      <table class="sd-table" style="margin-top:12px">
        <tr><th><?php esc_html_e('DC cable', 'solar-estimator'); ?></th><td <?php $e('o-dc'); ?>></td></tr>
        <tr><th><?php esc_html_e('DC voltage drop and length', 'solar-estimator'); ?></th><td <?php $e('o-dcd'); ?>></td></tr>
        <tr><th><?php esc_html_e('AC cable', 'solar-estimator'); ?></th><td <?php $e('o-ac'); ?>></td></tr>
        <tr><th><?php esc_html_e('AC voltage drop and length', 'solar-estimator'); ?></th><td <?php $e('o-acd'); ?>></td></tr>
      </table>
    </div>

    <div class="sd-card">
      <div class="sd-step"><b class="green">$</b><h2><?php esc_html_e('Revenue and payback', 'solar-estimator'); ?></h2></div>
      <div class="sd-kpis">
        <div class="sd-kpi g"><span><?php esc_html_e('First-year revenue, SATBA guaranteed purchase', 'solar-estimator'); ?></span><strong <?php $e('o-satba'); ?>></strong></div>
        <div class="sd-kpi g"><span><?php esc_html_e('First-year revenue, energy-exchange green board', 'solar-estimator'); ?></span><strong <?php $e('o-green'); ?>></strong></div>
      </div>
      <button type="button" class="sd-go2" data-act="openlead"><?php esc_html_e('Get the equipment list and quote', 'solar-estimator'); ?></button>
    </div>
  </div>
</div>

<div class="sd-card sd-bom" <?php $e('bom'); ?> hidden>
  <div class="sd-step"><b>✓</b><h2><?php esc_html_e('Quote and equipment list', 'solar-estimator'); ?></h2></div>
  <div <?php $e('sheet'); ?>></div>
  <button type="button" class="sd-go2" data-act="print"><?php esc_html_e('Print or save as PDF', 'solar-estimator'); ?></button>
</div>

<div class="sd-modal" <?php $e('lead'); ?> hidden>
  <div class="sd-box" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($uid); ?>-lt">
    <div class="sd-step"><h2 id="<?php echo esc_attr($uid); ?>-lt"><?php esc_html_e('Issue quote', 'solar-estimator'); ?></h2></div>
    <form <?php $e('leadform'); ?> novalidate>
      <div class="sd-fg"><label for="<?php echo esc_attr($uid); ?>-ln"><?php esc_html_e('Full name', 'solar-estimator'); ?></label><input id="<?php echo esc_attr($uid); ?>-ln" name="name" class="sd-fc" placeholder="<?php esc_attr_e('e.g. Eng. Rezaei', 'solar-estimator'); ?>" autocomplete="name"></div>
      <div class="sd-fg"><label for="<?php echo esc_attr($uid); ?>-lc"><?php esc_html_e('Company or organization', 'solar-estimator'); ?></label><input id="<?php echo esc_attr($uid); ?>-lc" name="company" class="sd-fc" autocomplete="organization"></div>
      <div class="sd-fg"><label for="<?php echo esc_attr($uid); ?>-lp"><?php esc_html_e('Mobile number', 'solar-estimator'); ?></label><input id="<?php echo esc_attr($uid); ?>-lp" name="phone" type="tel" inputmode="tel" dir="ltr" class="sd-fc" placeholder="0912XXXXXXX" autocomplete="tel"></div>
      <input type="text" name="website" tabindex="-1" autocomplete="off" class="sd-hp" aria-hidden="true">
      <p class="sd-error" <?php $e('formerr'); ?> role="alert" hidden></p>
      <div style="display:flex;gap:8px"><button type="submit" class="sd-go" <?php $e('submit'); ?>><?php esc_html_e('Confirm and issue', 'solar-estimator'); ?></button><button type="button" class="sd-bt" data-act="closelead"><?php esc_html_e('Cancel', 'solar-estimator'); ?></button></div>
    </form>
  </div>
</div>
</div>
