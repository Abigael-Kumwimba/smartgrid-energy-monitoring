<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_data.php';
$user = smartgrid_require_login(['client']);
$clientId = (int)($user['client_id'] ?? 0);
$data = fetch_client_dashboard_data($clientId);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mes factures SmartGrid</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
  <style>
    body{font-family:'Source Sans 3',sans-serif;background:linear-gradient(135deg,#f8e9ff 0%,#eef5ff 48%,#fff6d8 100%);color:#1c2540}.wrap{max-width:1080px;margin:0 auto;padding:32px}.panel{background:rgba(255,255,255,.82);border:1px solid rgba(28,37,64,.07);border-radius:28px;padding:24px;box-shadow:0 20px 45px rgba(79,96,140,.14)}.muted{color:#66708f}
  </style>
</head>
<body class="sg-client">
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Mes factures</h1><div class="muted">Consultation et suivi du statut de paiement</div></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-dark" href="/smartgrid_dashboard/client/index.php">Dashboard</a><a class="btn btn-outline-dark" href="/smartgrid_dashboard/logout.php">Deconnexion</a></div>
  </div>
  <div class="panel">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead><tr><th>Date</th><th>Echeance</th><th>Energie</th><th>Montant</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($data['factures'] as $facture): ?>
          <tr>
            <td><?= htmlspecialchars($facture['date_facture']) ?></td>
            <td><?= htmlspecialchars($facture['date_echeance'] ?? '-') ?></td>
            <td><?= $facture['energie_totale'] !== null ? number_format((float)$facture['energie_totale'], 2) . ' kWh' : '-' ?></td>
            <td>$<?= number_format((float)$facture['montant'], 2) ?></td>
            <td>
              <?php if ($facture['statut'] === 'payee'): ?>
                <span class="badge text-bg-success">Payee</span>
              <?php elseif ((int)($facture['en_retard'] ?? 0) === 1): ?>
                <span class="badge text-bg-danger">En retard</span>
              <?php else: ?>
                <span class="badge text-bg-warning">En attente</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-sm <?= $facture['statut'] === 'payee' ? 'btn-outline-dark' : 'btn-primary' ?>" href="/smartgrid_dashboard/client/paiement_facture.php?id=<?= (int)$facture['id'] ?>">
                <?= $facture['statut'] === 'payee' ? 'Voir recu' : 'Payer' ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
