-- Optional directory index, verified with 5,003 synthetic wallets on MySQL 8.4.
-- Apply once through the normal migration process after checking production workload.
-- The default ORDER BY uses mixed directions; a plain ascending index is not equivalent.
CREATE INDEX idx_renters_storage_rank
    ON Renters (contracted_filesize DESC, renter_wallet_address ASC);
