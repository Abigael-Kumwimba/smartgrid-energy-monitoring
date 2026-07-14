<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_data.php';

$user = smartgrid_require_login(['admin']);
$conn = dashboard_db();
$selectedClientId = isset($_GET['client_id']) ? max(0, (int) $_GET['client_id']) : 0;
$stmt = $conn->prepare(
    'SELECT a.date_alerte, a.niveau, a.message, cp.numero_compteur, cl.id AS id_client, cl.nom AS client_nom, cl.telegram_chat_id
     FROM alertes a
     LEFT JOIN compteurs cp ON cp.id = a.id_compteur
     LEFT JOIN clients cl ON cl.id = cp.id_client
     ORDER BY a.date_alerte DESC
     LIMIT 150'
);
$rows = fetch_all_assoc($stmt);
$totalAlerts = count($rows);
$criticalCount = count(array_filter($rows, static fn($row) => $row['niveau'] === 'critique'));
$warningCount = count(array_filter($rows, static fn($row) => $row['niveau'] === 'warning'));
$telegramReady = count(array_filter($rows, static fn($row) => !empty($row['telegram_chat_id'])));
$latestAlert = $rows[0] ?? null;
$alertsByClient = [];
foreach ($rows as $row) {
    $clientKey = (string) ($row['client_nom'] ?? 'Client non rattache');
    if (!isset($alertsByClient[$clientKey])) {
        $alertsByClient[$clientKey] = [
            'client_nom' => $row['client_nom'] ?? 'Client non rattache',
            'total' => 0,
            'critiques' => 0,
            'warnings' => 0,
            'derniere' => $row['date_alerte'] ?? null,
        ];
    }
    $alertsByClient[$clientKey]['total']++;
    if (($row['niveau'] ?? '') === 'critique') {
        $alertsByClient[$clientKey]['critiques']++;
    }
    if (($row['niveau'] ?? '') === 'warning') {
        $alertsByClient[$clientKey]['warnings']++;
    }
    if (!empty($row['date_alerte']) && $row['date_alerte'] > (string) $alertsByClient[$clientKey]['derniere']) {
        $alertsByClient[$clientKey]['derniere'] = $row['date_alerte'];
    }
}

