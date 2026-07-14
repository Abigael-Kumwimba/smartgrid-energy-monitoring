<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_data.php';

$user = smartgrid_require_login(['admin']);
$clients = fetch_clients_list();
$totalClients = count($clients);
$latestClient = $clients[0]['date_creation'] ?? '-';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clients SmartGrid</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
  <style>
    .stat-card{background:linear-gradient(135deg,rgba(41,211,145,.14),rgba(73,184,255,.08));border:1px solid var(--sg-line);border-radius:22px;padding:18px}.stat-icon{width:44px;height:44px;display:grid;place-items:center;border-radius:16px;background:rgba(255,255,255,.09);font-size:1.35rem}.field-label{font-size:.86rem;font-weight:700;color:var(--sg-muted);margin-bottom:6px}.client-avatar{width:38px;height:38px;display:grid;place-items:center;border-radius:14px;background:rgba(41,211,145,.16);color:var(--sg-primary);font-weight:800}.table-card{border-radius:18px;overflow:hidden}.search-box{max-width:320px}.empty-state{padding:28px;text-align:center;color:var(--sg-muted)}
  </style>
</head>
<body class="sg-admin">
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Gestion des clients</h1>
      <div class="muted">Ajout, modification et suppression des clients</div>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-light" href="/smartgrid_dashboard/admin/index.php"><i class="bi bi-grid me-1"></i> Dashboard</a>
      <a class="btn btn-outline-light" href="/smartgrid_dashboard/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Deconnexion</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="stat-card d-flex justify-content-between align-items-center">
        <div><div class="muted small">Clients enregistres</div><div class="fs-2 fw-bold" id="clients-count"><?= $totalClients ?></div></div>
        <div class="stat-icon"><i class="bi bi-people"></i></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="stat-card d-flex justify-content-between align-items-center">
        <div><div class="muted small">Dernier ajout</div><div class="fs-5 fw-bold" id="latest-client-date"><?= htmlspecialchars($latestClient) ?></div></div>
        <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="panel h-100">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="stat-icon"><i class="bi bi-person-plus"></i></div>
          <div><h2 class="h5 mb-0" id="client-form-title">Nouveau client</h2></div>
        </div>
        <div id="client-form-msg" class="small mb-3"></div>
        <form id="client-form" class="d-grid gap-3">
          <input type="hidden" id="client_id" name="id">
          <div>
            <label class="field-label" for="nom">Nom complet</label>
            <input id="nom" class="form-control" name="nom" placeholder="Ex. Kumwimba Abigael" required>
          </div>
          <div>
            <label class="field-label" for="telephone">Telephone</label>
            <input id="telephone" class="form-control" name="telephone" placeholder="Ex. +243 ..." required>
          </div>
          <div>
            <label class="field-label" for="telegram_chat_id">Chat ID Telegram</label>
            <input id="telegram_chat_id" class="form-control" name="telegram_chat_id" placeholder="Ex. 5249947478">
            
          </div>
          <div>
            <label class="field-label" for="adresse">Adresse</label>
            <textarea id="adresse" class="form-control" name="adresse" placeholder="Quartier, avenue, numero" rows="4" required></textarea>
          </div>
          <button class="btn btn-primary" id="client-submit-btn" type="submit"><i class="bi bi-check2-circle me-1"></i> Ajouter le client</button>
          <button class="btn btn-outline-light d-none" id="client-cancel-edit" type="button"><i class="bi bi-x-circle me-1"></i> Annuler la modification</button>
        </form>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="panel">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div><h2 class="h5 mb-0">Historique des clients</h2></div>
          <div class="d-flex gap-2">
            <input class="form-control search-box" id="client-search" placeholder="Rechercher un client...">
            <button class="btn btn-outline-light btn-sm" id="reload-clients" type="button"><i class="bi bi-arrow-clockwise me-1"></i> Actualiser</button>
          </div>
        </div>
        <div class="table-responsive table-card">
          <table class="table align-middle mb-0">
            <thead><tr><th>Client</th><th>Telephone</th><th>Telegram</th><th>Adresse</th><th>Compteurs rattaches</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody id="clients-table-body">
            <?php foreach ($clients as $client): ?>
              <tr>
                <td><div class="d-flex align-items-center gap-2"><div class="client-avatar"><?= strtoupper(substr((string) $client['nom'], 0, 1)) ?></div><div><div class="fw-bold"><?= htmlspecialchars($client['nom']) ?></div><div class="muted small">ID #<?= (int) $client['id'] ?></div></div></div></td>
                <td><?= htmlspecialchars($client['telephone']) ?></td>
                <td>
                  <?php if (!empty($client['telegram_chat_id'])): ?>
                    <span class="badge text-bg-success">Configure</span>
                    <div class="muted small mt-1"><?= htmlspecialchars($client['telegram_chat_id']) ?></div>
                  <?php else: ?>
                    <span class="badge text-bg-warning">Non renseigne</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($client['adresse']) ?></td>
                <td>
                  <?php if (!empty($client['compteurs'])): ?>
                    <span class="badge text-bg-info"><?= (int) $client['total_compteurs'] ?> compteur(s)</span>
                    <div class="muted small mt-1"><?= htmlspecialchars($client['compteurs']) ?></div>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Aucun</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($client['date_creation']) ?></td>
                <td>
                  <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-outline-light edit-client-btn" type="button" data-id="<?= (int) $client['id'] ?>"><i class="bi bi-pencil-square"></i></button>
                    <button class="btn btn-sm btn-outline-danger delete-client-btn" type="button" data-id="<?= (int) $client['id'] ?>" data-name="<?= htmlspecialchars($client['nom']) ?>"><i class="bi bi-trash"></i></button>
                  </div>
                </td>              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const clientSearch = document.getElementById('client-search');
