<?php
use Siagraph\Utils\Formatter;
use Siagraph\Utils\Locale;

/**
 * Render a compact stat card with label, value and change delta.
 * Args:
 *  - icon (string): bootstrap-icons class (e.g., 'bi bi-hdd-fill')
 *  - label (string)
 *  - value (string): preformatted value text
 *  - changeRaw (float|int|null): numeric change for arrow/color logic
 *  - changeFormat (string|null): 'bytes'|'sc'|'number'|'fiat:USD'|'fiat:EUR' or null to skip change
 *  - decimals (int): for number/fiat when applicable
 *  - context (string|null): small descriptive text under the label
 *  - deltaLabel (string|null): short label shown before the delta (e.g., 'vs prev day')
 *  - tooltip (string|null): info tooltip text on the label
 */
function render_stat_card(array $args) {
    $icon        = $args['icon']        ?? '';
    $label       = $args['label']       ?? '';
    $value       = $args['value']       ?? '';
    $changeRaw   = $args['changeRaw']   ?? null;
    $changeFmt   = $args['changeFormat']?? null;
    $decimals    = $args['decimals']    ?? 2;
    $compact     = $args['compact']     ?? false;
    $context     = $args['context']     ?? null;
    $deltaLabel  = $args['deltaLabel']  ?? null;
    $tooltip     = $args['tooltip']     ?? null;
    // Combine provided tooltip with context (e.g., "Daily snapshot as of ...")
    $tooltipCombined = null;
    if ($tooltip || $context) {
        $tooltipCombined = trim(($tooltip ?? '') . (($tooltip && $context) ? "\n" : '') . ($context ?? ''));
    }

    $valueId    = $args['valueId']     ?? null;
    $changeId   = $args['changeId']    ?? null;

    $deltaHtml = '';
    if ($changeFmt !== null && $changeRaw !== null) {
        $sign = $changeRaw == 0 ? 0 : ($changeRaw > 0 ? 1 : -1);
        // Remove up/down icons; keep color only
        $colorClass = $sign > 0 ? 'text-green-400' : ($sign < 0 ? 'text-red-400' : 'text-gray-400');
        $formatted = '';
        switch ($changeFmt) {
            case 'bytes':
                $formatted = Formatter::formatBytes($changeRaw);
                break;
            case 'sc':
                $formatted = Formatter::formatSiacoins($changeRaw);
                break;
            case 'number':
                $formatted = Locale::decimal($changeRaw, $decimals);
                break;
            case 'fiat:USD':
                // Preserve existing pattern: 'USD ' + localized number
                $formatted = 'USD ' . Locale::decimal($changeRaw, $decimals);
                break;
            case 'fiat:EUR':
                $formatted = 'EUR ' . Locale::decimal($changeRaw, $decimals);
                break;
            default:
                $formatted = (string)$changeRaw;
        }
        $idAttr = $changeId ? ' id="'.htmlspecialchars($changeId, ENT_QUOTES, 'UTF-8').'"' : '';
        $deltaClass = $compact ? 'stat-delta' : 'fs-6';
        $prefix = $deltaLabel ? '<span class="text-gray-400 me-1">'.htmlspecialchars($deltaLabel).'</span>' : '';
        $deltaHtml = "$prefix<span$idAttr class=\"$colorClass $deltaClass\">$formatted</span>";
    }

    if ($compact) {
        echo "<div class=\"stat-tile\">";
        echo "<div class=\"stat-label\">";
        if ($icon) { echo "<i class=\"$icon me-1\"></i>"; }
        echo htmlspecialchars($label);
        if ($tooltipCombined) {
            $tt = htmlspecialchars($tooltipCombined, ENT_QUOTES, 'UTF-8');
            echo " <span title=\"$tt\" class=\"text-gray-400\"><i class=\"bi bi-info-circle\"></i></span>";
        }
        echo "</div>";
        echo "<div class=\"flex items-center justify-between\">";
        $valueIdAttr = $valueId ? ' id="'.htmlspecialchars($valueId, ENT_QUOTES, 'UTF-8').'"' : '';
        echo "<span$valueIdAttr class=\"stat-value\">$value</span>";
        if ($deltaHtml !== '') {
            echo "<div class=\"ms-2\">$deltaHtml</div>";
        }
        echo "</div>";
        echo "</div>";
    } else {
        echo "<div class=\"card\">";
        echo   "<div class=\"card__content\">";
        echo     "<div class=\"mb-1 text-gray-400 text-sm\">";
        if ($icon) { echo "<i class=\"$icon me-1\"></i>"; }
        echo htmlspecialchars($label);
        if ($tooltipCombined) {
            $tt = htmlspecialchars($tooltipCombined, ENT_QUOTES, 'UTF-8');
            echo " <span title=\"$tt\" class=\"text-gray-400\"><i class=\"bi bi-info-circle\"></i></span>";
        }
        echo     "</div>";
        echo     "<div class=\"flex items-center justify-between\">";
        $valueIdAttr = $valueId ? ' id="'.htmlspecialchars($valueId, ENT_QUOTES, 'UTF-8').'"' : '';
        echo       "<span$valueIdAttr class=\"glanceNumber text-2xl font-semibold\">$value</span>";
        if ($deltaHtml !== '') {
            echo   "<div class=\"ms-2 text-sm\">$deltaHtml</div>";
        }
        echo     "</div>";
        echo   "</div>";
        echo "</div>";
    }
}
