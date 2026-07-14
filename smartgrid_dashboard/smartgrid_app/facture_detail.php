<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$idFacture = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT f.*, cl.nom AS client_nom, cl.telephone, cl.telegram_chat_id, cl.adresse
     FROM factures f
     LEFT JOIN clients cl ON cl.id = f.id_client
     WHERE f.id = ?'
);
$stmt->execute([$idFacture]);
$facture = $stmt->fetch();

if (!$facture) {
    http_response_code(404);
    echo 'Facture introuvable.';
    exit;
}

$compteursStmt = $pdo->prepare(
    'SELECT numero_compteur
     FROM compteurs
     WHERE id_client = ?
     ORDER BY id'
);
$compteursStmt->execute([(int) $facture['id_client']]);
$compteurs = $compteursStmt->fetchAll();

$tarifEstime = $facture['tarif_kwh'] !== null
    ? (float) $facture['tarif_kwh']
    : (((float) $facture['energie_totale'] > 0)
        ? (float) $facture['montant'] / (float) $facture['energie_totale']
        : 0.0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture #<?= (int) $facture['id']; ?></title>
    <link rel="stylesheet" href="/smartgrid_dashboard/smartgrid_app/assets/css/theme.css">
    <style>
        @media print {
            .invoice-actions { display: none !important; }
            body { background: #fff !important; }
            .invoice-sheet { box-shadow: none !important; border: 0 !important; }
        }
    </style>
</head>
<body class="invoice-body">
<main class="invoice-page">
    <?php if (isset($_GET['telegram'])): ?>
        <div class="invoice-actions">
            <div class="btn <?= $_GET['telegram'] === 'ok' ? '' : 'btn-secondary' ?>">
                <?= $_GET['telegram'] === 'ok'
                    ? 'Facture envoyee au client via Telegram.'
                    : 'Echec de l\'envoi Telegram. Verifiez le chat ID du client.' ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="invoice-actions">
        <button class="btn btn-secondary" type="button" onclick="history.back()">Retour</button>
        <button class="btn" type="button" onclick="window.print()">Imprimer</button>
        <button class="btn" type="button" onclick="window.print()">Telecharger en PDF</button>
        <form method="post" action="/smartgrid_dashboard/smartgrid_app/api/send_facture_telegram.php" style="display:inline">
            <input type="hidden" name="id_facture" value="<?= (int) $facture['id']; ?>">
            <button class="btn" type="submit" <?= empty($facture['telegram_chat_id']) ? 'disabled' : '' ?>>
                Envoyer au client via Telegram
            </button>
        </form>
    </div>

    <section class="invoice-sheet">
        <header class="invoice-header">
            <div>
                <p class="eyebrow">SmartGrid Energy Meter</p>
                <h1>Facture d'electricite</h1>
                <p class="muted">Prototype IoT de suivi de consommation electrique</p>
            </div>
            <div class="invoice-number">
                <strong>Facture #<?= (int) $facture['id']; ?></strong>
                <span><?= e($facture['date_facture']); ?></span>
            </div>
        </header>

        <section class="invoice-grid">
            <article>
                <h2>Client</h2>
                <p><strong><?= e($facture['client_nom']); ?></strong></p>
                <p><?= e($facture['telephone']); ?></p>
                <p>Telegram : <?= !empty($facture['telegram_chat_id']) ? e($facture['telegram_chat_id']) : 'Non renseigne'; ?></p>
                <p><?= e($facture['adresse']); ?></p>
            </article>
            <article>
                <h2>Compteur(s)</h2>
                <?php if ($compteurs): ?>
                    <?php foreach ($compteurs as $compteur): ?>
                        <p><?= e($compteur['numero_compteur']); ?></p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="muted">Aucun compteur associe.</p>
                <?php endif; ?>
            </article>
        </section>

        <section class="invoice-grid">
            <article>
                <h2>Periode facturee</h2>
                <p>
                    <?= e($facture['periode_debut'] ?? 'Non precisee'); ?>
                    au
                    <?= e($facture['periode_fin'] ?? 'Non precisee'); ?>
                </p>
            </article>
            <article>
                <h2>Echeance</h2>
                <p><?= e($facture['date_echeance'] ?? 'Non precisee'); ?></p>
            </article>
        </section>

        <table class="invoice-table">
            <thead>
            <tr>
                <th>Description</th>
                <th>Energie</th>
                <th>Tarif estime</th>
                <th>Montant</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>Consommation electrique mesuree</td>
                <td><?= number_value($facture['energie_totale'], 3); ?> kWh</td>
                <td><?= money($tarifEstime); ?> / kWh</td>
                <td><?= money($facture['montant']); ?></td>
            </tr>
            </tbody>
        </table>

        <section class="invoice-total">
            <span>Statut : <strong><?= e($facture['statut']); ?></strong></span>
            <strong>Total : <?= money($facture['montant']); ?></strong>
        </section>

        <footer class="invoice-footer">
            <p>
                Cette facture est generee automatiquement a partir des mesures collectees par le prototype.
                Elle permet au client de consulter le montant associe a sa consommation electrique.
            </p>
        </footer>
    </section>
</main>
</body>
</html>
