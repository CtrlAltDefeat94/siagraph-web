# V1 to V2 Data Parity Map

This document maps each V1 endpoint to its canonical V2 counterpart.
V2 responses use the envelope:

```json
{ "data": ..., "meta": ..., "errors": [] }
```

For parity, each V2 endpoint preserves all V1 payload fields under `data.parity` (when a normalized projection is also provided) or directly under `data` when no projection is needed.

## Alerts
- `POST /api/v1/alerts/subscribe` -> `POST /api/v2/alerts/subscriptions`
- `GET /api/v1/alerts/unsubscribe` -> `GET /api/v2/alerts/unsubscribe`
- canonical delete added: `DELETE /api/v2/alerts/subscriptions?token=...`

## Hosts
- `GET /api/v1/hosts` -> `GET /api/v2/hosts`
- `GET /api/v1/hosts?meta=1` -> `GET /api/v2/hosts/meta`
- `GET /api/v1/host?id=...|public_key=...` -> `GET /api/v2/hosts/details?host_id=...|public_key=...`
- `GET /api/v1/host_troubleshooter` -> `GET /api/v2/hosts/troubleshoot`
- `POST /api/v1/scan_host` -> `POST /api/v2/hosts/scan`

## Network
- `GET /api/v1/daily/metrics` -> `GET /api/v2/network/metrics?interval=day`
- `GET /api/v1/monthly/metrics` -> `GET /api/v2/network/metrics?interval=month`
- `GET /api/v1/daily/aggregates` -> `GET /api/v2/network/aggregates?interval=day`
- `GET /api/v1/monthly/aggregates` -> `GET /api/v2/network/aggregates?interval=month`
- `GET /api/v1/daily/growth` -> `GET /api/v2/network/growth?interval=day`
- `GET /api/v1/monthly/growth` -> `GET /api/v2/network/growth?interval=month`
- `GET /api/v1/daily/compare_metrics` -> `GET /api/v2/network/compare-metrics`
- `GET /api/v1/daily/host_prices` -> `GET /api/v2/network/host-prices`
- `GET /api/v1/monthly/revenue` -> `GET /api/v2/network/revenue/monthly`
- `GET /api/v1/storage/ath` -> `GET /api/v2/network/storage/ath`
- `GET /api/v1/storage/renter_distribution` -> `GET /api/v2/network/storage/renter-distribution`

## Explorer
- `GET /api/v1/explorer_metrics` -> `GET /api/v2/explorer/metrics`
- `GET /api/v1/peers` -> `GET /api/v2/explorer/peers`
- `GET /api/v1/transactions.php` -> `GET /api/v2/explorer/transactions`

## Markets
- `GET /api/v1/daily/exchange_rate` -> `GET /api/v2/markets/exchange-rates`

## Precision note
Where V1 relied on string precision for large/high-precision values, V2 preserves those values in parity payloads without lossy coercion.
