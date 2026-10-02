# Statistics dashboard standard

`contract_activity.php` and `contracts_collateral.php` are the reference pages. `index.php` retains its original custom layout, comparison tabs, and chart rendering; it does not use the dashboard framework. They use the existing PHP layout and Chart.js renderer through a shared dashboard component.

## Files

- `include/components/dashboard.php`: `render_dashboard($page)` renders the heading, range toolbar, KPI group, and chart sections; loads the shared assets.
- `include/dashboard_metrics.php`: metric labels, data types, explicit divisors, ratio calculations, and chart colors.
- `css/components/dashboard.css`: shared visual rules.
- `js/dashboard/sources.js`: independent source loading and the earned/anticipated network revenue adapter.
- `js/dashboard/ranges.js`: UTC date validation, preset boundaries, and URL parsing.
- `js/dashboard/dashboard.js`: data loading, normalization, KPIs, charts, and tooltips.

## Adding a page

Keep the page configuration small. Declare `title`, `source`, `metrics`, `kpis`, and `sections`, then call `render_dashboard`. A simple `source` must return an array of records with a `date` field. For multiple sources, declare a `sources` map and a `source` key on each chart. A source may be a URL or an adapter descriptor. Each source loads once and fails independently. SQL datetime strings are normalized to UTC calendar dates. Invalid dates are discarded and records are sorted before selecting ranges.

```php
render_dashboard([
    'title' => 'Example',
    'source' => '/api/v1/daily/aggregates',
    'metrics' => ['contracts_formed' => $metrics['contracts_formed']],
    'kpis' => ['mode' => 'range-total', 'metrics' => ['contracts_formed']],
    'sections' => [
        ['title' => 'Contract Formation', 'charts' => [
            ['id' => 'formed', 'metrics' => ['contracts_formed'], 'type' => 'bar'],
        ]],
    ],
]);
```

Use unique, static chart IDs containing letters, digits, and hyphens. Chart groups should contain compatible units. For multiple dashboards on one page, give each configuration a unique `id` and each chart a unique chart ID. Call `render_dashboard($page, true)` to embed it without a second page heading, main-content ID, header, or footer. The host page loads the shared data-page and dashboard stylesheets and the ranges, sources, and dashboard scripts. Omit `kpis` when a separate live summary already provides them.

## Layout and behavior

- Unboxed page title; short optional metadata description. No repetitive explanatory text or duplicate chart headings.
- Section explanations belong in `help`, shown through the heading’s information tooltip. The renderer does not support section `note` paragraphs. Keep units in metric labels; retain visible observation dates and actionable loading, empty, and failure states.
- One card per content section; compact KPI panels and 240px full-width charts.
- One shared range control: **30D / 3M / 1Y / All / Custom**, default **3M**.
- Presets end at the latest available record, independent of today's date. All presets remain available with short or empty histories.
- 30D includes 30 calendar dates. 3M and 1Y subtract calendar months, clamping month ends.
- Custom From/Through dates are inclusive. Reject invalid dates or reversed ranges without replacing the current view.
- URL parameters: `range=custom&from=2024-01-01&through=2024-12-31`, or `range=30d|3m|1y|all`. Preserve unrelated parameters and fragments.
- Start a chart at its first nonzero record, retaining subsequent zeros. If the entire series is zero, retain those valid zero records. Set chart `startAt` to `first-valid` when leading zeros matter.
- Never extend the x-axis before the first displayed record. Empty selected periods show an explicit empty state and recover when another range is selected.
- A section may set `xLabels` to `last` to show date labels only on its last chart.
- Only charts containing multiple series show a legend.
- One tooltip on the hovered chart shows its `tooltipMetrics` group (or all configured metrics when omitted) for that date and highlights that chart's metrics only when the tooltip also includes metrics outside the current chart. When all tooltip metrics belong to the chart, they retain the neutral text color. Tooltips do not synchronize across charts.

## Metric and KPI semantics

