#!/usr/bin/env python3
"""Temporary-table integration check; persistent data is never changed.
Run with the collector Python environment and optionally its directory as argv[1].
"""
import importlib.util
from pathlib import Path
import sys
from decimal import Decimal
from types import SimpleNamespace

collector = sys.argv[1] if len(sys.argv) > 1 else '/opt/siagraph'
sys.path.insert(0, collector)
from utils.database import DatabaseConnector

spec = importlib.util.spec_from_file_location('estimator', Path('/opt/siagraph/estimate_host_egress.py'))
e = importlib.util.module_from_spec(spec)
spec.loader.exec_module(e)
import uuid
e.JOB = 'egress_fixture_' + uuid.uuid4().hex
db = DatabaseConnector(); db.connect()
conn = db.connection
cur = conn.cursor()
try:
    # Temporary tables shadow the persistent names only on this connection.
    cur.execute('CREATE TEMPORARY TABLE HostsDailyStats (public_key VARCHAR(72), date DATE, storage_price BIGINT, upload_price BIGINT, download_price BIGINT, contract_price DECIMAL(50,0), estimated_egress_gb BIGINT UNSIGNED NULL, egress_unestimated_intervals INT UNSIGNED NULL, PRIMARY KEY(public_key,date))')
    cur.execute('CREATE TEMPORARY TABLE HostEgressEstimateJob (job_name VARCHAR(64) PRIMARY KEY, calculation_version INT, last_block_height INT, egress_min_price DECIMAL(50,0) NULL, calculated_at DATETIME)')
    cur.execute('CREATE TEMPORARY TABLE siagraph_raw.BlockTime (block_height INT PRIMARY KEY, timestamp DATETIME)')
    cur.execute('CREATE TEMPORARY TABLE siagraph_raw.Contracts_Active (contract_id CHAR(64), renewed_from_contract_id CHAR(64), host_public_key VARCHAR(72), v2 TINYINT)')
    cur.execute('CREATE TEMPORARY TABLE siagraph_raw.Contracts (contract_id CHAR(64), v2 TINYINT, revisionnumber DECIMAL(21,0), block_height INT, confirmation_height INT, filesize BIGINT, windowend INT, revenue_locked DECIMAL(50,0), revenue_unlocked DECIMAL(50,0), host_public_key VARCHAR(72), renewed_from_contract_id CHAR(64), resolution_type VARCHAR(20))')
    cur.execute("INSERT INTO HostsDailyStats VALUES ('fixture', '2027-07-31',0,0,2,0,NULL,NULL),('fixture','2027-08-01',0,0,2,0,NULL,NULL)")
    cur.execute("INSERT INTO siagraph_raw.BlockTime VALUES (100,'2027-07-31 12:00:00'),(120,'2027-08-01 12:00:00')")
    cur.execute("INSERT INTO siagraph_raw.Contracts VALUES ('a',1,0,100,100,0,150,0,0,'fixture',NULL,NULL),('a',1,1,120,100,0,150,4000000000,0,'fixture',NULL,NULL)")
    cur.execute('CREATE TEMPORARY TABLE siagraph_raw.EgressFixtureFormationTimes AS SELECT * FROM siagraph_raw.BlockTime')
    conn.commit()
    # MySQL cannot reference a temporary table twice in one query; the second
    # timestamp join uses an identical temporary copy in this fixture only.
    class FixtureCursor:
        def __init__(self, cursor): self.cursor = cursor
        def execute(self, sql, params=()):
            return self.cursor.execute(sql.replace('.BlockTime f ON', '.EgressFixtureFormationTimes f ON'), params)
        def __getattr__(self, name): return getattr(self.cursor, name)
    class FixtureConnection:
        def cursor(self, **kwargs): return FixtureCursor(conn.cursor(**kwargs))
        def __getattr__(self, name): return getattr(conn, name)
    # Keep the same connection alive across runs so fixtures remain temporary.
    fake = SimpleNamespace(connection=FixtureConnection(), connect=lambda: None, close_connection=lambda: None)
    original = sys.modules['utils.database'].DatabaseConnector
    sys.modules['utils.database'].DatabaseConnector = lambda: fake
    args = SimpleNamespace(collector_dir=collector, raw_database='siagraph_raw', host=None,
        start=None, end=None, rebuild=False, dry_run=False, confirmations=0, overlap_blocks=288)
    for _ in range(2):
        e.run(args)
        cur.execute('SELECT estimated_egress_gb FROM HostsDailyStats ORDER BY date')
        assert cur.fetchall() == [(1,), (1,)], 'Reruns must replace, not accumulate'
    cur.execute('SELECT last_block_height FROM HostEgressEstimateJob')
    assert cur.fetchone() == (120,)
    # Incremental runs must preserve historical rows before the active boundary.
    cur.execute("INSERT INTO HostsDailyStats VALUES ('fixture','2025-08-01',0,0,2,0,77,NULL)")
    cur.execute("INSERT INTO siagraph_raw.BlockTime VALUES (90,'2025-08-01 12:00:00')")
    cur.execute("INSERT INTO siagraph_raw.EgressFixtureFormationTimes VALUES (90,'2025-08-01 12:00:00')")
    cur.execute("INSERT INTO siagraph_raw.Contracts VALUES ('old',1,0,90,90,0,95,0,0,'fixture',NULL,NULL)")
    cur.execute("INSERT INTO siagraph_raw.Contracts_Active VALUES ('a',NULL,'fixture',1)")
    conn.commit()
    args.overlap_blocks = 0
    e.run(args)
    cur.execute('SELECT estimated_egress_gb FROM HostsDailyStats ORDER BY date')
    assert cur.fetchall() == [(77,), (1,), (1,)], 'Bounded updates must preserve older totals'
    # Corrected prices make an interval unestimable; rebuilding must clear stale totals.
    cur.execute("UPDATE HostsDailyStats SET download_price=NULL WHERE date='2027-08-01'")
    conn.commit()
    args.rebuild = True
    e.run(args)
    cur.execute('SELECT estimated_egress_gb FROM HostsDailyStats ORDER BY date')
    assert cur.fetchall() == [(0,), (0,), (None,)]
    cur.execute('SELECT egress_unestimated_intervals FROM HostsDailyStats ORDER BY date')
    assert cur.fetchall() == [(0,), (1,), (1,)]
    args.rebuild = False
    cur.execute('UPDATE HostEgressEstimateJob SET calculation_version=4')
    conn.commit()
    try:
        e.run(args)
        raise AssertionError('Changing the calculation version must require a rebuild')
    except RuntimeError as error:
        assert 'version changed' in str(error)
    args.rebuild = True
    cur.execute('UPDATE HostsDailyStats SET storage_price=1')
    conn.commit()
    e.run(args)
    cur.execute('SELECT estimated_egress_gb FROM HostsDailyStats ORDER BY date')
    assert cur.fetchall() == [(None,), (None,), (None,)]
    print('PASS transactional writes, checkpoint, idempotent reruns, bounded history and stale-total clearing')
finally:
    cur.close()
    conn.close()  # drops all temporary tables
