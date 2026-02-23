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
        this.yAxisTitle = options.yAxisTitle || null;
        this.useFiat = options.useFiat !== undefined ? options.useFiat : true;
        this.currency = options.currency || 'eur';
        if (this.currency === 'sc') {
            this.useFiat = false;
            const unitLower = (this.unitType || '').toLowerCase();
            if (unitLower === 'usd' || unitLower === 'eur' || unitLower === 'sc') {
                this.unitType = 'SC';
            }
        }
        this.startDateIndex = null;
        this.endDateIndex = null;
        this.chart = null;
        this.datasetVisibility = [];
        this.monthlyData = null;
        this.dates = [];
        this.datasetsConfig = [];
        this.jsonKey = options.jsonKey;
        this.yScale = options.yScale || 'linear';
        this.stacked = options.stacked === true;
        this.firstValidIndex = 0;

        this.fetchData();
    }
    fetchData() {

        if (this.jsonData) {
            const parsedData = typeof this.jsonData === 'string' ? JSON.parse(this.jsonData) : this.jsonData;
            const source = this.jsonKey ? parsedData && parsedData[this.jsonKey] : parsedData;
            this.monthlyData = source;
            //this.monthlyData = parsedData;
            this.initialize();
        } else {
            fetchWithCache(this.jsonUrl)
                .then((jsonData) => {
                    const source = this.jsonKey ? jsonData && jsonData[this.jsonKey] : jsonData;
                    this.monthlyData = source;
                    //this.monthlyData = jsonData;
                    this.initialize();
                })
                .catch(error => console.error(error));
        }
    }

    initialize() {
        this.computeDatasetConfig();

        // Determine the first index that has any non-null datapoint
        this.computeFirstValidIndex();

        // Initialize dataset visibility
        this.datasetVisibility = this.datasetsConfig.map(ds => ds.hidden);

        if (this.charttype !== 'pie') {
            this.calculateInitialDateRange();
        }

        this.updateChart(this.startDateIndex, this.endDateIndex);

        if (this.rangeslider && this.charttype !== 'pie') {
            this.initializeSlider();
        }
    }

    computeDatasetConfig() {
        // Only coerce to [] for series charts; pies can use object maps
        if (this.charttype !== 'pie' && !Array.isArray(this.monthlyData)) {
            this.monthlyData = [];
        }
        if (this.charttype === 'pie') {
            const dataset = this.datasets[0];
            const dataValues = [];
            const labels = [];
            const backgroundColors = [];
            const generateColor = (index) => `hsl(${(index * 137.5) % 360}, 70%, 60%)`;
            let index = 0;
            for (let version in this.monthlyData) {
                if (this.monthlyData.hasOwnProperty(version)) {
                    labels.push(version);
                    dataValues.push(this.monthlyData[version]);
                    backgroundColors.push(generateColor(index));
                    index++;
                }
            }
            this.chartDataLabels = labels;
            this.datasetsConfig = [{
                label: dataset.label,
                data: dataValues,
                backgroundColor: backgroundColors,
                borderColor: dataset.borderColor || getComputedStyle(document.documentElement).getPropertyValue('--chart-border').trim(),
                borderWidth: 2,
                unit: dataset.unit,
                unitDivisor: dataset.unitDivisor,
                decimalPlaces: dataset.decimalPlaces || 0,
                startAtZero: dataset.startAtZero || false,
                hidden: dataset.hidden || false
            }];
        } else {
            this.dates = this.monthlyData.map(item => item[this.dateKey]);
            this.datasetsConfig = this.datasets.map((dataset) => {
                const transformFunc = dataset.transform ? new Function('entry', 'useFiat', 'currency', dataset.transform) : null;
                const rawValues = this.monthlyData.map(entry => transformFunc ? transformFunc(entry, this.useFiat, this.currency) : entry[dataset.key]);

                // Determine effective unit + divisor for current currency mode
                const effUnit = this.useFiat ? (dataset.fiatUnit || dataset.unit) : (dataset.scUnit || dataset.unit);
                let effDivisor = this.useFiat ? (dataset.fiatUnitDivisor ?? dataset.unitDivisor) : (dataset.scUnitDivisor ?? dataset.unitDivisor);

                let dataValues = rawValues;
                // Normalize SC datasets to SC base values for charting so axis/ticks format correctly
                if ((this.unitType === 'SC' || this.unitType === 'sc') && effUnit === 'SC' && !transformFunc && effDivisor && effDivisor !== 1) {
                    dataValues = rawValues.map(v => (v == null ? null : v / effDivisor));
                    effDivisor = 1; // Avoid double division in tooltips
                }

                const cfg = {
                    label: dataset.label,
                    data: dataValues,
                    backgroundColor: dataset.backgroundColor,
                    borderColor: dataset.borderColor || getComputedStyle(document.documentElement).getPropertyValue('--chart-border').trim(),
                    borderWidth: 2,
                    unit: effUnit,
                    unitDivisor: effDivisor,
                    decimalPlaces: dataset.decimalPlaces || 0,
                    startAtZero: dataset.startAtZero || false,
                    hidden: dataset.hidden || false
                };

                // Preserve optional dataset-level properties like fill/stack when provided
                if (dataset.fill !== undefined) cfg.fill = dataset.fill;
                if (dataset.stack !== undefined) cfg.stack = dataset.stack;

                // If global stacked option is on for line charts, default-fill and stack datasets together
                if (this.stacked && this.charttype === 'line') {
                    if (cfg.fill === undefined) cfg.fill = true;
                    if (cfg.stack === undefined) cfg.stack = 'stacked';
                }

                return cfg;
            });
        }
    }


    calculateInitialDateRange() {
        const startOffsetMonths = this.defaultrangeinmonths;
        const currentDate = new Date();
        const startDate = new Date(currentDate);
        startDate.setMonth(startDate.getMonth() - startOffsetMonths);
        this.startDateIndex = this.dates.findIndex(date => new Date(date) >= startDate);
        this.startDateIndex = this.startDateIndex === -1 ? 0 : this.startDateIndex;
        // Clamp to first valid (non-null) datapoint
        if (this.startDateIndex < this.firstValidIndex) {
            this.startDateIndex = this.firstValidIndex;
        }
        this.endDateIndex = this.dates.length - 1;
    }

    formatTooltipLabel(context) {
        let label = '';

        if (this.charttype === 'pie') {
            // Access the label from the pie chart's data labels directly
            console.log(context.chart.data);
            const sliceName = context.chart.data.labels[context.dataIndex] || 'Unknown';

            // Prepare the label text with the slice name
            label = sliceName + ': ';

            // Access the raw value directly for pie charts
            const value = context.raw !== undefined ? context.raw : 0;
            const decimalPlaces = context.dataset.decimalPlaces || 0;

            // Format the value with units and divisors, if specified
            if (context.dataset.unit) {
                const scValue = value / context.dataset.unitDivisor;
                if (context.dataset.unit === 'SC') {
                    label += formatSC(scValue);
                } else {
                    const formattedValue = scValue.toFixed(decimalPlaces)
                        .replace(/\d(?=(\d{3})+\.)/g, '$&,');
                    label += `${formattedValue} ${context.dataset.unit}`;
                }
            } else {
                label += value.toFixed(decimalPlaces).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            }
        } else {
            // For non-pie charts (line, bar, etc.), use the standard approach with `context.parsed.y`
            label = context.dataset.label ? context.dataset.label + ': ' : '';
            let value = context.parsed.y;
            const decimalPlaces = context.dataset.decimalPlaces;

            const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
            if (context.dataset.unit) {
                value = value / context.dataset.unitDivisor;
                if (context.dataset.unit === 'SC') {
                    label += formatSC(value);
                } else {
                    label += Number(value).toLocaleString(loc, { minimumFractionDigits: decimalPlaces, maximumFractionDigits: decimalPlaces }) + ' ' + context.dataset.unit;
                }
            } else {
                label += Number(value).toLocaleString(loc, { minimumFractionDigits: decimalPlaces, maximumFractionDigits: decimalPlaces });
            }
        }

        return label;
    }




    formatValue(value, unitType) {
        const bytesDivisors = { bytes: 1, KB: 1e3, MB: 1e6, GB: 1e9, TB: 1e12, PB: 1e15 };
        const metricDivisors = { M: 1e6, B: 1e9, T: 1e12 };
        if (this.unitType === 'bytes') {
            const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
            if (value >= bytesDivisors.PB) return Number(value / bytesDivisors.PB).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' PB';
            if (value >= bytesDivisors.TB) return Number(value / bytesDivisors.TB).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' TB';
            if (value >= bytesDivisors.GB) return Number(value / bytesDivisors.GB).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' GB';
            if (value >= bytesDivisors.MB) return Number(value / bytesDivisors.MB).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MB';
            if (value >= bytesDivisors.KB) return Number(value / bytesDivisors.KB).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' KB';
            return Number(value).toLocaleString(loc) + ' bytes';
        } else if (this.unitType === 'eur') {
            const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
            if (value >= metricDivisors.T) return "EUR " + Number(value / metricDivisors.T).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' T';
            if (value >= metricDivisors.B) return "EUR " + Number(value / metricDivisors.B).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' B';
            if (value >= metricDivisors.M) return "EUR " + Number(value / metricDivisors.M).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' M';
            return "EUR " + Number(value).toLocaleString(loc);

        } else if (this.unitType === 'usd') {
            const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
            if (value >= metricDivisors.T) return "USD " + Number(value / metricDivisors.T).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' T';
            if (value >= metricDivisors.B) return "USD " + Number(value / metricDivisors.B).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' B';
            if (value >= metricDivisors.M) return "USD " + Number(value / metricDivisors.M).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' M';
            return "USD " + Number(value).toLocaleString(loc);

        } else if (this.unitType === 'SC' || this.unitType === 'sc') {
            return formatSC(value);
        } else if (this.unitType === 'difficulty') {
            return formatDifficulty(value);
        } else if (this.unitType === 'percentage') {
            return formatPercent(value);
        } else if (this.unitType === 'scientific') {
            return formatScientific(value);
        }
        return value;
    }

    updateChart(startDateIndex, endDateIndex) {
        // Clamp indices to data bounds and first valid index
        const minIndex = this.firstValidIndex || 0;
        const maxIndex = this.dates.length ? this.dates.length - 1 : 0;
        if (startDateIndex == null) startDateIndex = minIndex;
        if (endDateIndex == null) endDateIndex = maxIndex;
        if (startDateIndex < minIndex) startDateIndex = minIndex;
        if (startDateIndex > maxIndex) startDateIndex = maxIndex;
        if (endDateIndex < startDateIndex) endDateIndex = startDateIndex;
        if (endDateIndex > maxIndex) endDateIndex = maxIndex;
        let filteredDates, filteredDatasets;

        // Ensure proper handling of indexes
        if (this.charttype === 'pie') {
            const dataset = this.datasetsConfig[0];
            filteredDatasets = [{
                ...dataset,
                data: dataset.data,
                backgroundColor: dataset.backgroundColor || this.datasets.map(ds => ds.backgroundColor)
            }];
            filteredDates = this.chartDataLabels;  // Use the stored pie chart labels here
        } else {
            // Slice the dates and datasets based on current index range
            filteredDates = this.dates.slice(startDateIndex, endDateIndex + 1);
            filteredDatasets = this.datasetsConfig.map((dataset, index) => ({
                ...dataset,
                data: dataset.data.slice(startDateIndex, endDateIndex + 1),
                hidden: this.datasetVisibility[index]
            }));
        }

        const yBeginAtZero = (this.charttype !== 'pie') && filteredDatasets.some(ds => ds.startAtZero) && this.yScale !== 'logarithmic';

        // Determine min/max for dynamic axis scaling
        const allValues = [];
        filteredDatasets.forEach(ds => {
            ds.data.forEach(v => {
                const n = (v === null || v === undefined || v === '') ? NaN : Number(v);
                if (Number.isFinite(n)) {
                    allValues.push(n);
                }
            });
        });
        const dataMax = allValues.length ? Math.max(...allValues) : 0;
        const positiveValues = allValues.filter(v => v > 0);
        const dataMinPos = positiveValues.length ? Math.min(...positiveValues) : undefined;
        const suggestedMax = (this.yScale !== 'logarithmic' && dataMax > 0 && dataMax < 0.01) ? dataMax * 1.1 : undefined;

        if (this.chart) {
            // Update existing chart data
            this.chart.data.labels = filteredDates;
            this.chart.data.datasets = filteredDatasets;
            if (this.charttype !== 'pie') {
                this.chart.options.scales.y.beginAtZero = yBeginAtZero;
                if (suggestedMax !== undefined) {
                    this.chart.options.scales.y.suggestedMax = suggestedMax;
                } else {
                    delete this.chart.options.scales.y.suggestedMax;
                }
                if (this.yScale === 'logarithmic') {
                    if (dataMinPos !== undefined) {
                        this.chart.options.scales.y.min = Math.max(dataMinPos / 10, Number.EPSILON);
                    }
                    if (dataMax > 0) {
                        this.chart.options.scales.y.max = dataMax * 1.1;
                    }
                } else {
                    delete this.chart.options.scales.y.min;
                    delete this.chart.options.scales.y.max;
                }
            }
            this.chart.update();
        } else {
            // Create a new chart instance
            const ctx = document.getElementById(this.canvasId).getContext("2d");
            const options = {
                type: this.charttype,
                data: {
                    labels: filteredDates,  // Now this will be set for pie charts as well
                    datasets: filteredDatasets
                },
                options: {
                    plugins: {
                        decimation: this.charttype === 'line' ? { enabled: true, algorithm: 'min-max' } : undefined,
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
                            intersect: this.charttype === 'pie' ? true : false,
                            boxPadding: 6,
                            callbacks: {
                                title: (context) => {
                                    if (this.charttype === 'pie') {
                                        return null; // No title for pie chart
                                    }
                                    const label = context[0].label; // Retrieve the label from the first tooltip item
                                    const dateOnly = new Date(label).toLocaleDateString(); // Format the label as a date
                                    return dateOnly; // Return the formatted date for other chart types
                                },
                                label: (context) => {
                                    if (this.charttype === 'pie') {
                                        const value = context.raw;
                                        const dataLabel = context.chart.data.labels[context.dataIndex]; // actual label like "1.6.0"
                                        const dataset = context.dataset;
                                        const decimalPlaces = dataset.decimalPlaces || 0;

                                        let formattedValue;
                                        if (dataset.unit) {
                                            const scValue = value / dataset.unitDivisor;
                                            if (dataset.unit === 'SC') {
                                                formattedValue = formatSC(scValue);
                                            } else if (dataset.unit === '%') {
                                                formattedValue = formatPercent(scValue);
                                            } else {
                                                formattedValue = scValue.toFixed(decimalPlaces)
                                                    .replace(/\d(?=(\d{3})+\.)/g, '$&,') + ' ' + dataset.unit;
                                            }
                                        } else {
                                            formattedValue = value.toFixed(decimalPlaces).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                                        }

                                        return `${dataLabel}: ${formattedValue}`;
                                    } else {
                                        const dataset = context.dataset;
                                        const value = context.parsed.y;
                                        const decimalPlaces = dataset.decimalPlaces || 0;

                                        let formattedValue;
                                        if (dataset.unit) {
                                            const scValue = value / dataset.unitDivisor;
                                            if (dataset.unit === 'SC') {
                                                formattedValue = formatSC(scValue);
                                            } else {
                                                formattedValue = scValue.toFixed(decimalPlaces)
                                                    .replace(/\d(?=(\d{3})+\.)/g, '$&,') + ' ' + dataset.unit;
                                            }
                                        } else {
                                            if (this.unitType === 'difficulty') {
                                                formattedValue = formatDifficulty(value);
                                            } else if (this.unitType === 'SC' || this.unitType === 'sc') {
                                                formattedValue = formatSC(value);
                                            } else if (this.unitType === 'scientific') {
                                                formattedValue = formatScientific(value);
                                            } else {
                                                formattedValue = value.toFixed(decimalPlaces).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                                            }
                                        }

                                        return `${dataset.label}: ${formattedValue}`;
                                    }
                                },
                                footer: (tooltipItems) => {
                                    if (this.charttype === 'pie' || !this.stacked || !tooltipItems || !tooltipItems.length) {
                                        return '';
                                    }
                                    const index = tooltipItems[0].dataIndex;
                                    const chart = tooltipItems[0].chart;
                                    const datasets = chart.data && chart.data.datasets ? chart.data.datasets : [];
                                    let sum = 0;
                                    for (let i = 0; i < datasets.length; i++) {
                                        const meta = chart.getDatasetMeta(i);
                                        if (meta && meta.hidden) continue;
                                        const v = datasets[i].data && datasets[i].data[index];
                                        const n = (v === null || v === undefined || v === '') ? NaN : Number(v);
                                        if (Number.isFinite(n)) sum += n;
                                    }

                                    // Choose formatting based on the first hovered dataset
                                    const ds0 = tooltipItems[0].dataset || {};
                                    const unit = ds0.unit;
                                    const unitDiv = ds0.unitDivisor || 1;
                                    const decimals = ds0.decimalPlaces || 0;
                                    let formatted;
                                    if (unit) {
                                        const val = sum / unitDiv;
                                        if (unit === 'SC') {
                                            formatted = formatSC(val);
                                        } else if (unit === '%') {
                                            formatted = formatPercent(val);
                                        } else if (this.unitType === 'scientific') {
                                            formatted = formatScientific(val);
                                        } else {
                                            formatted = (Number(val).toFixed(decimals)).replace(/\d(?=(\d{3})+\.)/g, '$&,') + ' ' + unit;
                                        }
                                    } else {
                                        // Fall back to renderer-level unit formatting
                                        if (this.unitType === 'difficulty') {
                                            formatted = formatDifficulty(sum);
                                        } else if (this.unitType === 'SC' || this.unitType === 'sc') {
                                            formatted = formatSC(sum);
                                        } else if (this.unitType === 'scientific') {
                                            formatted = formatScientific(sum);
                                        } else if (this.unitType === 'percentage') {
                                            formatted = formatPercent(sum);
                                        } else {
                                            formatted = Number(sum).toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                                        }
                                    }
                                    return 'Total: ' + formatted;
                                }
                            }
                        }
                    },
                    elements: {
                        point: {
                            // Hide points by default but show a dot on hover
                            radius: 0,
                            hoverRadius: 4
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
                        },
                        stacked: this.stacked === true
                    },
                    y: {
                        type: this.yScale,
                        beginAtZero: yBeginAtZero,
                        title: {
                            display: this.displayYAxis === 'true',
                            text: this.yAxisTitle || "Value"
                        },
                        ticks: {
                            callback: value => this.formatValue(value)
                        },
                        suggestedMax: suggestedMax,
                        min: this.yScale === 'logarithmic' && dataMinPos !== undefined ? Math.max(dataMinPos / 10, Number.EPSILON) : undefined,
                        max: this.yScale === 'logarithmic' && dataMax > 0 ? dataMax * 1.1 : undefined,
                        stacked: this.stacked === true
                    }
                };
            }

            this.chart = new Chart(ctx, options);
        }
    }

    setFiat(useFiat) {
        if (this.currency === 'sc') {
            this.useFiat = false;
            this.unitType = 'SC';
        } else {
            this.useFiat = useFiat;
            this.unitType = useFiat ? this.currency : 'SC';
        }
        this.computeDatasetConfig();
        this.updateChart(this.startDateIndex, this.endDateIndex);
    }

    setCurrency(newCurrency) {
        this.currency = newCurrency;
        if (newCurrency === 'sc') {
            this.useFiat = false;
            this.unitType = 'SC';
        } else {
            if (this.useFiat) {
                this.unitType = newCurrency;
            }
        }
        this.computeDatasetConfig();
        this.updateChart(this.startDateIndex, this.endDateIndex);
    }

    destroy() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }


    initializeSlider() {
        var dateRangeSlider = document.getElementById("dateRangeSlider-" + this.canvasId);
        if (dateRangeSlider) {
            const totalPoints = Math.max(this.dates.length - 1, 1);
            const minPct = totalPoints > 0 ? (this.firstValidIndex / totalPoints) * 100 : 100;
            const spanPct = 100 - minPct;
            const idxToPct = (idx) => {
                if (spanPct <= 0) return 100;
                const clamped = Math.max(this.firstValidIndex, Math.min(idx, this.dates.length - 1));
                return minPct + ((clamped - this.firstValidIndex) / (this.dates.length - 1 - this.firstValidIndex)) * spanPct;
            };

            const startPct = idxToPct(this.startDateIndex);

            noUiSlider.create(dateRangeSlider, {
                start: [startPct, 100],
                connect: true,
                range: {
                    min: minPct,
                    max: 100
                }
            });

            // Add event listener to slider input
            dateRangeSlider.noUiSlider.on("update", (values, handle) => {
                const v0 = parseFloat(values[0]);
                const v1 = parseFloat(values[1]);
                const span = Math.max(100 - minPct, 0.00001);
                const scale = (v) => {
                    if (v <= minPct) return this.firstValidIndex;
                    const ratio = (v - minPct) / span;
                    const maxIdx = this.dates.length - 1;
                    return Math.round(this.firstValidIndex + ratio * (maxIdx - this.firstValidIndex));
                };
                const startIndex = scale(v0);
                const endIndex = scale(v1);

                // Update chart
                this.updateChart(startIndex, endIndex);
            });
        }
    }
}