const clientForm = document.getElementById('client-form');
const clientFormMsg = document.getElementById('client-form-msg');
const clientFormTitle = document.getElementById('client-form-title');
const clientSubmitBtn = document.getElementById('client-submit-btn');
const cancelEditBtn = document.getElementById('client-cancel-edit');
let currentClients = <?= json_encode($clients, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

async function fetchJson(url, options = {}) {
  const response = await fetch(url, { headers: { 'Accept': 'application/json' }, ...options });
  const text = await response.text();
  try { return JSON.parse(text); } catch { return text; }
}

function esc(value) {
  return String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
}

function renderClients(clients) {
  const query = clientSearch.value.trim().toLowerCase();
  const filtered = clients.filter((client) => `${client.nom ?? ''} ${client.telephone ?? ''} ${client.adresse ?? ''} ${client.telegram_chat_id ?? ''}`.toLowerCase().includes(query));
  document.getElementById('clients-count').textContent = clients.length;
  document.getElementById('latest-client-date').textContent = clients[0]?.date_creation || '-';

  document.getElementById('clients-table-body').innerHTML = filtered.length ? filtered.map((client) => {
    const initial = esc((client.nom || '?').slice(0, 1).toUpperCase());
    const meters = client.compteurs
      ? `<span class="badge text-bg-info">${esc(client.total_compteurs)} compteur(s)</span><div class="muted small mt-1">${esc(client.compteurs)}</div>`
      : '<span class="badge text-bg-secondary">Aucun</span>';
    return `<tr>
      <td><div class="d-flex align-items-center gap-2"><div class="client-avatar">${initial}</div><div><div class="fw-bold">${esc(client.nom)}</div><div class="muted small">ID #${esc(client.id)}</div></div></div></td>
      <td>${esc(client.telephone)}</td>
      <td>${client.telegram_chat_id ? `<span class="badge text-bg-success">Configure</span><div class="muted small mt-1">${esc(client.telegram_chat_id)}</div>` : '<span class="badge text-bg-warning">Non renseigne</span>'}</td>
      <td>${esc(client.adresse)}</td>
      <td>${meters}</td>
      <td>${esc(client.date_creation)}</td>
      <td><div class="d-flex gap-2 flex-wrap"><button class="btn btn-sm btn-outline-light edit-client-btn" type="button" data-id="${esc(client.id)}"><i class="bi bi-pencil-square"></i></button><button class="btn btn-sm btn-outline-danger delete-client-btn" type="button" data-id="${esc(client.id)}" data-name="${esc(client.nom)}"><i class="bi bi-trash"></i></button></div></td>
    </tr>`;
  }).join('') : `<tr><td colspan="7" class="empty-state">Aucun client trouve.</td></tr>`;
}

function resetClientForm() {
  clientForm.reset();
  document.getElementById('client_id').value = '';
  clientFormTitle.textContent = 'Nouveau client';
  clientSubmitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Ajouter le client';
  cancelEditBtn.classList.add('d-none');
}

function editClient(id) {
  const client = currentClients.find((item) => String(item.id) === String(id));
  if (!client) return;
  document.getElementById('client_id').value = client.id || '';
  document.getElementById('nom').value = client.nom || '';
  document.getElementById('telephone').value = client.telephone || '';
  document.getElementById('telegram_chat_id').value = client.telegram_chat_id || '';
  document.getElementById('adresse').value = client.adresse || '';
  clientFormTitle.textContent = 'Modifier le client';
  clientSubmitBtn.innerHTML = '<i class="bi bi-pencil-square me-1"></i> Enregistrer les modifications';
  cancelEditBtn.classList.remove('d-none');
  clientFormMsg.textContent = '';
  clientForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function deleteClient(id, name) {
  if (!confirm(`Supprimer le client ${name} ?`)) return;
  const form = new FormData();
  form.append('id', id);
  const response = await fetch('/smartgrid_energy_api/clients/delete_client.php', { method: 'POST', body: form });
  const text = await response.text();
  clientFormMsg.textContent = text;
  if (response.ok) {
    resetClientForm();
    loadClients();
  }
}

async function loadClients() {
  const [data, meters] = await Promise.all([
    fetchJson('/smartgrid_energy_api/clients/read_clients.php'),
    fetchJson('/smartgrid_energy_api/compteurs/read_compteurs.php')
  ]);
  if (Array.isArray(data)) {
    const meterRows = Array.isArray(meters) ? meters : [];
    currentClients = data.slice().reverse().map((client) => {
      const linkedMeters = meterRows.filter((meter) => String(meter.id_client) === String(client.id));
      return {
        ...client,
        total_compteurs: linkedMeters.length,
        compteurs: linkedMeters.map((meter) => meter.numero_compteur).join(', ')
      };
    });
    renderClients(currentClients);
  }
}

document.getElementById('reload-clients').addEventListener('click', loadClients);
clientSearch.addEventListener('input', () => renderClients(currentClients));
cancelEditBtn.addEventListener('click', resetClientForm);
document.getElementById('clients-table-body').addEventListener('click', (event) => {
  const editButton = event.target.closest('.edit-client-btn');
  const deleteButton = event.target.closest('.delete-client-btn');
  if (editButton) editClient(editButton.dataset.id);
  if (deleteButton) deleteClient(deleteButton.dataset.id, deleteButton.dataset.name || 'ce client');
});
clientForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(event.target);
  const isEdit = Boolean(form.get('id'));
  const endpoint = isEdit ? '/smartgrid_energy_api/clients/update_client.php' : '/smartgrid_energy_api/clients/create_client.php';
  const response = await fetch(endpoint, { method: 'POST', body: form });
  const text = await response.text();
  clientFormMsg.textContent = text;
  if (response.ok) {
    resetClientForm();
    loadClients();
  }
});
</script>
</body>
</html>
