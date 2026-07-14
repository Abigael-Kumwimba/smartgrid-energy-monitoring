<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../smartgrid_app/config/database.php';
require_once __DIR__ . '/../smartgrid_app/includes/helpers.php';

$user = smartgrid_require_login(['client']);
$idFacture = (int) ($_GET['id'] ?? $_POST['id_facture'] ?? 0);
$clientId = (int) ($user['client_id'] ?? 0);
$paymentMessage = null;

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $idFacture > 0) {
    $conditions = ['id = ?'];
    $params = [$idFacture];

    $conditions[] = 'id_client = ?';
    $params[] = $clientId;

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare(
            'UPDATE factures
             SET statut = "payee", date_paiement = NOW()
             WHERE ' . implode(' AND ', $conditions) . ' AND statut <> "payee"'
        );
        $update->execute($params);

        if ($update->rowCount() > 0) {
            $remaining = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM factures
                 WHERE id_client = ?
                   AND statut = "non_payee"
                   AND date_echeance IS NOT NULL
                   AND date_echeance < NOW()'
            );
            $remaining->execute([$clientId]);

            if ((int) $remaining->fetchColumn() === 0) {
                $reactivate = $pdo->prepare(
                    'UPDATE compteurs SET statut = "actif" WHERE id_client = ?'
                );
                $reactivate->execute([$clientId]);
            }

            $paymentMessage = 'Paiement valide avec succes. Les compteurs sont reactives si aucun autre impaye n existe.';
        } else {
            $paymentMessage = 'Cette facture est deja payee ou introuvable.';
        }

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

$sql = 'SELECT f.*, cl.nom AS client_nom, cl.telephone, cl.adresse
        FROM factures f
        LEFT JOIN clients cl ON cl.id = f.id_client
        WHERE f.id = ?';
$params = [$idFacture];

$sql .= ' AND f.id_client = ?';
$params[] = $clientId;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$facture = $stmt->fetch();

