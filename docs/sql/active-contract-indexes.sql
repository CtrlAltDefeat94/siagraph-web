-- Apply separately to the configured raw_database (currently siagraph_raw).
-- Existing indexes do not start with renter_wallet_address or host_public_key.
-- Do not drop existing producer indexes as part of this change.
ALTER TABLE siagraph_raw.Contracts_Active
    ADD INDEX idx_active_renter_end (renter_wallet_address, windowend, contract_id),
    ADD INDEX idx_active_host_end (host_public_key, windowend, contract_id);

-- Supports each host-key encoding's newest resolved rows without sorting its
-- whole contract history. The existing primary key supports the revision
-- deduplication probe. This can be a large index; use the normal DB deployment.
ALTER TABLE siagraph_raw.Contracts
    ADD INDEX idx_contracts_host_completed
        (host_public_key, resolution_height DESC, contract_id DESC,
         block_height DESC, revisionnumber DESC);
