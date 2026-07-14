<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_data.php';

$user = smartgrid_require_login(['admin']);
$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';
$results = [
    'clients' => [],
    'compteurs' => [],
    'consommations' => [],
    'factures' => [],
    'alertes' => [],
    'predictions' => [],
];

if ($q !== '') {
    $conn = dashboard_db();

    $stmt = $conn->prepare(
        'SELECT cl.id, cl.nom, cl.telephone, cl.adresse, COUNT(cp.id) AS total_compteurs,
                GROUP_CONCAT(cp.numero_compteur ORDER BY cp.numero_compteur SEPARATOR ", ") AS compteurs
         FROM clients cl
         LEFT JOIN compteurs cp ON cp.id_client = cl.id
         WHERE cl.nom LIKE ? OR cl.telephone LIKE ? OR cl.adresse LIKE ? OR cp.numero_compteur LIKE ?
         GROUP BY cl.id, cl.nom, cl.telephone, cl.adresse
         ORDER BY cl.id DESC
         LIMIT 20'
    );
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $results['clients'] = fetch_all_assoc($stmt);

    $stmt = $conn->prepare(
        'SELECT cp.id, cp.numero_compteur, cp.statut, cp.date_installation, cl.nom AS client_nom
         FROM compteurs cp
         LEFT JOIN clients cl ON cl.id = cp.id_client
         WHERE cp.numero_compteur LIKE ? OR cp.statut LIKE ? OR cl.nom LIKE ?
         ORDER BY cp.id DESC
         LIMIT 20'
    );
    $stmt->bind_param('sss', $like, $like, $like);
    $results['compteurs'] = fetch_all_assoc($stmt);

    $stmt = $conn->prepare(
        'SELECT co.id, co.tension, co.courant, co.puissance, co.energie, co.date_mesure,
                cp.numero_compteur, cl.nom AS client_nom
         FROM consommations co
         LEFT JOIN compteurs cp ON cp.id = co.id_compteur
         LEFT JOIN clients cl ON cl.id = cp.id_client
         WHERE cp.numero_compteur LIKE ? OR cl.nom LIKE ? OR co.date_mesure LIKE ?
         ORDER BY co.date_mesure DESC
         LIMIT 20'
    );
    $stmt->bind_param('sss', $like, $like, $like);
    $results['consommations'] = fetch_all_assoc($stmt);

    $stmt = $conn->prepare(
        'SELECT f.id, f.montant, f.energie_totale, f.statut, f.date_facture, cl.nom AS client_nom
         FROM factures f
         LEFT JOIN clients cl ON cl.id = f.id_client
         WHERE cl.nom LIKE ? OR f.statut LIKE ? OR f.date_facture LIKE ? OR CAST(f.id AS CHAR) LIKE ?
         ORDER BY f.date_facture DESC, f.id DESC
         LIMIT 20'
    );
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $results['factures'] = fetch_all_assoc($stmt);

    $stmt = $conn->prepare(
        'SELECT a.id, a.niveau, a.message, a.date_alerte, cp.numero_compteur, cl.nom AS client_nom
         FROM alertes a
         LEFT JOIN compteurs cp ON cp.id = a.id_compteur
         LEFT JOIN clients cl ON cl.id = cp.id_client
         WHERE a.niveau LIKE ? OR a.message LIKE ? OR cp.numero_compteur LIKE ? OR cl.nom LIKE ?
         ORDER BY a.date_alerte DESC
         LIMIT 20'
    );
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $results['alertes'] = fetch_all_assoc($stmt);

    $stmt = $conn->prepare(
        'SELECT p.id, p.prediction, p.date_prediction, cp.numero_compteur, cl.nom AS client_nom
         FROM predictions p
         LEFT JOIN compteurs cp ON cp.id = p.id_compteur
         LEFT JOIN clients cl ON cl.id = cp.id_client
         WHERE cp.numero_compteur LIKE ? OR cl.nom LIKE ? OR p.date_prediction LIKE ?
         ORDER BY p.date_prediction DESC, p.id DESC
         LIMIT 20'
    );
    $stmt->bind_param('sss', $like, $like, $like);
    $results['predictions'] = fetch_all_assoc($stmt);
}

