-- OPTIONAL: review EXPLAIN and measure representative host exports first.
-- Never applied by the endpoint. Replace siagraph_raw if configured differently.
CREATE INDEX idx_contracts_host_v2_resolution_tax
    ON siagraph_raw.Contracts
    (host_public_key, v2, resolution_type, resolution_height,
     contract_id, block_height, revisionnumber);
-- Adds one secondary-index entry per historical revision (not just resolutions).
-- Increases ingest/update work and disk usage; building it scans Contracts and
-- causes substantial I/O. It can reduce unresolved rows read for a host, but the
-- authoritative-resolution preference still requires a window sort.
