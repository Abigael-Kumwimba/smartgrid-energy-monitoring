<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard_data.php';

$user = smartgrid_require_login(['admin']);
$clients = fetch_clients_list();
$factures = fetch_factures_list();
$tarifKwh = (float) fetch_setting_value('tarif_kwh_residentiel', '0.15');
$facturesByClient = [];
foreach ($factures as $facture) {
    $clientKey = (string) ($facture['id_client'] ?? '0');
    if (!isset($facturesByClient[$clientKey])) {
        $facturesByClient[$clientKey] = [
            'id_client' => $facture['id_client'] ?? null,
            'client_nom' => $facture['client_nom'] ?? 'Client non renseigne',
            'total' => 0,
            'non_payees' => 0,
        ];
    }
    $facturesByClient[$clientKey]['total']++;
    if (($facture['statut'] ?? '') !== 'payee') {
        $facturesByClient[$clientKey]['non_payees']++;
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Factures SmartGrid</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
</head>
<body class="sg-admin">
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Gestion des factures</h1>
      <div class="muted">Generation, consultation, impression et export PDF</div>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-light" href="/smartgrid_dashboard/admin/index.php">
        <i class="bi bi-grid me-1"></i> Dashboard
      </a>
      <a class="btn btn-outline-light" href="/smartgrid_dashboard/logout.php">
        <i class="bi bi-box-arrow-right me-1"></i> Deconnexion
      </a>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="panel h-100">
        <h2 class="h5 mb-3">Nouvelle facture</h2>
        <div class="soft-card mb-3">
          <div class="small muted">
            La facture est calculee automatiquement selon la consommation mesuree et le tarif par kWh.
          </div>
        </div>
        <div class="soft-card mb-3">
          <div class="d-flex justify-content-between align-items-center gap-2">
            <div>
              <div class="small muted">Tarif residentiel configure</div>
              <div class="fw-bold"><span id="current-tarif"><?= number_format($tarifKwh, 4, '.', '') ?></span> USD / kWh</div>
            </div>
            <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#tarif-settings">Modifier</button>
          </div>
          <div class="collapse mt-3" id="tarif-settings">
            <form id="tarif-form" class="d-flex gap-2">
              <input class="form-control" name="tarif_kwh" type="number" step="0.0001" min="0.0001" value="<?= number_format($tarifKwh, 4, '.', '') ?>" required>
              <button class="btn btn-primary" type="submit">Enregistrer</button>
            </form>
            <div id="tarif-form-msg" class="small mt-2 muted"></div>
          </div>
        </div>
        <div id="facture-form-msg" class="small mb-3"></div>
        <form id="facture-form" class="d-grid gap-3">
          <select class="form-select" name="id_client" required>
            <?php foreach ($clients as $client): ?>
              <option value="<?= (int) $client['id'] ?>">
                <?= htmlspecialchars($client['nom']) ?> (#<?= (int) $client['id'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <div class="row g-2">
            <div class="col-6">
              <label class="small muted mb-1" for="date_debut">Debut</label>
              <input id="date_debut" class="form-control" type="date" name="date_debut">
            </div>
            <div class="col-6">
              <label class="small muted mb-1" for="date_fin">Fin</label>
              <input id="date_fin" class="form-control" type="date" name="date_fin">
            </div>
          </div>
          <div>
            <label class="small muted mb-1" for="tarif_kwh">Tarif applique par kWh</label>
            <input id="tarif_kwh" class="form-control" name="tarif_kwh" type="number" step="0.0001" min="0.0001" value="<?= number_format($tarifKwh, 4, '.', '') ?>" required>
          </div>
          <div class="d-grid gap-2">
            <button class="btn btn-outline-primary" id="preview-facture" type="button">
              <i class="bi bi-eye me-1"></i> Apercu du calcul
            </button>
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-calculator me-1"></i> Generer la facture
            </button>
          </div>
        </form>
        <div class="soft-card mt-3 d-none" id="billing-preview">
          <div class="fw-bold mb-2">Detail du calcul</div>
          <div id="billing-preview-content"></div>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h2 class="h5 mb-0">Liste des factures</h2>
            <div class="muted small">Chaque facture peut etre ouverte, imprimee ou enregistree en PDF.</div>
          </div>
          <button class="btn btn-outline-light btn-sm" id="reload-factures" type="button">
            <i class="bi bi-arrow-clockwise me-1"></i> Actualiser
          </button>
        </div>
        <div id="overdue-process-msg" class="alert alert-light border d-none mb-3"></div>

        <div class="soft-card mb-3">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <div class="fw-bold">Factures par client</div>
              <div class="muted small">Filtrer les factures pour suivre une periode, une energie consommee et un statut client.</div>
            </div>
            <select class="form-select" id="invoice-client-filter" style="max-width:280px">
              <option value="">Tous les clients</option>
              <?php foreach ($facturesByClient as $clientRow): ?>
                <option value="<?= htmlspecialchars((string) ($clientRow['id_client'] ?? '0')) ?>">
                  <?= htmlspecialchars($clientRow['client_nom'] ?? '-') ?> - <?= (int) $clientRow['total'] ?> facture(s)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
            <tr>
              <th>Client</th>
              <th>Periode</th>
              <th>Energie consommee</th>
              <th>Tarif</th>
              <th>Montant</th>
              <th>Statut</th>
              <th>Echeance</th>
              <th>Action</th>
            </tr>
            </thead>
            <tbody id="factures-table-body">
            <?php foreach ($factures as $facture): ?>
              <tr data-client="<?= htmlspecialchars((string) ($facture['id_client'] ?? '0')) ?>">
                <td><?= htmlspecialchars($facture['client_nom'] ?? '-') ?></td>
                <td>
                  <?= htmlspecialchars(($facture['periode_debut'] ?? '-') . ' au ' . ($facture['periode_fin'] ?? '-')) ?>
                  <div class="muted small">Facture #<?= (int) $facture['id'] ?> - <?= htmlspecialchars($facture['date_facture']) ?></div>
                </td>
                <td><?= $facture['energie_totale'] !== null ? number_format((float) $facture['energie_totale'], 2) . ' kWh' : '-' ?></td>
                <td><?= $facture['tarif_kwh'] !== null ? number_format((float) $facture['tarif_kwh'], 4) . ' USD/kWh' : '-' ?></td>
                <td>$<?= number_format((float) $facture['montant'], 2) ?></td>
                <td>
                  <?php if ($facture['statut'] === 'payee'): ?>
                    <span class="badge text-bg-success">Payee</span>
                  <?php elseif ((int)($facture['en_retard'] ?? 0) === 1): ?>
                    <span class="badge text-bg-danger">En retard</span>
                  <?php else: ?>
                    <span class="badge text-bg-warning">En attente</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($facture['date_echeance'] ?? '-') ?></td>
                <td>
                  <div class="d-flex gap-1 flex-wrap">
                    <a class="btn btn-sm btn-primary" href="/smartgrid_dashboard/smartgrid_app/facture_detail.php?id=<?= (int) $facture['id'] ?>">
                      <i class="bi bi-printer me-1"></i> Voir
                    </a>
                    <?php if ($facture['statut'] !== 'payee'): ?>
                      <button class="btn btn-sm btn-outline-warning reminder-btn" type="button" data-id="<?= (int) $facture['id'] ?>">
                        <i class="bi bi-send me-1"></i> Relancer
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="/AdminLTE-4.0.0-rc7/dist/js/adminlte.min.js"></script>
<script>
async function fetchJson(url, options = {}) {
  const response = await fetch(url, { headers: { 'Accept': 'application/json' }, ...options });
  return response.json();
}

function esc(value) {
  return String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#039;',
    '"': '&quot;'
  }[char]));
}

