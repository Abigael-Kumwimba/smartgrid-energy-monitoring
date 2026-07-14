<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_data.php';

$user = smartgrid_require_login(['admin']);
$conn = dashboard_db();
$selectedClientId = isset($_GET['client_id']) ? max(0, (int) $_GET['client_id']) : 0;
$stmt = $conn->prepare(
    'SELECT co.date_mesure, cl.id AS id_client, cl.nom AS client_nom, cp.numero_compteur, co.tension, co.courant, co.puissance, co.energie
     FROM consommations co
     LEFT JOIN compteurs cp ON cp.id = co.id_compteur
     LEFT JOIN clients cl ON cl.id = cp.id_client
     ORDER BY co.date_mesure DESC
     LIMIT 200'
);
$rows = fetch_all_assoc($stmt);
$chronologicalRows = array_reverse($rows);

$totalRows = count($rows);
$avgVoltage = $totalRows ? array_sum(array_map(static fn($row) => (float) $row['tension'], $rows)) / $totalRows : 0;
$avgCurrent = $totalRows ? array_sum(array_map(static fn($row) => (float) $row['courant'], $rows)) / $totalRows : 0;
$maxPower = $totalRows ? max(array_map(static fn($row) => (float) $row['puissance'], $rows)) : 0;
$latestEnergy = $rows ? (float) $rows[0]['energie'] : 0;
$uniqueClients = count(array_unique(array_filter(array_column($rows, 'client_nom'))));
$uniqueMeters = count(array_unique(array_filter(array_column($rows, 'numero_compteur'))));
$clientsSummary = [];
foreach ($rows as $row) {
    $clientKey = (string) ($row['id_client'] ?? '0');
    if (!isset($clientsSummary[$clientKey])) {
        $clientsSummary[$clientKey] = [
            'id_client' => $row['id_client'] ?? null,
            'client_nom' => $row['client_nom'] ?? 'Client non rattache',
            'mesures' => 0,
            'compteurs' => [],
            'energie_max' => 0.0,
            'puissance_max' => 0.0,
            'derniere_mesure' => $row['date_mesure'] ?? null,
        ];
    }
    $clientsSummary[$clientKey]['mesures']++;
    if (!empty($row['numero_compteur'])) {
        $clientsSummary[$clientKey]['compteurs'][$row['numero_compteur']] = true;
    }
    $clientsSummary[$clientKey]['energie_max'] = max($clientsSummary[$clientKey]['energie_max'], (float) $row['energie']);
    $clientsSummary[$clientKey]['puissance_max'] = max($clientsSummary[$clientKey]['puissance_max'], (float) $row['puissance']);
    if (!empty($row['date_mesure']) && $row['date_mesure'] > (string) $clientsSummary[$clientKey]['derniere_mesure']) {
        $clientsSummary[$clientKey]['derniere_mesure'] = $row['date_mesure'];
    }
}
usort($clientsSummary, static fn($a, $b) => strcmp((string) $b['derniere_mesure'], (string) $a['derniere_mesure']));

$clientCardsStmt = $conn->prepare(
    'SELECT cl.id, cl.nom,
            COUNT(DISTINCT cp.id) AS total_compteurs,
            COUNT(co.id) AS total_mesures,
            COALESCE(AVG(co.tension), 0) AS tension_moyenne,
            COALESCE(MAX(co.puissance), 0) AS puissance_max,
            COALESCE(MAX(co.energie), 0) AS energie_relevee,
            MAX(co.date_mesure) AS derniere_mesure,
            GROUP_CONCAT(DISTINCT cp.numero_compteur ORDER BY cp.numero_compteur SEPARATOR ", ") AS compteurs
     FROM clients cl
     LEFT JOIN compteurs cp ON cp.id_client = cl.id
     LEFT JOIN consommations co ON co.id_compteur = cp.id
     GROUP BY cl.id, cl.nom
     ORDER BY cl.nom ASC'
);
$clientCards = fetch_all_assoc($clientCardsStmt);
$selectedClient = null;
foreach ($clientCards as $clientCard) {
    if ((int) $clientCard['id'] === $selectedClientId) {
        $selectedClient = $clientCard;
        break;
    }
}

