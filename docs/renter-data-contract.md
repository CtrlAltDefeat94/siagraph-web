# Renter website data contract

Current contract summaries are on the Contracts tab; per-contract drill-downs are on `/renter_contracts?address=...`; see [active-contracts.md](active-contracts.md). The summary/history sources and publication rules below remain unchanged.

The renter website reads `Renters`, `RenterPublicKeys`, and `RentersDailyStats` from the configured primary database. It never writes these tables or computes their metrics. Wallet addresses are canonical profile identifiers. Metrics, heights, and monetary values are returned as strings, with null preserved. Pagination uses a one-row lookahead instead of an aggregate count.

## Routes and identity

- `/renters`: directory, published overview, and published daily network history.
- `/renter?address=...`: current state, observed keys, and daily wallet history.
- `/renter?public_key=...`: the details API resolves the wallet internally; the browser retains this public-key URL while displaying wallet data. Optional `address` context must match a recorded key/wallet association.
- `/renter_distribution`: published top 10 distribution.

Public-key links in the active PHP explorer's contract and transaction views use the resolution route. Resolution happens on navigation rather than issuing per-key requests when rendering a list. Unknown associations show unavailable; ambiguous mappings offer paginated wallet choices. Both bare 64-character hex and `ed25519:` key representations are looked up, and duplicate encodings do not duplicate a wallet. Wallet inputs use 76-character hex addresses. The database remains authoritative for whether a validated identifier exists.

## API

All endpoints are GET-only and use the V2 `{data, meta, errors}` envelope:

| Path below `/api/v2/` | Parameters / response |
| --- | --- |
| `renters/index.php` | `search` exact wallet/key, `active=0|1` (default 1), `sort=contracted_filesize|active_contracts|active_hosts|last_active|renter_wallet_address`, `direction=asc|desc`; `{items, pagination}` |
| `renters/details.php` | `address` or `public_key`; stored wallet row |
| `renters/public-keys.php` | Required `address`; `{items, pagination}` |
| `renters/resolve.php` | Required `public_key`, optional `address`; `{status: resolved|unmapped|ambiguous, renter_wallet_address, items, pagination?}` |
| `renters/daily.php` | Optional `address` or `public_key`, `start`, `end`, or `all=1` with `after` cursor; wallet rows or published network rows containing `payload` and snapshot metadata |
| `renters/overview.php` | Latest published overview payload, or null |
| `network/storage/renter-distribution.php` | Fixed `limit=10`; latest published distribution payload, or null |

Paged endpoints accept `page` (1–100000) and `per_page` (1–100, default 25); pagination includes `has_more`. Dates must be real `YYYY-MM-DD` values, ordered, with at most 366 inclusive days. The default range is 30 days. Invalid input returns 400, absent wallet details/history/keys return 404, unsupported methods 405, and infrastructure failures 503. A known wallet with no daily rows returns an empty list. Resolver no-match is a successful `unmapped` result.

Responses cache for at most 60 seconds in Redis and tell browsers not to store them. Cache namespaces are distinct from the old V1 implementation. Missing Redis is tolerated. API generation time is separate from source update/snapshot time. Wallet snapshots carry `snapshot_completeness: unverified` and `partial_data: true` because the three tables lack batch completion metadata. They are not presented as complete network snapshots. Dates/times are shown as stored; producer timezone and cutoff still need confirmation.

## Producer publication dependency

Network totals, shares, Others, and network history require stored summaries. No runtime fallback sums contracts or wallet rows. Until the optional producer table is deployed and configured, these endpoints return `availability: awaiting_summary` and the pages show unavailable. Individual wallet pages continue working.

Apply `docs/sql/renter-published-summaries.sql` through the database deployment process. This optional table is a publication boundary with `(kind, snapshot_date)` as its primary key, a source `snapshot_height`, a complete JSON `payload`, and `published_at`. The producer must atomically publish validated snapshots. Staged rows with null `published_at` are never served. Corrected snapshots should replace the payload atomically; they become visible within 60 seconds. All values listed as metrics below must be JSON strings, including zeros. Unknown monetary values must be null.

Payloads:

- `overview`: object containing approved stored network metrics such as `active_wallets`, `contracted_filesize`, `active_contracts`, and `spending` (spending covers the snapshot date). Do not add per-wallet host counts as though they were unique network hosts.
- `daily`: object of precomputed network metrics with the same field names as wallet daily metrics where semantics match. API returns each payload with `snapshot_date`, `snapshot_height`, and `published_at`. Do not sum snapshot values across dates or average unweighted averages in the producer.
- `distribution`: object described below. `share_percent`, totals, ranks, and Others must already be calculated by the producer, for one coherent snapshot and population. The browser plots the supplied shares; it does not calculate them.

```json
{
  "largest_sizes": ["1000"],
  "total_filesize": "1200",
  "total_renters": "2",
  "renters": [
    {"rank": "1", "renter_wallet_address": "<76 hex characters>", "contracted_filesize": "1000", "share_percent": "83.333333"}
  ],
  "others": {"renter_wallet_address": null, "contracted_filesize": "200", "share_percent": "16.666667", "renter_count": "1"}
}
```