if (!$facture) {
    http_response_code(404);
    echo 'Facture introuvable pour ce compte.';
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Paiement facture SmartGrid</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
  <style>
    body.sg-client{background:linear-gradient(135deg,#eef5ff 0%,#f8e9ff 48%,#fff7dd 100%)}.payment-shell{max-width:1180px;margin:0 auto;padding:32px}.page-kicker{display:inline-flex;align-items:center;gap:8px;background:rgba(86,72,255,.1);color:#5140d8;border-radius:999px;padding:8px 14px;font-weight:700}.invoice-preview,.payment-card{background:rgba(255,255,255,.92);border:1px solid rgba(28,37,64,.08);border-radius:30px;box-shadow:0 24px 60px rgba(79,96,140,.17);overflow:hidden}.invoice-top{background:linear-gradient(135deg,#102351,#4f39d5 58%,#12b886);color:#fff;padding:28px}.invoice-body{padding:26px}.invoice-total{background:#f5f7ff;border:1px solid rgba(28,37,64,.06);border-radius:22px;padding:18px}.status-pill{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:8px 14px;font-weight:800}.status-paid{background:#dff8ec;color:#107a43}.status-unpaid{background:#fff2c7;color:#8a5a00}.detail-row{display:flex;justify-content:space-between;gap:18px;padding:12px 0;border-bottom:1px dashed rgba(28,37,64,.12)}.detail-row:last-child{border-bottom:0}.payment-card{padding:28px}.method-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.method-tab,.provider-card{position:relative;display:flex;align-items:center;gap:12px;border:1px solid rgba(28,37,64,.1);border-radius:20px;padding:15px;background:#fff;cursor:pointer;transition:.18s ease}.method-tab:hover,.provider-card:hover{transform:translateY(-1px);box-shadow:0 14px 30px rgba(79,96,140,.12)}.method-tab input,.provider-card input{position:absolute;opacity:0}.method-tab:has(input:checked),.provider-card:has(input:checked){border-color:#5648ff;background:linear-gradient(135deg,rgba(86,72,255,.1),rgba(41,211,145,.08));box-shadow:0 16px 34px rgba(86,72,255,.14)}.method-icon,.provider-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:15px;background:#eef2ff;color:#5648ff;font-size:1.2rem}.provider-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.provider-logo{font-weight:900;letter-spacing:.02em}.airtel{color:#e10000}.orange{color:#ff7900}.mpesa{color:#17a65b}.visa{color:#1434cb}.mastercard{color:#eb001b}.unionpay{color:#0073cf}.qc{color:#5b2bd6}.pay-summary{background:linear-gradient(135deg,#17203f,#263b73);color:#fff;border-radius:24px;padding:20px}.pay-button{border:0;border-radius:18px;padding:14px 22px;font-weight:800;background:linear-gradient(135deg,#5648ff,#12b886);box-shadow:0 18px 34px rgba(86,72,255,.28)}@media(max-width:575px){.method-tabs,.provider-grid{grid-template-columns:1fr}.payment-shell{padding:20px}}@media print{.no-print{display:none!important}.payment-shell{padding:0}.invoice-preview,.payment-card{box-shadow:none;border:0}}
  </style>
</head>
<body class="sg-client">
<main class="payment-shell">
  <div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
      <div class="page-kicker mb-2"><i class="bi bi-shield-check"></i> Paiement securise</div>
      <h1 class="h3 mb-1">Paiement de facture</h1>
      <div class="muted">Choisissez un moyen de paiement et validez votre facture</div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-dark" href="/smartgrid_dashboard/client/factures.php">Mes factures</a>
      <a class="btn btn-outline-dark" href="/smartgrid_dashboard/client/index.php">Dashboard</a>
    </div>
  </div>

  <?php if ($paymentMessage): ?>
    <div class="alert alert-info no-print"><?= e($paymentMessage) ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-5">
      <section class="invoice-preview h-100">
        <div class="invoice-top">
          <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
              <div class="opacity-75">SmartGrid Energy</div>
              <h2 class="h4 fw-bold mb-0">Facture #<?= (int) $facture['id'] ?></h2>
            </div>
            <span class="status-pill <?= $facture['statut'] === 'payee' ? 'status-paid' : 'status-unpaid' ?>">
              <i class="bi <?= $facture['statut'] === 'payee' ? 'bi-check-circle' : 'bi-clock' ?>"></i>
              <?= e($facture['statut']) ?>
            </span>
          </div>
          <div class="opacity-75">Montant a payer</div>
          <div class="display-5 fw-bold"><?= money($facture['montant']) ?></div>
        </div>
        <div class="invoice-body">
          <div class="invoice-total mb-3">
            <div class="detail-row"><span class="muted">Client</span><strong><?= e($facture['client_nom']) ?></strong></div>
            <div class="detail-row"><span class="muted">Telephone</span><strong><?= e($facture['telephone']) ?></strong></div>
            <div class="detail-row"><span class="muted">Energie</span><strong><?= number_value($facture['energie_totale'], 3) ?> kWh</strong></div>
            <div class="detail-row"><span class="muted">Date</span><strong><?= e($facture['date_facture']) ?></strong></div>
          </div>
          <div class="muted small"><?= e($facture['adresse']) ?></div>
        </div>
      </section>
    </div>

    <div class="col-lg-7">
      <section class="payment-card">
        <?php if ($facture['statut'] === 'payee'): ?>
          <h2 class="h5 mb-3">Recu de paiement</h2>
          <div class="alert alert-success">Cette facture est deja payee.</div>
          <button class="btn btn-outline-dark no-print" type="button" onclick="window.print()">Imprimer le recu</button>
        <?php else: ?>
          <form method="post" class="no-print">
            <input type="hidden" name="id_facture" value="<?= (int) $facture['id'] ?>">
            <h2 class="h5 mb-3">Mode de paiement</h2>
            <div class="method-tabs mb-4">
              <label class="method-tab">
                <input type="radio" name="method" value="mobile_money" checked data-panel="mobile-panel">
                <span class="method-icon"><i class="bi bi-phone"></i></span>
                <span><strong>Mobile Money</strong><br><small class="muted">Paiement par telephone</small></span>
              </label>
              <label class="method-tab">
                <input type="radio" name="method" value="card" data-panel="card-panel">
                <span class="method-icon"><i class="bi bi-credit-card"></i></span>
                <span><strong>Carte bancaire</strong><br><small class="muted">Paiement par carte</small></span>
              </label>
            </div>

            <div id="mobile-panel" class="payment-panel mb-4">
              <div class="muted small mb-2">Operateur Mobile Money</div>
              <div class="provider-grid">
                <label class="provider-card"><input type="radio" name="provider_mobile" value="Airtel Money" checked><span class="provider-icon"><i class="bi bi-phone-fill"></i></span><span class="provider-logo airtel">Airtel Money</span></label>
                <label class="provider-card"><input type="radio" name="provider_mobile" value="Orange Money"><span class="provider-icon"><i class="bi bi-phone-fill"></i></span><span class="provider-logo orange">Orange Money</span></label>
                <label class="provider-card"><input type="radio" name="provider_mobile" value="M-Pesa"><span class="provider-icon"><i class="bi bi-phone-fill"></i></span><span class="provider-logo mpesa">M-Pesa</span></label>
                <label class="provider-card"><input type="radio" name="provider_mobile" value="Autre Mobile Money"><span class="provider-icon"><i class="bi bi-plus-circle"></i></span><span class="provider-logo">Autre</span></label>
              </div>
            </div>

            <div id="card-panel" class="payment-panel mb-4 d-none">
              <div class="muted small mb-2">Reseau de carte</div>
              <div class="provider-grid">
                <label class="provider-card"><input type="radio" name="provider_card" value="Visa" checked><span class="provider-icon"><i class="bi bi-credit-card-2-front"></i></span><span class="provider-logo visa">Visa</span></label>
                <label class="provider-card"><input type="radio" name="provider_card" value="Mastercard"><span class="provider-icon"><i class="bi bi-credit-card-2-front"></i></span><span class="provider-logo mastercard">Mastercard</span></label>
                <label class="provider-card"><input type="radio" name="provider_card" value="UnionPay"><span class="provider-icon"><i class="bi bi-credit-card-2-front"></i></span><span class="provider-logo unionpay">UnionPay</span></label>
                <label class="provider-card"><input type="radio" name="provider_card" value="QC"><span class="provider-icon"><i class="bi bi-credit-card-2-front"></i></span><span class="provider-logo qc">QC</span></label>
              </div>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label muted">Reference de paiement</label>
                <input class="form-control" value="PAY-SG-<?= (int) $facture['id'] ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label muted">Montant a payer</label>
                <input class="form-control" value="<?= money($facture['montant']) ?>" readonly>
              </div>
            </div>

            <div class="pay-summary d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
              <div>
                <div class="opacity-75 small">Total</div>
                <div class="fs-3 fw-bold"><?= money($facture['montant']) ?></div>
              </div>
              <div class="text-end">
                <div class="opacity-75 small">Facture</div>
                <div class="fw-bold">#<?= (int) $facture['id'] ?></div>
              </div>
            </div>

            <button class="btn btn-primary btn-lg pay-button" type="submit">
              <i class="bi bi-check2-circle me-1"></i> Payer
            </button>
          </form>
        <?php endif; ?>
      </section>
    </div>
  </div>
</main>
<script>
document.querySelectorAll('input[name="method"]').forEach((input) => {
  input.addEventListener('change', () => {
    document.querySelectorAll('.payment-panel').forEach((panel) => panel.classList.add('d-none'));
    document.getElementById(input.dataset.panel)?.classList.remove('d-none');
  });
});
</script>
</body>
</html>
