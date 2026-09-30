<?php
/** Render a configured statistics page. See docs/frontend-design.md. */
function render_dashboard(array $page, bool $embedded = false): void
{
    $escape = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $page['defaultRange'] = $page['defaultRange'] ?? '3m';
    $prefix = isset($page['id']) ? preg_replace('/[^a-zA-Z0-9-]/', '', $page['id']) . '-' : '';
    $page['currency'] = $page['currency'] ?? 'sc';
    if (!$embedded) render_header('SiaGraph - ' . $page['title'], $page['description'] ?? $page['title'], [
        '<link rel="stylesheet" href="' . $escape(versioned_asset_url('/css/components/data-page.css')) . '">',
        '<link rel="stylesheet" href="' . $escape(versioned_asset_url('/css/components/dashboard.css')) . '">',
    ]);
    ?>
    <section <?php if (!$embedded): ?>id="main-content"<?php endif; ?> class="sg-data-page <?php echo $embedded ? 'dashboard-page dashboard-embedded' . (($page['layout'] ?? '') === 'overview' ? ' dashboard-overview' : '') : 'sg-container dashboard-page'; ?>" data-dashboard data-dashboard-id="<?php echo $escape($page['id'] ?? ''); ?>">
        <script type="application/json" data-dashboard-config><?php echo json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?></script>
        <?php if (!$embedded): ?><h1 class="sg-data-title"><?php echo $escape($page['title']); ?></h1><?php endif; ?>
        <?php if (($page['rangeControls'] ?? true) !== false): ?>
        <div class="dashboard-page-toolbar">
            <div class="dashboard-range-buttons" role="group" aria-label="Page time range">
                <?php foreach (['30d' => '30D', '3m' => '3M', '1y' => '1Y', 'all' => 'All', 'custom' => 'Custom'] as $value => $label): ?>
                    <button type="button" data-range="<?php echo $value; ?>" aria-pressed="<?php echo $value === $page['defaultRange'] ? 'true' : 'false'; ?>"<?php if ($value === 'custom'): ?> aria-controls="<?php echo $prefix; ?>dashboard-custom-range" aria-expanded="false"<?php endif; ?>><?php echo $label; ?></button>
                <?php endforeach; ?>
            </div>
            <form id="<?php echo $prefix; ?>dashboard-custom-range" class="dashboard-custom-range" data-custom-range hidden>
                <label>From<input type="date" name="from" required></label>
                <label>Through<input type="date" name="through" required></label>
                <button type="submit">Apply</button>
            </form>
            <p data-range-status role="status" aria-live="polite" hidden></p>
        </div>
        <?php endif; ?>
        <?php if (!empty($page['currencies'])): ?>
        <label class="dashboard-currency">Display currency
            <select data-dashboard-currency>
                <?php foreach ($page['currencies'] as $code): ?><option value="<?php echo $escape($code); ?>" <?php if ($code === $page['currency']) echo 'selected'; ?>><?php echo $escape(strtoupper($code)); ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <?php if (!empty($page['kpis'])): ?>
        <section class="dashboard-content dashboard-overview-content" aria-labelledby="<?php echo $prefix; ?>dashboard-overview">
            <h2 id="<?php echo $prefix; ?>dashboard-overview"><?php echo $page['kpis']['mode'] === 'latest' ? 'Latest Snapshot' : 'Overview'; ?></h2>
            <?php render_dashboard_kpis($page['kpis'], $page['metrics']); ?>
        </section>
        <?php endif; ?>
        <?php foreach ($page['sections'] as $sectionIndex => $section): ?>
            <section class="card dashboard-content" aria-labelledby="<?php echo $prefix; ?>dashboard-section-<?php echo $sectionIndex; ?>">
                <div class="dashboard-section-heading"><h2 id="<?php echo $prefix; ?>dashboard-section-<?php echo $sectionIndex; ?>"><?php echo $escape($section['title']); ?><?php if (!empty($section['help'])): ?> <button type="button" class="dashboard-info" aria-label="<?php echo $escape($section['help']); ?>" title="<?php echo $escape($section['help']); ?>">ⓘ</button><?php endif; ?></h2>
                    <?php if (!empty($section['href'])): ?><a href="<?php echo $escape($section['href']); ?>">View more</a><?php endif; ?>
                </div>
                <?php if (!empty($section['kpis'])) render_dashboard_kpis($section['kpis'], $page['metrics']); ?>
                <div class="dashboard-chart-grid">
                    <?php foreach (($section['charts'] ?? []) as $chart): ?>
                        <section class="dashboard-chart" data-dashboard-chart="<?php echo $escape($chart['id']); ?>" aria-label="<?php echo $escape($chart['title'] ?? $section['title']); ?>">
                            <?php if (!empty($chart['title'])): ?><h3><?php echo $escape($chart['title']); ?></h3><?php endif; ?>
                            <?php if (!empty($chart['modes'])): ?><label>Projection <select data-chart-mode><?php foreach ($chart['modes'] as $index => $mode): ?><option value="<?php echo $index; ?>"><?php echo $escape($mode['label']); ?></option><?php endforeach; ?></select></label><?php endif; ?>
                            <p data-chart-status role="status">Loading…</p>
                            <p data-chart-warning class="dashboard-section-note" role="status" hidden></p>
                            <div class="dashboard-canvas" hidden><canvas id="dashboard-<?php echo $escape($chart['id']); ?>" role="img" aria-label="<?php echo $escape($chart['title'] ?? $section['title']); ?>"></canvas></div>
                            <button type="button" data-chart-retry hidden>Retry history</button>
                            <?php if (!empty($chart['table'])): ?>
                            <details><summary>View daily values as a table</summary><div class="dashboard-table"><table><thead><tr><th scope="col">Date (UTC)</th><?php foreach ($chart['metrics'] as $key): ?><th scope="col"><?php echo $escape($page['metrics'][$key]['label']); ?></th><?php endforeach; ?></tr></thead><tbody data-chart-rows></tbody></table></div></details>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <?php if (!empty($page['links'])): ?><nav aria-label="Related pages" class="dashboard-related"><?php foreach ($page['links'] as $href => $label): ?><a href="<?php echo $escape($href); ?>"><?php echo $escape($label); ?></a><?php endforeach; ?></nav><?php endif; ?>
        <noscript>Enable JavaScript to load dashboard data.</noscript>
    </section>
    <?php
    if (!$embedded) render_footer(['js/dashboard/ranges.js', 'js/dashboard/sources.js', 'js/dashboard/dashboard.js']);
}

