/* Anticipated revenue is fetched only when its card becomes visible. */
class HostScheduledRevenueLoader {
    constructor(publicKey, onLoad) {
        this.publicKey = publicKey;
        this.onLoad = onLoad;
        this.card = document.getElementById('hostScheduledRevenueCard');
        this.status = document.getElementById('hostScheduledRevenueStatus');
        this.rows = null;
        this.pending = false;
        this.attempted = false;
        if (typeof IntersectionObserver === 'function') {
            this.observer = new IntersectionObserver(entries => {
                if (entries.some(entry => entry.isIntersecting)) {
                    this.observer.disconnect();
                    this.load();
                }
            });
            this.observer.observe(this.card);
        } else {
            const loadVisible = () => {
                if (this.card.getClientRects().length && !this.attempted) this.load();
            };
            document.addEventListener('change', loadVisible);
            window.addEventListener('hashchange', loadVisible);
            setTimeout(loadVisible, 0);
        }
    }

    async load() {
        if (this.attempted) return;
        this.attempted = true;
        this.pending = true;
        this.status.textContent = 'Loading anticipated revenue…';
        try {
            const response = await fetch('/api/v1/host_scheduled_revenue?public_key=' + encodeURIComponent(this.publicKey));
            if (!response.ok) throw new Error('Request failed');
            const payload = await response.json();
            if (!Array.isArray(payload.scheduled_revenue)) throw new Error('Invalid response');
            this.rows = payload.scheduled_revenue;
            this.status.textContent = this.rows.length ? '' : 'No anticipated revenue for unresolved contracts.';
            this.onLoad(this.rows);
        } catch (error) {
            this.status.textContent = 'Anticipated revenue could not be loaded.';
        } finally {
            this.pending = false;
        }
    }

}
