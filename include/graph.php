<?php
function renderGraph(
    $canvasid,
    $datasets,
    $dateKey,
    $jsonUrl = null,
    $jsonData = null,
    $charttype = 'line',
    $interval = "day",
    $rangeslider = true,
    $displaylegend = "true",
    $defaultrangeinmonths = 3,
    $displayYAxis = "true",
    $unitType = 'bytes',
    $jsonKey = null
) {
    $encodedDatasets = json_encode($datasets);
    ?>
    <div id="canvasContainer-<?php echo htmlspecialchars($canvasid, ENT_QUOTES, 'UTF-8'); ?>">
        <canvas id="<?php echo htmlspecialchars($canvasid, ENT_QUOTES, 'UTF-8'); ?>"
            style="height:500px !important;width: 100% !important;"></canvas>

        <?php if ($rangeslider && $charttype !== 'pie'): ?>
            <div id="dateRangeSlider-<?php echo htmlspecialchars($canvasid, ENT_QUOTES, 'UTF-8'); ?>"></div>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                (function () {
                    var options = {
                        canvasId: "<?php echo htmlspecialchars($canvasid, ENT_QUOTES, 'UTF-8'); ?>",
                        jsonData: <?php echo $jsonData ? json_encode($jsonData) : 'null'; ?>,
                        jsonUrl: "<?php echo htmlspecialchars($jsonUrl, ENT_QUOTES); ?>",
                        unitType: "<?php echo $unitType; ?>",
                        datasets: <?php echo $encodedDatasets; ?>,
                        interval: "<?php echo $interval; ?>",
                        displaylegend: "<?php echo $displaylegend; ?>",
                        charttype: "<?php echo $charttype; ?>",
                        displayYAxis: "<?php echo $displayYAxis; ?>",
                        defaultrangeinmonths: <?php echo $defaultrangeinmonths; ?>,
                        rangeslider: <?php echo $rangeslider ? 'true' : 'false'; ?>,
                        dateKey: "<?php echo $dateKey; ?>",
                        jsonKey: "<?php echo $jsonKey; ?>"
                    };

                    new GraphRenderer(options);
                })();
            });
        </script>
    </div>
    <?php
}
?>
<script>
    class GraphRenderer {
        constructor(options) {
            this.canvasId = options.canvasId;
            this.jsonData = options.jsonData;
            this.jsonUrl = options.jsonUrl;
            this.unitType = options.unitType;
            this.datasets = options.datasets;
            this.interval = options.interval;
            this.displaylegend = options.displaylegend;
            this.charttype = options.charttype;
            this.displayYAxis = options.displayYAxis;
            this.defaultrangeinmonths = options.defaultrangeinmonths;
            this.rangeslider = options.rangeslider;
            this.dateKey = options.dateKey;
            this.startDateIndex = null;
            this.endDateIndex = null;
            this.chart = null;
            this.datasetVisibility = [];
            this.monthlyData = null;
            this.dates = [];
            this.datasetsConfig = [];
            this.jsonKey = options.jsonKey;

            this.fetchData();
        }
        fetchData() {

            if (this.jsonData) {
                const parsedData = typeof this.jsonData === 'string' ? JSON.parse(this.jsonData) : this.jsonData;
                this.monthlyData = this.jsonKey ? parsedData[this.jsonKey] : parsedData;
                //this.monthlyData =parsedData;
                this.initialize();
            } else {
                fetch(this.jsonUrl)
                    .then((response) => response.json())
                    .then((jsonData) => {
                        this.monthlyData = this.jsonKey ? jsonData[this.jsonKey] : jsonData;
                        //this.monthlyData =jsonData;
                        this.initialize();
                    })
                    .catch(error => console.error(error));
            }
        }

        initialize() {
            this.dates = this.monthlyData.map((item) => item[this.dateKey]);
            this.datasetsConfig = this.datasets.map((dataset) => {
                const transformFunc = dataset.transform ? new Function('entry', dataset.transform) : null;
                const dataValues = this.monthlyData.map((entry) => transformFunc ? transformFunc(entry) : entry[dataset.key]);
                return {
                    label: dataset.label,
                    data: dataValues,
                    backgroundColor: dataset.backgroundColor,
                    borderColor: dataset.borderColor,
                    borderWidth: 2,
                    unit: dataset.unit,
                    unitDivisor: dataset.unitDivisor,
                    decimalPlaces: dataset.decimalPlaces || 0,
                    startAtZero: dataset.startAtZero || false,
                    hidden: dataset.hidden || false
                };
            });
            this.datasetVisibility = this.datasetsConfig.map(ds => ds.hidden);
            this.calculateInitialDateRange();
            this.updateChart(this.startDateIndex, this.endDateIndex);
            if (this.rangeslider && this.charttype !== 'pie') {
                this.initializeSlider();
            }
        }

        calculateInitialDateRange() {
            const startOffsetMonths = this.defaultrangeinmonths;
            const currentDate = new Date();
            const startDate = new Date(currentDate);
            startDate.setMonth(startDate.getMonth() - startOffsetMonths);
            this.startDateIndex = this.dates.findIndex(date => new Date(date) >= startDate);
            this.startDateIndex = this.startDateIndex === -1 ? 0 : this.startDateIndex;
            this.endDateIndex = this.dates.length - 1;
        }

        formatTooltipLabel(context) {
            let label = '';
            if (context.dataset.label) {
                label = context.dataset.label + ': ';
            }
            let value = context.parsed.y;
            const decimalPlaces = context.dataset.decimalPlaces;
            if (context.dataset.unit) {
                value = value / context.dataset.unitDivisor;
                label += value.toFixed(decimalPlaces).replace(/\d(?=(\d{3})+\.)/g, '$&,') + ' ' + context.dataset.unit;
            } else {
                label += value.toFixed(decimalPlaces).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            }
            return label;
        }

        formatValue(value) {
            const bytesDivisors = { bytes: 1, KB: 1e3, MB: 1e6, GB: 1e9, TB: 1e12, PB: 1e15 };
            const metricDivisors = { M: 1e6, B: 1e9, T: 1e12 };
            if (this.unitType === 'bytes') {
                if (value >= bytesDivisors.PB) return (value / bytesDivisors.PB).toFixed(2) + ' PB';
                if (value >= bytesDivisors.TB) return (value / bytesDivisors.TB).toFixed(2) + ' TB';
                if (value >= bytesDivisors.GB) return (value / bytesDivisors.GB).toFixed(2) + ' GB';
                if (value >= bytesDivisors.MB) return (value / bytesDivisors.MB).toFixed(2) + ' MB';
                if (value >= bytesDivisors.KB) return (value / bytesDivisors.KB).toFixed(2) + ' KB';
                return value + ' bytes';
            } else if (this.unitType === 'fiat') {
                if (value >= metricDivisors.T) return (value / metricDivisors.T).toFixed(2) + ' T';
                if (value >= metricDivisors.B) return (value / metricDivisors.B).toFixed(2) + ' B';
                if (value >= metricDivisors.M) return (value / metricDivisors.M).toFixed(2) + ' M';
                return value;
            }
            return value;
        }

        updateChart(startDateIndex, endDateIndex) {
            let filteredDates, filteredDatasets;

            // Ensure proper handling of indexes
            if (this.charttype === 'pie') {
                const dataset = this.datasetsConfig[0];
                filteredDatasets = [{
                    ...dataset,
                    data: dataset.data,
                    backgroundColor: dataset.backgroundColor || this.datasets.map(ds => ds.backgroundColor)
                }];
                filteredDates = [];
            } else {
                // Slice the dates and datasets based on current index range
                filteredDates = this.dates.slice(startDateIndex, endDateIndex + 1);
                filteredDatasets = this.datasetsConfig.map((dataset, index) => ({
                    ...dataset,
                    data: dataset.data.slice(startDateIndex, endDateIndex + 1),
                    hidden: this.datasetVisibility[index]
                }));
            }

            const yBeginAtZero = (this.charttype !== 'pie') && filteredDatasets.some(ds => ds.startAtZero);

            if (this.chart) {
                // Update existing chart data
                this.chart.data.labels = filteredDates;
                this.chart.data.datasets = filteredDatasets;
                if (this.charttype !== 'pie') {
                    this.chart.options.scales.y.beginAtZero = yBeginAtZero;
                }
                this.chart.update();
            } else {
                // Create a new chart instance
                const ctx = document.getElementById(this.canvasId).getContext("2d");
                const options = {
                    type: this.charttype,
                    data: {
                        labels: filteredDates,
                        datasets: filteredDatasets
                    },
                    options: {
                        plugins: {
                            legend: {
                                display: this.displaylegend,
                                onClick: (event, legendItem, legend) => {
                                    const index = legendItem.datasetIndex;
                                    this.datasetVisibility[index] = !this.datasetVisibility[index];
                                    this.chart.getDatasetMeta(index).hidden = !this.datasetVisibility[index];
                                    this.chart.update();
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: context => this.formatTooltipLabel(context)
                                }
                            }
                        },
                        elements: {
                            point: {
                                radius: 0//this.charttype === 'pie' ? 0 : undefined
                            }
                        }
                    }
                };

                if (this.charttype !== 'pie') {
                    options.options.scales = {
                        x: {
                            type: "time",
                            time: {
                                unit: this.interval,
                                displayFormats: {
                                    day: 'D MMM \'YY',
                                    week: 'D MMM \'YY',
                                    month: 'MMM \'YY'
                                }
                            },
                            title: {
                                display: false,
                                text: "Date"
                            }
                        },
                        y: {
                            beginAtZero: yBeginAtZero,
                            title: {
                                display: this.displayYAxis === 'true',
                                text: "Value"
                            },
                            ticks: {
                                callback: value => this.formatValue(value)
                            }
                        }
                    };
                }

                this.chart = new Chart(ctx, options);
            }
        }

        initializeSlider() {
            var dateRangeSlider = document.getElementById("dateRangeSlider-" + this.canvasId);
            if (dateRangeSlider) {
                noUiSlider.create(dateRangeSlider, {
                    start: [this.startDateIndex / (this.dates.length - 1) * 100, 100],
                    connect: true,
                    range: {
                        min: 0,
                        max: 100
                    }
                });

                // Add event listener to slider input
                dateRangeSlider.noUiSlider.on("update", (values, handle) => {
                    var startIndex = Math.round((values[0] / 100) * (this.dates.length - 1));
                    var endIndex = Math.round((values[1] / 100) * (this.dates.length - 1));
                    // Update chart
                    this.updateChart(startIndex, endIndex);
                });
            }
        }
    }
</script>