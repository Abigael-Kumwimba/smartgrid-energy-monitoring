<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_data.php';

$user = smartgrid_require_login(['admin']);
$clients = fetch_clients_list();
$meters = fetch_meters_list();
$totalMeters = count($meters);
$activeMeters = count(array_filter($meters, static fn($meter) => $meter['statut'] === 'actif'));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Compteurs SmartGrid</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
  <style>
    .stat-card{background:linear-gradient(135deg,rgba(124,92,255,.16),rgba(41,211,145,.08));border:1px solid var(--sg-line);border-radius:22px;padding:18px}.stat-icon{width:44px;height:44px;display:grid;place-items:center;border-radius:16px;background:rgba(255,255,255,.09);font-size:1.35rem}.field-label{font-size:.86rem;font-weight:700;color:var(--sg-muted);margin-bottom:6px}.meter-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:rgba(73,184,255,.1);color:#dff3ff;font-weight:700}.table-card{border-radius:18px;overflow:hidden}.search-box{max-width:320px}.empty-state{padding:28px;text-align:center;color:var(--sg-muted)}.status-note{font-size:.78rem;color:var(--sg-muted);margin-top:4px}.btn-status{border-radius:999px}
  </style>
</head>
<body class="sg-admin">
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Gestion des compteurs</h1>
      <div class="muted">Association des compteurs electriques aux clients enregistres</div>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-light" href="/smartgrid_dashboard/admin/index.php"><i class="bi bi-grid me-1"></i> Dashboard</a>
      <a class="btn btn-outline-light" href="/smartgrid_dashboard/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Deconnexion</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="stat-card d-flex justify-content-between align-items-center">
        <div><div class="muted small">Compteurs enregistres</div><div class="fs-2 fw-bold" id="meters-count"><?= $totalMeters ?></div></div>
        <div class="stat-icon"><i class="bi bi-cpu"></i></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card d-flex justify-content-between align-items-center">
        <div><div class="muted small">Compteurs actifs</div><div class="fs-2 fw-bold" id="active-meters-count"><?= $activeMeters ?></div></div>
        <div class="stat-icon"><i class="bi bi-lightning-charge"></i></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card d-flex justify-content-between align-items-center">
        <div><div class="muted small">Clients disponibles</div><div class="fs-2 fw-bold"><?= count($clients) ?></div></div>
        <div class="stat-icon"><i class="bi bi-person-check"></i></div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="panel h-100">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="stat-icon"><i class="bi bi-plus-circle"></i></div>
          <div><h2 class="h5 mb-0">Nouveau compteur</h2><div class="muted small">Rattacher un compteur a un client</div></div>
        </div>
        <div id="meter-form-msg" class="small mb-3"></div>
        <form id="meter-form" class="d-grid gap-3">
          <div>
            <label class="field-label" for="numero_compteur">Numero du compteur</label>
            <input id="numero_compteur" class="form-control" name="numero_compteur" placeholder="Ex. SG001" required>
          </div>
          <div>
            <label class="field-label" for="id_client">Client associe</label>
            <select id="id_client" class="form-select" name="id_client" required>
              <?php foreach ($clients as $client): ?>
                <option value="<?= (int) $client['id'] ?>"><?= htmlspecialchars($client['nom']) ?> (#<?= (int) $client['id'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="soft-card small muted">L'etat du compteur sera determine automatiquement par les mesures envoyees par le module ESP32/PZEM.</div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Ajouter le compteur</button>
        </form>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="panel">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div><h2 class="h5 mb-0">Historique des compteurs</h2><div class="muted small">Etat technique calcule a partir des mesures recues</div></div>
          <div class="d-flex gap-2">
            <input class="form-control search-box" id="meter-search" placeholder="Rechercher un compteur...">
            <button class="btn btn-outline-light btn-sm" id="reload-meters" type="button"><i class="bi bi-arrow-clockwise me-1"></i> Actualiser</button>
          </div>
        </div>
        <div class="table-responsive table-card">
          <table class="table align-middle mb-0">
            <thead><tr><th>Compteur</th><th>Client</th><th>Etat du compteur</th><th>Derniere mesure</th><th>Date installation</th></tr></thead>
            <tbody id="meters-table-body">
            <?php foreach ($meters as $meter): ?>
              <?php $statut = $meter['statut'] === 'actif' ? 'actif' : 'inactif'; ?>
              <tr>
                <td><span class="meter-chip"><i class="bi bi-cpu"></i><?= htmlspecialchars($meter['numero_compteur']) ?></span><div class="muted small mt-1">ID #<?= (int) $meter['id'] ?></div></td>
                <td><?= htmlspecialchars($meter['client_nom'] ?? '-') ?></td>
                <td>
                  <span class="badge <?= $statut === 'actif' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $statut === 'actif' ? 'En service' : 'Hors service' ?></span>
                  <div class="status-note"><?= htmlspecialchars($meter['statut_detail'] ?? ($statut === 'actif' ? 'Mesures recues' : 'Aucune mesure recente')) ?></div>
                  <?php if (((int)($meter['factures_non_payees'] ?? 0)) > 0): ?>
                    <div class="status-note text-warning">Facture non payee a suivre</div>
                  <?php endif; ?>
                </td>
                <td><?= $meter['derniere_mesure'] ? htmlspecialchars($meter['derniere_mesure']) : '<span class="muted">Aucune</span>' ?></td>
                <td><?= htmlspecialchars($meter['date_installation']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const meterSearch = document.getElementById('meter-search');
let currentMeters = <?= json_encode($meters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

async function fetchJson(url, options = {}) {
  const response = await fetch(url, { headers: { 'Accept': 'application/json' }, ...options });
  const text = await response.text();
  try { return JSON.parse(text); } catch { return text; }
}

function esc(value) {
  return String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
}

function renderMeters(meters) {
  const query = meterSearch.value.trim().toLowerCase();
  const filtered = meters.filter((meter) => `${meter.numero_compteur ?? ''} ${meter.client_nom ?? ''} ${meter.id_client ?? ''}`.toLowerCase().includes(query));
  document.getElementById('meters-count').textContent = meters.length;
  document.getElementById('active-meters-count').textContent = meters.filter((meter) => meter.statut === 'actif').length;

  document.getElementById('meters-table-body').innerHTML = filtered.length ? filtered.map((meter) => {
    const statut = meter.statut === 'actif' ? 'actif' : 'inactif';
    const badge = statut === 'actif' ? 'text-bg-success' : 'text-bg-secondary';
    const label = statut === 'actif' ? 'En service' : 'Hors service';
    const note = meter.statut_detail || (statut === 'actif' ? 'Mesures recues' : 'Aucune mesure recente');
    const billingNote = Number(meter.factures_non_payees || 0) > 0 ? '<div class="status-note text-warning">Facture non payee a suivre</div>' : '';
    return `<tr>
      <td><span class="meter-chip"><i class="bi bi-cpu"></i>${esc(meter.numero_compteur)}</span><div class="muted small mt-1">ID #${esc(meter.id)}</div></td>
      <td>${esc(meter.client_nom || meter.id_client || '-')}</td>
      <td><span class="badge ${badge}">${label}</span><div class="status-note">${esc(note)}</div>${billingNote}</td>
      <td>${meter.derniere_mesure ? esc(meter.derniere_mesure) : '<span class="muted">Aucune</span>'}</td>
      <td>${esc(meter.date_installation)}</td>
    </tr>`;
  }).join('') : `<tr><td colspan="5" class="empty-state">Aucun compteur trouve.</td></tr>`;
}

async function loadMeters() {
  const [meters, clients] = await Promise.all([
    fetchJson('/smartgrid_energy_api/compteurs/read_compteurs.php'),
    fetchJson('/smartgrid_energy_api/clients/read_clients.php')
  ]);
  const clientMap = Object.fromEntries((Array.isArray(clients) ? clients : []).map((client) => [String(client.id), client.nom]));
  if (Array.isArray(meters)) {
    currentMeters = meters.slice().reverse().map((meter) => ({ ...meter, client_nom: clientMap[String(meter.id_client)] || meter.client_nom || meter.id_client }));
    renderMeters(currentMeters);
  }
}

document.getElementById('reload-meters').addEventListener('click', loadMeters);
meterSearch.addEventListener('input', () => renderMeters(currentMeters));
document.getElementById('meter-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(event.target);
  const response = await fetch('/smartgrid_energy_api/compteurs/create_compteur.php', { method: 'POST', body: form });
  const text = await response.text();
  document.getElementById('meter-form-msg').textContent = text;
  event.target.reset();
  loadMeters();
});
</script>
</body>
</html>