$selectedRows = [];
if ($selectedClientId > 0) {
    $selectedStmt = $conn->prepare(
        'SELECT co.date_mesure, cl.id AS id_client, cl.nom AS client_nom, cp.numero_compteur, co.tension, co.courant, co.puissance, co.energie
         FROM consommations co
         INNER JOIN compteurs cp ON cp.id = co.id_compteur
         INNER JOIN clients cl ON cl.id = cp.id_client
         WHERE cl.id = ?
         ORDER BY co.date_mesure DESC
         LIMIT 100'
    );
    $selectedStmt->bind_param('i', $selectedClientId);
    $selectedRows = fetch_all_assoc($selectedStmt);
}
$selectedChronologicalRows = array_reverse($selectedRows);

$chartLabels = json_encode(array_map(static fn($row) => substr((string) $row['date_mesure'], 5, 11), array_slice($chronologicalRows, -30)), JSON_UNESCAPED_UNICODE);
$chartVoltage = json_encode(array_map(static fn($row) => (float) $row['tension'], array_slice($chronologicalRows, -30)));
$chartPower = json_encode(array_map(static fn($row) => (float) $row['puissance'], array_slice($chronologicalRows, -30)));
$chartCurrent = json_encode(array_map(static fn($row) => (float) $row['courant'], array_slice($chronologicalRows, -30)));
$selectedLabels = json_encode(array_map(static fn($row) => substr((string) $row['date_mesure'], 5, 11), array_slice($selectedChronologicalRows, -30)), JSON_UNESCAPED_UNICODE);
$selectedEnergy = json_encode(array_map(static fn($row) => (float) $row['energie'], array_slice($selectedChronologicalRows, -30)));
$selectedPower = json_encode(array_map(static fn($row) => (float) $row['puissance'], array_slice($selectedChronologicalRows, -30)));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Consommations SmartGrid</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body{background:#0f1020;color:#fff;font-family:'Source Sans 3',sans-serif}.wrap{max-width:1280px;margin:0 auto;padding:32px}.panel{background:#181a2f;border:1px solid rgba(255,255,255,.07);border-radius:24px;padding:24px;box-shadow:0 18px 48px rgba(0,0,0,.28)}.table{color:#fff}.table>*{border-color:rgba(255,255,255,.06)!important}.muted{color:#a4acc4}.metric{border-radius:22px;padding:18px;min-height:132px;box-shadow:0 18px 38px rgba(0,0,0,.2);position:relative;overflow:hidden}.metric::after{content:"";position:absolute;right:-26px;top:-26px;width:96px;height:96px;border-radius:999px;background:rgba(255,255,255,.14)}.metric-icon{width:44px;height:44px;display:grid;place-items:center;border-radius:16px;background:rgba(255,255,255,.18);font-size:1.25rem}.tone-violet{background:linear-gradient(135deg,#6248ff,#8b7cff)}.tone-cyan{background:linear-gradient(135deg,#1686df,#4fd4ff)}.tone-orange{background:linear-gradient(135deg,#ff8f48,#ffc05f)}.tone-green{background:linear-gradient(135deg,#0da878,#2bd79f)}.status-badge{border-radius:999px;padding:.4rem .75rem;font-weight:700}.status-normal{background:rgba(41,211,145,.16);color:#74efbd}.status-low{background:rgba(255,180,77,.16);color:#ffd18a}.status-high{background:rgba(255,107,122,.16);color:#ff9da8}.filter-input{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#fff;border-radius:16px}.filter-input::placeholder{color:#a4acc4}.last-card{background:linear-gradient(135deg,rgba(41,211,145,.14),rgba(73,184,255,.1));border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:18px}.empty-state{text-align:center;color:#a4acc4;padding:28px}.client-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}.client-card{display:block;text-decoration:none;color:#fff;background:linear-gradient(135deg,rgba(255,255,255,.09),rgba(255,255,255,.04));border:1px solid rgba(255,255,255,.09);border-radius:22px;padding:18px;transition:.18s}.client-card:hover,.client-card.active{color:#fff;border-color:#4fd4ff;transform:translateY(-2px);box-shadow:0 18px 38px rgba(79,212,255,.12)}.client-card.active{background:linear-gradient(135deg,rgba(79,212,255,.18),rgba(41,211,145,.10))}.chip{display:inline-flex;gap:6px;align-items:center;border-radius:999px;padding:.35rem .7rem;background:rgba(79,212,255,.14);color:#9addff;font-weight:800;font-size:.86rem}
  </style>
</head>
<body class="sg-admin">
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Historique des consommations</h1>
      <div class="muted">Supervision des mesures recues depuis les compteurs ESP32/PZEM</div>
    </div>
    <div class="d-flex gap-2"><a class="btn btn-outline-light" href="/smartgrid_dashboard/admin/index.php">Dashboard</a><a class="btn btn-outline-light" href="/smartgrid_dashboard/logout.php">Deconnexion</a></div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6"><div class="metric tone-violet"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Tension moyenne</div><div class="display-6 fw-bold"><?= number_format($avgVoltage, 1) ?> V</div><div class="small">sur <?= $totalRows ?> mesures</div></div><div class="metric-icon"><i class="bi bi-lightning-charge"></i></div></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="metric tone-cyan"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Courant moyen</div><div class="display-6 fw-bold"><?= number_format($avgCurrent, 2) ?> A</div><div class="small"><?= $uniqueMeters ?> compteur(s)</div></div><div class="metric-icon"><i class="bi bi-activity"></i></div></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="metric tone-orange"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Puissance max</div><div class="display-6 fw-bold"><?= number_format($maxPower, 1) ?> W</div><div class="small">pic observe</div></div><div class="metric-icon"><i class="bi bi-speedometer2"></i></div></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="metric tone-green"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Derniere energie</div><div class="display-6 fw-bold"><?= number_format($latestEnergy, 3) ?> kWh</div><div class="small"><?= $uniqueClients ?> client(s)</div></div><div class="metric-icon"><i class="bi bi-battery-charging"></i></div></div></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="panel h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div><h2 class="h5 mb-0">Evolution recente</h2><div class="muted small">Tension, courant et puissance des dernieres mesures</div></div>
          <span class="badge text-bg-dark">30 points</span>
        </div>
        <div style="height:340px"><canvas id="consumptionChart"></canvas></div>
      </div>
    </div>
  </div>

  <div class="panel mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <div><h2 class="h5 mb-0">Consommation par client</h2><div class="muted small">Cliquez sur un client pour afficher uniquement son apercu de consommation</div></div>
      <span class="badge text-bg-dark"><?= count($clientCards) ?> client(s)</span>
    </div>
    <div class="client-grid">
      <?php foreach ($clientCards as $clientRow): ?>
        <a class="client-card <?= (int) $clientRow['id'] === $selectedClientId ? 'active' : '' ?>" href="?client_id=<?= (int) $clientRow['id'] ?>#client-detail">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <strong><?= htmlspecialchars($clientRow['nom'] ?? '-') ?></strong>
            <span class="chip"><?= (int) $clientRow['total_compteurs'] ?> compteur(s)</span>
          </div>
          <div class="muted small mb-3"><?= htmlspecialchars($clientRow['compteurs'] ?? 'Aucun compteur') ?></div>
          <div class="row g-2 small">
            <div class="col-6"><span class="muted">Mesures</span><br><strong><?= (int) $clientRow['total_mesures'] ?></strong></div>
            <div class="col-6"><span class="muted">Energie</span><br><strong><?= number_format((float) $clientRow['energie_relevee'], 3) ?> kWh</strong></div>
            <div class="col-6"><span class="muted">Tension moy.</span><br><strong><?= number_format((float) $clientRow['tension_moyenne'], 1) ?> V</strong></div>
            <div class="col-6"><span class="muted">Puissance max</span><br><strong><?= number_format((float) $clientRow['puissance_max'], 1) ?> W</strong></div>
          </div>
          <div class="muted small mt-3">Derniere mesure : <?= htmlspecialchars($clientRow['derniere_mesure'] ?? 'Aucune') ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel mb-4" id="client-detail">
    <?php if ($selectedClient): ?>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div><h2 class="h5 mb-0">Apercu de <?= htmlspecialchars($selectedClient['nom']) ?></h2><div class="muted small">Données limitees au client selectionne</div></div>
        <a class="btn btn-sm btn-outline-light" href="/smartgrid_dashboard/admin/consommations.php">Revenir a la vue clients</a>
      </div>
      <div class="row g-4 mb-4">
        <div class="col-lg-7"><div style="height:280px"><canvas id="clientChart"></canvas></div></div>
        <div class="col-lg-5">
          <div class="last-card">
            <div class="muted">Compteurs</div>
            <div class="h5"><?= htmlspecialchars($selectedClient['compteurs'] ?? '-') ?></div>
            <div class="muted mt-2">Total mesures</div>
            <div class="h4"><?= (int) $selectedClient['total_mesures'] ?></div>
            <div class="muted mt-2">Derniere mesure</div>
            <div class="fw-bold"><?= htmlspecialchars($selectedClient['derniere_mesure'] ?? 'Aucune') ?></div>
          </div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Date</th><th>Compteur</th><th>Tension</th><th>Etat tension</th><th>Courant</th><th>Puissance</th><th>Energie</th></tr></thead>
          <tbody>
          <?php foreach ($selectedRows as $row): ?>
            <?php
              $voltage = (float) $row['tension'];
              $statusClass = 'status-normal';
              $statusLabel = 'Normale';
              if ($voltage > 0 && $voltage < 180) { $statusClass = 'status-low'; $statusLabel = 'Sous-tension'; }
              if ($voltage > 255) { $statusClass = 'status-high'; $statusLabel = 'Surtension'; }
            ?>
            <tr>
              <td><?= htmlspecialchars($row['date_mesure']) ?></td>
              <td><?= htmlspecialchars($row['numero_compteur'] ?? '-') ?></td>
              <td><?= number_format($voltage, 1) ?> V</td>
              <td><span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
              <td><?= number_format((float)$row['courant'], 3) ?> A</td>
              <td><?= number_format((float)$row['puissance'], 1) ?> W</td>
              <td><?= number_format((float)$row['energie'], 3) ?> kWh</td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$selectedRows): ?>
            <tr><td colspan="7" class="empty-state">Aucune consommation disponible pour ce client.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state"><i class="bi bi-person-lines-fill fs-1 d-block mb-2"></i>Selectionnez un client ci-dessus pour voir son detail de consommation.</div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <div><h2 class="h5 mb-0">Journal des mesures</h2><div class="muted small">Recherche par client, compteur ou date</div></div>
      <div class="d-flex gap-2 flex-wrap">
        <select class="form-select filter-input" id="client-filter">
          <option value="">Tous les clients</option>
          <?php foreach ($clientsSummary as $clientRow): ?>
            <option value="<?= htmlspecialchars((string) ($clientRow['id_client'] ?? '0')) ?>"><?= htmlspecialchars($clientRow['client_nom'] ?? '-') ?></option>
          <?php endforeach; ?>
        </select>
        <input class="form-control filter-input" id="consumption-search" placeholder="Rechercher...">
        <span class="badge text-bg-dark align-self-center" id="rows-count"><?= count($rows) ?> lignes</span>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Date</th><th>Client</th><th>Compteur</th><th>Tension</th><th>Etat tension</th><th>Courant</th><th>Puissance</th><th>Energie</th></tr></thead>
        <tbody id="consumption-table-body">
        <?php foreach ($rows as $row): ?>
          <?php
            $voltage = (float) $row['tension'];
            $statusClass = 'status-normal';
            $statusLabel = 'Normale';
            if ($voltage > 0 && $voltage < 180) { $statusClass = 'status-low'; $statusLabel = 'Sous-tension'; }
            if ($voltage > 255) { $statusClass = 'status-high'; $statusLabel = 'Surtension'; }
          ?>
          <tr data-client="<?= htmlspecialchars((string) ($row['id_client'] ?? '0')) ?>" data-search="<?= htmlspecialchars(strtolower(($row['date_mesure'] ?? '') . ' ' . ($row['client_nom'] ?? '') . ' ' . ($row['numero_compteur'] ?? ''))) ?>">
            <td><?= htmlspecialchars($row['date_mesure']) ?></td>
            <td><?= htmlspecialchars($row['client_nom'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['numero_compteur'] ?? '-') ?></td>
            <td><?= number_format($voltage, 1) ?> V</td>
            <td><span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
            <td><?= number_format((float)$row['courant'], 3) ?> A</td>
            <td><?= number_format((float)$row['puissance'], 1) ?> W</td>
            <td><?= number_format((float)$row['energie'], 3) ?> kWh</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
const chartLabels = <?= $chartLabels ?>;
const chartVoltage = <?= $chartVoltage ?>;
const chartPower = <?= $chartPower ?>;
const chartCurrent = <?= $chartCurrent ?>;
new Chart(document.getElementById('consumptionChart'), {
  type: 'line',
  data: {
    labels: chartLabels,
    datasets: [
      { label: 'Tension (V)', data: chartVoltage, borderColor: '#8b7cff', backgroundColor: 'rgba(139,124,255,.12)', fill: true, tension: .35 },
      { label: 'Courant (A)', data: chartCurrent, borderColor: '#4fd4ff', backgroundColor: 'rgba(79,212,255,.08)', fill: true, tension: .35 },
      { label: 'Puissance (W)', data: chartPower, borderColor: '#ffb44d', backgroundColor: 'rgba(255,180,77,.08)', fill: true, tension: .35 }
    ]
  },
  options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { labels: { color: '#f8fbff' } } }, scales: { x: { ticks: { color: '#a4acc4' }, grid: { color: 'rgba(255,255,255,.05)' } }, y: { ticks: { color: '#a4acc4' }, grid: { color: 'rgba(255,255,255,.05)' } } } }
});

