<?php
/** Fixed chart groups: each chart compares fields with the same unit. */
function render_renter_history(string $group): void
{
    $groups = [
        'contracts' => [
            ['active', 'Active contracts', '', ['active_contracts', 'active_hosts']],
            ['formed', 'Contract formations & revisions', '', ['contracts_formed', 'contract_revisions'], 'bar'],
            ['resolved', 'Contract resolutions', '', ['contracts_resolved_storage_proof', 'contracts_resolved_expiration', 'contracts_resolved_renewal'], 'bar'],
            ['duration', 'Average contract duration', 'Time derived from blocks is an estimate.', ['average_contract_duration']],
        ],
        'economics' => [
            ['spending', 'Daily spending & returned funds', '', ['spending', 'funds_returned'], 'bar'],
            ['allowance', 'Contract funds', '', ['refundable_allowance', 'host_revenue_committed']],
            ['renewal', 'Renewal funding', 'Gaps indicate unknown values, not zero funding.', ['renewal_funds_rolled', 'additional_renewal_funds'], 'bar'],
        ],
        'storage' => [
            ['storage', 'Contracted storage', '', ['contracted_filesize']],
            ['hosts', 'Active hosts', '', ['active_hosts']],
        ],
        'network' => [
            ['storage', 'Network contracted storage', '', ['contracted_filesize']],
            ['contracts', 'Network active contracts', '', ['active_contracts']],
            ['spending', 'Network spending & returned funds', '', ['spending', 'funds_returned'], 'bar'],
        ],
    ];
    $charts = $groups[$group] ?? [];
    ?>
    <div class="renter-history-group" data-history-group="<?php echo $group; ?>">
        <section class="card renter-history-controls" aria-label="<?php echo ucfirst($group); ?> history range">
            <div class="renter-range-buttons" role="group" aria-label="History range">
                <?php foreach (['30' => '30D', '90' => '90D', '365' => '1Y', 'all' => 'All'] as $days => $label): ?>
                <button type="button" data-days="<?php echo $days; ?>" aria-pressed="<?php echo $days === 365 ? 'true' : 'false'; ?>"><?php echo $label; ?></button>
                <?php endforeach; ?>
            </div>
            <form class="renter-controls" data-history-form>
                <label>From<input type="date" name="start" required></label>
                <label>Through<input type="date" name="end" required></label>
                <button type="submit">Apply dates</button>
            </form>
            <p data-history-status role="status" aria-live="polite">Loading history…</p>
        </section>
        <div class="renter-history-grid">
            <?php foreach ($charts as $chart):
                [$id, $title, $description, $fields] = $chart;
                $chartType = $chart[4] ?? 'line';
            ?>
            <section class="card renter-history-card" id="renter-history-<?php echo $group . '-' . $id; ?>" data-chart-type="<?php echo $chartType; ?>" data-history-fields="<?php echo htmlspecialchars(json_encode($fields), ENT_QUOTES, 'UTF-8'); ?>">
                <h2<?php if ($description !== ''): ?> title="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>><?php echo htmlspecialchars($title); ?></h2>
                <p data-chart-status role="status">Loading…</p>
                <div class="renter-chart" data-chart-wrap hidden><canvas role="img" aria-label="<?php echo htmlspecialchars($title); ?>"></canvas></div>
            </section>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
