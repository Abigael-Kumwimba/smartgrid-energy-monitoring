<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_data.php';
$user = smartgrid_require_login(['admin']);
$data = fetch_admin_dashboard_data();
$historyLabels = json_encode(array_column($data['history'], 'label'));
$historyPower = json_encode(array_map('floatval', array_column($data['history'], 'puissance')));
$historyEnergy = json_encode(array_map('floatval', array_column($data['history'], 'energie')));
$billingAmounts = json_encode([
    $data['billingBreakdown']['payee']['amount'],
    $data['billingBreakdown']['non_payee']['amount'],
]);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SmartGrid Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root { --sg-bg:#0f1020; --sg-border:rgba(255,255,255,.08); --sg-text:#f5f7ff; --sg-muted:#a4acc4; --sg-accent:#7c5cff; --sg-green:#29d391; --sg-blue:#49b8ff; --sg-orange:#ffb44d; }
    body { background: radial-gradient(circle at top, #1f1b3d 0%, var(--sg-bg) 42%, #0b0c16 100%); color: var(--sg-text); font-family: 'Source Sans 3', sans-serif; }
    .app-wrapper, .app-main, .app-content, .app-sidebar, .app-header { background: transparent !important; }
    .app-sidebar { background: rgba(18, 20, 36, .9) !important; backdrop-filter: blur(14px); border-right: 1px solid var(--sg-border); }
    .brand-link, .sidebar-wrapper, .app-header .navbar { background: transparent !important; }
    .brand-text { color: #fff; font-weight: 700; letter-spacing: .04em; }
    .nav-link { color: #c7cbed !important; border-radius: 14px; margin-bottom: .35rem; }
    .nav-link.active, .nav-link:hover { background: rgba(124, 92, 255, .18) !important; color: #fff !important; }
    .app-header .navbar { border-bottom: 1px solid var(--sg-border); }
    .top-search { background: rgba(255,255,255,.06); border: 1px solid var(--sg-border); color: #fff; border-radius: 999px; min-width: 320px; }
    .top-search::placeholder { color: #aab0cb; }
    .metric-card, .glass-panel { background: linear-gradient(180deg, rgba(31,33,59,.95), rgba(23,24,43,.92)); border: 1px solid var(--sg-border); border-radius: 24px; box-shadow: 0 18px 48px rgba(0,0,0,.28); }
    .metric-card { padding: 1.15rem; min-height: 148px; }
    .metric-icon { width: 46px; height: 46px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.2rem; }
    .bg-accent { background: linear-gradient(135deg, var(--sg-accent), #9c85ff); } .bg-green { background: linear-gradient(135deg, #1fbf75, var(--sg-green)); } .bg-blue { background: linear-gradient(135deg, #2b86ff, var(--sg-blue)); } .bg-orange { background: linear-gradient(135deg, #ff8a3d, var(--sg-orange)); }
    .panel-title { font-size: 1.1rem; font-weight: 700; } .muted { color: var(--sg-muted); } .chart-wrap { height: 320px; }
    .table { color: var(--sg-text); } .table > :not(caption) > * > * { background: transparent !important; border-color: rgba(255,255,255,.05); }
    .quick-note { background: linear-gradient(135deg, rgba(124,92,255,.22), rgba(73,184,255,.12)); border: 1px solid rgba(124,92,255,.26); border-radius: 24px; padding: 1.15rem; }
    .action-btn { border-radius: 999px; }
    .flash-box { min-height: 24px; }
</style>
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
</head>
<body class="layout-fixed sidebar-expand-lg sg-admin-light">
<div class="app-wrapper">
  <nav class="app-header navbar navbar-expand border-0"><div class="container-fluid"><ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list"></i></a></li></ul><form class="mx-auto d-none d-md-flex" method="get" action="/smartgrid_dashboard/admin/recherche.php"><input class="form-control top-search" name="q" placeholder="Rechercher un client, compteur ou une mesure" required></form><ul class="navbar-nav ms-auto align-items-center gap-2"><li class="nav-item text-white-50 small">Connecte : <?= htmlspecialchars($user['label']) ?></li><li class="nav-item"><a class="btn btn-sm btn-outline-light" href="/smartgrid_dashboard/logout.php">Deconnexion</a></li></ul></div></nav>
  <aside class="app-sidebar shadow" data-bs-theme="dark"><div class="sidebar-brand p-3"><a href="#" class="brand-link text-decoration-none"><span class="brand-text">SmartGrid</span></a></div><div class="sidebar-wrapper px-3"><nav class="mt-2"><ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu"><li class="nav-item"><a href="/smartgrid_dashboard/admin/index.php" class="nav-link active"><i class="nav-icon bi bi-grid"></i><p>Dashboard Admin</p></a></li><li class="nav-item"><a href="/smartgrid_dashboard/admin/clients.php" class="nav-link"><i class="nav-icon bi bi-people"></i><p>Clients</p></a></li><li class="nav-item"><a href="/smartgrid_dashboard/admin/compteurs.php" class="nav-link"><i class="nav-icon bi bi-cpu"></i><p>Compteurs</p></a></li><li class="nav-item"><a href="/smartgrid_dashboard/admin/consommations.php" class="nav-link"><i class="nav-icon bi bi-activity"></i><p>Consommations</p></a></li><li class="nav-item"><a href="/smartgrid_dashboard/admin/factures.php" class="nav-link"><i class="nav-icon bi bi-receipt"></i><p>Factures</p></a></li><li class="nav-item"><a href="/smartgrid_dashboard/admin/alertes.php" class="nav-link"><i class="nav-icon bi bi-bell"></i><p>Alertes</p></a></li><li class="nav-item"><a href="/smartgrid_dashboard/admin/predictions.php" class="nav-link"><i class="nav-icon bi bi-graph-up-arrow"></i><p>Predictions ML</p></a></li></ul></nav><div class="quick-note mt-4 text-white"><div class="fw-bold mb-2">Prototype connecte</div><div class="small text-white-50">ESP32 + PZEM + API PHP + MySQL fonctionnels.</div></div></div></aside>
  <main class="app-main"><div class="app-content py-4"><div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><h1 class="h3 mb-1 text-white">Dashboard Energie</h1><div class="muted">Vue globale du reseau, des mesures temps reel et de la facturation</div></div><div class="d-flex gap-2 flex-wrap"><button class="btn btn-outline-light action-btn" id="refresh-dashboard-btn"><i class="bi bi-arrow-repeat"></i> Actualiser</button></div></div>
    <div id="dashboard-flash" class="flash-box small text-info mb-3"></div>
    <div class="row g-4 mb-4"><div class="col-xl-3 col-md-6"><div class="metric-card"><div class="d-flex justify-content-between"><div><div class="muted">Clients</div><div class="display-6 fw-bold" id="metric-clients"><?= $data['totalClients'] ?></div><div class="small text-success">API clients</div></div><div class="metric-icon bg-accent"><i class="bi bi-people"></i></div></div></div></div><div class="col-xl-3 col-md-6"><div class="metric-card"><div class="d-flex justify-content-between"><div><div class="muted">Compteurs actifs</div><div class="display-6 fw-bold" id="metric-meters"><?= $data['activeMeters'] ?></div><div class="small text-info">API compteurs</div></div><div class="metric-icon bg-green"><i class="bi bi-cpu"></i></div></div></div></div><div class="col-xl-3 col-md-6"><div class="metric-card"><div class="d-flex justify-content-between"><div><div class="muted">Revenus factures</div><div class="display-6 fw-bold" id="metric-revenue">$<?= number_format($data['totalRevenue'], 2) ?></div><div class="small text-warning">API factures</div></div><div class="metric-icon bg-blue"><i class="bi bi-cash-stack"></i></div></div></div></div><div class="col-xl-3 col-md-6"><div class="metric-card"><div class="d-flex justify-content-between"><div><div class="muted">Predictions</div><div class="display-6 fw-bold" id="metric-predictions"><?= $data['totalPredictions'] ?></div><div class="small text-warning">API predictions</div></div><div class="metric-icon bg-orange"><i class="bi bi-graph-up-arrow"></i></div></div></div></div></div>
    <div class="row g-4 mb-4"><div class="col-xl-8"><div class="glass-panel p-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><div><div class="panel-title text-white">Evolution recente de la consommation</div><div class="muted">Graphique relie a l'API des consommations</div></div><div class="badge rounded-pill text-bg-dark" id="avg-voltage-pill">Moy. tension <?= number_format($data['avgVoltage'], 1) ?> V</div></div><div class="chart-wrap"><canvas id="energyTrendChart"></canvas></div></div></div><div class="col-xl-4"><div class="glass-panel p-4 h-100 d-flex flex-column justify-content-between"><div><div class="panel-title text-white">Repartition des factures</div><div class="muted mb-3">Montants payes et non payes</div><div class="chart-wrap" style="height:260px"><canvas id="billingChart"></canvas></div></div><div class="row g-3 mt-2"><div class="col-6"><div class="p-3 rounded-4" style="background:rgba(41,211,145,.08)"><div class="small muted">Payees</div><div class="fw-bold text-success" id="billing-paid-count"><?= $data['billingBreakdown']['payee']['count'] ?></div></div></div><div class="col-6"><div class="p-3 rounded-4" style="background:rgba(255,180,77,.08)"><div class="small muted">Non payees</div><div class="fw-bold text-warning" id="billing-unpaid-count"><?= $data['billingBreakdown']['non_payee']['count'] ?></div></div></div></div></div></div></div>
    <div class="row g-4"><div class="col-xl-8"><div class="glass-panel p-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><div><div class="panel-title text-white">Historique de consommation</div><div class="muted">Acces au journal complet des mesures recues depuis ESP32/PZEM</div></div><a class="btn btn-outline-light btn-sm" href="/smartgrid_dashboard/admin/consommations.php">Voir l historique</a></div><div class="row g-3"><div class="col-md-4"><div class="p-3 rounded-4" style="background:rgba(73,184,255,.08)"><div class="small muted">Tension moyenne</div><div class="fs-3 fw-bold text-info" id="smart-avg-voltage"><?= number_format($data['avgVoltage'], 1) ?> V</div></div></div><div class="col-md-4"><div class="p-3 rounded-4" style="background:rgba(255,109,178,.08)"><div class="small muted">Puissance moyenne</div><div class="fs-3 fw-bold" style="color:#ff93c6" id="smart-avg-power"><?= number_format($data['avgPower'], 1) ?> W</div></div></div><div class="col-md-4"><div class="p-3 rounded-4" style="background:rgba(41,211,145,.08)"><div class="small muted">Dernieres lignes</div><div class="fs-3 fw-bold text-success"><?= count($data['recentMeasurements']) ?></div></div></div></div></div></div><div class="col-xl-4"><div class="glass-panel p-4 h-100"><div class="panel-title text-white">Priorites de supervision</div><div class="d-grid gap-3"><a class="text-decoration-none p-3 rounded-4" href="/smartgrid_dashboard/admin/alertes.php" style="background:rgba(255,107,122,.10);color:#fff"><div class="small muted">Alertes critiques</div><div class="fs-3 fw-bold text-danger"><?= (int)$data['criticalAlerts'] ?></div></a><a class="text-decoration-none p-3 rounded-4" href="/smartgrid_dashboard/admin/factures.php" style="background:rgba(255,180,77,.10);color:#fff"><div class="small muted">Factures non payees</div><div class="fs-3 fw-bold text-warning"><?= (int)$data['unpaidInvoices'] ?></div></a><a class="text-decoration-none p-3 rounded-4" href="/smartgrid_dashboard/admin/compteurs.php" style="background:rgba(124,92,255,.12);color:#fff"><div class="small muted">Compteurs hors service</div><div class="fs-3 fw-bold" style="color:#b7a8ff"><?= (int)$data['inactiveMeters'] ?></div></a></div></div></div></div>
  </div></div></main>
</div>
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"></script><script src="/AdminLTE-4.0.0-rc7/dist/js/adminlte.min.js"></script>
<script>
const initialHistoryLabels = <?= $historyLabels ?>;
const initialHistoryPower = <?= $historyPower ?>;
const initialHistoryEnergy = <?= $historyEnergy ?>;
const initialBillingAmounts = <?= $billingAmounts ?>;
const flashBox = document.getElementById('dashboard-flash');
const trendChart = new Chart(document.getElementById('energyTrendChart'), { type: 'line', data: { labels: initialHistoryLabels, datasets: [{ label: 'Puissance (W)', data: initialHistoryPower, borderColor: '#29d391', backgroundColor: 'rgba(41, 211, 145, 0.18)', tension: 0.35, fill: true }, { label: 'Energie (kWh)', data: initialHistoryEnergy, borderColor: '#7c5cff', backgroundColor: 'rgba(124, 92, 255, 0.14)', tension: 0.35, fill: true }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#d8dcf7' } } }, scales: { x: { ticks: { color: '#9fa6c4' }, grid: { color: 'rgba(255,255,255,.05)' } }, y: { ticks: { color: '#9fa6c4' }, grid: { color: 'rgba(255,255,255,.05)' } } } } });
const billingChart = new Chart(document.getElementById('billingChart'), { type: 'doughnut', data: { labels: ['Factures payees', 'Factures non payees'], datasets: [{ data: initialBillingAmounts, backgroundColor: ['#29d391', '#ffb44d'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend: { labels: { color: '#d8dcf7' } } } } });
const currency = value => `$${Number(value || 0).toFixed(2)}`;
const fixed = (value, digits = 1) => Number(value || 0).toFixed(digits);
async function fetchJson(url, options = {}) { const response = await fetch(url, { headers: { 'Accept': 'application/json' }, ...options }); if (!response.ok) throw new Error(`HTTP ${response.status}`); return response.json(); }
function updateMeasurementsTable(consumptions) { return; }
function updateConsumptionCharts(consumptions) { const recent = consumptions.slice(0, 8).reverse(); trendChart.data.labels = recent.map(item => item.date_mesure ? item.date_mesure.slice(5, 16) : 'Mesure'); trendChart.data.datasets[0].data = recent.map(item => Number(item.puissance || 0)); trendChart.data.datasets[1].data = recent.map(item => Number(item.energie || 0)); trendChart.update(); const avgVoltage = recent.length ? recent.reduce((sum, item) => sum + Number(item.tension || 0), 0) / recent.length : 0; const avgPower = recent.length ? recent.reduce((sum, item) => sum + Number(item.puissance || 0), 0) / recent.length : 0; document.getElementById('avg-voltage-pill').textContent = `Moy. tension ${fixed(avgVoltage, 1)} V`; document.getElementById('smart-avg-voltage').textContent = `${fixed(avgVoltage, 1)} V`; document.getElementById('smart-avg-power').textContent = `${fixed(avgPower, 1)} W`; }
function updateBilling(factures) { const paid = factures.filter(item => item.statut === 'payee'); const unpaid = factures.filter(item => item.statut === 'non_payee'); const paidAmount = paid.reduce((sum, item) => sum + Number(item.montant || 0), 0); const unpaidAmount = unpaid.reduce((sum, item) => sum + Number(item.montant || 0), 0); billingChart.data.datasets[0].data = [paidAmount, unpaidAmount]; billingChart.update(); document.getElementById('billing-paid-count').textContent = paid.length; document.getElementById('billing-unpaid-count').textContent = unpaid.length; document.getElementById('metric-revenue').textContent = currency(paidAmount + unpaidAmount); }
async function refreshDashboardFromApis() { try { const [clients, compteurs, consommations, factures, predictions] = await Promise.all([ fetchJson('/smartgrid_energy_api/clients/read_clients.php'), fetchJson('/smartgrid_energy_api/compteurs/read_compteurs.php'), fetchJson('/smartgrid_energy_api/consommations/read_consommation.php'), fetchJson('/smartgrid_energy_api/factures/read_factures.php'), fetchJson('/smartgrid_energy_api/predictions/read_predictions.php') ]); const orderedConsumptions = consommations.slice().reverse(); document.getElementById('metric-clients').textContent = clients.length; document.getElementById('metric-meters').textContent = compteurs.filter(item => !item.statut || item.statut === 'actif').length; document.getElementById('metric-predictions').textContent = predictions.length; updateMeasurementsTable(orderedConsumptions); updateConsumptionCharts(orderedConsumptions); updateBilling(factures); flashBox.textContent = 'Dashboard synchronise avec succes.'; } catch (error) { console.error('Erreur de synchronisation API', error); flashBox.textContent = 'Impossible de synchroniser les APIs pour le moment.'; } }
document.getElementById('refresh-dashboard-btn').addEventListener('click', refreshDashboardFromApis); refreshDashboardFromApis(); setInterval(refreshDashboardFromApis, 15000);
</script>
</body>
</html>








