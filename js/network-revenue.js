/* Homepage revenue keeps historical valuation separate from conditional future payouts. */
// The layout loads page scripts before its deferred shared renderer executes.
window.createNetworkRevenueChart = function (options) {
    class NetworkRevenueChart extends GraphRenderer {
        async fetchData() {
            this.history = [];
            this.anticipated = [];
            this.rates = {};
            this.status = document.getElementById('networkRevenueStatus');
            this.forecastState = 'loading';
            const history = fetchWithCache(this.jsonUrl).then(rows => {
                if (!Array.isArray(rows)) throw new Error('Invalid historical revenue');
                this.history = rows;
                this.refreshRevenue();
            }).catch(() => { this.historyFailed = true; });
            const forecast = fetch('/api/v1/network_anticipated_revenue.php').then(async response => {
                if (!response.ok) throw new Error('Anticipated revenue unavailable');
                const payload = await response.json();
                if (!Array.isArray(payload.scheduled_revenue)) throw new Error('Invalid anticipated revenue');
                this.anticipated = payload.scheduled_revenue;
                this.forecastState = this.anticipated.length ? 'loaded' : 'empty';
                this.refreshRevenue();
            }).catch(() => { this.forecastState = 'failed'; });
            const rates = fetchWithCache('/api/v1/daily/compare_metrics').then(payload => {
                this.rates = payload?.actual?.coin_price || {};
                this.refreshRevenue();
            }).catch(() => {});
            await Promise.allSettled([history, forecast, rates]);
            this.refreshRevenue();
        }

        refreshRevenue() {
            const months = new Map(this.history.map(row => [row.date.slice(0, 7), { ...row }]));
            const currentMonth = new Date().toISOString().slice(0, 7);
            for (const row of this.anticipated) {
                const key = String(row.unlock_date || '').slice(0, 7);
                const value = row.revenue_sc == null || String(row.revenue_sc).trim() === '' ? NaN : Number(row.revenue_sc);
                if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(key) || key < currentMonth || !Number.isFinite(value)) continue;
                if (!months.has(key)) months.set(key, { date: key + '-01' });
                months.get(key).anticipated_revenue_sc = value;
            }
            const keys = [...months.keys()].sort();
            if (keys.length) {
                const cursor = new Date(keys[0] + '-01T00:00:00Z');
                const last = keys[keys.length - 1];
                while (cursor.toISOString().slice(0, 7) <= last) {
                    const key = cursor.toISOString().slice(0, 7);
                    if (!months.has(key)) months.set(key, { date: key + '-01' });
                    cursor.setUTCMonth(cursor.getUTCMonth() + 1);
                }
            }
            this.monthlyData = [...months.values()].sort((a, b) => a.date.localeCompare(b.date));
            this.computeDatasetConfig();
            this.computeFirstValidIndex();
            if (!this.datasetVisibility.length) this.datasetVisibility = this.datasetsConfig.map(ds => ds.hidden);
            this.calculateInitialDateRange();
            this.updateChart(this.startDateIndex, this.endDateIndex);
            this.updateRevenueStatus();
        }

        computeDatasetConfig() {
            super.computeDatasetConfig();
            const rate = Number(this.rates?.[this.currency]);
            this.datasetsConfig[1].data = this.monthlyData.map(row => {
                const sc = row.anticipated_revenue_sc;
                if (sc == null) return null;
                return this.useFiat ? (Number.isFinite(rate) && rate > 0 ? sc * rate : null) : sc;
            });
            // Missing historical buckets must remain gaps, including in fiat mode.
            this.datasetsConfig[0].data = this.datasetsConfig[0].data.map((value, i) =>
                this.monthlyData[i].contract_revenue == null ? null : value);
            this.updateRevenueStatus();
        }

        updateRevenueStatus() {
            if (!this.status) return;
            const messages = [];
            if (this.historyFailed) messages.push('Earned revenue could not be loaded.');
            if (this.forecastState === 'failed') messages.push('Anticipated revenue could not be loaded.');
            if (this.forecastState === 'empty') messages.push('No anticipated revenue for unresolved contracts.');
            if (this.forecastState === 'loaded' && this.useFiat && !(Number(this.rates?.[this.currency]) > 0)) {
                messages.push('Current exchange rate unavailable; select SC to view anticipated revenue.');
            }
            this.status.textContent = messages.join(' ');
        }

        updateChart(startDateIndex, endDateIndex) {
            super.updateChart(startDateIndex, endDateIndex);
            this.chart.options.plugins.tooltip.callbacks.label = context => this.formatTooltipLabel(context);
        }

        formatTooltipLabel(context) {
            const label = super.formatTooltipLabel(context);
            if (context.datasetIndex !== 1) return label;
            const row = this.monthlyData[this.startDateIndex + context.dataIndex];
            const sc = this.useFiat && row?.anticipated_revenue_sc != null
                ? ` (${formatSC(row.anticipated_revenue_sc)})` : '';
            return label + sc;
        }
    }

    return new NetworkRevenueChart(options);
};
