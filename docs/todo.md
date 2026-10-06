# Global TODO status

The shared dashboard framework now covers the global redesign requirements:

- Charts start at the first meaningful observation while preserving later zeroes.
- Range controls are fixed to 30D, 3M, 1Y, All, and Custom, with a 3M default.
- Dashboard sections avoid nested card treatment and use more spacious analytical charts.
- KPI groups remain tied to the page's analytical concept and calculation mode.
- Shared pages use concise headings without repeated visible descriptions.


# Frontend page TODOs

Includes frontend page entry points and legacy redirects. Excludes API endpoints, shared components, test pages, and archived copies.

# Logo's
- Download the new versions from https://media.discordapp.net/attachments/629226638361755648/1516154025194557552/siagraph_banner_white.png?ex=6aba0f38&is=6ab8bdb8&hm=afbd7b01ef54e09aa61bc1460901e889aeafe618fa95fd46f11aae62a42d02f2&=&format=webp&quality=lossless
 https://media.discordapp.net/attachments/629226638361755648/1516154025635221704/siagraph_banner_white2x.png?ex=6aba0f38&is=6ab8bdb8&hm=66e2f927aca0f1024ec746323482d96beb5d3b4e1414a58a0d97e6bc9c4417ef&=&format=webp&quality=lossless
 https://media.discordapp.net/attachments/629226638361755648/1516154025979023460/siagraph_logo_32_transparent.png?ex=6aba0f38&is=6ab8bdb8&hm=df30bc70b597781188c904f41b2d923bfce2cc92c77d73f8923d5c6af844659e&=&format=webp&quality=lossless
deprecate the old logo files instead of removing them entirely.

## Site pages



### `contracts_collateral.php`

- add actions

### `contracts_funds.php`

- add actions

### `dashboard.php`

- add actions

### `foundation_subsidy_tracker.php`

- add actions

### `host.php`

- Confirm contract end window locked revenue. It's not even close to anticipated revenue
- Make sure monthly summary charts state sept-2026 while snapshot charts may say 01-sept-2026
- see what goes wrong with the collateral multiplier as it seems to have a rounding issue 

### `host_alerts.php`

- add actions

### `host_benchmarks.php`

- add actions

### `host_contracts.php`

- add actions

### `host_explorer.php`

- add actions

### `host_overview.php`

- add actions

### `host_pricing.php`

- add actions

### `host_revenue_export.php`

- add actions

### `host_troubleshooter.php`

- add actions

### `hosting.php`

- add actions

### `index.php`

- add actions

### `mining_stats.php`

- add actions

### `network_aggregates.php`

- add actions

### `network_growth.php`

- add actions

### `network_metrics.php`

- add actions

### `network_overview.php`

- add actions

### `network_percent.php`

- add actions

### `network_storage.php`

- add actions

### `peers.php`

- add actions

### `renter.php`

- add actions

### `renter_contracts.php`

- add actions

### `renter_distribution.php`

- add actions

### `renter_explorer.php`

- add actions

### `revenue.php`

- add actions

### `siacoin_price.php`

- add actions

### `siafunds.php`

- add actions

### `token_transfer_volume.php`

- add actions

### `token_volume.php`

- add actions

### `tokenomics.php`

- add actions

### `transactions_export.php`

- add actions

## Explorer pages

The explorer remains a cold blockchain-data reference interface, separate from the statistics dashboard framework. It should share SiaGraph's visual primitives and interaction states without gaining analytics ranges or calculated dashboard KPIs.

### Explorer foundation

- [x] Load `css/components/data-page.css` from `explorer/_page.php` and apply `.sg-data-page` to the shell.
- [x] Reduce the outer card treatment and align explorer shell states and controls with shared data-page primitives.
- [x] Add shared loading, empty, not-found, failure, and retry states to `js/explorer-v2/components/view.js`.
- [x] Centralize copyable identifiers and raw JSON disclosures in the explorer components.
- [ ] Add explorer browser coverage for route states, keyboard focus, responsive tables, and V1/V2 identity labels. The dependency-free shell contract is covered by `node tests/explorer/static.cjs`.

### Explorer home and status

- [x] Remove `explorer/index.php`. Global Sia network search now routes directly into deep explorer pages while the explorer keeps its shared site shell.
- [x] Align `explorer/consensus/index.php`, `explorer/metrics/index.php`, `explorer/peers/index.php`, `explorer/exchange/index.php`, and `explorer/txpool/index.php` with shared data-page surfaces and current-observation wording.

### Explorer entity pages

- [ ] Apply a consistent entity header and observation metadata to `explorer/block/index.php`, `explorer/transaction/index.php`, `explorer/contract/index.php`, and `explorer/output/index.php`. Address activity is now the reference ledger implementation.
- [x] Add shared responsive table/card representations for entity records, preserving desktop comparison tables where appropriate.
- [x] Keep identity, state, monetary movement, related entities, and raw source data as distinct information groups on the address page.
- [x] Make V1/V2 identity explicit on `explorer/v2-contract/index.php` and `explorer/v2-tx/index.php` whenever the underlying fields differ.

### Explorer lookup and auxiliary pages

- [x] Align `explorer/search/index.php` and `explorer/height/index.php` with shared search, result, empty, and failure states.
- [x] Escape block-metric query output through the shared raw-data disclosure without introducing time-series dashboard controls.
- [x] Give `explorer/event/index.php` an accessible structured payload view with a collapsed raw JSON disclosure.

## API documentation

### `swagger/index.html`

- Update to latest version after all API changes have been made
- Include explanations for what returned fields mean or how they are calculated.
