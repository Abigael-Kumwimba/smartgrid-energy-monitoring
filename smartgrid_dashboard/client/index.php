<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_data.php';
$user = smartgrid_require_login(['client']);
$clientId = (int) ($user['client_id'] ?? 0);
$data = fetch_client_dashboard_data($clientId);
$historyLabels = json_encode(array_column($data['history'], 'label'));
$historyEnergy = json_encode(array_map('floatval', array_column($data['history'], 'energie')));
$historyVoltage = json_encode(array_map('floatval', array_column($data['history'], 'tension')));
$historyPower = json_encode(array_map('floatval', array_column($data['history'], 'puissance')));
$historyCurrent = json_encode(array_map('floatval', array_column($data['history'], 'courant')));
$clientMeterIds = json_encode(array_map('intval', array_column(array_filter($data['meters'], static fn($meter) => $meter['statut'] === 'actif'), 'id')));
$latest = $data['latestMeasurement'];
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SmartGrid Client Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: 'Source Sans 3', sans-serif; background: linear-gradient(135deg, #f8e9ff 0%, #eef5ff 48%, #fff6d8 100%); color: #1c2540; }
    .app-header, .app-sidebar, .app-main, .app-content { background: transparent !important; }
    .app-sidebar { background: rgba(255,255,255,.72) !important; backdrop-filter: blur(18px); border-right: 1px solid rgba(28,37,64,.08); }
    .brand-text, .nav-link { color: #2c3457 !important; }
    .nav-link.active, .nav-link:hover { background: rgba(255,184,77,.18) !important; }
    .floating-card { background: rgba(255,255,255,.78); border: 1px solid rgba(28,37,64,.07); border-radius: 28px; box-shadow: 0 20px 45px rgba(79, 96, 140, .14); }
    .hero { background: linear-gradient(135deg, rgba(255,255,255,.82), rgba(255,249,232,.85)); border-radius: 32px; padding: 1.5rem; border: 1px solid rgba(28,37,64,.06); }
    .mini-card { border-radius: 22px; padding: 1.1rem; color: #fff; min-height: 130px; }
    .live-card { border-radius: 24px; padding: 1.15rem; min-height: 142px; color: #fff; position: relative; overflow: hidden; box-shadow: 0 18px 36px rgba(79,96,140,.18); }
    .live-card::after { content: ""; position: absolute; width: 90px; height: 90px; border-radius: 999px; right: -24px; top: -24px; background: rgba(255,255,255,.16); }
    .live-icon { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 16px; background: rgba(255,255,255,.18); font-size: 1.25rem; }
    .tone-green { background: linear-gradient(135deg, #0bbf8a, #33d6a6); }
    .tone-red { background: linear-gradient(135deg, #ff6b6b, #ff9a76); }
    .tone-indigo { background: linear-gradient(135deg, #5648ff, #8a7cff); }
    .tone-cyan { background: linear-gradient(135deg, #1aa7ec, #54d2ff); }
    .tone-purple { background: linear-gradient(135deg, #5648ff, #8679ff); }
    .tone-blue { background: linear-gradient(135deg, #43a6ff, #7ecbff); }
    .tone-orange { background: linear-gradient(135deg, #ff9a4d, #ffc36e); }
    .muted { color: #66708f; }
  </style>
</head>
<body class="layout-fixed sidebar-expand-lg">
<div class="app-wrapper">
  <nav class="app-header navbar navbar-expand border-0 py-3">
    <div class="container-fluid">
      <ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list"></i></a></li></ul>
      <ul class="navbar-nav ms-auto align-items-center gap-2">
        <li class="nav-item"><a class="btn btn-sm btn-outline-dark" href="/smartgrid_dashboard/logout.php">Deconnexion</a></li>
      </ul>
    </div>
  </nav>
  <aside class="app-sidebar shadow-sm">
    <div class="sidebar-brand p-3"><a href="#" class="brand-link text-decoration-none"><span class="brand-text fw-bold">Espace Client</span></a></div>
    <div class="sidebar-wrapper px-3">
      <ul class="nav sidebar-menu flex-column">
        <li class="nav-item"><a class="nav-link active" href="/smartgrid_dashboard/client/index.php"><i class="nav-icon bi bi-house"></i><p>Mon dashboard</p></a></li>
        <li class="nav-item"><a class="nav-link" href="/smartgrid_dashboard/client/factures.php"><i class="nav-icon bi bi-receipt"></i><p>Mes factures</p></a></li>
        <li class="nav-item"><a class="nav-link" href="/smartgrid_dashboard/client/predictions.php"><i class="nav-icon bi bi-graph-up-arrow"></i><p>Mes predictions</p></a></li>
      </ul>
    </div>
  </aside>
  <main class="app-main">
    <div class="app-content py-4">
      <div class="container-fluid">
        <?php if (!$data['client']): ?>
          <div class="alert alert-warning">Aucun client disponible.</div>
        <?php else: ?>
          <div class="hero mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
              <div>
                <div class="muted">Bonjour</div>
                <h1 class="h2 mb-1"><?= htmlspecialchars($data['client']['nom']) ?></h1>
                <div class="muted">Suivi de vos consommations, compteurs et factures</div>
              </div>
              <div class="text-end">
                <div class="badge text-bg-light">Client #<?= (int)$data['client']['id'] ?></div>
                <div class="small muted mt-2">Compte : <?= htmlspecialchars($user['label']) ?></div>
              </div>
            </div>
          </div>

          <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6"><div class="live-card tone-indigo"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Tension</div><div class="display-6 fw-bold"><span id="live-voltage"><?= $latest ? number_format((float)$latest['tension'], 1) : '0.0' ?></span> V</div><div class="small">Mesure instantanee</div></div><div class="live-icon"><i class="bi bi-lightning-charge"></i></div></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="live-card tone-cyan"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Courant</div><div class="display-6 fw-bold"><span id="live-current"><?= $latest ? number_format((float)$latest['courant'], 2) : '0.00' ?></span> A</div><div class="small">Intensite actuelle</div></div><div class="live-icon"><i class="bi bi-activity"></i></div></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="live-card tone-red"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Puissance</div><div class="display-6 fw-bold"><span id="live-power"><?= $latest ? number_format((float)$latest['puissance'], 1) : '0.0' ?></span> W</div><div class="small">Charge en cours</div></div><div class="live-icon"><i class="bi bi-speedometer2"></i></div></div></div></div>
            <div class="col-xl-3 col-md-6"><div class="live-card tone-green"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Energie</div><div class="display-6 fw-bold"><span id="live-energy"><?= $latest ? number_format((float)$latest['energie'], 3) : '0.000' ?></span> kWh</div><div class="small" id="live-date"><?= $latest ? htmlspecialchars($latest['date_mesure']) : 'Aucune mesure' ?></div></div><div class="live-icon"><i class="bi bi-battery-charging"></i></div></div></div></div>
          </div>

          <div class="row g-4 mb-4">
            <div class="col-xl-4"><div class="mini-card tone-purple"><div class="small opacity-75">Consommation totale</div><div class="display-6 fw-bold"><?= number_format($data['totals']['consumption'], 2) ?> kWh</div><div class="small">Historique client cumule</div></div></div>
            <div class="col-xl-4"><div class="mini-card tone-blue"><div class="small opacity-75">Facturation totale</div><div class="display-6 fw-bold">$<?= number_format($data['totals']['billed'], 2) ?></div><div class="small">Montants enregistres</div></div></div>
            <div class="col-xl-4"><div class="mini-card tone-orange"><div class="small opacity-75">Compteurs</div><div class="display-6 fw-bold"><?= (int)$data['totals']['meters'] ?></div><div class="small">Points de mesure actifs</div></div></div>
          </div>

          <div class="row g-4 mb-4">
            <div class="col-12"><div class="floating-card p-4"><div class="d-flex justify-content-between mb-3"><div><div class="fw-bold fs-5">Courbe de consommation en temps reel</div><div class="muted">Tension, courant, puissance et energie des dernieres mesures</div></div><span class="badge text-bg-light" id="sync-badge">Synchronisation...</span></div><div style="height:360px"><canvas id="clientChart"></canvas></div></div></div>
          </div>

          <div class="row g-4">
            <div class="col-12"><div class="floating-card p-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><div class="fw-bold fs-5">Mes compteurs</div><div class="muted">Etat des compteurs associes a votre compte</div></div><a class="btn btn-outline-dark btn-sm" href="/smartgrid_dashboard/client/factures.php">Mes factures</a></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Numero</th><th>Statut</th><th>Installation</th></tr></thead><tbody><?php foreach ($data['meters'] as $meter): ?><?php $isActive = $meter['statut'] === 'actif'; ?><tr><td><?= htmlspecialchars($meter['numero_compteur']) ?></td><td><span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $isActive ? 'En service' : 'Hors service' ?></span></td><td><?= htmlspecialchars($meter['date_installation']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<script src="/AdminLTE-4.0.0-rc7/dist/js/adminlte.min.js"></script>
<script>
  const labels = <?= $historyLabels ?>;
  const energy = <?= $historyEnergy ?>;
  const voltage = <?= $historyVoltage ?>;
  const power = <?= $historyPower ?>;
  const current = <?= $historyCurrent ?>;
  const clientMeterIds = <?= $clientMeterIds ?>;
  const chartTarget = document.getElementById('clientChart');
  let clientChart = null;
  if (chartTarget) {
    clientChart = new Chart(chartTarget, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Tension (V)', data: voltage, borderColor: '#5648ff', backgroundColor: 'rgba(86,72,255,.10)', fill: true, tension: .35 },
          { label: 'Courant (A)', data: current, borderColor: '#1aa7ec', backgroundColor: 'rgba(26,167,236,.08)', fill: true, tension: .35 },
          { label: 'Puissance (W)', data: power, borderColor: '#ff6b6b', backgroundColor: 'rgba(255,107,107,.08)', fill: true, tension: .35 },
          { label: 'Energie (kWh)', data: energy, borderColor: '#0bbf8a', backgroundColor: 'rgba(11,191,138,.08)', fill: true, tension: .35 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false } }
    });
  }

  const fmt = (value, digits) => Number(value || 0).toFixed(digits);
  async function refreshClientDashboard() {
    try {
      const response = await fetch('/smartgrid_energy_api/consommations/read_consommation.php', { headers: { 'Accept': 'application/json' } });
      const rows = await response.json();
      const filtered = rows
        .filter((item) => clientMeterIds.includes(Number(item.id_compteur)))
        .slice(-20);
      if (!filtered.length) {
        document.getElementById('sync-badge').textContent = 'Aucune mesure';
        document.getElementById('live-voltage').textContent = '0.0';
        document.getElementById('live-current').textContent = '0.00';
        document.getElementById('live-power').textContent = '0.0';
        document.getElementById('live-energy').textContent = '0.000';
        document.getElementById('live-date').textContent = clientMeterIds.length ? 'Aucune mesure recue' : 'Aucun compteur actif';
        return;
      }
      const latest = filtered[filtered.length - 1];
      document.getElementById('live-voltage').textContent = fmt(latest.tension, 1);
      document.getElementById('live-current').textContent = fmt(latest.courant, 2);
      document.getElementById('live-power').textContent = fmt(latest.puissance, 1);
      document.getElementById('live-energy').textContent = fmt(latest.energie, 3);
      document.getElementById('live-date').textContent = latest.date_mesure || 'Derniere mesure';
      document.getElementById('sync-badge').textContent = 'Temps reel actif';
      if (clientChart) {
        clientChart.data.labels = filtered.map((item) => (item.date_mesure || '').slice(5, 19));
        clientChart.data.datasets[0].data = filtered.map((item) => Number(item.tension || 0));
        clientChart.data.datasets[1].data = filtered.map((item) => Number(item.courant || 0));
        clientChart.data.datasets[2].data = filtered.map((item) => Number(item.puissance || 0));
        clientChart.data.datasets[3].data = filtered.map((item) => Number(item.energie || 0));
        clientChart.update();
      }
    } catch (error) {
      document.getElementById('sync-badge').textContent = 'Sync indisponible';
      console.error(error);
    }
  }
  refreshClientDashboard();
  setInterval(refreshClientDashboard, 5000);
</script>
</body>
</html>
