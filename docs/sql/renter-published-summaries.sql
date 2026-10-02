-- Optional producer-owned publication table. Apply through the database deployment process.
-- Write an entire snapshot payload atomically; set published_at only after validation.
CREATE TABLE IF NOT EXISTS RenterPublishedSummaries (
    kind ENUM('overview', 'daily', 'distribution') NOT NULL,
    snapshot_date DATE NOT NULL,
    snapshot_height INT NOT NULL,
    payload JSON NOT NULL,
    published_at DATETIME DEFAULT NULL,
    PRIMARY KEY (kind, snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