function renderFactures(factures, clientMap) {
  const rows = factures.map((facture) => {
    const id = Number(facture.id || 0);
    const client = clientMap[String(facture.id_client)] || facture.client_nom || facture.id_client || '-';
    const energie = facture.energie_totale !== null && facture.energie_totale !== undefined
      ? `${Number(facture.energie_totale).toFixed(2)} kWh`
      : '-';
    const period = `${facture.periode_debut || '-'} au ${facture.periode_fin || '-'}`;
    const dueDate = facture.date_echeance || '-';
    const overdue = facture.statut !== 'payee'
      && dueDate !== '-'
      && new Date(dueDate.replace(' ', 'T')).getTime() < Date.now();
    const badge = facture.statut === 'payee'
      ? 'text-bg-success'
      : (overdue ? 'text-bg-danger' : 'text-bg-warning');
    const statusLabel = facture.statut === 'payee'
      ? 'Payee'
      : (overdue ? 'En retard' : 'En attente');

    return `
      <tr data-client="${esc(facture.id_client || '0')}">
        <td>${client}</td>
        <td>${period}<div class="muted small">Facture #${id} - ${facture.date_facture || ''}</div></td>
        <td>${energie}</td>
        <td>${facture.tarif_kwh ? Number(facture.tarif_kwh).toFixed(4) + ' USD/kWh' : '-'}</td>
        <td>$${Number(facture.montant || 0).toFixed(2)}</td>
        <td><span class="badge ${badge}">${statusLabel}</span></td>
        <td>${dueDate}</td>
        <td>
          <div class="d-flex gap-1 flex-wrap">
            <a class="btn btn-sm btn-primary" href="/smartgrid_dashboard/smartgrid_app/facture_detail.php?id=${id}">
              <i class="bi bi-printer me-1"></i> Voir
            </a>
            ${facture.statut !== 'payee' ? `<button class="btn btn-sm btn-outline-warning reminder-btn" type="button" data-id="${id}"><i class="bi bi-send me-1"></i> Relancer</button>` : ''}
          </div>
        </td>
      </tr>
    `;
  }).join('');

  document.getElementById('factures-table-body').innerHTML = rows;
  filterInvoices();
}