Types: `count`, `bytes` (decimal storage units), `money`, `sc`, and `ratio`. Monetary source divisors are explicit (Hastings use `1e24`). Ratios declare numerator fields and a denominator; zero or missing denominators produce missing values.

- `range-total`: sum available values in the selected period. Zero remains zero; entirely missing values show N/A. Partial totals carry a title explaining that only available records were summed.
- `latest`: show the latest record, independent of the chart period, under **Latest Snapshot**. Its date is available on the value's title. Never sum balances or supply snapshots.
- Monetary charts, KPIs, and tooltips use the same conversion. If rates are unavailable, the dashboard consistently falls back to labeled SC values. Circulating supply stays in SC.
- Loading, missing data, and request failures are distinct states. Chart failures do not erase KPI values.
- Data availability belongs in the source/API configuration. Collateral uses `history=all` so older stored records remain accessible; no clock-based frontend cutoff is applied.

New calculation types or data formats should be implemented centrally with checks. Do not copy a page's JavaScript or introduce another page-specific version of these controls. Profile pages and tables can retain their own layouts; migrate their shared components deliberately.

## Verification

```sh
node tests/dashboard/ranges.cjs
CHART_JS=/path/to/chart-3.9.1.min.js node tests/dashboard/browser.cjs
CHART_JS=/path/to/chart-3.9.1.min.js node tests/dashboard/home-browser.cjs
```

The browser suite renders the real PHP pages without database connections, uses the real Chart.js bundle with a deterministic UTC date adapter, and supplies fixture API records. It checks both pages, ranges anchored to historical data, inclusive custom dates and URL restoration, KPI calculations, units, tooltips, short/empty histories, API failures, and narrow layouts. `CHROME_BIN` can select a Chromium executable.

## Front-page exception

The front-page migration was reverted. Keep `index.php` on its original custom layout and chart scripts unless explicitly asked to redesign it. Its summary comparison tabs, explorer, and three chart previews are independent of the shared dashboards.

The shared renderer still supports embedded sections, `rangeControls: false`, multiple sources, tooltip groups, byte formatting, and the revenue adapter for future uses. Those options are not currently used by the front page.

`tests/dashboard/home-browser.cjs` verifies the restored front-page markup, original chart initialization, comparison tabs, detail links, and desktop/mobile layouts. The dashboard browser suite separately verifies the two statistics pages.

## Exchange-rate dashboards

`siacoin_price.php` uses the shared dashboard with an optional `currencies` selector. The `price` metric type reads the selected currency field directly, requires a positive rate, and formats six to eight decimals. Currency changes refresh cached records without losing the selected range.

A source descriptor with `kind: 'record'` wraps a single dated API object. `kpis.source` selects an independent latest snapshot source; its observation timestamp remains visible above the KPI. Price history uses daily averages, while its latest quote comes from the hourly endpoint. `gaps: 'day'` inserts a missing point between nonconsecutive dates so lines do not bridge missing history.

Set chart `table: true` for an accessible table of selected dates and values. Failed sources offer a retry button. Optional `links` map related URLs to labels below the content.

Run `CHART_JS=/path/to/chart-3.9.1.min.js node tests/dashboard/price-browser.cjs` to verify precision, custom dates, currency changes, missing observations, independent snapshots, empty history, and retry recovery.

## Storage and hosting

`network_storage.php` combines storage capacity, utilization, host counts, block height, all-time-high statistics, and a 24-month forecast. `hosting.php` redirects to it. Daily NetworkStats history anchors the shared range; block height and ATH snapshots use independent sources. No source is filtered relative to the current clock.

Sections can declare `kpis` with the same `mode`, `source`, and `metrics` keys as the page overview. Latest snapshots show their observation timestamps and stay independent of the selected history range. The `date` metric type supports dates such as an ATH observation.

