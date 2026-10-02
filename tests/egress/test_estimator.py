import importlib.util
from pathlib import Path
from datetime import datetime, date
from decimal import Decimal
import unittest

spec = importlib.util.spec_from_file_location('estimator', Path('/opt/siagraph/estimate_host_egress.py'))
e = importlib.util.module_from_spec(spec)
spec.loader.exec_module(e)


def row(**kw):
    r = dict(contract_id='a', revisionnumber=0, block_height=100, confirmation_height=100,
             filesize=0, windowend=120, revenue_locked=0, revenue_unlocked=0,
             renewed_from_contract_id=None, resolution_type=None,
             observed_at=datetime(2026, 1, 31, 12), formation_at=datetime(2026, 1, 31, 12))
    r.update(kw)
    return r


def prices(**kw):
    p = dict(storage_price=0, upload_price=0, download_price=2, contract_price=0)
    p.update(kw)
    return {date(2026,1,31): dict(p), date(2026,2,1): dict(p)}


class EstimatorTests(unittest.TestCase):
    def test_spread_across_month_boundary(self):
        a = row()
        b = row(revisionnumber=1, block_height=120, observed_at=datetime(2026,2,1,12), revenue_locked=4_000_000_000)
        self.assertEqual(e.aggregate([a,b], prices()), {date(2026,1,31):1, date(2026,2,1):1})
        self.assertEqual(e.aggregate([a,b], prices()), e.aggregate([a,b], prices()))

    def test_prepaid_storage_and_net_ingress(self):
        a = row()
        b = row(block_height=120, revisionnumber=1, observed_at=datetime(2026,2,1,12),
                filesize=100, revenue_locked=4321200)
        out = e.estimate(a,b,prices(storage_price=1, upload_price=2, download_price=4320))
        self.assertAlmostEqual(float(sum(n for _,n in out)), 1000)

    def test_price_changes_use_time_weighted_price(self):
        p = prices(); p[date(2026,2,1)]['download_price'] = 6
        b = row(block_height=120, revisionnumber=1, observed_at=datetime(2026,2,1,12), revenue_locked=4000)
        self.assertEqual(sum(n for _,n in e.estimate(row(),b,p)), Decimal(1000))

    def test_refresh_excludes_carried_revenue_and_ingress(self):
        parent = row(contract_id='parent', filesize=100, resolution_type='renewal', revenue_locked=400)
        child = row(contract_id='child', renewed_from_contract_id='parent', filesize=100, revenue_locked=400)
        self.assertEqual(e.estimate(None,child,prices(upload_price=999),parent), [(date(2026,1,31),0)])

    def test_renewal_charges_extension_but_no_inherited_ingress(self):
        parent = row(contract_id='parent', filesize=100, resolution_type='renewal')
        child = row(renewed_from_contract_id='parent', filesize=100, windowend=130, revenue_locked=433000)
        out = e.estimate(None,child,prices(storage_price=1,upload_price=999,download_price=4320),parent)
        self.assertEqual(out, [(date(2026,1,31),100)])

    def test_missing_prices_and_negative_residual_are_unknown(self):
        b = row(block_height=120, observed_at=datetime(2026,2,1,12), revenue_locked=0, filesize=100)
        for p in [prices(storage_price=1), {}, prices(download_price=0)]:
            self.assertTrue(all(v is None for _,v in e.estimate(row(),b,p)))

    def test_resolution_does_not_double_count_revenue(self):
        a = row(revenue_locked=400, filesize=0)
        b = row(block_height=120, observed_at=datetime(2026,2,1,12), revenue_locked=0,
                revenue_unlocked=400, resolution_type='storage_proof')
        self.assertEqual(sum(v for _,v in e.estimate(a,b,prices())), 0)

    def test_missing_initial_state_not_invented(self):
        a = row(block_height=110)
        self.assertEqual(e.estimate(None,a,prices()), [(date(2026,1,31),None)])

    def test_round_after_aggregating_contracts(self):
        a = row(revenue_locked=800_000_000)
        b = row(contract_id='b', revenue_locked=800_000_000)
        self.assertEqual(e.aggregate([a,b],prices()), {date(2026,1,31):1})

    def test_unknown_contribution_preserves_known_total_and_coverage(self):
        a = row(revenue_locked=4_000_000_000)
        b = row(contract_id='b', revenue_locked=None)
        coverage = {}
        self.assertEqual(e.aggregate([a,b],prices(),coverage=coverage), {date(2026,1,31):2})
        self.assertEqual(coverage, {date(2026,1,31):1})

    def test_relative_floor_skips_low_prices_without_substitution(self):
        a = row(revenue_locked=432000)
        for price in [0, 2159]:
            self.assertEqual(e.estimate(None,a,prices(storage_price=1,download_price=price)), [(date(2026,1,31),None)])
        self.assertEqual(e.estimate(None,a,prices(storage_price=1,download_price=2160)), [(date(2026,1,31),200)])
        self.assertEqual(e.estimate(None,a,prices(storage_price=1,download_price=8640)), [(date(2026,1,31),50)])

    def test_no_valid_interval_remains_null(self):
        coverage = {}
        self.assertEqual(e.aggregate([row(revenue_locked=None)],prices(),coverage=coverage), {date(2026,1,31):None})
        self.assertEqual(coverage, {date(2026,1,31):1})

    def test_interval_crossing_low_price_day_is_unknown(self):
        p = prices(storage_price=1,download_price=4320); p[date(2026,2,1)]['download_price'] = 2159
        b = row(block_height=120, observed_at=datetime(2026,2,1,12), revenue_locked=100)
        self.assertTrue(all(v is None for _,v in e.estimate(row(),b,p)))

    def test_renewal_size_recovers_hidden_upload_charges(self):
        start = row()
        end = row(block_height=120, revisionnumber=0, observed_at=datetime(2026,2,1,12),
                  revenue_locked=4321200, resolution_type='renewal')
        child = row(contract_id='child', renewed_from_contract_id='a', block_height=120,
                    confirmation_height=120, filesize=100, revenue_locked=4321200,
                    observed_at=datetime(2026,2,1,12), formation_at=datetime(2026,2,1,12))
        fixed, _ = e.reconcile_renewal_sizes([start,end,child])
        self.assertEqual(fixed[1]['filesize'], 100)
        self.assertEqual(end['filesize'], 0, 'Input records must remain unchanged')
        p = prices(storage_price=1,upload_price=2,download_price=4320)
        self.assertEqual(sum(v for _,v in e.estimate(fixed[0],fixed[1],p)), 1000)
        self.assertEqual(sum(v for _,v in e.estimate(None,fixed[2],p,fixed[1])), 0,
                         'Inherited data must not incur ingress twice')

    def test_child_baseline_corrects_parent_outside_window(self):
        parent = row(resolution_type='renewal', filesize=10)
        child = row(contract_id='child', renewed_from_contract_id='a', filesize=20)
        _, parents = e.reconcile_renewal_sizes([child], {'a':parent})
        self.assertEqual(parents['a']['filesize'],20)
        self.assertEqual(parent['filesize'],10)

    def test_renewal_size_requires_matching_formation(self):
        parent = row(resolution_type='renewal', filesize=10)
        child = row(contract_id='child', renewed_from_contract_id='a', filesize=20)
        for changes in [dict(block_height=101),dict(revisionnumber=1),dict(host_public_key='other'),dict(filesize=None)]:
            fixed,_ = e.reconcile_renewal_sizes([parent,dict(child,**changes)])
            self.assertEqual(fixed[0]['filesize'],10)
        fixed,_ = e.reconcile_renewal_sizes([parent,child,dict(child,contract_id='second')])
        self.assertEqual(fixed[0]['filesize'],10, 'Ambiguous child linkage must not be inferred')


if __name__ == '__main__': unittest.main()