async function loadFactures() {
  const [factures, clients] = await Promise.all([
    fetchJson('/smartgrid_energy_api/factures/read_factures.php'),
    fetchJson('/smartgrid_energy_api/clients/read_clients.php')
  ]);
  const clientMap = Object.fromEntries(clients.map((client) => [String(client.id), client.nom]));
  renderFactures(factures, clientMap);
}

document.getElementById('reload-factures').addEventListener('click', loadFactures);
document.getElementById('invoice-client-filter').addEventListener('change', filterInvoices);
function filterInvoices() {
  const selectedClient = document.getElementById('invoice-client-filter')?.value || '';
  document.querySelectorAll('#factures-table-body tr').forEach((row) => {
    row.style.display = !selectedClient || row.dataset.client === selectedClient ? '' : 'none';
  });
}
document.getElementById('preview-facture').addEventListener('click', async () => {
  const formElement = document.getElementById('facture-form');
  const form = new FormData(formElement);
  const preview = document.getElementById('billing-preview');
  const content = document.getElementById('billing-preview-content');
  const button = document.getElementById('preview-facture');

  button.disabled = true;
  content.textContent = 'Calcul en cours...';
  preview.classList.remove('d-none');

  try {
    const response = await fetch('/smartgrid_energy_api/factures/preview_facture.php', {
      method: 'POST',
      body: form
    });
    const data = await response.json();

    if (data.status !== 'success') {
      content.textContent = data.message || 'Apercu indisponible';
      return;
    }

    const meterRows = (data.compteurs || []).map((meter) => `
      <tr>
        <td>${esc(meter.numero_compteur || '-')}</td>
        <td>${meter.nb_mesures || 0}</td>
        <td>${meter.energie_debut ?? '-'}</td>
        <td>${meter.energie_fin ?? '-'}</td>
        <td class="fw-bold">${Number(meter.energie_facturable || 0).toFixed(3)} kWh</td>
      </tr>
    `).join('');

    content.innerHTML = `
      <div class="small mb-2"><strong>Client :</strong> ${esc(data.client?.nom || '-')}</div>
      <div class="small mb-3"><strong>Periode :</strong> ${esc(data.periode_debut || 'debut des mesures')} au ${esc(data.periode_fin || 'derniere mesure')}</div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-2">
          <thead><tr><th>Compteur</th><th>Mesures</th><th>Debut</th><th>Fin</th><th>Consommee</th></tr></thead>
          <tbody>${meterRows || '<tr><td colspan="5">Aucun compteur</td></tr>'}</tbody>
        </table>
      </div>
      <div class="d-flex justify-content-between"><span>Energie totale</span><strong>${Number(data.energie_totale || 0).toFixed(3)} kWh</strong></div>
      <div class="d-flex justify-content-between"><span>Tarif</span><strong>${Number(data.tarif_kwh || 0).toFixed(4)} USD/kWh</strong></div>
      <hr>
      <div class="d-flex justify-content-between fs-5"><span>Montant</span><strong>$${Number(data.montant || 0).toFixed(2)}</strong></div>
    `;
  } catch (error) {
    content.textContent = 'Impossible de calculer l apercu.';
  } finally {
    button.disabled = false;
  }
});
document.getElementById('factures-table-body').addEventListener('click', async (event) => {
  const button = event.target.closest('.reminder-btn');
  if (!button) return;

  button.disabled = true;
  const form = new FormData();
  form.append('id_facture', button.dataset.id);
  const response = await fetch('/smartgrid_dashboard/smartgrid_app/api/send_unpaid_reminder.php', {
    method: 'POST',
    body: form
  });
  const data = await response.json();
  document.getElementById('overdue-process-msg').classList.remove('d-none');
  document.getElementById('overdue-process-msg').textContent = data.message || 'Traitement termine';
  button.disabled = false;
});

