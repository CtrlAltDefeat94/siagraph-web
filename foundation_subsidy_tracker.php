<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';
require_once 'include/components/stat_card.php';
use Siagraph\Utils\ApiClient;
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
$subsidyEvents = [];

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

    $txId = null;
    if ($event['type'] === 'v2Transaction') {
        $txId = $event['data']['id'] ?? null;
    } elseif ($event['type'] === 'v1Transaction') {
        $txId = $event['data']['transaction']['id'] ?? null;
    }

    $subsidyEvents[] = [
        'timestamp' => (string) ($event['timestamp'] ?? ''),
        'height' => intval($event['index']['height'] ?? 0),
        'type' => (string) ($event['type'] ?? ''),
        'value' => (float) $value,
        'txId' => $txId ? (string) $txId : null,
    ];

    if ($value > 0) $totalIn += $value;
    if ($value < 0) $totalOut += -$value;
    $totalAbs += abs($value);
}

$count = count($subsidyEvents);
$avgTxNum = $count ? $totalAbs / $count : 0;
$totalInNum = $totalIn;
$totalOutNum = $totalOut;
$firstDate = $count ? new DateTime(end($subsidyEvents)['timestamp']) : new DateTime();
$lastDate = $count ? new DateTime(reset($subsidyEvents)['timestamp']) : $firstDate;
$monthsDiff = ($lastDate->format('Y') - $firstDate->format('Y')) * 12 + ($lastDate->format('n') - $firstDate->format('n')) + 1;
if ($monthsDiff <= 0) $monthsDiff = 1;
$avgInNum = $totalInNum / $monthsDiff;
$avgOutNum = $totalOutNum / $monthsDiff;

$currencyCookie = isset($_COOKIE['currency']) ? strtolower((string) $_COOKIE['currency']) : 'eur';
$currencyCookie = in_array($currencyCookie, ['usd', 'eur', 'sc'], true) ? $currencyCookie : 'eur';
$recentStats = ApiClient::fetchJson('/api/v1/daily/compare_metrics', true, 'hour');
$coinPrice = 1.0;
if ($currencyCookie !== 'sc' && is_array($recentStats) && isset($recentStats['actual']['coin_price'][$currencyCookie])) {
    $candidateRate = (float) $recentStats['actual']['coin_price'][$currencyCookie];
    if ($candidateRate > 0) {
        $coinPrice = $candidateRate;
    }
}

$formatCardValue = static function (float $scValue) use ($currencyCookie, $coinPrice): array {
    $scDisplay = format_sc($scValue);
    if ($currencyCookie === 'sc' || $coinPrice <= 0) {
        return [
            'value' => htmlspecialchars($scDisplay, ENT_QUOTES, 'UTF-8'),
            'tooltip' => null,
        ];
    }

    $fiatCode = strtoupper($currencyCookie);
    $fiatValue = $scValue * $coinPrice;
    $fiatDisplay = $fiatCode . ' ' . Locale::decimal($fiatValue, 2);
    $title = 'SC value: ' . $scDisplay;

    return [
        'value' => '<span title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($fiatDisplay, ENT_QUOTES, 'UTF-8') . '</span>',
        'tooltip' => $title,
    ];
};

$balanceCard = $formatCardValue($balanceNum);
$avgTxCard = $formatCardValue($avgTxNum);
$avgInCard = $formatCardValue($avgInNum);
$avgOutCard = $formatCardValue($avgOutNum);

$fiatCurrency = in_array($currencyCookie, ['usd', 'eur'], true) ? $currencyCookie : 'usd';
$fiatCode = strtoupper($fiatCurrency);
$fiatSymbol = $fiatCurrency === 'eur' ? 'EUR ' : 'USD ';
$ratesByDate = [];

