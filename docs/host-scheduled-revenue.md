# Host anticipated revenue

`GET /api/v1/host_scheduled_revenue?public_key=ed25519:<64 hex characters>`

Uses `database.database` in `include/config.php`, with the configured database connection credentials. That account needs SELECT access to `HostRevenueForecast` in the main database. The host-key predicate uses a bound parameter.

Response:

```json
{
  "public_key": "ed25519:<64 hex characters>",
  "as_of": "2026-09-08T12:00:00Z",
  "scheduled_revenue": [
    { "unlock_date": "2026-10-01", "revenue_sc": "123.4500", "contract_count": "3" }
  ]
}
```

The endpoint reads the precomputed `HostRevenueForecast` table in the configured main database. SQL groups daily rows into calendar months, sums `revenue_hastings` and `contract_count`, and converts Hastings to SC. `unlock_date` is the first day of the month. Decimal amounts retain the database representation. An empty result returns an empty array; invalid keys return 400, and database failures return 503 without caching the error.

Successful results, including empty arrays, are cached in Redis per normalized host key and UTC date until midnight UTC. The cache namespace is versioned to bypass results from the previous contract query. Redis must be available to retain this cache.

The host Economics tab fetches the endpoint when the monthly revenue card is visible. Anticipated revenue appears as a separate bar series in that existing chart, alongside earned revenue and burned funds. Historical 12M/24M/All controls retain their existing behavior; the revenue chart also includes all forecast months, with calendar gaps preserved. Other charts do not extend into the forecast period. Currency changes reuse the loaded response; forecasts use the current exchange rate with SC retained in tooltips. There is no separate forecast chart or month-end extrapolation.


Checks:

```sh
node tests/hosts/chart-start.cjs
node tests/hosts/scheduled-revenue.cjs
php -l api/v1/host_scheduled_revenue.php
php -l src/Services/HostScheduledRevenue.php
```