The `storage-ath` adapter preserves the ATH API's latest observation date and elapsed days. The `storage-forecast` adapter projects from the latest five monthly observations using actual elapsed calendar months. It requires at least two months, retains missing data, and reports insufficient data separately from request failures. Projected metrics use dashed lines. Chart `modes` select metric groups and `yScale` without changing the page range; storage retains linear projections and exponential projections on a logarithmic axis. Presets include the forecast horizon; custom dates constrain it inclusively.

Checks: `node tests/dashboard/storage-sources.cjs` and `CHART_JS=/path/to/chart-3.9.1.min.js node tests/dashboard/storage-browser.cjs` cover calculations, missing data, source isolation, shared ranges, snapshots, forecast modes, tooltips, and mobile layout.

## Table-based data pages

`css/components/data-page.css` provides shared titles, panels, controls, focus states, and scroll regions for `.sg-data-page`. Dashboards load it before their dashboard stylesheet; embedded consumers must load both stylesheets. `host_explorer.php` uses these visual components while retaining its filter sidebar, toolbar, table, currency formatting, and pagination logic.

Host Explorer uses the compact details view below 768px. On screens wider than the normal 1560px shell, the results card expands into the unused right margin as columns need space, keeping its left edge and filters anchored. It shrinks back to the normal panel width when fewer columns are selected. At desktop widths, columns that exceed the available screen space remain inside a keyboard-focusable horizontal scroll region instead of wrapping values or switching to stacked details. Host identity stays pinned on the left; long addresses use an ellipsis with the complete address retained as link text and a title. Pagination stays outside the scrolling area. Column selections remain persisted, and request failures provide a retry button.

Run `node tests/hosts/explorer-browser.cjs` for desktop and mobile layout checks with all columns enabled, bounded row heights, scrolling, column preferences, search, sorting, pagination, loading, empty states, and retry recovery. Rendering rows must never trigger another data request.

## Blockchain explorer pages

The pages under `explorer/` are a separate cold-data reference interface. They are not dashboard pages and must not be migrated to `render_dashboard()`.

The explorer presents current blockchain state and immutable entity records rather than internally calculated statistics:

- Network and status pages show the current tip, consensus, transaction pool, peers, exchange data, and explorer metrics.
- Entity pages show blocks, transactions, addresses, contracts, outputs, events, and their related records.
- Lookup pages resolve searches, heights, and block-metric queries.
- V1 and V2 routes may share the client route implementation, but the displayed entity version must remain explicit wherever fields or interpretation differ.

The explorer shares SiaGraph's visual language but retains its own information architecture. Use the shared PHP layout, `.sg-data-page` primitives, warm dark surfaces, typography, focus states, control sizing, and table treatments. Do not add dashboard range controls, trend charts, period-total KPIs, or analytics-oriented copy to cold-data pages.

### Explorer layout rules

- Make search the primary action on the explorer home page; current network values support lookup rather than becoming a dashboard summary.
- Use an unboxed page title and identify the entity type, identifier, block height, and observation timestamp when available.
- Group information by meaning: identity and state, monetary movement, related entities, and raw source data. Do not put every field in a visually identical card.
- Use key/value layouts for entity properties and tables for repeated records. Preserve horizontal table scrolling on desktop where comparison is useful.
- Provide readable mobile alternatives for block, transaction, contract, address, and output tables rather than relying only on squeezed desktop tables.
- Keep raw JSON in a collapsed, keyboard-accessible disclosure for technical inspection.
- Use shared loading, empty, not-found, failure, and retry states. A missing entity, an empty collection, and an unavailable API are different states.
- Copyable identifiers must have accessible labels and visible success or failure feedback. Avoid inline event handlers.
- Display formatted values as derived presentations of source data. Preserve links to the original entity and retain precise identifiers behind shortened labels.
- Use `Observed at`, `Included in block`, or equivalent entity-specific wording instead of treating every record as a statistical “latest snapshot”.

### Explorer implementation boundary