if ($count > 0) {
    $startIso = $firstDate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\T00:00:00\Z');
    $endIso = $lastDate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\T23:59:59\Z');
    $rateEndpoint = '/api/v1/daily/exchange_rate?start=' . rawurlencode($startIso) . '&end=' . rawurlencode($endIso);
    $rateData = ApiClient::fetchJson($rateEndpoint, true, 'day');
    if (is_array($rateData)) {
        foreach ($rateData as $rateRow) {
            if (!is_array($rateRow) || !isset($rateRow['date'])) {
                continue;
            }
            $dateKey = substr((string) $rateRow['date'], 0, 10);
            if (isset($rateRow[$fiatCurrency]) && is_numeric($rateRow[$fiatCurrency])) {
                $ratesByDate[$dateKey] = (float) $rateRow[$fiatCurrency];
            }
        }
    }
}

foreach ($subsidyEvents as &$entry) {
    $dateKey = substr((string) ($entry['timestamp'] ?? ''), 0, 10);
    $rate = $ratesByDate[$dateKey] ?? null;
    $entry['fiatRate'] = $rate;
    $entry['valueFiat'] = $rate !== null ? ((float) $entry['value']) * $rate : null;
}
unset($entry);

render_header('SiaGraph - Foundation Subsidy Tracker', 'SiaGraph - Foundation Subsidy Tracker', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/foundation-subsidy-tracker.css'), ENT_QUOTES, 'UTF-8') . '">'
]);
?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-bank me-2"></i>Foundation Subsidy Tracker</h1>
    <p class="text-center text-gray-400 text-sm mb-3">
        Context: Transparency reports from the Sia Foundation provide broader treasury and governance updates.
        <a href="https://www.siafoundation.com/#reports" target="_blank" rel="noopener" class="underline text-gray-300">Read reports</a>.
    </p>
    <div class="flex justify-center mb-3">
        <a href="https://www.siafoundation.com/#reports"
           target="_blank"
           rel="noopener"
           class="foundation-reports-link"
           title="Open Sia Foundation Transparency Reports">
            <i class="bi bi-journal-text" aria-hidden="true"></i>
            <span>Transparency Reports</span>
            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
        </a>
    </div>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-wallet2',
                'label' => 'Current Balance',
                'value' => $balanceCard['value'],
                'tooltip' => $balanceCard['tooltip'],
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-receipt',
                'label' => 'Avg Tx Size',
                'value' => $avgTxCard['value'],
                'tooltip' => $avgTxCard['tooltip'],
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-arrow-down-circle',
                'label' => 'Avg Monthly In',
                'value' => $avgInCard['value'],
                'tooltip' => $avgInCard['tooltip'],
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-arrow-up-circle',
                'label' => 'Avg Monthly Out',
                'value' => $avgOutCard['value'],
                'tooltip' => $avgOutCard['tooltip'],
            ]);
            ?>
            </div>
        </div>
    </div>
    <div class="sg-container__row">
        <div class="sg-container__row-content">
            <div class="sg-container__column">
                <section class="card">
                    <h2 class="card__heading">
                        Subsidy Events
                        <a href="https://www.siafoundation.com/#reports"
                           target="_blank"
                           rel="noopener"
                           class="ms-2 text-gray-400 hover:text-gray-200"
                           title="For official governance and spending context, see Sia Foundation Transparency Reports.">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            <span class="visually-hidden">Transparency reports context</span>
                        </a>
                    </h2>
                    <div class="card__content overflow-x-auto">
                        <table id="foundationTable" class="table table-dark table-clean table-sm text-white w-full text-sm sm:text-base border-collapse table-loading">
                            <thead></thead>
                            <tbody id="foundationTableBody"><tr><td colspan="5" class="px-3 py-3"><span class="skeleton-line"></span></td></tr></tbody>
                        </table>
                    </div>
                    <div class="px-3 pb-3 text-xs text-gray-400">
                        For additional context beyond on-chain activity, review
                        <a href="https://www.siafoundation.com/#reports" target="_blank" rel="noopener" class="underline text-gray-300">Sia Foundation Transparency Reports</a>.
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const subsidyEvents = <?php echo json_encode($subsidyEvents, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  const fiatCode = <?php echo json_encode($fiatCode); ?>;
  const fiatSymbol = <?php echo json_encode($fiatSymbol); ?>;
  let sortState = { key: 'timestamp', dir: 'desc' };

  function isCompactFoundationView() {
    const container = document.querySelector('.card__content.overflow-x-auto');
    const availableWidth = container ? container.clientWidth : window.innerWidth;
    return availableWidth < 980;
  }

  function showTable() {
    const tbl = document.getElementById('foundationTable');
    if (tbl) tbl.classList.remove('table-loading');
  }

  function hideTable() {
    const tbl = document.getElementById('foundationTable');
    if (tbl) tbl.classList.add('table-loading');
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatLocalizedTime(timestamp) {
    try {
      if (typeof getLocalizedTime === 'function') return getLocalizedTime(timestamp);
    } catch (e) {}
    return new Date(timestamp).toLocaleString(window.APP_LOCALE || undefined);
  }

  function getShortLocalizedTime(timestamp) {
    const d = new Date(timestamp);
    if (Number.isNaN(d.getTime())) return escapeHtml(timestamp);
    const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
    return new Intl.DateTimeFormat(loc, {
      month: 'short',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    }).format(d);
  }

  function formatValueSC(value) {
    const n = Number(value) || 0;
    const abs = Math.abs(n);
    const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
    const formatted = abs.toLocaleString(loc, { maximumFractionDigits: 0 });
    return `${n < 0 ? '-' : ''}${formatted}`;
  }

  function getSortValue(entry, key) {
    switch (key) {
      case 'timestamp':
        return new Date(entry.timestamp).getTime();
      case 'height':
        return Number(entry.height) || 0;
      case 'type':
        return String(entry.type || '').toLowerCase();
      case 'value':
        return Number(entry.value) || 0;
      case 'valueFiat':
        return entry.valueFiat === null || entry.valueFiat === undefined ? Number.NEGATIVE_INFINITY : Number(entry.valueFiat);
      default:
        return 0;
    }
  }

  function formatFiatValue(value) {
    if (value === null || value === undefined || !Number.isFinite(Number(value))) return 'N/A';
    const n = Number(value);
    const abs = Math.abs(n);
    const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
    const decimals = abs >= 1 ? 2 : 4;
    const formatted = abs.toLocaleString(loc, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    return `${n < 0 ? '-' : ''}${fiatSymbol}${formatted}`;
  }

  function getSortedEvents() {
    const sorted = [...subsidyEvents];
    sorted.sort((a, b) => {
      const aValue = getSortValue(a, sortState.key);
      const bValue = getSortValue(b, sortState.key);
      if (aValue === bValue) return 0;
      if (sortState.dir === 'asc') return aValue > bValue ? 1 : -1;
      return aValue < bValue ? 1 : -1;
    });
    return sorted;
  }

  function getSortArrow(key) {
    if (sortState.key !== key) return '';
    return sortState.dir === 'asc' ? ' ▲' : ' ▼';
  }

  function renderSortButton(label, key, extraClasses = '') {
    return `<button type="button" class="table-sort-btn ${extraClasses}" data-sort-key="${key}">${label}${getSortArrow(key)}</button>`;
  }

  function bindSortHandlers() {
    const buttons = document.querySelectorAll('#foundationTable thead .table-sort-btn[data-sort-key]');
    buttons.forEach(button => {
      button.addEventListener('click', function () {
        const nextKey = this.getAttribute('data-sort-key');
        if (!nextKey) return;
        if (sortState.key === nextKey) {
          sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
        } else {
          sortState.key = nextKey;
          sortState.dir = nextKey === 'timestamp' ? 'desc' : 'asc';
        }
        populateTable();
      });
    });
  }

  function renderTypePill(entry) {
    const label = escapeHtml(entry.type);
    if (!entry.txId) {
      return `<span class="pill">${label}</span>`;
    }
    const href = `https://siascan.com/tx/${encodeURIComponent(entry.txId)}`;
    return `<a class="tx-link" href="${href}" target="_blank" rel="noopener" title="View transaction on SiaScan"><span class="pill tx-pill">${label} <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></span></a>`;
  }

  function renderTableHeader() {
    const thead = document.querySelector('#foundationTable thead');
    if (!thead) return;
    const compact = isCompactFoundationView();
    const table = document.getElementById('foundationTable');
    if (table) table.classList.toggle('compact-table', compact);

    if (compact) {
      thead.innerHTML = `
        <tr class="bg-primary-400 text-white">
          <th class="px-3 py-2 timestamp-col">${renderSortButton('Timestamp', 'timestamp')}</th>
          <th class="px-3 py-2 type-col">${renderSortButton('Event', 'type')}</th>
        </tr>
      `;
    } else {
      thead.innerHTML = `
        <tr class="bg-primary-400 text-white">
          <th class="px-3 py-2 timestamp-col">${renderSortButton('Timestamp', 'timestamp')}</th>
          <th class="px-3 py-2 height-col num">${renderSortButton('Block Height', 'height', 'num')}</th>
          <th class="px-3 py-2 type-col">${renderSortButton('Type', 'type')}</th>
          <th class="px-3 py-2 value-col num">${renderSortButton('Value (SC)', 'value', 'num')}</th>
          <th class="px-3 py-2 fiat-col num">${renderSortButton(`Value Fiat (${fiatCode})`, 'valueFiat', 'num')}</th>
        </tr>
      `;
    }
    bindSortHandlers();
  }

  function populateTable() {
    renderTableHeader();
    const tableBody = document.getElementById('foundationTableBody');
    if (!tableBody) return;

    if (!subsidyEvents.length) {
      const colspan = isCompactFoundationView() ? 2 : 5;
      tableBody.innerHTML = `<tr><td colspan="${colspan}" class="text-center">No subsidy transactions found</td></tr>`;
      showTable();
      return;
    }

    const compact = isCompactFoundationView();
    const sorted = getSortedEvents();
    let rows = '';

    sorted.forEach((entry, index) => {
      const rowClass = ((index + 1) % 2 === 0) ? 'bg-gray-800' : 'bg-gray-900';
      const valueClass = Number(entry.value) < 0 ? 'text-red-400' : 'text-green-400';
      const valueText = formatValueSC(entry.value);
      const fiatText = formatFiatValue(entry.valueFiat);
      const localized = formatLocalizedTime(entry.timestamp);
      const shortLocalized = getShortLocalizedTime(entry.timestamp);
      const typePill = renderTypePill(entry);

      if (compact) {
        rows += `
          <tr class="${rowClass}">
            <td class="border px-3 py-2 timestamp-col" data-timestamp="${escapeHtml(entry.timestamp)}">
              <span class="timestamp-short" title="${escapeHtml(localized)}">${escapeHtml(shortLocalized)}</span>
            </td>
            <td class="border px-3 py-2 type-col">
              <div>${typePill}</div>
              <div class="bench-detail-list text-xs mt-1">
                <div class="bench-detail-row"><span class="bench-detail-label">Height</span><span class="num">${escapeHtml(entry.height)}</span></div>
                <div class="bench-detail-row"><span class="bench-detail-label">Value</span><span class="num ${valueClass}">${escapeHtml(valueText)} SC</span></div>
                <div class="bench-detail-row"><span class="bench-detail-label">Fiat</span><span class="num ${valueClass}">${escapeHtml(fiatText)}</span></div>
              </div>
            </td>
          </tr>`;
      } else {
        rows += `
          <tr class="${rowClass}">
            <td class="border px-3 py-2 timestamp-col" data-timestamp="${escapeHtml(entry.timestamp)}">${escapeHtml(localized)}</td>
            <td class="border px-3 py-2 height-col num">${escapeHtml(entry.height)}</td>
            <td class="border px-3 py-2 type-col">${typePill}</td>
            <td class="border px-3 py-2 value-col num ${valueClass}">${escapeHtml(valueText)}</td>
            <td class="border px-3 py-2 fiat-col num ${valueClass}">${escapeHtml(fiatText)}</td>
          </tr>`;
      }
    });

    tableBody.innerHTML = rows;
    showTable();
  }

  hideTable();
  populateTable();

  let lastCompact = isCompactFoundationView();
  window.addEventListener('resize', () => {
    const nowCompact = isCompactFoundationView();
    if (nowCompact !== lastCompact) {
      lastCompact = nowCompact;
      populateTable();
    }
  });
});
</script>
<?php render_footer(); ?>