async function processOverdueInvoices() {
  const box = document.getElementById('overdue-process-msg');
  try {
    const response = await fetch('/smartgrid_dashboard/smartgrid_app/api/process_overdue_invoices.php');
    const data = await response.json();
    if (Number(data.overdue_invoices || 0) > 0) {
      box.classList.remove('d-none');
      box.textContent = `${data.overdue_invoices} facture(s) en retard, ${data.suspended_clients} client(s) suspendu(s), ${data.telegram_notifications} notification(s) Telegram envoyee(s).`;
    }
  } catch (error) {
    console.error('Traitement des impayes impossible', error);
  }
}
document.getElementById('tarif-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(event.target);
  const response = await fetch('/smartgrid_dashboard/smartgrid_app/api/update_tarif.php', {
    method: 'POST',
    body: form
  });
  const data = await response.json();
  const messageBox = document.getElementById('tarif-form-msg');
  messageBox.textContent = data.message || 'Tarif mis a jour';
  if (data.status === 'success') {
    const tarif = Number(data.tarif_kwh || 0).toFixed(4);
    document.getElementById('current-tarif').textContent = tarif;
    document.getElementById('tarif_kwh').value = tarif;
  }
});
document.getElementById('facture-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(event.target);
  const response = await fetch('/smartgrid_energy_api/factures/generate_facture.php', {
    method: 'POST',
    body: form
  });
  const data = await response.json();
  const messageBox = document.getElementById('facture-form-msg');
  if (data.status === 'success') {
    messageBox.textContent = `${data.message} : ${Number(data.energie_totale || 0).toFixed(3)} kWh x ${Number(data.tarif_kwh || 0).toFixed(2)} = $${Number(data.montant || 0).toFixed(2)}`;
    event.target.reset();
    loadFactures();
  } else {
    messageBox.textContent = data.message || 'Impossible de generer la facture';
  }
});
function setDefaultMonthlyPeriod() {
  const startInput = document.getElementById('date_debut');
  const endInput = document.getElementById('date_fin');
  if (!startInput.value || !endInput.value) {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    const toDateValue = (date) => date.toISOString().slice(0, 10);
    startInput.value = toDateValue(firstDay);
    endInput.value = toDateValue(lastDay);
  }
}
setDefaultMonthlyPeriod();
processOverdueInvoices().then(loadFactures);
</script>
</body>
</html>