$clientCardsStmt = $conn->prepare(
    'SELECT cl.id, cl.nom, cl.telegram_chat_id,
            COUNT(DISTINCT cp.id) AS total_compteurs,
            COUNT(a.id) AS total_alertes,
            SUM(CASE WHEN a.niveau = "critique" THEN 1 ELSE 0 END) AS critiques,
            SUM(CASE WHEN a.niveau = "warning" THEN 1 ELSE 0 END) AS warnings,
            MAX(a.date_alerte) AS derniere_alerte,
            GROUP_CONCAT(DISTINCT cp.numero_compteur ORDER BY cp.numero_compteur SEPARATOR ", ") AS compteurs
     FROM clients cl
     LEFT JOIN compteurs cp ON cp.id_client = cl.id
     LEFT JOIN alertes a ON a.id_compteur = cp.id
     GROUP BY cl.id, cl.nom, cl.telegram_chat_id
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
        'SELECT a.date_alerte, a.niveau, a.message, cp.numero_compteur, cl.nom AS client_nom, cl.telegram_chat_id
         FROM alertes a
         INNER JOIN compteurs cp ON cp.id = a.id_compteur
         INNER JOIN clients cl ON cl.id = cp.id_client
         WHERE cl.id = ?
         ORDER BY a.date_alerte DESC
         LIMIT 100'
    );
    $selectedStmt->bind_param('i', $selectedClientId);
    $selectedRows = fetch_all_assoc($selectedStmt);
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Alertes SmartGrid</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
  <style>
    body{background:#0f1020;color:#fff;font-family:'Source Sans 3',sans-serif}.wrap{max-width:1220px;margin:0 auto;padding:32px}.panel{background:#181a2f;border:1px solid rgba(255,255,255,.07);border-radius:24px;padding:24px;box-shadow:0 18px 48px rgba(0,0,0,.28)}.metric{border-radius:22px;padding:18px;min-height:124px;position:relative;overflow:hidden}.metric:after{content:"";position:absolute;right:-28px;top:-28px;width:92px;height:92px;border-radius:999px;background:rgba(255,255,255,.15)}.tone-red{background:linear-gradient(135deg,#ff4d6d,#ff8a5b)}.tone-yellow{background:linear-gradient(135deg,#ffb44d,#ffd166)}.tone-blue{background:linear-gradient(135deg,#2b86ff,#49d2ff)}.tone-green{background:linear-gradient(135deg,#0da878,#29d391)}.metric-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:15px;background:rgba(255,255,255,.18);font-size:1.2rem}.table{color:#fff}.table>*{border-color:rgba(255,255,255,.06)!important}.muted{color:#a4acc4}.level-badge{border-radius:999px;padding:.4rem .75rem;font-weight:800}.level-warning{background:rgba(255,180,77,.16);color:#ffd18a}.level-critique{background:rgba(255,107,122,.16);color:#ff9da8}.level-info{background:rgba(73,184,255,.16);color:#9addff}.search-input{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);color:#fff;border-radius:16px}.search-input::placeholder{color:#a4acc4}.status-ok{color:#29d391}.status-missing{color:#ffb44d}.last-card{background:linear-gradient(135deg,rgba(255,107,122,.12),rgba(255,180,77,.08));border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:18px}.empty-state{text-align:center;color:#a4acc4;padding:28px}.test-card{background:linear-gradient(135deg,rgba(73,184,255,.13),rgba(41,211,145,.09));border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:18px}.test-status{min-height:24px}.telegram-btn{border-radius:999px}.client-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}.client-card{display:block;text-decoration:none;color:#fff;background:linear-gradient(135deg,rgba(255,255,255,.09),rgba(255,255,255,.04));border:1px solid rgba(255,255,255,.09);border-radius:22px;padding:18px;transition:.18s}.client-card:hover,.client-card.active{color:#fff;border-color:#ffb44d;transform:translateY(-2px);box-shadow:0 18px 38px rgba(255,180,77,.12)}.client-card.active{background:linear-gradient(135deg,rgba(255,180,77,.18),rgba(255,107,122,.10))}.chip{display:inline-flex;gap:6px;align-items:center;border-radius:999px;padding:.35rem .7rem;background:rgba(255,180,77,.14);color:#ffd18a;font-weight:800;font-size:.86rem}
  </style>
</head>
<body class="sg-admin">
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Alertes et anomalies</h1>
      <div class="muted">Detection automatique des sous-tensions, surtensions, surcharges et surintensites</div>
      <div class="test-status small mt-2" id="telegram-test-status"></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button class="btn btn-primary telegram-btn" id="telegram-test-btn" type="button">
        <i class="bi bi-send-check me-1"></i> Envoyer la derniere alerte
      </button>
      <a class="btn btn-outline-light" href="/smartgrid_dashboard/admin/index.php">Dashboard</a>
      <a class="btn btn-outline-light" href="/smartgrid_dashboard/logout.php">Deconnexion</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6"><div class="metric tone-blue"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Alertes totales</div><div class="display-6 fw-bold"><?= $totalAlerts ?></div><div class="small">historique recent</div></div><div class="metric-icon"><i class="bi bi-bell"></i></div></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="metric tone-red"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Critiques</div><div class="display-6 fw-bold"><?= $criticalCount ?></div><div class="small">action rapide</div></div><div class="metric-icon"><i class="bi bi-exclamation-triangle"></i></div></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="metric tone-yellow"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Warnings</div><div class="display-6 fw-bold"><?= $warningCount ?></div><div class="small">surveillance</div></div><div class="metric-icon"><i class="bi bi-activity"></i></div></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="metric tone-green"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Clients notifiables</div><div class="display-6 fw-bold"><?= $telegramReady ?></div><div class="small">Telegram configure</div></div><div class="metric-icon"><i class="bi bi-send-check"></i></div></div></div></div>
  </div>
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="panel h-100">
        <h2 class="h5 mb-3">Derniere alerte</h2>
        <?php if ($latestAlert): ?>
          <div class="last-card">
            <div class="d-flex justify-content-between mb-2"><strong><?= htmlspecialchars($latestAlert['numero_compteur'] ?? '-') ?></strong><span class="level-badge level-<?= htmlspecialchars($latestAlert['niveau']) ?>"><?= htmlspecialchars($latestAlert['niveau']) ?></span></div>
            <div class="muted small mb-2"><?= htmlspecialchars($latestAlert['date_alerte']) ?></div>
            <div><?= htmlspecialchars($latestAlert['message']) ?></div>
            <div class="mt-2 muted">Client : <?= htmlspecialchars($latestAlert['client_nom'] ?? '-') ?></div>
  </div>
        <?php else: ?>
          <div class="empty-state">Aucune alerte enregistree.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="panel mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <div><h2 class="h5 mb-0">Alertes par client</h2><div class="muted small">Cliquez sur un client pour afficher uniquement ses alertes</div></div>
      <span class="badge text-bg-dark"><?= count($clientCards) ?> client(s)</span>
    </div>
    <div class="client-grid">
      <?php foreach ($clientCards as $clientRow): ?>
        <a class="client-card <?= (int) $clientRow['id'] === $selectedClientId ? 'active' : '' ?>" href="?client_id=<?= (int) $clientRow['id'] ?>#client-detail">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <strong><?= htmlspecialchars($clientRow['nom'] ?? '-') ?></strong>
            <span class="chip"><?= (int) $clientRow['total_alertes'] ?> alerte(s)</span>
          </div>
          <div class="muted small mb-3"><?= htmlspecialchars($clientRow['compteurs'] ?? 'Aucun compteur') ?></div>
          <div class="row g-2 small">
            <div class="col-6"><span class="muted">Critiques</span><br><strong><?= (int) $clientRow['critiques'] ?></strong></div>
            <div class="col-6"><span class="muted">Warnings</span><br><strong><?= (int) $clientRow['warnings'] ?></strong></div>
            <div class="col-12"><span class="muted">Telegram</span><br><?= !empty($clientRow['telegram_chat_id']) ? '<span class="status-ok fw-bold">Configure</span>' : '<span class="status-missing fw-bold">Chat ID manquant</span>' ?></div>
          </div>
          <div class="muted small mt-3">Derniere alerte : <?= htmlspecialchars($clientRow['derniere_alerte'] ?? 'Aucune') ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel mb-4" id="client-detail">
    <?php if ($selectedClient): ?>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div><h2 class="h5 mb-0">Alertes de <?= htmlspecialchars($selectedClient['nom']) ?></h2><div class="muted small">Historique limite au client selectionne</div></div>
        <a class="btn btn-sm btn-outline-light" href="/smartgrid_dashboard/admin/alertes.php">Revenir a la vue clients</a>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Date</th><th>Compteur</th><th>Niveau</th><th>Message</th><th>Notification client</th></tr></thead>
          <tbody>
          <?php foreach ($selectedRows as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['date_alerte']) ?></td>
              <td><?= htmlspecialchars($row['numero_compteur'] ?? '-') ?></td>
              <td><span class="level-badge level-<?= htmlspecialchars($row['niveau']) ?>"><?= htmlspecialchars($row['niveau']) ?></span></td>
              <td><?= htmlspecialchars($row['message']) ?></td>
              <td><?= !empty($row['telegram_chat_id']) ? '<span class="status-ok">Telegram configure</span>' : '<span class="status-missing">Chat ID manquant</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$selectedRows): ?>
            <tr><td colspan="5" class="empty-state">Aucune alerte disponible pour ce client.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state"><i class="bi bi-bell fs-1 d-block mb-2"></i>Selectionnez un client ci-dessus pour voir son detail d'alertes.</div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <div><h2 class="h5 mb-0">Historique des alertes</h2><div class="muted small">Recherche par client, compteur, niveau ou message</div></div>
      <div class="d-flex gap-2 flex-wrap">
        <select class="form-select search-input" id="alert-client-filter">
          <option value="">Tous les clients</option>
          <?php foreach ($alertsByClient as $clientRow): ?>
            <option value="<?= htmlspecialchars(strtolower((string) ($clientRow['client_nom'] ?? ''))) ?>"><?= htmlspecialchars($clientRow['client_nom'] ?? '-') ?></option>
          <?php endforeach; ?>
        </select>
        <input class="form-control search-input" id="alert-search" placeholder="Rechercher...">
        <span class="badge text-bg-dark align-self-center" id="rows-count"><?= count($rows) ?> lignes</span>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Date</th><th>Client</th><th>Compteur</th><th>Niveau</th><th>Message</th><th>Notification client</th></tr></thead>
        <tbody id="alert-table-body">
        <?php foreach ($rows as $row): ?>
          <tr data-client="<?= htmlspecialchars(strtolower((string) ($row['client_nom'] ?? ''))) ?>" data-search="<?= htmlspecialchars(strtolower(($row['date_alerte'] ?? '') . ' ' . ($row['client_nom'] ?? '') . ' ' . ($row['numero_compteur'] ?? '') . ' ' . ($row['niveau'] ?? '') . ' ' . ($row['message'] ?? ''))) ?>">
            <td><?= htmlspecialchars($row['date_alerte']) ?></td>
            <td><?= htmlspecialchars($row['client_nom'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['numero_compteur'] ?? '-') ?></td>
            <td><span class="level-badge level-<?= htmlspecialchars($row['niveau']) ?>"><?= htmlspecialchars($row['niveau']) ?></span></td>
            <td><?= htmlspecialchars($row['message']) ?></td>
            <td><?= !empty($row['telegram_chat_id']) ? '<span class="status-ok">Telegram configure</span>' : '<span class="status-missing">Chat ID manquant</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="empty-state">Aucune alerte disponible.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
const search = document.getElementById('alert-search');
const clientFilter = document.getElementById('alert-client-filter');
const rowsCount = document.getElementById('rows-count');
const telegramBtn = document.getElementById('telegram-test-btn');
const telegramStatus = document.getElementById('telegram-test-status');
function filterAlerts() {
  const query = search.value.trim().toLowerCase();
  const selectedClient = clientFilter.value;
  let visible = 0;
  document.querySelectorAll('#alert-table-body tr[data-search]').forEach((row) => {
    const show = row.dataset.search.includes(query) && (!selectedClient || row.dataset.client === selectedClient);
    row.style.display = show ? '' : 'none';
    if (show) visible += 1;
  });
  rowsCount.textContent = `${visible} lignes`;
}
search.addEventListener('input', filterAlerts);
clientFilter.addEventListener('change', filterAlerts);
telegramBtn.addEventListener('click', async () => {
  telegramBtn.disabled = true;
  telegramStatus.className = 'test-status small mt-3 text-info';
  telegramStatus.textContent = 'Envoi de la derniere alerte...';
  try {
    const response = await fetch('/smartgrid_dashboard/smartgrid_app/api/send_latest_alert_telegram.php', { method: 'POST' });
    const data = await response.json();
    telegramStatus.className = data.status === 'success' ? 'test-status small mt-3 text-success' : 'test-status small mt-3 text-warning';
    telegramStatus.textContent = data.message || 'Envoi termine.';
  } catch (error) {
    telegramStatus.className = 'test-status small mt-3 text-warning';
    telegramStatus.textContent = 'Impossible de contacter le serveur Telegram.';
  } finally {
    telegramBtn.disabled = false;
  }
});
</script>
</body>
</html>

