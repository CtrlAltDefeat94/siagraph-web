<?php
/**
 * Render a standard months/resolution control form.
 * Config keys:
 * - months (int): current selected months
 * - resolution (string): 'daily' or 'monthly'
 * - monthsOptions (array<int>): available month options (default [1,6,12,24])
 * - formClass (string): additional classes for <form>
 * - action (string): form action (default current page)
 * - preserveQuery (bool): include hidden inputs for existing query params (default true)
 */
function render_range_controls(array $config = []) {
    $months = isset($config['months']) ? (int)$config['months'] : 12;
    $resolution = isset($config['resolution']) && $config['resolution'] === 'monthly' ? 'monthly' : 'daily';
    $monthsOptions = $config['monthsOptions'] ?? [1, 6, 12, 24];
    $formClass = $config['formClass'] ?? 'mb-3';
    $action = $config['action'] ?? '';
    $preserve = array_key_exists('preserveQuery', $config) ? (bool)$config['preserveQuery'] : true;
    $showMonths = array_key_exists('showMonths', $config) ? (bool)$config['showMonths'] : true;
    $showResolution = array_key_exists('showResolution', $config) ? (bool)$config['showResolution'] : true;
    $label = $config['label'] ?? null;
    $labelClass = $config['labelClass'] ?? 'me-2';

    echo '<form'.($formClass? ' class="'.htmlspecialchars($formClass, ENT_QUOTES, 'UTF-8').'"' : '').($action!==''? ' action="'.htmlspecialchars($action, ENT_QUOTES, 'UTF-8').'"' : '').' method="get">';
    // Preserve existing query params except ones we control
    if ($preserve && !empty($_GET)) {
        foreach ($_GET as $k => $v) {
            if ($k === 'months' || $k === 'resolution') continue;
            if (is_array($v)) continue; // skip arrays for simplicity
            echo '<input type="hidden" name="'.htmlspecialchars($k, ENT_QUOTES, 'UTF-8').'" value="'.htmlspecialchars($v, ENT_QUOTES, 'UTF-8').'">';
        }
    }

    if ($showMonths) {
    if ($label === null) {
        if ($showMonths && $showResolution) $label = 'Range:';
        elseif ($showResolution) $label = 'Resolution:';
        else $label = 'Range:';
    }

    if ($label) {
        echo '<span class="'.htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</span>';
    }

    echo '<select name="months" onchange="this.form.submit()" class="form-select w-auto d-inline">';
        foreach ($monthsOptions as $m) {
            $sel = ($months === (int)$m) ? ' selected' : '';
            $label = $m.' Month'.($m>1?'s':'');
            echo '<option value="'.$m.'"'.$sel.'>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</option>';
        }
        echo '</select>';
    }

    if ($showResolution) {
        $ms = $showMonths ? ' ms-2' : '';
        echo '<select name="resolution" onchange="this.form.submit()" class="form-select w-auto d-inline'.$ms.'">';
        echo '<option value="daily"'.($resolution==='daily'?' selected':'').'>Daily</option>';
        echo '<option value="monthly"'.($resolution==='monthly'?' selected':'').'>Monthly</option>';
        echo '</select>';
    }

    echo '</form>';
}
