# Host egress estimates

Version 2 upgrade: see `/opt/siagraph/docs/host-egress-v2.md` and apply `/opt/siagraph/docs/sql/host-egress-estimates-v2.sql` to an existing version-1 installation before rebuilding. Version 2 preserves partial daily totals and defaults to a minimum egress price of 1 SC/TB.

`/opt/siagraph/estimate_host_egress.py` is a dedicated collector job. It imports `utils.database.DatabaseConnector` and `settings.py` from `--collector-dir` (default `/opt/siagraph`), using the collector's existing database configuration. It requires Python 3.10+ and the collector's `mysql-connector-python` dependency. No PHP configuration or credentials are copied into this script.

Apply `docs/sql/host-egress-estimates.sql` in the database containing `HostsDailyStats`. No tables in the raw database need changing. Existing indexes were inspected and are sufficient.

Example, using the Python environment normally used for collection:

```sh
python3 /opt/siagraph/estimate_host_egress.py --host 'ed25519:HOST_KEY' --dry-run
python3 /opt/siagraph/estimate_host_egress.py --rebuild
python3 /opt/siagraph/estimate_host_egress.py
```

Schedule the last command after contract ingestion and daily host price collection. A MySQL advisory lock prevents overlapping estimator jobs. The default cutoff leaves 12 indexed blocks of confirmation headroom. The job revisits 288 blocks behind its checkpoint to catch recent corrected observations. The first write run rebuilds all hosts. Dry runs do not require the migration, never update the checkpoint, and scan all hosts unless restricted with `--host`.

Calculations start on **2025-07-01 UTC**. Earlier `--start` values are clamped to that date, including rebuilds. Until the confirmed indexed chain reaches that date, the job exits without updating daily rows or its checkpoint. Earlier observations and prices may still be read as baselines for intervals crossing the boundary; only dates on or after the cutoff are written.

## Model

Only V2 contracts are supported in calculation version 1: the producer populates their renewal-aware `revenue_locked` and `revenue_unlocked` fields. Legacy contracts are not estimated.

For consecutive observations, take the change in `revenue_locked + revenue_unlocked`. Estimate newly uploaded data as positive net filesize growth. Assume these additions occurred uniformly across the interval, charge ingress at each day's advertised rate, and charge storage through contract expiration using that day's storage price. Interpolate block heights between observations to estimate remaining paid storage duration. Storage units are Hastings/byte/block; bandwidth units are Hastings/byte. All accounting is done in Decimal with 80 significant digits.

For a new contract observed at formation, subtract its advertised contract fee and initial storage/ingress costs. For a renewal, use the parent's final filesize as the inherited baseline, exclude that data from ingress, deduct any revenue carried into the child, and charge inherited retained storage only for the added contract duration. A missing formation or parent baseline is unknown, not invented. Resolution transfers between locked and unlocked revenue do not create new traffic.

Divide the residual by the interval's time-weighted download price. This assumes roughly uniform egress volume over time. Spread the estimated bytes over UTC dates in proportion to elapsed seconds. Same-block changes are assigned to that observation date. Nonmonotonic block timestamps are clamped to the preceding observation time.

This cannot distinguish unobserved replacement uploads, account funding, sector-access/RPC fees, reserved capacity versus filesize, or prices actually negotiated between snapshots. Those charges may be misclassified as egress. Expiration is the available storage-duration proxy; the raw table lacks proof height. The output is an educated guess, not measured traffic or a statistical confidence interval.

## Stored values and rebuilding

`HostsDailyStats.estimated_egress_gb` contains whole decimal GB, rounded only after summing all contract contributions for that host/day. It publishes no ingress estimate. Unusable intervals (missing rates, zero/below-threshold download prices, negative residuals, missing renewal data) are omitted and counted in `egress_unestimated_intervals`. Valid contributions from other intervals remain in the daily sum. NULL egress means no valid interval contributed; numeric totals with omitted intervals are partial. Days with no bounded observations have both fields NULL. Zero means valid contributions rounded to zero, not measured zero traffic. Unobserved open tails are not extrapolated; totals remain provisional.

After a checkpoint exists, unrestricted incremental runs start at the oldest observed height of the renewal parents referenced by that host's `Contracts_Active.renewed_from_contract_id`. Active contracts without parents use their own first observation. Contracts changed in the checkpoint overlap are also included so newly completed contracts, absent from the active snapshot, are not lost. Missing linkage falls back to a full-history read.

The height maps to a UTC day boundary. Load every host observation from that day onward, plus the immediately preceding observation for each crossing contract and any required renewal-parent final states. Those baseline rows can predate the window; they are needed to calculate complete interval deltas and account for every contract contributing to the updated days. Historical daily price rows are still read for pricing crossing intervals. Replace only daily rows on or after the boundary (and never before 2025-07-01); earlier estimates remain untouched. No interval table is needed.

The initial run, explicit `--rebuild`, and host/date-restricted runs retain full-history processing. The job processes one host at a time and does not create missing HostsDailyStats rows. A read-only comparison on host 10 reduced loaded observations from 2,757 to 822 with a 2026-06-23 boundary and identical daily results inside the window.

Each host's writes commit together; the global checkpoint advances only after all hosts finish. A failed job can safely be rerun. Hosts/date-restricted runs do not advance the global checkpoint. `--start YYYY-MM-DD --end YYYY-MM-DD` limits writes but still reads the full history so boundary-spanning intervals are calculated correctly. A new observed revision can update earlier months.

Use `--rebuild` after historical prices become available, old records are corrected, deep reorganizations occur, or the formula changes. Incremental block discovery cannot detect arbitrary edits to old prices or rows. Run a periodic full rebuild after daily statistics collection to repair such changes; use a consistent indexed source and rebuild after any reorganization affecting the processed range. Formula changes require incrementing `VERSION` and a full unrestricted rebuild.

```sh
python3 /opt/siagraph/estimate_host_egress.py --start 2025-08-01 --end 2025-08-31 --rebuild
python3 -m unittest discover -s tests/egress -v
```

The host page History tab displays monthly “Estimated egress” in decimal GB, with the estimation caveat in its heading tooltip. It loads `/api/v1/host_estimated_egress?public_key=ed25519:HOST_KEY` when viewed. The endpoint accepts prefixed or bare 64-character hex host keys, uses the configured statistics database, and aggregates records from 2025-07-01. Responses contain `public_key`, `start_date`, and an `estimated_egress` array with only `month` and `estimated_egress_gb`. Coverage fields remain internal to the query and are excluded from the API response. Results are cached hourly. Invalid keys return 400; unavailable data sources return 503.

The chart displays only egress and preserves NULL values and missing months as gaps. Monthly totals are provisional and may be partial, including when omitted intervals occur on days with estimates. Run `node tests/hosts/estimated-egress.cjs` for chart data and loading checks.

Validation also includes `tests/egress/database_smoke.py`, run in the collector's Python environment. It uses connection-local temporary tables to exercise updates, checkpoint advancement, idempotent reruns, and clearing stale totals. The configured host-10 dry run on 2026-09-12 read 2,757 observations and 900 daily rows: 47 days had calculable estimates, with the remainder NULL. No persistent data was changed during validation.

The host explorer displays total estimated egress in decimal GB over the previous 30 complete UTC days (excluding the current partial day), with the same caveat tooltip. `/api/v1/hosts?sort=estimated_egress` sorts totals descending before pagination, with unknown totals last and matching filtered ranks. The host list exposes only `estimated_egress_gb`; no coverage fields are added.
