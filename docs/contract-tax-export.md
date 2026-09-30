# Completed V2 contract revenue export

`GET /api/v2/contracts/tax-export.php?host_public_key=<64-hex-key>&from=2025-07-01&to=2026-09-12&format=json&currency=EUR`

This public, read-only endpoint exports one row per eligible completed V2 contract. Eligibility and the selected period are separate rules:

- The contract resolution must have occurred on or after **2025-07-01 00:00:00 UTC**.
- `from` and `to` select an inclusive range of **payout-maturity dates**. The payout maturity height is `resolution_height + 144`.
- The maturity height must already exist in `BlockTime`; current and future payouts are not estimated.

Only GET is accepted. JSON uses the V2 `data` / `meta` / `errors` envelope and includes `data.items` plus `data.summary`. CSV streams the same item columns without a summary. Responses are not cached.

## Parameters

| Parameter | Behavior |
| --- | --- |
| `host_public_key` | Required 64 hexadecimal characters, optionally prefixed with `ed25519:`. |
| `from` | Inclusive UTC payout-maturity date, default `2025-07-01`. |
| `to` | Inclusive UTC payout-maturity date, default yesterday UTC. |
| `format` | `json` (default) or `csv`. |
| `currency` | BTC, CAD, CNY, ETH, EUR, GBP, JPY, RUB or USD; default EUR. |

The CSV filename contains the complete normalized host public key, maturity period and selected currency. The public key is intentionally not repeated in every row.

## Accounting convention

SiaGraph assigns unlocked revenue and realized losses to the UTC date on which the contract payout reaches its protocol maturity height. This is an objective blockchain date when an output becomes spendable. It is an export convention, not a determination that every tax jurisdiction uses the same recognition rule or that every reported loss is deductible.

Revenue is classified by its disposition at resolution:

- `revenue_unlocked_sc` became payable to the host and is included in revenue totals.
- `revenue_rolled_sc` remained committed through a renewal/refresh successor. It is not included in revenue totals at this event and is counted only if a later contract unlocks it.
- `revenue_forfeited_sc` was lost on expiration and was never received.
- `collateral_lost_sc` is committed host principal that was not returned.

For expirations, the total protocol burn is derived and internally checked as:

```text
protocol burn = revenue_forfeited_sc + collateral_lost_sc
```

The derived total is not repeated as a public column.

### Reproducing revenue from explorer fields

Revenue is not a standalone value supplied by the explorer. SiaGraph derives it from the public V2 contract and resolution fields. All calculations below use integer Hastings and clamp a negative subtraction to zero.

For a formation, revision, storage proof or expiration, potential host revenue is:

```text
potential revenue = successful host output - total collateral committed by the host
```

If the subtraction would be negative, SiaGraph uses zero. In the explorer data, these values are `hostOutput.value` and `totalCollateral`.

The resolution determines its disposition:

- Storage proof: `revenue_unlocked = potential revenue`.
- Expiration: `revenue_forfeited = potential revenue`.
- Neither outcome rolls revenue into another contract.

Renewals need a different source because V2 revisions are normally exchanged off-chain. The parent contract shown on-chain may therefore predate its actual final state. The renewal resolution preserves the latest host-side value across its final output and rollover:

```text
final host value = amount paid directly to the host + host amount carried into the successor
final parent revenue = final host value - total host collateral in the parent
successor revenue = successful host output of the successor - total host collateral in the successor
```

Negative revenue results are treated as zero. The corresponding explorer fields are `finalHostOutput.value`, `hostRollover`, `parent.totalCollateral`, `newContract.hostOutput.value` and `newContract.totalCollateral`.

If the parent and successor have the same `proofHeight` and `expirationHeight`, SiaGraph treats the operation as a refresh and calculates:

```text
revenue_rolled = the smaller of final parent revenue and successor revenue
revenue_unlocked = final parent revenue - revenue_rolled
```

If either height changes, it is a duration-extending renewal. In that case:

```text
revenue_rolled = 0
revenue_unlocked = final parent revenue
```

For an expiration, collateral loss can likewise be reproduced from public values:

```text
collateral lost = total host collateral in the parent - collateral returned after failure
```

