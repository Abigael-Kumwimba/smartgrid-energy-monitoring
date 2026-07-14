<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_data.php';

$user = smartgrid_require_login(['admin']);
$conn = dashboard_db();
$selectedClientId = isset($_GET['client_id']) ? max(0, (int) $_GET['client_id']) : 0;
$stmt = $conn->prepare(
    'SELECT p.id, p.date_prediction, p.prediction, cp.numero_compteur, cl.id AS id_client, cl.nom AS client_nom
     FROM predictions p
     LEFT JOIN compteurs cp ON cp.id = p.id_compteur
     LEFT JOIN clients cl ON cl.id = cp.id_client
     ORDER BY p.date_prediction ASC, p.id ASC
     LIMIT 200'
);
$rows = fetch_all_assoc($stmt);
$today = date('Y-m-d');
$futureRows = array_values(array_filter($rows, static fn($row) => (string) $row['date_prediction'] >= $today));
$displayRows = array_reverse($rows);

$totalPredictions = count($rows);
$futureCount = count($futureRows);
$uniqueClients = count(array_unique(array_filter(array_column($rows, 'client_nom'))));
$uniqueMeters = count(array_unique(array_filter(array_column($rows, 'numero_compteur'))));
$nextPrediction = $futureRows[0] ?? ($rows ? $rows[count($rows) - 1] : null);
$avgFuture = $futureRows ? array_sum(array_map(static fn($row) => (float) $row['prediction'], $futureRows)) / count($futureRows) : 0;
$highestRow = null;
foreach ($futureRows ?: $rows as $row) {
    if ($highestRow === null || (float) $row['prediction'] > (float) $highestRow['prediction']) {
        $highestRow = $row;
    }
}
$predictionsByClient = [];
foreach ($rows as $row) {
    $clientKey = (string) ($row['client_nom'] ?? 'Client non rattache');
    if (!isset($predictionsByClient[$clientKey])) {
        $predictionsByClient[$clientKey] = [
            'client_nom' => $row['client_nom'] ?? 'Client non rattache',
            'total' => 0,
            'compteurs' => [],
            'moyenne' => 0.0,
            'max' => 0.0,
        ];
    }
    $predictionValue = (float) ($row['prediction'] ?? 0);
    $predictionsByClient[$clientKey]['total']++;
    $predictionsByClient[$clientKey]['moyenne'] += $predictionValue;
    $predictionsByClient[$clientKey]['max'] = max($predictionsByClient[$clientKey]['max'], $predictionValue);
    if (!empty($row['numero_compteur'])) {
        $predictionsByClient[$clientKey]['compteurs'][$row['numero_compteur']] = true;
    }
}
foreach ($predictionsByClient as &$clientRow) {
    $clientRow['moyenne'] = $clientRow['total'] > 0 ? $clientRow['moyenne'] / $clientRow['total'] : 0;
}
unset($clientRow);

$clientCardsStmt = $conn->prepare(
    'SELECT cl.id, cl.nom,
            COUNT(DISTINCT cp.id) AS total_compteurs,
            COUNT(p.id) AS total_predictions,
            COALESCE(AVG(p.prediction), 0) AS moyenne_prediction,
            COALESCE(MAX(p.prediction), 0) AS max_prediction,
            MIN(CASE WHEN p.date_prediction >= CURDATE() THEN p.date_prediction END) AS prochaine_date,
            GROUP_CONCAT(DISTINCT cp.numero_compteur ORDER BY cp.numero_compteur SEPARATOR ", ") AS compteurs
     FROM clients cl
     LEFT JOIN compteurs cp ON cp.id_client = cl.id
     LEFT JOIN predictions p ON p.id_compteur = cp.id
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
        'SELECT p.id, p.date_prediction, p.prediction, cp.numero_compteur, cl.nom AS client_nom
         FROM predictions p
         INNER JOIN compteurs cp ON cp.id = p.id_compteur
         INNER JOIN clients cl ON cl.id = cp.id_client
         WHERE cl.id = ?
         ORDER BY p.date_prediction ASC, p.id ASC
         LIMIT 100'
    );
    $selectedStmt->bind_param('i', $selectedClientId);
    $selectedRows = fetch_all_assoc($selectedStmt);
}

