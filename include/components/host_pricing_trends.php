<?php
/**
 * Reusable Host Pricing Trends component.
 * Renders the pricing trends graph with exchange rate bootstrapping.
 *
 * Options (assoc array):
 *  - hideDownload (bool): hide the download dataset (default: false)
 *  - height (int): canvas height in px (default: 500)
 *  - rangeslider (bool): show range slider (default: true)
 *  - displaylegend (string): 'true'|'false' (default: 'true')
 *  - interval (string): 'week'|'day'|'month' (default: 'week')
 */

if (!function_exists('render_host_pricing_trends')) {
    function render_host_pricing_trends(
        string $canvasId = 'price-trend',
        int $months = 6,
        string $currencyCookie = 'eur',
        array $options = []
    ): void {
        include_once __DIR__ . '/../graph.php';
        $graphConfigs = require __DIR__ . '/../graph_configs.php';

        $hideDownload = isset($options['hideDownload']) ? (bool)$options['hideDownload'] : false;
        $height = isset($options['height']) ? (int)$options['height'] : 500;
        $rangeslider = array_key_exists('rangeslider', $options) ? (bool)$options['rangeslider'] : true;
        $displaylegend = isset($options['displaylegend']) ? (string)$options['displaylegend'] : 'true';
        $interval = isset($options['interval']) ? (string)$options['interval'] : 'week';

        $datasets = [
            array_merge($graphConfigs['avg_storage_price'], ['fiatUnit' => strtoupper($currencyCookie)]),
            array_merge($graphConfigs['avg_upload_price'], ['fiatUnit' => strtoupper($currencyCookie)]),
            array_merge($graphConfigs['avg_download_price'], ['fiatUnit' => strtoupper($currencyCookie), 'hidden' => $hideDownload])
        ];

        // Render the graph. If cookie is fiat, defer init until rates are ready
        renderGraph(
            $canvasId,
            $datasets,
            'date',
            '/api/v1/daily/host_prices',
            null,
            'line',
            $interval,
            $rangeslider,
            $displaylegend,
            $months,
            'false',
            $currencyCookie,
            null,
            $height,
            null,
            'linear',
            false,
            strtolower($currencyCookie) !== 'sc',
            strtolower($currencyCookie) !== 'sc' ? ($canvasId . '-rates-ready') : null
        );

        // Exchange rates bootstrapping and currency sync for this graph instance
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
          try {
            if (!window.hostHistoricalRates) window.hostHistoricalRates = {};
            function proceed(){
              fetchWithCache('/api/v1/daily/exchange_rate')
                .then(function(json){
                  if (Array.isArray(json)) {
                    json.forEach(function(e){ window.hostHistoricalRates[e.date] = e; });
                    var evtName = '<?php echo $canvasId; ?>' + '-rates-ready';
                    document.dispatchEvent(new CustomEvent(evtName));
                  }
                })
                .catch(function(err){ console.warn('Failed loading historical rates', err); });
            }
            if (typeof fetchWithCache === 'function') {
              proceed();
            } else {
              // Wait for global helper to be defined (loaded via deferred script.js)
              var tries = 0;
              (function waitForFetch(){
                if (typeof fetchWithCache === 'function') return proceed();
                if (tries++ < 200) return setTimeout(waitForFetch, 25);
                console.warn('fetchWithCache unavailable; skipping rate bootstrap');
              })();
            }
          } catch (e) { console.warn('Rate init error', e); }
        });
        </script>
        <?php
    }
}