// Compute earliest index where any dataset has a valid numeric value
GraphRenderer.prototype.computeFirstValidIndex = function () {
    this.firstValidIndex = 0;
    if (!this.datasetsConfig || !this.datasetsConfig.length || !this.dates.length) return;
    const len = this.dates.length;
    for (let i = 0; i < len; i++) {
        let hasValue = false;
        for (let d = 0; d < this.datasetsConfig.length; d++) {
            const v = this.datasetsConfig[d].data[i];
            const n = (v === null || v === undefined || v === '') ? NaN : Number(v);
            if (Number.isFinite(n)) {
                hasValue = true;
                break;
            }
        }
        if (hasValue) {
            this.firstValidIndex = i;
            return;
        }
    }
    // default stays 0 if none found
};

function formatSC(value) {
    const units = [
        { value: 1e12, symbol: 'TS' },
        { value: 1e9, symbol: 'GS' },
        { value: 1e6, symbol: 'MS' },
        { value: 1e3, symbol: 'KS' },
        { value: 1, symbol: 'SC' },
        { value: 1e-3, symbol: 'mS' },
        { value: 1e-6, symbol: '\u03BCS' },
        { value: 1e-9, symbol: 'nS' },
        { value: 1e-12, symbol: 'pS' },
        { value: 1e-24, symbol: 'H' }
    ];

    if (value === 0) return '0 SC';

    const absValue = Math.abs(value);
    const unit = units.find(u => absValue >= u.value) || units[units.length - 1];
    const scaled = value / unit.value;
    return scaled.toFixed(2) + ' ' + unit.symbol;
}

function formatPercent(value) {
    const v = Math.abs(value);
    if (v >= 0.01) {
        return (Math.sign(value) * v).toFixed(2) + ' %';
    } else if (v >= 1e-4) {
        return (Math.sign(value) * v).toFixed(6) + ' %';
    } else {
        return (Math.sign(value) * v).toExponential(2) + ' %';
    }
}

function formatScientific(value) {
    const v = Math.abs(value);
    if (v >= 0.01) {
        return (Math.sign(value) * v).toFixed(2);
    } else if (v >= 1e-6) {
        return (Math.sign(value) * v).toFixed(6);
    } else {
        return (Math.sign(value) * v).toExponential(2);
    }
}

function formatDifficulty(value) {
    const units = ['H', 'KH', 'MH', 'GH', 'TH', 'PH', 'EH'];
    let v = Math.abs(value);
    let idx = 0;
    while (v >= 1000 && idx < units.length - 1) {
        v /= 1000;
        idx++;
    }
    const scaled = (Math.sign(value) * v).toFixed(2);
    return `${scaled} ${units[idx]}`;
}
