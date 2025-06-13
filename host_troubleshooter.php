<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SiaGraph Host Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@latest/dist/tailwind.min.css" rel="stylesheet">
  <script src="script.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="icon" href="img/favicon.ico" type="image/png">
</head>

<body>
  <?php include "include/header.html" ?>
  <section id="main-content" class="container-sm mt-4 pb-5">

    <!-- Search Section -->
    <div class="row mb-4">
      <div class="col-lg-4 d-none d-lg-block"></div>
      <div class="col-lg-8">
        <div class="max-w-2xl" style="width: 100%;">
          <!-- Net Address Search -->
          <form id="netAddressForm" class="row g-2 mb-3">
            <div class="col-sm-9">
              <input type="text" id="netAddressInput" class="form-control" placeholder="example.com:9984" />
            </div>
            <div class="col-sm-3">
              <button type="submit" class="btn btn-primary w-100">Lookup</button>
            </div>
          </form>

          <!-- Recently Searched Hosts -->
          <div id="recentHosts" class="mb-5 hidden">
            <div class="bg-white shadow rounded-lg p-4">
              <h5 class="text-md font-semibold mb-2">Recently Searched Hosts</h5>
              <ul id="recentHostList" class="list-disc pl-5 text-sm text-gray-700"></ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Results Section -->
    <div id="resultsSection" style="display: none;">

      <!-- Issues Panel -->
      <div id="warningsErrors" class="mb-6"></div>

      <div class="row mb-6">
        <!-- Connection Status Panel -->
        <div class="col-lg-5 mb-4 mb-lg-0">
          <div class="bg-white shadow rounded-lg p-4 h-100">
            <h3 class="text-lg font-bold mb-3">Connection Status</h3>
            <div id="connectionStatus"></div>
          </div>
        </div>

        <!-- Host Info Panel -->
        <div class="col-lg-7">
          <div class="bg-white shadow rounded-lg p-4 h-100">
            <h3 class="text-lg font-bold mb-3">Host Information</h3>
            <table class="table-auto w-full text-sm" id="hostInfo"></table>
          </div>
        </div>
      </div>

      <!-- Storage Usage Panel -->
      <div class="bg-white shadow rounded-lg p-4 mb-6">
        <h3 class="text-lg font-bold mb-3">Storage Usage</h3>
        <div id="storageUsage" class="w-full bg-gray-200 rounded h-6">
          <div class="bg-blue-500 h-6 rounded text-xs text-white text-center flex items-center justify-center"
            id="storageBar">
            0%
          </div>
        </div>
        <div id="storageStats" class="text-sm text-gray-600 mt-2 text-center"></div>
      </div>

      <!-- Settings & Pricing Grid -->
      <div class="bg-white shadow rounded-lg p-4">
        <h3 class="text-lg font-bold mb-3">Settings & Pricing</h3>
        <div id="settingsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3"></div>
      </div>
    </div> <!-- end #resultsSection -->

  </section>
  <?php include "include/footer.php" ?>
