-- Apply to the configured raw_database before enabling heavy renter history use.
-- The host-first history index cannot serve wallet-filtered history queries.
ALTER TABLE siagraph_raw.Contracts
    ADD INDEX idx_contracts_renter_completed
        (renter_wallet_address, resolution_height DESC, contract_id DESC,
         block_height DESC, revisionnumber DESC);
