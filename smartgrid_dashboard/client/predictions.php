<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_data.php';

$user = smartgrid_require_login(['client']);
$clientId = (int) ($user['client_id'] ?? 0);
$conn = dashboard_db();

$stmt = $conn->prepare(
    'SELECT p.date_prediction, p.prediction, cp.numero_compteur
     FROM predictions p
     INNER JOIN compteurs cp ON cp.id = p.id_compteur
     WHERE cp.id_client = ?
     ORDER BY p.date_prediction ASC, p.id ASC'
);
$stmt->bind_param('i', $clientId);
$rows = fetch_all_assoc($stmt);

$latest = $rows ? $rows[count($rows) - 1] : null;
$labels = json_encode(array_column($rows, 'date_prediction'));
$values = json_encode(array_map('floatval', array_column($rows, 'prediction')));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mes predictions SmartGrid</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body{font-family:'Source Sans 3',sans-serif;background:linear-gradient(135deg,#f8e9ff 0%,#eef5ff 48%,#fff6d8 100%);color:#1c2540}.wrap{max-width:1160px;margin:0 auto;padding:32px}.panel{background:rgba(255,255,255,.84);border:1px solid rgba(28,37,64,.07);border-radius:28px;padding:24px;box-shadow:0 20px 45px rgba(79,96,140,.14)}.hero{background:linear-gradient(135deg,#4f39d5,#16b886);color:#fff;border-radius:30px;padding:28px;box-shadow:0 22px 50px rgba(79,96,140,.18)}.metric{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.22);border-radius:22px;padding:18px}.muted{color:#66708f}.hero .muted{color:rgba(255,255,255,.74)}.table>:not(caption)>*>*{background:transparent!important;border-color:rgba(28,37,64,.08)!important}
  </style>
</head>
<body class="sg-client">
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Mes predictions</h1>
      <div class="muted">Estimation de votre consommation electrique future</div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-dark" href="/smartgrid_dashboard/client/index.php">Dashboard</a>
      <a class="btn btn-outline-dark" href="/smartgrid_dashboard/client/factures.php">Mes factures</a>
      <a class="btn btn-outline-dark" href="/smartgrid_dashboard/logout.php">Deconnexion</a>
    </div>
  </div>

  <section class="hero mb-4">
    <div class="row g-4 align-items-center">
      <div class="col-lg-7">
        <div class="muted text-uppercase small fw-bold">Suivi intelligent</div>
        <h2 class="display-6 fw-bold mb-2">Prévision de consommation</h2>
        <p class="mb-0">Consultez l'estimation de votre consommation future afin d'anticiper vos dépenses énergétiques.</p>
      </div>
      <div class="col-lg-5">
        <div class="metric">
          <div class="muted">Derniere prediction</div>
          <?php if ($latest): ?>
            <div class="display-6 fw-bold"><?= number_format((float) $latest['prediction'], 3) ?> kWh</div>
            <div>Compteur <?= htmlspecialchars($latest['numero_compteur']) ?> - <?= htmlspecialchars($latest['date_prediction']) ?></div>
          <?php else: ?>
            <div class="h4 mb-0">Aucune prediction</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <div class="row g-4">
    <div class="col-xl-7">
      <div class="panel">
        <h2 class="h5 mb-3">Courbe des predictions futures</h2>
        <div style="height:340px"><canvas id="predictionChart"></canvas></div>
      </div>
    </div>
    <div class="col-xl-5">
      <div class="panel">
        <h2 class="h5 mb-3">Historique des predictions</h2>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Date</th><th>Compteur</th><th>Prediction</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($rows) as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['date_prediction']) ?></td>
                <td><?= htmlspecialchars($row['numero_compteur']) ?></td>
                <td><?= number_format((float) $row['prediction'], 3) ?> kWh</td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
              <tr><td colspan="3" class="text-center muted py-4">Aucune prediction disponible pour vos compteurs.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
new Chart(document.getElementById('predictionChart'), {
  type: 'line',
  data: {
    labels: <?= $labels ?>,
    datasets: [{
      label: 'Consommation predite (kWh)',
      data: <?= $values ?>,
      borderColor: '#4f39d5',
      backgroundColor: 'rgba(79,57,213,.14)',
      fill: true,
      tension: .35
    }]
  },
  options: { responsive: true, maintainAspectRatio: false }
});
</script>
</body>
</html>