$chartRows = $futureRows ?: $rows;
$labels = json_encode(array_map(static fn($row) => (string) $row['date_prediction'], $chartRows), JSON_UNESCAPED_UNICODE);
$values = json_encode(array_map(static fn($row) => (float) $row['prediction'], $chartRows));
$meters = json_encode(array_map(static fn($row) => (string) ($row['numero_compteur'] ?? '-'), $chartRows), JSON_UNESCAPED_UNICODE);
$selectedLabels = json_encode(array_map(static fn($row) => (string) $row['date_prediction'], $selectedRows), JSON_UNESCAPED_UNICODE);
$selectedValues = json_encode(array_map(static fn($row) => (float) $row['prediction'], $selectedRows));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Prévisions SmartGrid</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body{background:#0f1020;color:#fff;font-family:'Source Sans 3',sans-serif}.wrap{max-width:1260px;margin:0 auto;padding:32px}.panel{background:#181a2f;border:1px solid rgba(255,255,255,.07);border-radius:24px;padding:24px;box-shadow:0 18px 48px rgba(0,0,0,.28)}.hero{background:linear-gradient(135deg,#4f39d5 0%,#167adf 48%,#10b981 100%);border-radius:30px;padding:26px 28px;box-shadow:0 26px 60px rgba(0,0,0,.24);position:relative;overflow:hidden}.hero:after{content:"";position:absolute;right:-60px;top:-80px;width:240px;height:240px;border-radius:999px;background:rgba(255,255,255,.14)}.metric{background:rgba(255,255,255,.11);border:1px solid rgba(255,255,255,.16);border-radius:22px;padding:18px}.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.stat-card{background:linear-gradient(135deg,rgba(124,92,255,.22),rgba(73,184,255,.11));border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:18px;min-height:128px;display:flex;justify-content:space-between;gap:14px}.stat-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:15px;background:rgba(255,255,255,.1);font-size:1.2rem;flex:0 0 auto}.table{color:#fff}.table>*{border-color:rgba(255,255,255,.06)!important}.muted{color:#a4acc4}.hero .muted{color:rgba(255,255,255,.76)}.search-input{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#fff;border-radius:16px}.search-input::placeholder{color:#a4acc4}.risk-high{color:#ffb44d}.risk-normal{color:#29d391}.badge-soft,.chip{background:rgba(73,184,255,.14);color:#8fd4ff;border-radius:999px;padding:.38rem .75rem;font-weight:800}.empty-state{text-align:center;color:#a4acc4;padding:28px}.perf-note{background:rgba(255,255,255,.06);border-radius:18px;padding:14px}.client-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}.client-card{display:block;text-decoration:none;color:#fff;background:linear-gradient(135deg,rgba(255,255,255,.09),rgba(255,255,255,.04));border:1px solid rgba(255,255,255,.09);border-radius:22px;padding:18px;transition:.18s}.client-card:hover,.client-card.active{color:#fff;border-color:#29d391;transform:translateY(-2px);box-shadow:0 18px 38px rgba(41,211,145,.12)}.client-card.active{background:linear-gradient(135deg,rgba(41,211,145,.18),rgba(73,184,255,.10))}@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.stats-grid{grid-template-columns:1fr}.hero{padding:22px}}
  </style>
</head>
<body class="sg-admin">
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Prévisions de consommation</h1>
      <div class="muted">Estimation des consommations futures par client et par compteur</div>
    </div>
    <div class="d-flex gap-2"><a class="btn btn-outline-light" href="/smartgrid_dashboard/admin/index.php">Dashboard</a><a class="btn btn-outline-light" href="/smartgrid_dashboard/logout.php">Deconnexion</a></div>
  </div>

  <section class="hero mb-4">
    <div class="row g-4 align-items-center position-relative">
      <div class="col-lg-7">
        <div class="muted text-uppercase small fw-bold">Supervision prévisionnelle</div>
        <h2 class="display-6 fw-bold mb-2">Anticiper les pics de consommation</h2>
      </div>
      <div class="col-lg-5">
        <div class="metric">
          <div class="muted">Prochaine prévision</div>
          <?php if ($nextPrediction): ?>
            <div class="display-6 fw-bold"><?= number_format((float) $nextPrediction['prediction'], 3) ?> kWh</div>
            <div><?= htmlspecialchars($nextPrediction['client_nom'] ?? '-') ?> - <?= htmlspecialchars($nextPrediction['numero_compteur'] ?? '-') ?></div>
            <div class="muted small"><?= htmlspecialchars($nextPrediction['date_prediction']) ?></div>
          <?php else: ?>
            <div class="h4 mb-0">Aucune donnée</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <div class="stats-grid mb-4">
    <div class="stat-card"><div><div class="muted small">Prévisions enregistrées</div><div class="display-6 fw-bold"><?= $totalPredictions ?></div><div class="small text-info"><?= $futureCount ?> future(s)</div></div><div class="stat-icon"><i class="bi bi-calendar2-week"></i></div></div>
    <div class="stat-card"><div><div class="muted small">Clients concernés</div><div class="display-6 fw-bold"><?= $uniqueClients ?></div><div class="small text-info"><?= $uniqueMeters ?> compteur(s)</div></div><div class="stat-icon"><i class="bi bi-people"></i></div></div>
    <div class="stat-card"><div><div class="muted small">Moyenne future</div><div class="display-6 fw-bold"><?= number_format($avgFuture, 3) ?></div><div class="small text-info">kWh prévus</div></div><div class="stat-icon"><i class="bi bi-bar-chart-line"></i></div></div>
    <div class="stat-card"><div><div class="muted small">Prévision la plus élevée</div><div class="display-6 fw-bold"><?= $highestRow ? number_format((float) $highestRow['prediction'], 3) : '0.000' ?></div><div class="small text-info"><?= $highestRow ? htmlspecialchars($highestRow['numero_compteur'] ?? '-') : '-' ?></div></div><div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="panel h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div><h2 class="h5 mb-0">Courbe des prévisions</h2><div class="muted small">Consommation estimée en kWh sur les prochaines dates disponibles</div></div>
          <span class="badge-soft"><?= count($chartRows) ?> point(s)</span>
        </div>
        <div style="height:350px"><canvas id="predictionChart"></canvas></div>
      </div>
    </div>
  </div>

  <div class="panel mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <div><h2 class="h5 mb-0">Previsions par client</h2><div class="muted small">Cliquez sur un client pour afficher uniquement ses previsions</div></div>
      <span class="badge text-bg-dark"><?= count($clientCards) ?> client(s)</span>
    </div>
    <div class="client-grid">
      <?php foreach ($clientCards as $clientRow): ?>
        <a class="client-card <?= (int) $clientRow['id'] === $selectedClientId ? 'active' : '' ?>" href="?client_id=<?= (int) $clientRow['id'] ?>#client-detail">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <strong><?= htmlspecialchars($clientRow['nom'] ?? '-') ?></strong>
            <span class="chip"><?= (int) $clientRow['total_predictions'] ?> prevision(s)</span>
          </div>
          <div class="muted small mb-3"><?= htmlspecialchars($clientRow['compteurs'] ?? 'Aucun compteur') ?></div>
          <div class="row g-2 small">
            <div class="col-6"><span class="muted">Moyenne</span><br><strong><?= number_format((float) $clientRow['moyenne_prediction'], 3) ?> kWh</strong></div>
            <div class="col-6"><span class="muted">Max</span><br><strong><?= number_format((float) $clientRow['max_prediction'], 3) ?> kWh</strong></div>
            <div class="col-12"><span class="muted">Prochaine date</span><br><strong><?= htmlspecialchars($clientRow['prochaine_date'] ?? 'Aucune') ?></strong></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel mb-4" id="client-detail">
    <?php if ($selectedClient): ?>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div><h2 class="h5 mb-0">Previsions de <?= htmlspecialchars($selectedClient['nom']) ?></h2><div class="muted small">Estimations limitees au client selectionne</div></div>
        <a class="btn btn-sm btn-outline-light" href="/smartgrid_dashboard/admin/predictions.php">Revenir a la vue clients</a>
      </div>
      <div class="row g-4 mb-4">
        <div class="col-lg-7"><div style="height:280px"><canvas id="clientPredictionChart"></canvas></div></div>
        <div class="col-lg-5">
          <div class="perf-note">
            <div class="muted">Compteurs</div>
            <div class="h5"><?= htmlspecialchars($selectedClient['compteurs'] ?? '-') ?></div>
            <div class="muted mt-2">Moyenne estimee</div>
            <div class="h4"><?= number_format((float) $selectedClient['moyenne_prediction'], 3) ?> kWh</div>
            <div class="muted mt-2">Plus haute estimation</div>
            <div class="fw-bold"><?= number_format((float) $selectedClient['max_prediction'], 3) ?> kWh</div>
          </div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Date prevue</th><th>Compteur</th><th>Consommation estimee</th><th>Lecture</th></tr></thead>
          <tbody>
          <?php foreach ($selectedRows as $row): ?>
            <?php $prediction = (float) $row['prediction']; ?>
            <tr>
              <td><?= htmlspecialchars($row['date_prediction']) ?></td>
              <td><?= htmlspecialchars($row['numero_compteur'] ?? '-') ?></td>
              <td><strong><?= number_format($prediction, 3) ?> kWh</strong></td>
              <td><span class="<?= $prediction >= 1.5 ? 'risk-high' : 'risk-normal' ?>"><?= $prediction >= 1.5 ? 'Consommation à surveiller' : 'Consommation modérée' ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$selectedRows): ?>
            <tr><td colspan="4" class="empty-state">Aucune prevision disponible pour ce client.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state"><i class="bi bi-graph-up-arrow fs-1 d-block mb-2"></i>Selectionnez un client ci-dessus pour voir son detail de predictions.</div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <div><h2 class="h5 mb-0">Historique des prévisions</h2><div class="muted small">Recherche par client, compteur ou date</div></div>
      <div class="d-flex gap-2 flex-wrap">
        <select class="form-select search-input" id="prediction-client-filter">
          <option value="">Tous les clients</option>
          <?php foreach ($predictionsByClient as $clientRow): ?>
            <option value="<?= htmlspecialchars(strtolower((string) ($clientRow['client_nom'] ?? ''))) ?>"><?= htmlspecialchars($clientRow['client_nom'] ?? '-') ?></option>
          <?php endforeach; ?>
        </select>
        <input class="form-control search-input" id="prediction-search" placeholder="Rechercher...">
        <span class="badge text-bg-dark align-self-center" id="rows-count"><?= count($displayRows) ?> lignes</span>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Date prévue</th><th>Client</th><th>Compteur</th><th>Consommation estimée</th><th>Lecture</th></tr></thead>
        <tbody id="prediction-table-body">
        <?php foreach ($displayRows as $row): ?>
          <?php $prediction = (float) $row['prediction']; ?>
          <tr data-client="<?= htmlspecialchars(strtolower((string) ($row['client_nom'] ?? ''))) ?>" data-search="<?= htmlspecialchars(strtolower(($row['date_prediction'] ?? '') . ' ' . ($row['client_nom'] ?? '') . ' ' . ($row['numero_compteur'] ?? ''))) ?>">
            <td><?= htmlspecialchars($row['date_prediction']) ?></td>
            <td><?= htmlspecialchars($row['client_nom'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['numero_compteur'] ?? '-') ?></td>
            <td><strong><?= number_format($prediction, 3) ?> kWh</strong></td>
            <td><span class="<?= $prediction >= 1.5 ? 'risk-high' : 'risk-normal' ?>"><?= $prediction >= 1.5 ? 'Consommation à surveiller' : 'Consommation modérée' ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$displayRows): ?>
          <tr><td colspan="5" class="empty-state">Aucune prévision disponible.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