const clientChartElement = document.getElementById('clientChart');
if (clientChartElement) {
  new Chart(clientChartElement, {
    type: 'line',
    data: {
      labels: <?= $selectedLabels ?>,
      datasets: [
        { label: 'Energie (kWh)', data: <?= $selectedEnergy ?>, borderColor: '#29d391', backgroundColor: 'rgba(41,211,145,.12)', fill: true, tension: .35 },
        { label: 'Puissance (W)', data: <?= $selectedPower ?>, borderColor: '#ffb44d', backgroundColor: 'rgba(255,180,77,.10)', fill: true, tension: .35 }
      ]
    },
    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { labels: { color: '#f8fbff' } } }, scales: { x: { ticks: { color: '#a4acc4' }, grid: { color: 'rgba(255,255,255,.05)' } }, y: { ticks: { color: '#a4acc4' }, grid: { color: 'rgba(255,255,255,.05)' } } } }
  });
}

const search = document.getElementById('consumption-search');
const clientFilter = document.getElementById('client-filter');
const rowsCount = document.getElementById('rows-count');
function filterRows() {
  const query = search.value.trim().toLowerCase();
  const client = clientFilter.value;
  let visible = 0;
  document.querySelectorAll('#consumption-table-body tr').forEach((row) => {
    const show = row.dataset.search.includes(query) && (!client || row.dataset.client === client);
    row.style.display = show ? '' : 'none';
    if (show) visible += 1;
  });
  rowsCount.textContent = `${visible} lignes`;
}
search.addEventListener('input', filterRows);
clientFilter.addEventListener('change', filterRows);
</script>
</body>
</html>