</body>
<script>
  function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
  }

  async function loadHostData() {
    const netAddress = getQueryParam("net_address");

    // Set value into search input
    document.getElementById("netAddressInput").value = netAddress || '';

    // Hide results if no net address
    const resultsSection = document.getElementById("resultsSection");
    if (!netAddress) {
      resultsSection.style.display = "none";
      loadRecentHistory();
      return;
    } else {
      resultsSection.style.display = "block";
    }

    try {
      const response = await fetch(`/api/v1/host_troubleshooter?net_address=${encodeURIComponent(netAddress)}`);
      const data = await response.json();
      renderHostData(data);
      addToRecentHistory(netAddress);
    } catch (error) {
      console.error("Failed to load host data", error);
    }
  }
  function renderHostData(data) {
    const isV2 = data.v2;
    const settings = data.settings;

    const warningsErrors = document.getElementById('warningsErrors');
    warningsErrors.innerHTML = '';
    if (data.warnings?.length || data.errors?.length) {
      let html = '<div class="bg-red-100 border border-red-300 text-red-700 rounded p-3">';
      html += '<h4 class="font-bold mb-2">Issues</h4><ul class="list-disc pl-5">';
      data.warnings.forEach(w => html += `<li class='text-yellow-600'>⚠️ ${w}</li>`);
      data.errors.forEach(e => html += `<li class='text-red-600'>❌ ${e}</li>`);
      html += '</ul></div>';
      warningsErrors.innerHTML = html;
    }

    const connectionStatus = document.getElementById('connectionStatus');

    const connectionChecks = {
      'Online': data.online,
      'Accepting Contracts': settings.acceptingcontracts || settings.acceptingContracts,
      'IPv4 Enabled': data.ipv4_enabled,
      'IPv4 RHP2': data.port_status?.ipv4_rhp2,
      'IPv4 RHP3': data.port_status?.ipv4_rhp3,
      'IPv4 RHP4': data.port_status?.ipv4_rhp4,
      'IPv6 Enabled': data.ipv6_enabled,
      'IPv6 RHP2': data.port_status?.ipv6_rhp2,
      'IPv6 RHP3': data.port_status?.ipv6_rhp3,
      'IPv6 RHP4': data.port_status?.ipv6_rhp4
    };

    const ipv4Checks = ['IPv4 Enabled'];
    const ipv6Checks = ['IPv6 Enabled'];

    if (!isV2) {
      ipv4Checks.push('IPv4 RHP2', 'IPv4 RHP3');
      ipv6Checks.push('IPv6 RHP2', 'IPv6 RHP3');
    }

    ipv4Checks.push('IPv4 RHP4');
    ipv6Checks.push('IPv6 RHP4');


    const renderSection = (title, keys, gridCols = 1) => {
      const gridClass = gridCols > 1 ? `grid grid-cols-${gridCols} gap-2` : '';
      return `<div class="mb-3">
    <h5 class='font-semibold mb-2'>${title}</h5>
    <div class="${gridClass}">` +
        keys.map(k => {
          const v = connectionChecks[k];
          return `<div class='p-2 rounded ${v ? "bg-green-100 text-green-700" : "bg-red-100 text-red-700"}'>${v ? "✅" : "❌"} ${k}</div>`;
        }).join('') + '</div></div>';
    };

    connectionStatus.innerHTML =
      `<div class="mb-4">
    ${renderSection('General', ['Online', 'Accepting Contracts'], 2)}
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>${renderSection('IPv4 Status', ipv4Checks, 1)}</div>
      <div>${renderSection('IPv6 Status', ipv6Checks, 1)}</div>
   </div>`;

    const hostInfo = document.getElementById('hostInfo');
    const infoFields = {
      'Public Key': data.public_key,
      'Net Address': data.net_address,
      'V2': data.v2,
      'Uptime': (data.uptime * 100).toFixed(2) + "%",
      'Last scan': new Date(data.last_scan).toLocaleString(),
      'Next scan': new Date(data.next_scan).toLocaleString(),
      'Software Version': data.software_version,
      'Protocol Version': data.protocol_version
    };
    hostInfo.innerHTML = Object.entries(infoFields).map(([k, v]) =>
      `<tr><td class='py-1 pr-2 font-semibold text-gray-600'>${k}</td><td class='py-1'>${v || '—'}</td></tr>`
    ).join('');


    const usedBytes = parseInt(data.used_storage);
    const totalBytes = parseInt(data.total_storage);

    const remainingBytes = totalBytes - usedBytes;

    // Calculate % bar
    const percentage = totalBytes > 0 ? Math.floor((remainingBytes / totalBytes) * 100) : 0;
    document.getElementById('storageBar').style.width = 100 - percentage + '%';
    document.getElementById('storageBar').innerText = 100 - percentage + '%';



    document.getElementById('storageStats').innerText =
      `${formatDecimalBytes(usedBytes)} / ${formatDecimalBytes(totalBytes)} used`;
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
        type: 'sc',
        unit: 'blocks'
      },
      'Max Download Batch Size': {
        value: settings.maxdownloadbatchsize,
        type: 'sc',
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
  <div class='p-3 bg-gray-50 rounded shadow-sm border'>
    <div class='text-xs text-gray-500 mb-1'>${key}</div>
    <div class='font-mono break-all text-sm'>
      ${display}
      ${extraHtml || ''}
      ${fiatHtml}
    </div>
  </div>
`;
    }).join('');

    applyFiatValues();
  }

  document.getElementById("netAddressForm").addEventListener("submit", function (e) {
    e.preventDefault();
    const newAddress = document.getElementById("netAddressInput").value.trim().toLowerCase();
    if (newAddress) {
      const url = new URL(window.location.href);
      url.searchParams.set("net_address", newAddress);
      window.location.href = url.toString();
    }
  });

  function addToRecentHistory(address) {
    const history = JSON.parse(localStorage.getItem("recentHosts") || "[]");
    if (!history.includes(address)) {
      history.unshift(address);
      if (history.length > 5) history.pop();
      localStorage.setItem("recentHosts", JSON.stringify(history));
    }
  }

  function loadRecentHistory() {
    const list = document.getElementById("recentHostList");
    const container = document.getElementById("recentHosts");
    const history = JSON.parse(localStorage.getItem("recentHosts") || "[]");

    if (history.length === 0) return;

    list.innerHTML = history.map(addr =>
      `<li><a href="?net_address=${encodeURIComponent(addr)}" class="text-blue-600 hover:underline">${addr}</a></li>`
    ).join("");

    container.classList.remove("hidden");
  }

  async function applyFiatValues() {
    const currency = getCookie("currency") || "eur";
    try {
      const response = await fetch(`https://explorer.siagraph.info/api/exchange-rate/siacoin/${currency}`);
      const rateText = await response.text();
      const rate = parseFloat(rateText);

      if (isNaN(rate)) {
        return;
      }
      document.querySelectorAll('.fiat-value').forEach(el => {
        const scRaw = parseFloat(el.getAttribute('data-sc') || "0");
        if (!isNaN(scRaw)) {
          const fiat = scRaw * rate;
          el.textContent = `${currency.toUpperCase()} ${fiat >= 0.01 ? fiat.toFixed(2) : fiat.toFixed(5)}`;

        }
      });
    } catch (err) {
      console.error("Failed to fetch exchange rate", err);
    }
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
  loadHostData();
</script>
</body>

</html>