const labels = <?= $labels ?>;
const values = <?= $values ?>;
const meters = <?= $meters ?>;
new Chart(document.getElementById('predictionChart'), {
  type: 'line',
  data: {
    labels,
    datasets: [{
      label: 'Consommation estimée (kWh)',
      data: values,
      borderColor: '#29d391',
      backgroundColor: 'rgba(41,211,145,.14)',
      fill: true,
      tension: .35,
      pointRadius: 4
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { labels: { color: '#f8fbff' } },
      tooltip: { callbacks: { afterLabel: (ctx) => `Compteur : ${meters[ctx.dataIndex] || '-'}` } }
    },
    scales: {
      x: { ticks: { color: '#a4acc4' }, grid: { color: 'rgba(255,255,255,.05)' } },
      y: { ticks: { color: '#a4acc4' }, grid: { color: 'rgba(255,255,255,.05)' } }
    }
  }
});

const clientPredictionChart = document.getElementById('clientPredictionChart');
if (clientPredictionChart) {
  new Chart(clientPredictionChart, {
    type: 'line',
    data: {
      labels: <?= $selectedLabels ?>,
      datasets: [{
        label: 'Consommation estimee du client (kWh)',
        data: <?= $selectedValues ?>,
        borderColor: '#29d391',
        backgroundColor: 'rgba(41,211,145,.14)',
        fill: true,
        tension: .35,
        pointRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { labels: { color: '#f8fbff' } } },
      scales: {
        x: { ticks: { color: '#a4acc4' }, grid: { color: 'rgba(255,255,255,.05)' } },
        y: { ticks: { color: '#a4acc4' }, grid: { color: 'rgba(255,255,255,.05)' } }
      }
    }
  });
}

const search = document.getElementById('prediction-search');
const clientFilter = document.getElementById('prediction-client-filter');
const rowsCount = document.getElementById('rows-count');
function filterPredictions() {
  const query = search.value.trim().toLowerCase();
  const selectedClient = clientFilter.value;
  let visible = 0;
  document.querySelectorAll('#prediction-table-body tr[data-search]').forEach((row) => {
    const show = row.dataset.search.includes(query) && (!selectedClient || row.dataset.client === selectedClient);
    row.style.display = show ? '' : 'none';
    if (show) visible += 1;
  });
  rowsCount.textContent = `${visible} lignes`;
}
search.addEventListener('input', filterPredictions);
clientFilter.addEventListener('change', filterPredictions);
</script>
</body>
</html>
