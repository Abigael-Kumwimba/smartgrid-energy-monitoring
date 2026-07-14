<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$pageTitle = 'Facturation';
$activePage = 'factures';
$message = '';
$tarifKwh = 0.15;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $clientId = (int) ($_POST['id_client'] ?? 0);
    $tarifKwh = (float) ($_POST['tarif_kwh'] ?? $tarifKwh);

    $stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(c.energie), 0) AS energie_totale
         FROM consommations c
         INNER JOIN compteurs co ON co.id = c.id_compteur
         WHERE co.id_client = ?'
    );
    $stmt->execute([$clientId]);
    $energieTotale = (float) $stmt->fetchColumn();
    $montant = $energieTotale * $tarifKwh;

    $insert = $pdo->prepare(
        "INSERT INTO factures (id_client, energie_totale, montant, statut)
         VALUES (?, ?, ?, 'non_payee')"
    );
    $insert->execute([$clientId, $energieTotale, $montant]);
    $message = 'Facture generee avec succes.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    $stmt = $pdo->prepare("UPDATE factures SET statut = 'payee' WHERE id = ?");
    $stmt->execute([(int) ($_POST['id_facture'] ?? 0)]);
    $message = 'Facture marquee comme payee.';
}

$clients = $pdo->query('SELECT id, nom FROM clients ORDER BY nom')->fetchAll();
$factures = $pdo->query(
    'SELECT f.*, cl.nom AS client_nom
     FROM factures f
     LEFT JOIN clients cl ON cl.id = f.id_client
     ORDER BY f.date_facture DESC'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="notice"><?= e($message); ?></div><?php endif; ?>

<section class="grid two-cols">
    <article class="card">
        <h2>Generer une facture</h2>
        <form class="form" method="post">
            <input type="hidden" name="action" value="generate">
            <label>Client
                <select name="id_client" required>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id']; ?>"><?= e($client['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Tarif par kWh
                <input type="number" name="tarif_kwh" step="0.01" value="<?= e((string) $tarifKwh); ?>">
            </label>
            <button class="btn" type="submit">Generer la facture</button>
        </form>
        <p class="muted">Pour le prototype, le paiement est simule par changement de statut.</p>
    </article>

    <article class="card">
        <h2>Liste des factures</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Energie</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($factures as $facture): ?>
                    <tr>
                        <td><?= e($facture['date_facture']); ?></td>
                        <td><?= e($facture['client_nom']); ?></td>
                        <td><?= number_value($facture['energie_totale'], 3); ?> kWh</td>
                        <td><?= money($facture['montant']); ?></td>
                        <td><span class="badge"><?= e($facture['statut']); ?></span></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-secondary" href="facture_detail.php?id=<?= (int) $facture['id']; ?>">
                                    Voir / imprimer
                                </a>
                            <?php if ($facture['statut'] === 'non_payee'): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="pay">
                                    <input type="hidden" name="id_facture" value="<?= (int) $facture['id']; ?>">
                                    <button class="btn btn-secondary" type="submit">Marquer payee</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">OK</span>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
