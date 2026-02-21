<?php require_once 'bootstrap.php'; ?>
<?php require_once 'include/layout.php'; ?>
<?php render_header('SiaGraph - Host Troubleshooter'); ?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-wrench me-2"></i>Host Troubleshooter</h1>

    <!-- Search -->
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column" style="max-width: 800px;">
                <section class="card">
                    <h2 class="card__heading">Lookup a Host</h2>
                    <div class="card__content">
                        <form id="hostLookupForm" class="row g-2">
                            <div class="col-sm-9">
                                <input type="text" id="hostLookupInput" class="form-control" placeholder="example.com:9984 or ed25519:...">
                            </div>
                            <div class="col-sm-3">
                                <button type="submit" class="button w-100">Lookup</button>
                            </div>
                        </form>
                    </div>
                </section>
                <section id="recentHosts" class="card mt-3" style="display: none;">
                    <h2 class="card__heading">Recently Searched Hosts</h2>
                    <div class="card__content">
                        <ul id="recentHostList" class="mb-0"></ul>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div id="resultsSection" style="display: none;">
        <div id="warningsErrors" class="mb-4"></div>

        <div class="sg-container__row mb-4">
            <div class="sg-container__row-content">
                <div class="sg-container__column" style="flex:0 0 360px;max-width:360px">
                    <section class="card">
                        <h2 class="card__heading">Connection Status</h2>
                        <div class="card__content" id="connectionStatus"></div>
                    </section>
                </div>
                <div class="sg-container__column" style="flex:1 1 0%;min-width:0">
                    <section class="card">
                        <h2 class="card__heading">Host Information</h2>
                        <div class="card__content">
                            <table class="table table-dark table-clean" style="table-layout:fixed" id="hostInfo"></table>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div class="sg-container__row mb-4">
            <div class="sg-container__row-content">
                <div class="sg-container__column">
                    <section class="card">
                        <h2 class="card__heading">Storage Usage</h2>
                        <div class="card__content">
                            <div class="progress" style="height: 24px;">
                                <div id="storageBar" class="progress-bar" role="progressbar" style="width: 0%;">0%</div>
                            </div>
                            <div id="storageStats" class="text-center text-muted mt-2"></div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div class="sg-container__row">
            <div class="sg-container__row-content">
                <div class="sg-container__column">
                    <section class="card">
                        <h2 class="card__heading">Settings &amp; Pricing</h2>
                        <div class="card__content">
                            <div id="settingsGrid" class="row g-3 settings-grid"></div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
  // Fallbacks when global helpers (from script.js) haven't executed yet
  async function fetchCachedOrDirect(url, options = {}, ttl = 3600000, parseAs = 'json') {
    try {
      if (typeof fetchWithCache === 'function') {
        return await fetchWithCache(url, options, ttl, parseAs);
      }
    } catch (_) { /* ignore and use direct fetch */ }
    const res = await fetch(url, options);
    if (!res.ok) throw new Error(`Unexpected HTTP code: ${res.status}`);
    return parseAs === 'text' ? res.text() : res.json();
  }
  function getCookieSafe(name) {
    if (typeof getCookie === 'function') return getCookie(name);
    const cookieArr = document.cookie.split('; ');
    for (let i = 0; i < cookieArr.length; i++) {
      const cookiePair = cookieArr[i].split('=');
      if (cookiePair[0] === name) return cookiePair[1];
    }
    return null;
  }
  function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
  }

  async function loadHostData() {
    const netAddress = getQueryParam("net_address");
    const publicKey = getQueryParam("public_key");
    const lookupValue = publicKey || netAddress || '';
    const lookupType = publicKey ? 'public_key' : 'net_address';

    // Set value into search input
    document.getElementById("hostLookupInput").value = lookupValue;

    // Hide results if no net address
    const resultsSection = document.getElementById("resultsSection");
    if (!lookupValue) {
      resultsSection.style.display = "none";
      loadRecentHistory();
      return;
    } else {
      resultsSection.style.display = "block";
    }

    // Basic validation for net_address lookups (API expects host:port)
    const basicFormat = /^([\w\.-]+):(\d+)$/;
    if (lookupType === 'net_address' && !basicFormat.test(netAddress)) {
      const warningsErrors = document.getElementById('warningsErrors');
      resultsSection.style.display = 'block';
      warningsErrors.innerHTML = '<div class="alert alert-danger" role="alert">Invalid address. Use host:port (e.g. example.com:9984).</div>';
      return;
    }

    try {
      const query = lookupType === 'public_key'
        ? `public_key=${encodeURIComponent(publicKey)}`
        : `net_address=${encodeURIComponent(netAddress)}`;
      const data = await fetchCachedOrDirect(`/api/v1/host_troubleshooter?${query}`);
      if (data && data.error) {
        const warningsErrors = document.getElementById('warningsErrors');
        warningsErrors.innerHTML = `<div class=\"alert alert-danger\" role=\"alert\">${data.error}</div>`;
        return;
      }
      renderHostData(data || {});
      addToRecentHistory(lookupType, lookupValue);
    } catch (error) {
      console.error("Failed to load host data", error);
      const warningsErrors = document.getElementById('warningsErrors');
      resultsSection.style.display = 'block';
      warningsErrors.innerHTML = '<div class="alert alert-danger" role="alert">Failed to load host data. Please try again.</div>';
    }
  }
  function renderHostData(data) {
    const isV2 = !!data.v2;
    const settings = data.settings || {};

    const warningsErrors = document.getElementById('warningsErrors');
    warningsErrors.innerHTML = '';
    const warnList = Array.isArray(data.warnings) ? data.warnings : [];
    const errList = Array.isArray(data.errors) ? data.errors : [];
    if (warnList.length || errList.length) {
      let html = '';
      if (warnList.length) {
        html += '<div class="alert alert-warning" role="alert">';
        html += '<div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Warnings</div><ul class="mb-0 ps-3">';
        warnList.forEach(w => html += `<li>${w}</li>`);
        html += '</ul></div>';
      }
      if (errList.length) {
        html += '<div class="alert alert-danger" role="alert">';
        html += '<div class="fw-semibold mb-1"><i class="bi bi-x-circle-fill me-1"></i>Errors</div><ul class="mb-0 ps-3">';
        errList.forEach(e => html += `<li>${e}</li>`);
        html += '</ul></div>';
      }
      warningsErrors.innerHTML = html;
    }

    const connectionStatus = document.getElementById('connectionStatus');

    const connectionChecks = {
      'Online': !!data.online,
      'Accepting Contracts': !!(settings.acceptingcontracts || settings.acceptingContracts),
      'IPv4': !!data.ipv4_enabled,
      'IPv4 RHP2': data.port_status?.ipv4_rhp2 ?? false,
      'IPv4 RHP3': data.port_status?.ipv4_rhp3 ?? false,
      'IPv4 RHP4': data.port_status?.ipv4_rhp4 ?? false,
      'IPv6': !!data.ipv6_enabled,
      'IPv6 RHP2': data.port_status?.ipv6_rhp2 ?? false,
      'IPv6 RHP3': data.port_status?.ipv6_rhp3 ?? false,
      'IPv6 RHP4': data.port_status?.ipv6_rhp4 ?? false
    };

    const ipv4Checks = ['IPv4'];
    const ipv6Checks = ['IPv6'];

    if (!isV2) {
      ipv4Checks.push('IPv4 RHP2', 'IPv4 RHP3');
      ipv6Checks.push('IPv6 RHP2', 'IPv6 RHP3');
    }

    ipv4Checks.push('IPv4 RHP4');
    ipv6Checks.push('IPv6 RHP4');


    const renderSection = (title, keys) => {
      return `<div class="mb-3">
        <h5 class='mb-2'>${title}</h5>
        <div class="row row-cols-2 g-2 status-grid">` +
        keys.map(k => {
          const v = connectionChecks[k];
          const cls = v ? 'border-success text-success' : 'border-danger text-danger';
          const icon = v ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
          return `<div class='col'><div class='status-tile rounded border ${cls}'><i class="bi ${icon} me-1"></i><span class='status-label'>${k}</span></div></div>`;
        }).join('') +
        '</div></div>';
    };

    connectionStatus.innerHTML =
      `<div class="mb-2">${renderSection('General', ['Online', 'Accepting Contracts'])}</div>
       <div class="row g-3">
         <div class="col-12 col-lg-6">${renderSection('IPv4 Status', ipv4Checks)}</div>
         <div class="col-12 col-lg-6">${renderSection('IPv6 Status', ipv6Checks)}</div>
       </div>`;

    const hostInfo = document.getElementById('hostInfo');
    const infoFields = {
      'Public Key': data.public_key,
      'Net Address': data.net_address,
      'V2': isV2,
      'Uptime': (Number(data.uptime || 0) * 100).toFixed(2) + "%",
      'Last scan': data.last_scan ? new Date(data.last_scan).toLocaleString(window.APP_LOCALE || undefined) : '—',
      'Next scan': data.next_scan ? new Date(data.next_scan).toLocaleString(window.APP_LOCALE || undefined) : '—',
      'Software Version': data.software_version,
      'Protocol Version': data.protocol_version
    };
    const rows = Object.entries(infoFields).map(([k, v]) => {
      const value = (v ?? '—');
      const extraClass = k === 'Public Key' ? 'font-monospace text-break' : '';
      const extraStyle = k === 'Public Key' ? " style='word-break:break-all'" : '';
      return `<tr><th class='fw-semibold'>${k}</th><td class='text-start ${extraClass}'${extraStyle}>${value}</td></tr>`;
    }).join('');
    hostInfo.innerHTML = `<colgroup><col style='width:30%'><col style='width:70%'></colgroup>` + rows;


    const usedBytes = parseInt(data.used_storage || 0);
    const totalBytes = parseInt(data.total_storage || 0);

    const remainingBytes = totalBytes - usedBytes;

    // Calculate % used for progress bar
    const percentage = totalBytes > 0 ? Math.floor(((usedBytes) / totalBytes) * 100) : 0;
    const bar = document.getElementById('storageBar');
    bar.style.width = percentage + '%';
    bar.textContent = percentage + '%';



    document.getElementById('storageStats').innerText = `${formatDecimalBytes(usedBytes)} / ${formatDecimalBytes(totalBytes)} used`;
    const settingsGrid = document.getElementById('settingsGrid');
    const fieldConfig = {
      'Collateral': {
        value: settings.collateral,
        type: 'sc',
        normalize: 'monthly-tb'
      },
      'Contract Price': {
        value: settings.contractprice,
        type: 'sc'
      },
      'Download Bandwidth Price': {
        value: settings.egressprice,
        type: 'sc',
        normalize: 'tb'
      },
      'Upload Bandwidth Price': {
        value: settings.ingressprice,
        type: 'sc',
        normalize: 'tb'
      },
      'Storage Price': {
        value: settings.storageprice,
        type: 'sc',
        normalize: 'monthly-tb'
      },
      'Free Sector Price': {
        value: settings.freesectorprice,
        type: 'sc'
      },
      'Max Collateral': {
        value: settings.maxcollateral,
        type: 'sc',
      },
      'Max Duration': {
        value: settings.maxduration,
        type: 'number',
        unit: 'blocks',
        showMonths: true
      },
      'Base RPC Price': {
        value: settings.baserpcprice,
        type: 'sc'
      },
      'Max Ephemeral Account Balance': {
        value: settings.maxephemeralaccountbalance,
        type: 'sc'
      },
      'Ephemeral Account Expiry': {
        value: settings.ephemeralaccountexpiry,
        type: 'number',
        unit: 'blocks'
      },
      'Max Download Batch Size': {
        value: settings.maxdownloadbatchsize,
        type: 'number',
        unit: 'bytes'
      },
      'Max Revise Batch Size': {
        value: settings.maxrevisebatchsize,
        type: 'number',
        unit: 'bytes'
      },
      'Sector Access Price': {
        value: settings.sectoraccessprice,
        type: 'sc'
      },
      'Sector Size': {
        value: settings.sectorsize,
        type: 'number',
        unit: 'bytes'
      },
      'Window Size': {
        value: settings.windowsize,
        type: 'number',
        unit: 'blocks'
      }
    };

    settingsGrid.innerHTML = Object.entries(fieldConfig).map(([key, config]) => {
      const v = config.value;
      let display = v ?? '—';
      let fiatHtml = '';
      let extraHtml = '';

      if (v != null && config.type === 'sc') {
        const normalized = normalizeSC(v, config.normalize);
        display = `${normalized.toFixed(2)} ${getUnitLabel(config.normalize)}`;
        fiatHtml = `<span class="text-xs text-gray-400 block fiat-value" data-sc="${normalized}"></span>`;
      }

      if (v != null && config.type === 'number' && config.unit) {
        display = `${v} ${config.unit}`;
        if (config.showMonths) {
          const months = Math.round(v / 4320);
          extraHtml = `<span class="text-xs text-gray-400 block">${months} months</span>`;
        }
      }

      return `
        <div class='col-12 col-md-6 col-lg-4'>
          <div class='p-3 rounded border'>
            <div class='settings-label mb-1'>${key}</div>
            <div class='settings-value font-monospace'>
              ${display}
              ${extraHtml || ''}
              ${fiatHtml}
            </div>
          </div>
        </div>`;
    }).join('');

    applyFiatValues();
  }

  document.getElementById("hostLookupForm").addEventListener("submit", function (e) {
    e.preventDefault();
    const inputValue = document.getElementById("hostLookupInput").value.trim();
    if (inputValue) {
      const isPublicKey = /^ed25519:/i.test(inputValue);
      const url = new URL(window.location.href);
      if (isPublicKey) {
        url.searchParams.set("public_key", inputValue);
        url.searchParams.delete("net_address");
      } else {
        url.searchParams.set("net_address", inputValue.toLowerCase());
        url.searchParams.delete("public_key");
      }
      window.location.href = url.toString();
    }
  });

  function addToRecentHistory(type, value) {
    const history = JSON.parse(localStorage.getItem("recentHosts") || "[]");
    const normalizedHistory = history.map(item => {
      if (typeof item === 'string') {
        return { type: 'net_address', value: item };
      }
      return item;
    }).filter(item => item && item.type && item.value);

    const exists = normalizedHistory.some(item => item.type === type && item.value === value);
    if (!exists) normalizedHistory.unshift({ type, value });
    if (normalizedHistory.length > 5) normalizedHistory.length = 5;
    localStorage.setItem("recentHosts", JSON.stringify(normalizedHistory));
  }

  function loadRecentHistory() {
    const list = document.getElementById("recentHostList");
    const container = document.getElementById("recentHosts");
    const history = JSON.parse(localStorage.getItem("recentHosts") || "[]");
    const normalizedHistory = history.map(item => {
      if (typeof item === 'string') {
        return { type: 'net_address', value: item };
      }
      return item;
    }).filter(item => item && item.type && item.value);

    if (normalizedHistory.length === 0) return;

    list.innerHTML = normalizedHistory.map(item => {
      const param = item.type === 'public_key' ? 'public_key' : 'net_address';
      return `<li><a href="?${param}=${encodeURIComponent(item.value)}">${item.value}</a></li>`;
    }
    ).join("");
    container.style.display = '';
  }

  async function applyFiatValues() {
    const currency = getCookieSafe("currency") || "eur";
    let rate = 1;
    if (currency !== 'sc') {
      try {
        const rateText = await fetchCachedOrDirect(`https://explorer.siagraph.info/api/exchange-rate/siacoin/${currency}`, {}, 86400000, 'text');
        rate = parseFloat(rateText);

        if (isNaN(rate)) {
          return;
        }
      } catch (err) {
        console.error("Failed to fetch exchange rate", err);
        return;
      }
    }
    document.querySelectorAll('.fiat-value').forEach(el => {
      const scRaw = parseFloat(el.getAttribute('data-sc') || "0");
      if (!isNaN(scRaw)) {
        const fiat = scRaw * rate;
        el.textContent = `${currency.toUpperCase()} ${fiat >= 0.01 ? fiat.toFixed(2) : fiat.toFixed(5)}`;

      }
    });
  }
  // Convert to decimal GB / TB
  function formatDecimalBytes(bytes) {
    const tb = bytes / 1_000_000_000_000;
    const gb = bytes / 1_000_000_000;
    if (tb >= 1) return `${tb.toFixed(2)} TB`;
    if (gb >= 1) return `${gb.toFixed(2)} GB`;
    return `${(bytes / 1_000_000).toFixed(2)} MB`;
  }
  function normalizeSC(value, normalize) {
    let hastings = parseFloat(value);
    if (isNaN(hastings)) return 0;

    const HASTINGS_PER_SC = 1e24;
    const BYTES_IN_TB = 1e12;
    const BLOCKS_PER_MONTH = 4320;

    // convert to SC per byte first
    const scPerByte = hastings / HASTINGS_PER_SC;

    switch (normalize) {
      case 'monthly':
        return scPerByte * BLOCKS_PER_MONTH;
      case 'tb':
        return scPerByte * BYTES_IN_TB;
      case 'monthly-tb':
        return scPerByte * BYTES_IN_TB * BLOCKS_PER_MONTH;
      default:
        return scPerByte;
    }
  }

  function getUnitLabel(normalize) {
    switch (normalize) {
      case 'monthly': return 'SC/month';
      case 'tb': return 'SC/TB';
      case 'monthly-tb': return 'SC/month/TB';
      default: return 'SC';
    }
  }
  function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
      alert("Copied to clipboard!");
    }).catch(err => {
      console.error("Error copying text: ", err);
    });
  }
  // Ensure this runs after deferred global scripts (script.js) execute
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadHostData);
  } else {
    loadHostData();
  }
</script>

<?php render_footer(); ?>
