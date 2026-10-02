# Network anticipated revenue

`GET /api/v1/network_anticipated_revenue.php` returns:

```json
{
  "as_of": "2026-09-18T12:00:00Z",
  "scheduled_revenue": [
    { "unlock_date": "2026-10-01", "revenue_sc": "123.4500", "contract_count": "3" }
  ]
}
```

The endpoint sums precomputed `HostRevenueForecast` rows across all hosts in the configured main database (`database.database`). It filters `unlock_date >= today UTC` **before** grouping into calendar months, excluding earlier days even in the current month. Today's date remains included because this source stores dates, not intraday unlock times. Hastings are summed in SQL before conversion to SC; amounts retain the database decimal representation. Contract counts are summed from the daily forecast rows. No contract-date calculations or persistent schema changes are needed in this API.

The existing host forecast producer (`/opt/siagraph/gethostrevenueforecast.py`) calculates estimated dates from unresolved V2 contracts using `tip timestamp + (windowend + 144 − tip height) × 10 minutes`. Its refresh replaces current forecast rows and deletes rows not reproduced by that refresh, as well as past dates. The network endpoint reuses those published values. Accuracy therefore follows the producer's refresh cadence. Anticipated revenue is paid only if contracts succeed.

Successful responses, including empty arrays, share a versioned Redis cache lasting 60 seconds. The version bypasses both earlier network forecast results and the intermediate active-contract implementation. Browser responses use `Cache-Control: no-store`. `as_of` records API response generation, not the table's `calculated_at` timestamp. Failures return HTTP 503 and are not cached.

The homepage loads historical aggregates, anticipated revenue, and current exchange rates independently. It joins months, preserves gaps, and shows all anticipated months alongside its existing six-month historical window. Missing earned values stay null. Anticipated revenue uses the current exchange rate in fiat mode and includes SC in its tooltip. Currency changes reuse the loaded forecast. Missing rates hide fiat anticipated values and prompt selection of SC; failed forecasts leave earned revenue available.

Checks:

```sh
node tests/network/anticipated-revenue.cjs
php tests/network/anticipated-revenue.php
php -l api/v1/network_anticipated_revenue.php
php -l src/Services/NetworkAnticipatedRevenue.php
php -l index.php
```

The PHP integration check needs database connectivity and CREATE TEMPORARY TABLE permission. It uses only a connection-local temporary table, leaving persistent data untouched.
