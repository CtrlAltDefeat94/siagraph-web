<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';
require_once 'include/components/range_controls.php';
require_once 'include/components/stat_card.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;

$months = 12; // default visible range; slider covers all data
$metricsEndpoint = '/api/v1/monthly/metrics';
$growthEndpoint = '/api/v1/monthly/growth';
$intervalDefault = 'month';
$latestData = ApiClient::fetchJson($metricsEndpoint);
$dataError = !is_array($latestData);
$latest = $dataError ? [] : end($latestData);
$asOf = !$dataError && isset($latest['date']) ? $latest['date'] : null;
$prev = [];
?>
<?php render_header("SiaGraph - Storage"); ?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-hdd-network me-2"></i>Storage</h1>
    <?php if ($dataError): ?>
        <p class="text-center text-muted">Latest metrics unavailable.</p>
    <?php endif; ?>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fourth">
                <?php
                $valUS = isset($latest['utilized_storage']) ? Locale::decimal($latest['utilized_storage']/1e15,2).' PB' : 'N/A';
                render_stat_card([
                    'icon' => 'bi bi-hdd-fill',
                    'label' => 'Utilized Storage',
                    'value' => $valUS,
                    'context' => $asOf ? ('Monthly snapshot as of ' . $asOf) : 'Monthly snapshot',
                    'tooltip' => 'Data currently stored by renters across the network',
                ]);
                ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <?php
                $valTS = isset($latest['total_storage']) ? Locale::decimal($latest['total_storage']/1e15,2).' PB' : 'N/A';
                render_stat_card([
                    'icon' => 'bi bi-hdd-network',
                    'label' => 'Total Storage',
                    'value' => $valTS,
                    'context' => $asOf ? ('Monthly snapshot as of ' . $asOf) : 'Monthly snapshot',
                    'tooltip' => 'Aggregate capacity hosts are making available',
                ]);
                ?>
            </div>
            
        </div>
    </div>
    <div class="sg-container__row">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Network Storage</h2>
                <div class="card__content">
                <?php
                renderGraph(
                    'metrics-storage',
                    [
                        $graphConfigs['utilized_storage'],
                        $graphConfigs['total_storage']
                    ],
                    'date',
                    $metricsEndpoint,
                    null,
                    'line',
                    $intervalDefault,
                    true,
                    'true',
                    $months,
                    'false',
                    'bytes'
                );
                ?>
                </div>
            </section>
        </div>
        </div>
    </div>

    <div class="sg-container__row mt-4">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Storage Utilization %</h2>
                <div class="card__content">
                <?php
                renderGraph(
                    'utilization-percentage-inline',
                    [
                        $graphConfigs['utilization_percent']
                    ],
                    'date',
                    $metricsEndpoint,
                    null,
                    'line',
                    $intervalDefault,
                    true,
                    'true',
                    $months,
                    'false',
                    'percentage'
                );
                ?>
                </div>
            </section>
        </div>
        </div>
    </div>
    <div class="sg-container__row mt-4">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Storage Forecast</h2>
                <div class="card__content">
                <div class="text-end mb-2">
                    <button id="scaleToggle" class="btn btn-primary btn-sm">Switch to Logarithmic Forecast</button>
                </div>
                <div class="graph-container">
                    <canvas id="forecastChart" height="500" style="max-height:80vh !important;width:100% !important;"></canvas>
                </div>
                </div>
            </section>
        </div>
        </div>
    </div>
    <script>
        async function fetchGrowthData() {
            return await fetchWithCache('<?php echo $growthEndpoint; ?>');
        }

        function formatPB(value) {
            return (value / 1e15).toFixed(2) + ' PB';
        }

        document.addEventListener('DOMContentLoaded', async function () {
            try {
                const rawData = await fetchGrowthData();
                const data = rawData.slice(-5);
                const actualLen = data.length;
                const labels = data.map(d => d.date);
                const utilized = data.map(d => Number(d.utilized_storage));
                const total = data.map(d => Number(d.total_storage));

                const monthsForRate = Math.min(5, data.length - 1);
                const utilRate = (utilized[utilized.length - 1] - utilized[utilized.length - 1 - monthsForRate]) / monthsForRate;
                const totalRate = (total[total.length - 1] - total[total.length - 1 - monthsForRate]) / monthsForRate;

                const utilBase = utilized[utilized.length - 1 - monthsForRate];
                const totalBase = total[total.length - 1 - monthsForRate];
                const utilFactor = utilBase > 0 ? Math.pow(utilized[utilized.length - 1] / utilBase, 1 / monthsForRate) : 1;
                const totalFactor = totalBase > 0 ? Math.pow(total[total.length - 1] / totalBase, 1 / monthsForRate) : 1;

                function computePredictions(logarithmic) {
                    const predUtil = new Array(actualLen + 24).fill(null);
                    const predTot = new Array(actualLen + 24).fill(null);
                    predUtil[actualLen - 1] = utilized[actualLen - 1];
                    predTot[actualLen - 1] = total[actualLen - 1];
                    let lastU = Number(utilized[actualLen - 1]);
                    let lastT = Number(total[actualLen - 1]);
                    for (let i = 1; i <= 24; i++) {
                        if (logarithmic) {
                            lastU = Math.max(0, lastU * utilFactor);
                            lastT = Math.max(0, lastT * totalFactor);
                        } else {
                            lastU = Math.max(0, lastU + utilRate);
                            lastT = Math.max(0, lastT + totalRate);
                        }
                        predUtil[actualLen - 1 + i] = lastU;
                        predTot[actualLen - 1 + i] = lastT;
                    }
                    return { predUtil, predTot };
                }

                const labelsEnd = moment(labels[labels.length - 1]);
                for (let i = 1; i <= 24; i++) {
                    const nextDate = moment(labelsEnd).add(i, 'months').format('YYYY-MM-01');
                    labels.push(nextDate);
                    utilized.push(null);
                    total.push(null);
                }

                const initialPred = computePredictions(false);
                let predUtilized = initialPred.predUtil;
                let predTotal = initialPred.predTot;

                const ctx = document.getElementById('forecastChart').getContext('2d');
                const chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Utilized Storage',
                                data: utilized,
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 2,
                                fill: false
                            },
                            {
                                label: 'Total Storage',
                                data: total,
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                borderColor: 'rgba(255, 99, 132, 1)',
                                borderWidth: 2,
                                fill: false
                            },
                            {
                                label: 'Predicted Utilized',
                                data: predUtilized,
                                borderColor: 'rgba(75, 192, 192, 0.5)',
                                borderDash: [5, 5],
                                borderWidth: 2,
                                fill: false,
                                spanGaps: true
                            },
                            {
                                label: 'Predicted Total',
                                data: predTotal,
                                borderColor: 'rgba(255, 99, 132, 0.5)',
                                borderDash: [5, 5],
                                borderWidth: 2,
                                fill: false,
                                spanGaps: true
                            }
                        ]
                    },
                    options: {
                        // Match interaction behavior of other charts: show all series on x hover
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: true },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: ctx => ctx.dataset.label + ': ' + formatPB(ctx.parsed.y)
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: { unit: 'month', displayFormats: { month: 'MMM YY' } }
                            },
                            y: {
                                type: 'linear',
                                beginAtZero: true,
                                ticks: { callback: value => formatPB(value) }
                            }
                        }
                    }
                });

                document.getElementById('scaleToggle').addEventListener('click', function () {
                    const yAxis = chart.options.scales.y;
                    const toLog = yAxis.type === 'linear';
                    if (toLog) {
                        yAxis.type = 'logarithmic';
                        this.textContent = 'Switch to Linear Forecast';
                    } else {
                        yAxis.type = 'linear';
                        this.textContent = 'Switch to Log Scale';
                    }
                    const preds = computePredictions(toLog);
                    predUtilized = preds.predUtil;
                    predTotal = preds.predTot;
                    chart.data.datasets[2].data = predUtilized;
                    chart.data.datasets[3].data = predTotal;
                    chart.update();
                });
            } catch (e) {
                console.error('Failed to render storage forecast', e);
            }
        });
    </script>
</section>
<?php render_footer(); ?>
