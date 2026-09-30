<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
require_once dirname(__DIR__) . '/include/components/stat_card.php';
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
$totalInCard = $formatCardValue($totalInNum);
$totalOutCard = $formatCardValue($totalOutNum);
$netFlowCard = $formatCardValue($totalInNum - $totalOutNum);
$lastActivityText = $count ? Locale::date(reset($subsidyEvents)['timestamp']) : 'No activity';
$siagraphBaseUrl = rtrim((string) ($SETTINGS['siagraph_base_url'] ?? ''), '/');
$addressExplorerUrl = ($siagraphBaseUrl !== '' ? $siagraphBaseUrl : 'https://explorer.siagraph.info') . '/address/' . rawurlencode(FOUNDATION_ADDR);

$fiatCurrency = in_array($currencyCookie, ['usd', 'eur'], true) ? $currencyCookie : 'usd';
$fiatCode = strtoupper($fiatCurrency);
$fiatSymbol = $fiatCurrency === 'eur' ? 'EUR ' : 'USD ';
$ratesByDate = [];

if ($count > 0) {
    $startIso = $firstDate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\T00:00:00\Z');
    $endIso = $lastDate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\T23:59:59\Z');
    $rateEndpoint = '/api/v1/daily/exchange_rate?start=' . rawurlencode($startIso) . '&end=' . rawurlencode($endIso);
    $rateData = ApiClient::fetchJson($rateEndpoint, true, 'hour');
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
<section id="main-content" class="sg-container foundation-page">
    <section class="card foundation-hero">
        <div class="foundation-hero__main">
            <div class="foundation-hero__kicker">On-chain address activity</div>
            <h1 class="foundation-hero__title">Foundation Subsidy Address</h1>
            <p class="foundation-hero__subtitle">
                Tracks observed chain activity for the subsidy address. Foundation transparency reports provide off-chain spending and governance context.
            </p>
            <div class="foundation-address-box">
                <span class="foundation-address-box__label">Address</span>
                <code class="foundation-address-box__value"><?php echo htmlspecialchars(FOUNDATION_ADDR, ENT_QUOTES, 'UTF-8'); ?></code>
            </div>
        </div>
        <div class="foundation-hero__actions">
            <a href="<?php echo htmlspecialchars($addressExplorerUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="foundation-action-link">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                <span>View address</span>
            </a>
            <a href="https://www.siafoundation.com/#reports" target="_blank" rel="noopener" class="foundation-action-link foundation-action-link--secondary">
                <i class="bi bi-journal-text" aria-hidden="true"></i>
                <span>Transparency reports</span>
            </a>
        </div>
    </section>

    <section class="foundation-stat-grid" aria-label="Subsidy address summary">
        <article class="foundation-stat-card">
            <div class="foundation-stat-card__label">Current balance</div>
            <div class="foundation-stat-card__value"><?php echo $balanceCard['value']; ?></div>
        </article>
        <article class="foundation-stat-card">
            <div class="foundation-stat-card__label">Total received</div>
            <div class="foundation-stat-card__value foundation-stat-card__value--in"><?php echo $totalInCard['value']; ?></div>
        </article>
        <article class="foundation-stat-card">
            <div class="foundation-stat-card__label">Total sent</div>
            <div class="foundation-stat-card__value foundation-stat-card__value--out"><?php echo $totalOutCard['value']; ?></div>
        </article>
        <article class="foundation-stat-card">
            <div class="foundation-stat-card__label">Net flow</div>
            <div class="foundation-stat-card__value"><?php echo $netFlowCard['value']; ?></div>
        </article>
        <article class="foundation-stat-card">
            <div class="foundation-stat-card__label">Events tracked</div>
            <div class="foundation-stat-card__value"><?php echo htmlspecialchars(Locale::integer($count), ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="foundation-stat-card">
            <div class="foundation-stat-card__label">Last activity</div>
            <div class="foundation-stat-card__value foundation-stat-card__value--small"><?php echo htmlspecialchars($lastActivityText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
    </section>

    <section class="foundation-layout">
        <section class="card foundation-flow-card">
            <h2 class="card__heading">Activity Flow</h2>
            <p class="foundation-section-subtitle">Monthly subsidy address inflows and outflows from observed on-chain events.</p>
            <div class="foundation-flow-chart-wrap">
                <canvas id="foundationFlowChart"></canvas>
            </div>
        </section>

        <aside class="card foundation-context-card">
            <h2 class="card__heading">Context</h2>
            <p>
                SiaGraph derives these values from chain activity involving the subsidy address. The chain shows movement, timing, and amounts; it does not explain spending purpose.
            </p>
            <p>
                Use the Foundation transparency reports for off-chain allocation, governance, and spending context.
            </p>
            <a href="https://www.siafoundation.com/#reports" target="_blank" rel="noopener" class="foundation-context-card__link">
                Open transparency reports
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
            </a>
        </aside>
    </section>

    <section class="card foundation-activity-card">
        <h2 class="card__heading">Activity Log</h2>
        <p class="foundation-section-subtitle">Complete observed event history for the subsidy address. Newest events are shown first.</p>
        <div id="foundationActivityLog" class="foundation-activity-list"></div>
    </section>
</section>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const subsidyEvents = <?php echo json_encode($subsidyEvents, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  const fiatCode = <?php echo json_encode($fiatCode); ?>;
  const fiatSymbol = <?php echo json_encode($fiatSymbol); ?>;
  const siagraphBaseUrl = <?php echo json_encode($siagraphBaseUrl, JSON_UNESCAPED_SLASHES); ?>;

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

  function formatValueSC(value) {
    const n = Number(value) || 0;
    const abs = Math.abs(n);
    const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
    const formatted = abs.toLocaleString(loc, { maximumFractionDigits: 0 });
    return `${n < 0 ? '-' : ''}${formatted}`;
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
      const aValue = new Date(a.timestamp).getTime();
      const bValue = new Date(b.timestamp).getTime();
      if (aValue === bValue) return 0;
      return aValue < bValue ? 1 : -1;
    });
    return sorted;
  }

  function renderTypePill(entry) {
    const label = escapeHtml(entry.type);
    if (!entry.txId) {
      return `<span class="pill">${label}</span>`;
    }
    const href = `${siagraphBaseUrl || ''}/tx/${encodeURIComponent(entry.txId)}`;
    return `<a class="tx-link" href="${href}" target="_blank" rel="noopener" title="View transaction on SiaGraph"><span class="pill tx-pill">${label} <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></span></a>`;
  }

  function getDirection(entry) {
    const value = Number(entry.value) || 0;
    if (value > 0) return { label: 'Inflow', className: 'foundation-flow-in' };
    if (value < 0) return { label: 'Outflow', className: 'foundation-flow-out' };
    return { label: 'No net change', className: 'foundation-flow-neutral' };
  }

  function renderActivityLog() {
    const target = document.getElementById('foundationActivityLog');
    if (!target) return;
    const events = getSortedEvents();

    if (!events.length) {
      target.innerHTML = '<div class="foundation-empty-state">No subsidy address activity found.</div>';
      return;
    }

    target.innerHTML = events.map((entry) => {
      const direction = getDirection(entry);
      const valueClass = Number(entry.value) < 0 ? 'text-red-400' : 'text-green-400';
      const valueText = `${formatValueSC(entry.value)} SC`;
      const fiatText = formatFiatValue(entry.valueFiat);
      return `
        <article class="foundation-activity-item">
          <div class="foundation-activity-item__status">
            <span class="foundation-flow-pill ${direction.className}">${direction.label}</span>
          </div>
          <div class="foundation-activity-item__event">
            <div class="foundation-activity-item__time">${escapeHtml(formatLocalizedTime(entry.timestamp))}</div>
            <div class="foundation-activity-item__meta">Height ${escapeHtml(entry.height)}</div>
          </div>
          <div class="foundation-activity-item__type">
            ${renderTypePill(entry)}
          </div>
          <div class="foundation-activity-item__value">
            <strong class="${valueClass}">${escapeHtml(valueText)}</strong>
            <span>${escapeHtml(fiatText)}</span>
          </div>
        </article>
      `;
    }).join('');
  }

  function renderFlowChart() {
    const canvas = document.getElementById('foundationFlowChart');
    if (!canvas || typeof Chart !== 'function') return;

    const buckets = new Map();
    subsidyEvents.forEach((entry) => {
      const d = new Date(entry.timestamp);
      if (Number.isNaN(d.getTime())) return;
      const key = `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`;
      if (!buckets.has(key)) buckets.set(key, { in: 0, out: 0 });
      const bucket = buckets.get(key);
      const value = Number(entry.value) || 0;
      if (value >= 0) bucket.in += value;
      if (value < 0) bucket.out += Math.abs(value);
    });

    const keys = Array.from(buckets.keys()).sort();
    const labels = keys.map((key) => {
      const [year, month] = key.split('-').map(Number);
      const d = new Date(Date.UTC(year, month - 1, 1));
      return d.toLocaleDateString(window.APP_LOCALE || undefined, { month: 'short', year: '2-digit', timeZone: 'UTC' });
    });

    if (window.foundationFlowChart && typeof window.foundationFlowChart.destroy === 'function') {
      window.foundationFlowChart.destroy();
    }

    window.foundationFlowChart = new Chart(canvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Inflow',
            data: keys.map((key) => buckets.get(key).in),
            backgroundColor: 'rgba(72, 187, 120, 0.78)',
            borderColor: 'rgba(72, 187, 120, 1)',
            borderWidth: 1
          },
          {
            label: 'Outflow',
            data: keys.map((key) => -buckets.get(key).out),
            backgroundColor: 'rgba(248, 113, 113, 0.78)',
            borderColor: 'rgba(248, 113, 113, 1)',
            borderWidth: 1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#d8d5cf' } },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${formatValueSC(Math.abs(Number(ctx.raw) || 0))} SC`
            }
          }
        },
        scales: {
          x: { ticks: { color: '#cfc9d8' }, grid: { color: 'rgba(255,255,255,0.05)' } },
          y: {
            ticks: {
              color: '#cfc9d8',
              callback: (value) => `${formatValueSC(value)} SC`
            },
            grid: { color: 'rgba(255,255,255,0.08)' }
          }
        }
      }
    });
  }

  renderActivityLog();
  renderFlowChart();
});
</script>
<?php render_footer(); ?>