/** A server-rendered KPI usable in live summaries outside the historical dashboard. */
function render_dashboard_stat_card(array $metric): void
{
    $escape = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    ?>
    <article class="dashboard-stat-card">
        <p class="dashboard-stat-label"><?php echo $escape($metric['label']); ?></p>
        <p class="dashboard-stat-value" id="<?php echo $escape($metric['id']); ?>"><?php echo $escape($metric['value']); ?></p>
        <?php if (isset($metric['changeId'])): ?>
            <p class="dashboard-stat-change" id="<?php echo $escape($metric['changeId']); ?>"><?php echo $escape($metric['change']); ?></p>
        <?php endif; ?>
    </article>
    <?php
}

/** Snapshot/period cards can live next to the content they summarize. */
function render_dashboard_kpis(array $kpis, array $metrics): void
{
    $escape = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    ?>
    <div data-dashboard-kpis="<?php echo $escape(json_encode($kpis, JSON_THROW_ON_ERROR)); ?>">
        <?php if ($kpis['mode'] === 'latest'): ?><p data-snapshot-date class="dashboard-section-note" role="status"></p><?php endif; ?>
        <dl class="dashboard-stat-grid" style="--dashboard-kpi-columns: <?php echo min(4, count($kpis['metrics'])); ?>">
            <?php foreach ($kpis['metrics'] as $key): ?>
            <div class="dashboard-stat-card"><dt><?php echo $escape($metrics[$key]['label']); ?></dt><dd data-metric="<?php echo $escape($key); ?>">—</dd></div>
            <?php endforeach; ?>
        </dl>
    </div>
    <?php
}