If the subtraction would be negative, SiaGraph uses zero. The corresponding explorer fields are `parent.totalCollateral` and `parent.missedHostValue`.

These formulas are why the export can report unlocked, rolled and forfeited revenue even though those labels are not native explorer fields.

## Public row fields

JSON and CSV expose the same fields in this order:

| Field | Meaning |
| --- | --- |
| `contract_id` | Resolved V2 contract ID. |
| `successor_contract_id` | Contract created from this contract, or null. |
| `start_date_utc` | UTC `BlockTime` date at confirmation height. |
| `resolution_date_utc` | UTC `BlockTime` date at resolution height. |
| `payout_maturity_date_utc` | UTC `BlockTime` date at `resolution_height + 144`; date filters and price selection use this date. |
| `resolution_type` | `storage_proof`, `renewal` or `expiration`. |
| `revenue_rolled_sc` | Revenue carried into a renewal successor. |
| `revenue_unlocked_sc` | Revenue released by this resolution. |
| `revenue_forfeited_sc` | Potential revenue lost on expiration. |
| `collateral_lost_sc` | Host principal lost on expiration. |
| `average_sc_price_<currency>` | SiaGraph daily-average SC price for the payout-maturity date, or null when no valid observation exists. |
| `resolution_transaction_id` | Blockchain transaction resolving the contract. |

All SC amounts preserve 24 fractional digits because `1 SC = 10^24 Hastings`.

## Daily prices and currency reproduction

SiaGraph records Siacoin prices sourced from CoinGecko. For each UTC date, the endpoint uses the arithmetic mean of every valid positive price observation recorded by SiaGraph for the selected currency. It uses available observations and does not reject a date merely because fewer than 24 observations were recorded. No price is interpolated or substituted when a date has no valid observation.

A daily average was selected to reduce the influence of brief intraday price spikes, including short-lived pump-and-dump events. It is not a closing price or a volume-weighted price.

Per-row currency values are deliberately omitted because they are redundant. They can be reproduced exactly from the exported fields:

```text
revenue in currency = revenue_unlocked_sc × average_sc_price_<currency>
loss in currency    = collateral_lost_sc × average_sc_price_<currency>
```

The reported average is rounded half up to 24 fractional digits. Multiplication uses that reported average without minor-unit rounding. Accountants can apply locally required rounding afterward. JSON summary currency totals use the same multiplication; if any included row has no price, the corresponding total is null instead of presenting a partial total.

## Query and arithmetic

The service captures `MAX(BlockTime.block_height)` and bounds contract observations and maturity joins to that height. It selects the authoritative resolution row (`block_height = resolution_height`) per contract, falling back to the latest resolved state only when necessary. It then:

1. Joins confirmation, resolution and maturity heights to actual UTC `BlockTime` timestamps.
2. Enforces the 2025-07-01 resolution cutoff and requested maturity-date range.
3. Classifies nonnegative `revenue_locked` as rolled for renewals or forfeited for expirations.
4. Calculates expiration collateral loss as `max(host_balance - missed_host_output, 0)`.
5. Calculates SC by dividing integer Hastings by `10^24` using string arithmetic.
6. Calculates daily prices as `sum(valid recorded prices) / count(valid recorded prices)` and rounds half up to 24 fractional digits.

The JSON summary reports outcome counts, unlocked revenue totals, collateral-loss totals, the completion cutoff, selected maturity period, price method, currency, chain-height bound and generation time. CSV is intentionally a conventional rectangular file; the filename carries its host identity and request scope.

## Verification

Run arithmetic and endpoint checks:

```sh
php tests/tax/export.php
```

Run MySQL integration tests against a disposable local database:

```sh
docker run --detach --rm --name siagraph-tax-export-test -p 127.0.0.1:13377:3306 -e MYSQL_ROOT_PASSWORD=tax-fixture-only mysql:8.0 --skip-log-bin
php tests/tax/export.php --database --local
docker stop siagraph-tax-export-test
```

The integration suite covers resolution selection, the completion cutoff, successor linkage, all revenue dispositions, burn reconciliation, partial and missing daily price histories, exact decimal arithmetic, JSON/CSV equivalence and maturity/chain-height bounds.