$totalResults = array_sum(array_map('count', $results));
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recherche SmartGrid</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
  <style>
    .search-hero{background:linear-gradient(135deg,#4f39d5,#167adf 48%,#10b981);border-radius:28px;padding:26px;box-shadow:0 24px 58px rgba(0,0,0,.28)}
    .search-form{display:flex;gap:12px;max-width:760px}.search-form .form-control{height:52px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);color:#fff}.search-form .form-control::placeholder{color:rgba(255,255,255,.72)}
    .result-grid{display:grid;gap:18px}.result-card{background:#181a2f;border:1px solid rgba(255,255,255,.07);border-radius:22px;padding:20px;box-shadow:0 18px 48px rgba(0,0,0,.24)}
    .result-title{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.table{color:#fff}.table>*{border-color:rgba(255,255,255,.06)!important}.muted{color:#a4acc4}.empty-box{background:rgba(255,255,255,.05);border:1px dashed rgba(255,255,255,.14);border-radius:20px;padding:26px;text-align:center;color:#a4acc4}
    @media(max-width:720px){.search-form{display:grid}.search-form .btn{height:50px}}
  </style>
</head>
<body class="sg-admin">
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
      <h1 class="h3 mb-1">Recherche globale</h1>
      <div class="muted">Retrouver rapidement un client, un compteur, une facture ou une mesure.</div>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-light" href="/smartgrid_dashboard/admin/index.php"><i class="bi bi-grid me-1"></i> Dashboard</a>
      <a class="btn btn-outline-light" href="/smartgrid_dashboard/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Deconnexion</a>
    </div>
  </div>

  <div class="search-hero mb-4">
    <form class="search-form" method="get" action="/smartgrid_dashboard/admin/recherche.php">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Ex: SG001, Abigael, payee, critique..." autofocus required>
      <button class="btn btn-light rounded-pill px-4" type="submit"><i class="bi bi-search me-1"></i> Rechercher</button>
    </form>
    <?php if ($q !== ''): ?>
      <div class="mt-3 fw-bold"><?= (int) $totalResults ?> resultat(s) pour "<?= e($q) ?>"</div>
    <?php endif; ?>
  </div>

  <?php if ($q === ''): ?>
    <div class="empty-box">Entrez un nom de client, un numero de compteur, un statut ou une date pour lancer la recherche.</div>
  <?php elseif ($totalResults === 0): ?>
    <div class="empty-box">Aucun resultat trouve. Essayez avec un autre mot-cle.</div>
  <?php else: ?>
    <div class="result-grid">
      <section class="result-card">
        <div class="result-title"><h2 class="h5 mb-0"><i class="bi bi-people me-2"></i>Clients</h2><span class="badge text-bg-primary"><?= count($results['clients']) ?></span></div>
        <?php if (!$results['clients']): ?><div class="muted">Aucun client correspondant.</div><?php else: ?>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nom</th><th>Telephone</th><th>Adresse</th><th>Compteurs</th></tr></thead><tbody>
          <?php foreach ($results['clients'] as $row): ?><tr><td><?= e($row['nom']) ?></td><td><?= e($row['telephone']) ?></td><td><?= e($row['adresse']) ?></td><td><?= e($row['compteurs'] ?: '-') ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
      </section>

      <section class="result-card">
        <div class="result-title"><h2 class="h5 mb-0"><i class="bi bi-cpu me-2"></i>Compteurs</h2><span class="badge text-bg-primary"><?= count($results['compteurs']) ?></span></div>
        <?php if (!$results['compteurs']): ?><div class="muted">Aucun compteur correspondant.</div><?php else: ?>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Numero</th><th>Client</th><th>Statut</th><th>Installation</th></tr></thead><tbody>
          <?php foreach ($results['compteurs'] as $row): ?><tr><td><?= e($row['numero_compteur']) ?></td><td><?= e($row['client_nom'] ?: '-') ?></td><td><span class="badge <?= ($row['statut'] ?? '') === 'actif' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e($row['statut'] ?: 'inactif') ?></span></td><td><?= e($row['date_installation']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
      </section>

      <section class="result-card">
        <div class="result-title"><h2 class="h5 mb-0"><i class="bi bi-activity me-2"></i>Consommations</h2><span class="badge text-bg-primary"><?= count($results['consommations']) ?></span></div>
        <?php if (!$results['consommations']): ?><div class="muted">Aucune mesure correspondante.</div><?php else: ?>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date</th><th>Client</th><th>Compteur</th><th>Tension</th><th>Puissance</th><th>Energie</th></tr></thead><tbody>
          <?php foreach ($results['consommations'] as $row): ?><tr><td><?= e($row['date_mesure']) ?></td><td><?= e($row['client_nom'] ?: '-') ?></td><td><?= e($row['numero_compteur'] ?: '-') ?></td><td><?= number_format((float)$row['tension'], 1) ?> V</td><td><?= number_format((float)$row['puissance'], 1) ?> W</td><td><?= number_format((float)$row['energie'], 3) ?> kWh</td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
      </section>

      <section class="result-card">
        <div class="result-title"><h2 class="h5 mb-0"><i class="bi bi-receipt me-2"></i>Factures</h2><span class="badge text-bg-primary"><?= count($results['factures']) ?></span></div>
        <?php if (!$results['factures']): ?><div class="muted">Aucune facture correspondante.</div><?php else: ?>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>ID</th><th>Client</th><th>Montant</th><th>Energie</th><th>Statut</th><th>Action</th></tr></thead><tbody>
          <?php foreach ($results['factures'] as $row): ?><tr><td>#<?= (int)$row['id'] ?></td><td><?= e($row['client_nom'] ?: '-') ?></td><td>$<?= number_format((float)$row['montant'], 2) ?></td><td><?= number_format((float)$row['energie_totale'], 2) ?> kWh</td><td><span class="badge <?= $row['statut'] === 'payee' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e($row['statut']) ?></span></td><td><a class="btn btn-sm btn-primary" href="/smartgrid_dashboard/smartgrid_app/facture_detail.php?id=<?= (int)$row['id'] ?>">Voir</a></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
      </section>

      <section class="result-card">
        <div class="result-title"><h2 class="h5 mb-0"><i class="bi bi-bell me-2"></i>Alertes</h2><span class="badge text-bg-primary"><?= count($results['alertes']) ?></span></div>
        <?php if (!$results['alertes']): ?><div class="muted">Aucune alerte correspondante.</div><?php else: ?>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date</th><th>Client</th><th>Compteur</th><th>Niveau</th><th>Message</th></tr></thead><tbody>
          <?php foreach ($results['alertes'] as $row): ?><tr><td><?= e($row['date_alerte']) ?></td><td><?= e($row['client_nom'] ?: '-') ?></td><td><?= e($row['numero_compteur'] ?: '-') ?></td><td><?= e($row['niveau']) ?></td><td><?= e($row['message']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
      </section>

      <section class="result-card">
        <div class="result-title"><h2 class="h5 mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Predictions</h2><span class="badge text-bg-primary"><?= count($results['predictions']) ?></span></div>
        <?php if (!$results['predictions']): ?><div class="muted">Aucune prediction correspondante.</div><?php else: ?>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date</th><th>Client</th><th>Compteur</th><th>Consommation prevue</th></tr></thead><tbody>
          <?php foreach ($results['predictions'] as $row): ?><tr><td><?= e($row['date_prediction']) ?></td><td><?= e($row['client_nom'] ?: '-') ?></td><td><?= e($row['numero_compteur'] ?: '-') ?></td><td><?= number_format((float)$row['prediction'], 3) ?> kWh</td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
      </section>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
