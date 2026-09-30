-- Run in the database containing HostsDailyStats (collector settings.py).
ALTER TABLE HostsDailyStats
    ADD COLUMN estimated_egress_gb BIGINT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Estimated V2 egress in decimal GB, spread over observed revision intervals; provisional, not measured traffic',
    ADD COLUMN egress_unestimated_intervals INT UNSIGNED NULL DEFAULT NULL
    COMMENT 'Observed intervals omitted from the daily estimate; NULL means not assessed';

CREATE TABLE HostEgressEstimateJob (
    job_name VARCHAR(64) NOT NULL PRIMARY KEY,
    calculation_version INT UNSIGNED NOT NULL,
    last_block_height INT UNSIGNED NOT NULL,
    egress_min_price DECIMAL(50,0) NULL DEFAULT NULL,
    calculated_at DATETIME NOT NULL
) ENGINE=InnoDB;

-- No additional indexes required on the inspected database: Contracts already
-- has idx_contracts_block_height and a host_public_key-leading index;
-- BlockTime has PRIMARY KEY (block_height); HostsDailyStats has its host/date primary key.
