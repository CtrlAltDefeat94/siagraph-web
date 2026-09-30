CREATE TABLE {{schema}}.Contracts_Active (
 contract_id CHAR(64) NOT NULL PRIMARY KEY,
 revisionnumber DECIMAL(21,0) NOT NULL,
 v2 TINYINT NOT NULL DEFAULT 0,
 block_height INT NOT NULL,
 filesize BIGINT NULL,
 windowend INT NULL,
 confirmation_height INT NULL,
 renter_wallet_address VARCHAR(78) NULL,
 renter_public_key VARCHAR(72) NULL,
 host_public_key VARCHAR(72) NULL,
 renewed_from_contract_id VARCHAR(64) NULL,
 revenue_locked DECIMAL(50,0) NULL
) ENGINE=InnoDB;
CREATE TABLE {{schema}}.Contracts (
 contract_id CHAR(64) NOT NULL,
 v2 TINYINT NOT NULL,
 block_height INT NOT NULL,
 revisionnumber DECIMAL(21,0) NOT NULL,
 host_public_key VARCHAR(72) NULL,
 renter_wallet_address VARCHAR(78) NULL,
 renter_public_key VARCHAR(72) NULL,
 resolution_height INT NULL,
 resolution_type VARCHAR(20) NULL,
 PRIMARY KEY (contract_id,v2,block_height,revisionnumber)
) ENGINE=InnoDB;
CREATE TABLE {{schema}}.BlockTime (
 block_height INT PRIMARY KEY,
 timestamp DATETIME NULL
) ENGINE=InnoDB;
