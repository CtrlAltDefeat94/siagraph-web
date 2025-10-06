<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';
require_once 'include/components/stat_card.php';
use Siagraph\Utils\Locale;

const FOUNDATION_ADDR = '053b2def3cbdd078c19d62ce2b4f0b1a3c5e0ffbeeff01280efb1f8969b2f5bb4fdc680f0807';
const HASTINGS_PER_SC = 1e24;

function format_sc(float $value): string {
    $units = ['SC', 'KS', 'MS', 'GS', 'TS'];
    $index = 0;
    while ($value >= 1000 && $index < count($units) - 1) {
        $value /= 1000;
        $index++;
    }
    return Locale::decimal($value, 2) . ' ' . $units[$index];
}

// Fetch current balance
$balanceData = json_decode(@file_get_contents('https://explorer.siagraph.info/api/addresses/'.FOUNDATION_ADDR.'/balance'), true);
$balanceNum = 0.0;
if ($balanceData && isset($balanceData['unspentSiacoins'])) {
    $balanceNum = (float) $balanceData['unspentSiacoins'] / HASTINGS_PER_SC;
}

// Fetch events
$eventData = json_decode(@file_get_contents('https://explorer.siagraph.info/api/addresses/'.FOUNDATION_ADDR.'/events'), true);
if (!is_array($eventData)) {
    $eventData = [];
}

usort($eventData, function($a, $b) {
    return strtotime($b['timestamp']) <=> strtotime($a['timestamp']);
});

$totalIn = 0.0;
$totalOut = 0.0;
$totalAbs = 0.0;
$rows = '';

foreach ($eventData as $idx => $event) {
    $value = 0.0;
    if ($event['type'] === 'foundation') {
        $out = $event['data']['siacoinElement']['siacoinOutput'] ?? null;
        if (!$out) continue;
        $value = (float) $out['value'] / HASTINGS_PER_SC;
    } elseif ($event['type'] === 'v1Transaction' || $event['type'] === 'v2Transaction') {
        // Normalize transaction object: some APIs embed as data.transaction (v1),
        // others provide fields directly under data (v2)
        $tx = null;
        if (isset($event['data']['transaction']) && is_array($event['data']['transaction'])) {
            $tx = $event['data']['transaction'];
        } elseif (isset($event['data']) && is_array($event['data'])) {
            $tx = $event['data'];
        }
        if (!$tx) continue;

        // Handle inputs: v1 inputs may have address/value directly; v2 inputs
        // reference a parent.siacoinOutput with address/value.
        if (!empty($tx['siacoinInputs']) && is_array($tx['siacoinInputs'])) {
            foreach ($tx['siacoinInputs'] as $inp) {
                $inAddr = '';
                $inVal = null;
                if (isset($inp['parent']['siacoinOutput'])) {
                    $inAddr = $inp['parent']['siacoinOutput']['address'] ?? '';
                    $inVal = $inp['parent']['siacoinOutput']['value'] ?? null;
                } else {
                    $inAddr = $inp['address'] ?? '';
                    $inVal = $inp['value'] ?? null;
                }
                if ($inVal !== null && $inAddr === FOUNDATION_ADDR) {
                    $value -= (float) $inVal / HASTINGS_PER_SC;
                }
            }
        }

        // Handle outputs: v2 uses siacoinOutput nested; v1 may be flat.
        if (!empty($tx['siacoinOutputs']) && is_array($tx['siacoinOutputs'])) {
            foreach ($tx['siacoinOutputs'] as $out) {
                $val = $out['siacoinOutput']['value'] ?? $out['value'] ?? null;
                $addr = $out['siacoinOutput']['address'] ?? $out['address'] ?? '';
                if ($val !== null && $addr === FOUNDATION_ADDR) {
                    $value += (float) $val / HASTINGS_PER_SC;
                }
            }
        }
    } else {
        continue;
    }

    // Let global table theme handle zebra/hover; no explicit row bg classes
    $rowClass = '';
    $sign = $value < 0 ? '-' : '';
    $abs = abs($value);
    $valueSC = $sign . Locale::decimal($abs, 0);
    $valueClass = $value < 0 ? 'text-red-400' : 'text-green-400';
    // Neutral pill styling; icon added only for tx rows
    $typeClass = 'pill';

    // If this is a transaction event, link the type pill to SiaScan tx page
    $typeHtml = '<span class="'.$typeClass.'">'.htmlspecialchars($event['type']).'</span>';
    $txId = null;
    if ($event['type'] === 'v2Transaction') {
        $txId = $event['data']['id'] ?? null;
    } elseif ($event['type'] === 'v1Transaction') {
        $txId = $event['data']['transaction']['id'] ?? null;
    }
    if ($txId) {
        $href = 'https://siascan.com/tx/' . htmlspecialchars($txId, ENT_QUOTES, 'UTF-8');
        $pill = '<span class="'.$typeClass.' tx-pill">'.htmlspecialchars($event['type']).' <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></span>';
        $typeHtml = '<a class="tx-link" href="'.$href.'" target="_blank" rel="noopener" title="View transaction on SiaScan">'
                  . $pill
                  . '</a>';
    }

    $rows .= "<tr class=\"$rowClass\">".
        '<td class="px-3 py-1"><time class="js-localtime" datetime="'.htmlspecialchars($event['timestamp']).'">'.htmlspecialchars($event['timestamp']).'</time></td>'.
        '<td class="px-3 py-1 num">'.intval($event['index']['height'] ?? 0).'</td>'.
        '<td class="px-3 py-1">'.$typeHtml.'</td>'.
        '<td class="px-3 py-1 '.$valueClass.' num">'.$valueSC.'</td>'.
        "</tr>";

    if ($value > 0) $totalIn += $value;
    if ($value < 0) $totalOut += -$value;
    $totalAbs += abs($value);
}

