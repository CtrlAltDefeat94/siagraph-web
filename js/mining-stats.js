document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('mining-data');
    const tokenomics = JSON.parse(dataEl?.dataset.tokenomics || 'null');
    if (tokenomics && tokenomics.length) {
        const ctx = document.getElementById('blockRewardChart').getContext('2d');
        const labels = tokenomics.map(t => t.date);
        const rewards = tokenomics.map(t => t.cumulative_reward);
        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Block Reward (Cumulative)',
                    data: rewards,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const v = context.parsed.y;
                                return `Block Reward (Cumulative): ${formatSC(v)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: { type: 'time', time: { unit: 'year' } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => formatSC(value)
                        },
                        title: { display: true, text: 'Block Reward (SC)' }
                    }
                }
            }
        });
    }
});

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
    const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
    return Number(scaled).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + unit.symbol;
}