`explorer/_page.php` owns the PHP shell and API bootstrap. `js/explorer-v2/app.js` selects a route, `js/explorer-v2/services/api.js` owns requests, `js/explorer-v2/state/router.js` owns URL/entity parsing, and `js/explorer-v2/components/view.js` owns reusable presentation helpers. Shared explorer behavior belongs in these boundaries; route modules should provide page-specific data and section definitions.

The first migration pass should load `css/components/data-page.css` in the explorer shell and reuse its title, surface, control, focus, and scroll primitives. Keep explorer-specific layout rules in `css/pages/explorer.css`, but remove duplicate rules only after the shared primitive is applied and verified at desktop and mobile widths.

Explorer verification should cover route rendering, API failure and empty states, identifier links and copy actions, responsive tables, keyboard focus, and V1/V2 labels. It should not assert dashboard range behavior because the explorer intentionally has no historical range control.

The dependency-free explorer contract check is `node tests/explorer/static.cjs`. Browser coverage should extend this check when a browser harness is available.

## Embedded host dashboards

`host.php` retains its profile header, map, summary, tables, benchmarks, contracts, and tab navigation. `include/host_dashboard.php` configures independent History and Economics dashboards inside those tabs. Desktop chart columns and the locked-revenue snapshot are preserved.

- `id` scopes control/heading IDs and optional URL keys. Host History and Economics default to **1Y** and set `persistRange: false`: selections stay in memory, old range parameters are removed, and reloads return to 1Y. Host identifiers and navigation hashes are preserved. Other dashboards retain their configured URL behavior.
- `lazy: true` loads a dashboard when its tab is visible. Shared rendering defers hidden charts and resizes them on tab activation. `followCurrency: true` follows the site's currency selection, including changes before the tab first loads.
- `js/dashboard/host-sources.js` adapts host daily records, estimated egress, and scheduled revenue. Successful requests are shared across tabs; failed requests can be retried. Missing source data remains independent from other charts.
- Monthly revenue and contract completions are summed from daily records. Active contracts and collateral use the last available observation in the month. Burns are negative. Earned fiat revenue sums each daily SC amount at that UTC day’s average rate, falling back to SC if a required rate is missing. Month-end fiat rates (with current-rate fallback) apply to burns and collateral; anticipated revenue uses current rates. Pricing retains SC/TB/month and SC/TB conversions. Fiat tooltips retain the underlying SC amount.
- Only revenue extends into forecast months. Monthly gaps remain empty. Monthly charts include entire calendar months touched by custom dates; daily charts use inclusive dates.

Validation: `node tests/hosts/chart-start.cjs`, `node tests/hosts/current-rate.cjs`, and `CHART_JS=/path/to/chart-3.9.1.min.js node tests/dashboard/host-browser.cjs`. The browser fixture renders the actual host PHP page and checks independent tab ranges, lazy endpoints, chart resizing, currency conversion, source failures, retry recovery, and desktop/mobile widths.

Monthly charts use calendar buckets anchored to the latest available data: 30D shows the latest month, 3M shows three months, and 1Y shows twelve months including the latest month. For example, September 2026 selects October 2025 through September 2026 for 1Y. The latest month includes all available observations; no future observations are invented. Custom dates expand to the first and last days of their months for monthly charts, preventing partial-month selections from dropping monthly records. Daily chart ranges are unchanged.

`host_alerts.php` uses shared data-page title, card, control, and focus styles with an unboxed heading. Its introduction, delivery interval, setup explanation, and all three alert descriptions remain visible. Essential feature behavior belongs in visible text, even when supplementary chart explanations use tooltips. Telegram instructions remain conditional on delivery method. Subscription behavior is unchanged.

`host_revenue_export.php` uses shared data-page titles, cards, form controls, placeholders, and focus styles, with page-specific form spacing and methodology typography in `css/pages/host-revenue-export.css`. It does not load the transaction-export stylesheet. The full methodology stays in a native, keyboard-accessible disclosure, collapsed by default. Preserve its wording and the export form behavior when changing presentation.
