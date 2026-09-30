<section id="main-content" class="sg-container host-contracts-page">
    <section class="card host-section-card">
        <h1 class="card__heading"><?php echo ucfirst($profileKind); ?> contracts</h1>
        <?php if ($valid): ?>
        <p class="host-section-subtitle host-contracts-key"><?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?></p>
        <a class="button text-sm" href="/<?php echo $profileKind; ?>?<?php echo $identityParam; ?>=<?php echo rawurlencode($key); ?>#contracts">← Back to <?php echo $profileKind; ?></a>
        <?php else: ?>
        <p role="alert">A valid <?php echo $profileKind === 'host' ? 'host public key' : 'renter wallet address'; ?> is required. Open a profile and select Browse contracts.</p>
        <a class="button text-sm" href="/<?php echo $profileKind === 'host' ? 'host_explorer' : 'renters'; ?>">Browse <?php echo $profileKind === 'host' ? 'hosts' : 'renters'; ?></a>
        <?php endif; ?>
    </section>
    <?php if ($valid): ?>
    <div data-host-contract-browser data-kind="<?php echo $profileKind; ?>" data-identity="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="host-tabs" role="tablist" aria-label="Contract state">
            <?php foreach (['active' => 'Active', 'completed' => 'Completed'] as $state => $label): ?>
            <button type="button" class="host-tab" id="contracts-tab-<?php echo $state; ?>" role="tab" aria-selected="<?php echo $state === 'active' ? 'true' : 'false'; ?>" aria-controls="contracts-panel-<?php echo $state; ?>" tabindex="<?php echo $state === 'active' ? '0' : '-1'; ?>" data-contract-tab="<?php echo $state; ?>"><?php echo $label; ?></button>
            <?php endforeach; ?>
        </div>
        <?php foreach (['active', 'completed'] as $state): ?>
        <section class="card host-section-card active-contracts ac-host" role="tabpanel" id="contracts-panel-<?php echo $state; ?>" aria-labelledby="contracts-tab-<?php echo $state; ?>" data-contract-list="<?php echo $state; ?>" <?php if ($state === 'completed') echo 'hidden'; ?>>
            <h2 class="card__heading"><?php echo ucfirst($state); ?> contracts</h2>
            <p><?php echo $state === 'active' ? ($profileKind === 'host' ? 'Current contracts, their stored data, end heights, and committed revenue.' : 'Current contracts, their hosts, stored data, end heights, and renewal predecessors.') : 'Resolved contracts, with one entry per contract and its latest recorded resolution.'; ?></p>
            <form class="ac-controls ac-filterbar">
                <label class="ac-search">Find contract<input name="search" placeholder="Full contract ID" maxlength="64" pattern="[a-fA-F0-9]{64}"></label>
                <label>Sort<select name="sort">
                    <?php foreach ($state === 'active' ? ['ending' => 'Ending first', 'size' => 'Largest first', 'newest' => 'Newest first'] : ['newest' => 'Newest first', 'oldest' => 'Oldest first'] as $sort => $label): ?>
                    <option value="<?php echo $sort; ?>"><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select></label>
                <button type="submit" class="button text-sm">Apply</button>
            </form>
            <div class="ac-toolbar"><p class="ac-freshness" data-list-status role="status">Loading contracts…</p><button class="button text-sm" type="button" data-list-refresh>Refresh</button></div>
            <div class="ac-scroll" data-list-table tabindex="0" role="region" aria-label="<?php echo ucfirst($state); ?> contracts table"></div>
            <div class="ac-controls ac-pagination">
                <button class="button text-sm" type="button" data-list-previous disabled>Previous</button>
                <span data-list-page></span>
                <button class="button text-sm" type="button" data-list-next disabled>Next</button>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
