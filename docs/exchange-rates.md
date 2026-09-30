# Exchange-rate snapshot audit

`ExchangeRates` stores hourly SC prices. Daily history uses the arithmetic mean of the available observations for each UTC calendar day. This is a snapshot average, not a closing price, volume-weighted price, or interpolation over missing hours. A day with one snapshot uses that snapshot; today is provisional until all hourly observations arrive. SQL `AVG` ignores NULL currency values. Missing days remain absent and charts render gaps.

| Workflow | Behavior |
| --- | --- |
| `/api/v1/daily/exchange_rate` and `/api/v2/markets/exchange-rates` | One daily average per currency; existing `date` and currency response fields remain compatible. Midnight timestamps label the day rather than an observation time. Both range endpoints include their entire UTC day. |
| Siacoin price history, historical host pricing, renter history, foundation subsidy conversions and shared currency formatting | Consume daily averages. Price-page text identifies the aggregation. Daily-rate caches now expire hourly where previously cached for a day. |
| `daily/compare_metrics` 30-day revenue | Joins one averaged rate row per day, preventing hourly snapshots from multiplying SC totals and fiat revenue. |
| `daily/compare_metrics` price change | Uses the latest snapshot at or before the comparison timestamp, 24 hours earlier by default. Explicit comparison dates use midnight UTC. Current explorer-rate retrieval is retained. |
| Daily and monthly network aggregates; host historical revenue | Already join daily averaged rates; no query change needed. Monthly fiat revenue is the sum of daily revenue converted at each day's mean. |
| Host profile monthly earned revenue and Host Explorer 30-day revenue | Sum daily SC revenue multiplied by the matching UTC day’s average rate. Missing required daily rates fall back to SC instead of using current prices or partial fiat totals. |
| Current renter/contract amounts | Use the latest snapshot endpoint, with the site's current spot-rate fallback. They do not treat the last daily mean as a current price. |
| `/api/v1/exchange_rate` and host price filters | Continue using the latest hourly observation. The latest-rate endpoint now includes every currency supported by the daily endpoint. |
| Transaction JSON/CSV export | Already uses the latest observation at or before each transaction; hourly data improves timestamp accuracy automatically. Keep this historical point-in-time behavior. |

The daily-rate and comparison endpoint cache keys are versioned to bypass old midnight-only series and duplicated revenue totals. Browser and API-client caches created before deployment may persist until their existing expiry. No page polling or profile refresh controls were added.

## Indexes

The configured database already has the unique index `uq_exchange_rate_currency_timestamp (currency_code, timestamp)`. It supports date-range scans, latest-price lookups, and transaction-time lookups. No additional index is required. The table schema and recent 24-observation days were checked on 2026-09-12.

## Verification

`php tests/markets/exchange-rates.php` checks the SQL against connection-local temporary tables that shadow the production table names. It does not write persistent tables or Redis. Cases include multiple observations, days without midnight samples, NULL currencies, tiny BTC rates, inclusive date bounds, missing days, non-SC rows, hourly caching, nonduplicated revenue, and comparison-time cutoffs.

`node tests/renters/currency.cjs` checks that current amounts and historical amounts use different rates. Renter and contract browser fixtures verify rendering and retained profile loading behavior.

`php tests/hosts/revenue-totals.php` checks the explorer aggregation using temporary tables, including hourly averaging, UTC bounds, missing rates, zero revenue, and unchanged SC/egress totals. `node tests/hosts/dashboard-sources.cjs` checks the equivalent monthly host calculation.