$count = count($eventData);
$avgTxNum = $count ? $totalAbs / $count : 0;
$totalInNum = $totalIn;
$totalOutNum = $totalOut;
$firstDate = $count ? new DateTime(end($eventData)['timestamp']) : new DateTime();
$lastDate = $count ? new DateTime(reset($eventData)['timestamp']) : $firstDate;
$monthsDiff = ($lastDate->format('Y') - $firstDate->format('Y')) * 12 + ($lastDate->format('n') - $firstDate->format('n')) + 1;
if ($monthsDiff <= 0) $monthsDiff = 1;
$avgInNum = $totalInNum / $monthsDiff;
$avgOutNum = $totalOutNum / $monthsDiff;

render_header('SiaGraph - Foundation Subsidy Tracker');
?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-bank me-2"></i>Foundation Subsidy Tracker</h1>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-wallet2',
                'label' => 'Current Balance',
                'value' => format_sc($balanceNum),
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-receipt',
                'label' => 'Avg Tx Size',
                'value' => format_sc($avgTxNum),
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-arrow-down-circle',
                'label' => 'Avg Monthly In',
                'value' => format_sc($avgInNum),
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-arrow-up-circle',
                'label' => 'Avg Monthly Out',
                'value' => format_sc($avgOutNum),
            ]);
            ?>
            </div>
        </div>
    </div>
    <div class="sg-container__row">
        <div class="sg-container__row-content">
            <div class="sg-container__column">
                <section class="card">
                    <h2 class="card__heading">Subsidy Events</h2>
                    <div class="card__content overflow-x-auto">
                        <table class="table table-dark table-clean table-sm text-white w-full text-sm sm:text-base border-collapse">
                            <thead>
                                <tr class="bg-primary-400 text-white">
                                    <th class="px-3 py-1">Timestamp</th>
                                    <th class="px-3 py-1 num">Block Height</th>
                                    <th class="px-3 py-1">Type</th>
                                    <th class="px-3 py-1 num">Value (SC)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php echo $rows ?: '<tr><td colspan="4" class="text-center">No subsidy transactions found</td></tr>'; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.js-localtime').forEach(function(el){
    var t = el.getAttribute('datetime') || el.textContent.trim();
    try {
      if (typeof getLocalizedTime === 'function') {
        el.textContent = getLocalizedTime(t);
      } else {
        el.textContent = new Date(t).toLocaleString(window.APP_LOCALE || undefined);
      }
    } catch(e) {
      el.textContent = new Date(t).toLocaleString(window.APP_LOCALE || undefined);
    }
  });
});
</script>
<?php render_footer(); ?>