`largest_sizes`, `total_filesize`, and `total_renters` retain existing V2 field names; the new source explicitly uses precision-preserving strings. V1 is unchanged. V2 no longer bridges V1 raw-contract aggregation, so consumers must handle null while publication is unavailable. Only a top 10 publication is supported; if fewer than 10 wallets exist, publish all wallets with zero Others or null Others.

Once producer publishing is ready, set `$SETTINGS['renters']['published_summaries'] = true`. Source-unit defaults now follow the host page: `money_unit = hastings` and `duration_unit = blocks`. This is a host-convention assumption pending confirmation from the renter producer. Override with `sc` for already-converted currency or `days`, `hours`, or `seconds` for duration when appropriate. The frontend converts Hastings to SC using 10^24 Hastings per SC and block duration to estimated time using 600 seconds per block (30-day months), while the API continues returning unchanged source values. Storage uses decimal KB/MB/GB/TB/PB/EB units. The legacy `stored units` setting follows the new defaults. Currency presentation now follows the configured currency cookie: current amounts use the latest available rate, while daily charts use the arithmetic mean of available hourly exchange-rate snapshots for that UTC date. Current-rate fallback never substitutes a daily average. Missing historical rates remain gaps; current amounts fall back to clearly labelled SC when a rate is unavailable. Formatting uses integer arithmetic before approximate chart-coordinate conversion; exact source strings remain in card tooltips. Missing dates and nullable values remain gaps/unavailable.

Confirm the original plan's active-contract, renewal, expiration, unit, and daily cutoff semantics with the producer before labelling values more specifically. No producer source is included in this website repository, so summary generation/backfills are an external dependency; the supplied DDL was not applied to production.

## Verification

Run `php tests/renters/service.php` for input/fixture checks; use `php tests/renters/service.php --database` to exercise actual MySQL queries against connection-local temporary tables populated with synthetic fixtures. The latter uses configured credentials and local fixture definitions but never reads or modifies persistent application rows. Run `node tests/renters/explorer.mjs` for canonical explorer-link behavior. PHP lint and JS syntax checks require no database.


For an isolated local MySQL instance bound to `127.0.0.1:13376`, with database `renter_fixtures` and the test-only root password `renter-fixture-only`, run `php tests/renters/service.php --database --local --volume`. This also tests response envelopes and a 5,003-wallet query plan. `docs/sql/renter-directory-index.sql` contains the tested default-sort index; apply it separately through database deployment after checking the real workload.

`node tests/renters/browser.cjs` runs headless Chrome DOM checks on actual page markup with synthetic API responses and a Chart configuration spy. It covers mobile-width populated/empty pages, history gaps versus zero, published shares, and unresolved/ambiguous keys. This checks DOM behavior and chart inputs, not Chart.js drawing or production ingestion. Set `CHROME_BIN` if Chrome has a different executable name.

Validation completed: MySQL 8.4 fixture/service/API checks, the 5,003-wallet ranking plan (the proposed index removes filesort), explorer-link checks, seven Chrome fixtures, PHP/JS syntax, and OpenAPI JSON parsing. The configured database's metadata/temp-table reads timed out during verification, so database behavior was verified in a disposable local MySQL instance instead. Persistent application data and production schema were not modified. Renter APIs now use bounded connection/read timeouts and return a generic 503 on database failure.


## Focused history charts

The renter detail uses fixed chart groups on its existing deep-linkable tabs: `#contracts` (active/renewed contracts, formations/revisions, resolutions, expiration windows, duration), `#economics` (spending/returns, contract funds, renewal funding), and `#charts` (contracted storage and active hosts). Each group has independent 30D/90D/1Y/custom controls and charts no longer expose daily-value tables. Matching date-range requests share one response for up to 60 seconds; no per-chart API aggregation was added. `/renters` shows separate published network storage, contract, and economics charts as well.

Run `node tests/renters/format.cjs` for unit/precision checks. Browser fixtures also verify all 10 profile charts, shared fetches, independent group ranges, converted SC coordinates, and preserved zero/gap behavior.


## Public-key URLs, currency, and All history

The public-keys tab, active-key metric, data-added/removed chart, and daily-value tables have been removed from the UI. Mapping records remain in the database and API for identity resolution. `details.php?public_key=...` returns the resolved wallet row; `daily.php` also accepts a key. Ambiguous mappings return 409 (`ambiguous_key`) with candidate wallets; optional `address` context must match a recorded association. No browser redirect is performed. Subsequent chart requests use the resolved wallet, and tab changes preserve the query string.

All range buttons fetch `daily.php?all=1`, following `meta.pagination.next_after` while `has_more` is true. Each response contains at most 1,000 stored daily rows, ordered by date. The cursor is exclusive and uses the wallet/date index (or the published summary date index). All cannot be combined with start/end; normal custom ranges retain the 366-day limit. The browser consumes every page, then displays the full first-to-last date range with gaps preserved. No metric aggregation is added.

The frontend reads the same currency cookie as the site settings and supports SC and the currencies supplied by the existing exchange-rate endpoint, including EUR, USD, CAD, and GBP. Exact source-unit values remain in card tooltips. Currency conversion is presentation-only. Run `node tests/renters/currency.cjs` to verify selected currencies and missing-rate behavior. Browser tests include retained public-key URLs and multi-page All history; database fixtures cover server resolution and historical pagination beyond one year.
