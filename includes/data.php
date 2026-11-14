<?php
if (!defined('ABSPATH')) exit;

/** Equipment bank and cable tables (panels, inverters, DC/AC cables). */
function solest_equipment() {
    $cu = __('copper (NYY)', 'solar-estimator');
    $al = __('aluminum (NAYY)', 'solar-estimator');
    return [
        'panels' => [
            ['brand' => 'Jinko Solar', 'name' => 'Jinko Tiger Neo 585W (N-Type)',  'pmax' => 585, 'vmp' => 43.86, 'imp' => 13.34, 'voc' => 52.50, 'isc' => 14.07, 'tcP' => -0.29, 'tcV' => -0.25, 'bif' => 1.05],
            ['brand' => 'Trina Solar', 'name' => 'Trina Vertex N 610W (Bifacial)',  'pmax' => 610, 'vmp' => 40.80, 'imp' => 14.96, 'voc' => 48.70, 'isc' => 15.86, 'tcP' => -0.29, 'tcV' => -0.24, 'bif' => 1.05],
            ['brand' => 'LONGi Solar', 'name' => 'LONGi Hi-MO 6 580W',              'pmax' => 580, 'vmp' => 44.06, 'imp' => 13.17, 'voc' => 52.21, 'isc' => 14.20, 'tcP' => -0.29, 'tcV' => -0.25, 'bif' => 1.03],
            ['brand' => 'JA Solar',    'name' => 'JA Solar DeepBlue 4.0 Pro 590W',  'pmax' => 590, 'vmp' => 44.22, 'imp' => 13.35, 'voc' => 52.80, 'isc' => 14.12, 'tcP' => -0.30, 'tcV' => -0.26, 'bif' => 1.05],
        ],
        'inverters' => [
            ['brand' => 'Growatt', 'name' => 'Growatt MOD 10KTL3-X (10 kW)',      'pac' => 10,  'vmax' => 1100, 'strs' => 2,  'eff' => 98.1],
            ['brand' => 'Huawei',  'name' => 'Huawei SUN2000-30KTL-M3 (30 kW)',   'pac' => 30,  'vmax' => 1100, 'strs' => 8,  'eff' => 98.4],
            ['brand' => 'Huawei',  'name' => 'Huawei SUN2000-50KTL-M3 (50 kW)',   'pac' => 50,  'vmax' => 1100, 'strs' => 8,  'eff' => 98.4],
            ['brand' => 'Growatt', 'name' => 'Growatt MAX 60KTL3 LV (60 kW)',     'pac' => 60,  'vmax' => 1100, 'strs' => 12, 'eff' => 98.4],
            ['brand' => 'Huawei',  'name' => 'Huawei SUN2000-100KTL-M2 (100 kW)', 'pac' => 100, 'vmax' => 1100, 'strs' => 20, 'eff' => 98.6],
            ['brand' => 'Sungrow', 'name' => 'Sungrow SG110CX-P2 (110 kW)',       'pac' => 110, 'vmax' => 1100, 'strs' => 18, 'eff' => 98.5],
        ],
        'dc' => [
            ['mm' => 4,  'label' => '1×4 mm² Cu',  'r' => 6.09, 'amp' => 38],
            ['mm' => 6,  'label' => '1×6 mm² Cu',  'r' => 4.06, 'amp' => 48],
            ['mm' => 10, 'label' => '1×10 mm² Cu', 'r' => 2.33, 'amp' => 67],
            ['mm' => 16, 'label' => '1×16 mm² Cu', 'r' => 1.48, 'amp' => 90],
        ],
        'ac' => [
            ['mm' => 16,  'type' => 'Cu', 'label' => '4×16 mm² ' . $cu,             'r' => 1.380, 'x' => 0.082, 'amp' => 80,  'brk' => 63],
            ['mm' => 25,  'type' => 'Cu', 'label' => '4×25 mm² ' . $cu,             'r' => 0.870, 'x' => 0.080, 'amp' => 106, 'brk' => 100],
            ['mm' => 35,  'type' => 'Cu', 'label' => '4×35 mm² ' . $cu,             'r' => 0.627, 'x' => 0.078, 'amp' => 131, 'brk' => 125],
            ['mm' => 50,  'type' => 'Al', 'label' => '4×50 mm² ' . $al,             'r' => 1.200, 'x' => 0.085, 'amp' => 115, 'brk' => 100],
            ['mm' => 70,  'type' => 'Al', 'label' => '3×70+35 mm² ' . $al,          'r' => 0.860, 'x' => 0.080, 'amp' => 145, 'brk' => 125],
            ['mm' => 95,  'type' => 'Al', 'label' => '3×95+50 mm² ' . $al,          'r' => 0.640, 'x' => 0.078, 'amp' => 175, 'brk' => 160],
            ['mm' => 120, 'type' => 'Al', 'label' => '3×120+70 mm² ' . $al,         'r' => 0.500, 'x' => 0.075, 'amp' => 205, 'brk' => 180],
            ['mm' => 150, 'type' => 'Al', 'label' => '3×150+70 mm² ' . $al,         'r' => 0.390, 'x' => 0.074, 'amp' => 235, 'brk' => 200],
        ],
    ];
}
