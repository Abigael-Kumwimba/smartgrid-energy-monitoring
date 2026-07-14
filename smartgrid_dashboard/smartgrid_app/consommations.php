<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$pageTitle = 'Historique des consommations';
$activePage = 'consommations';

$rows = $pdo->query(
    'SELECT c.*, co.numero_compteur, cl.nom AS client_nom
     FROM consommations c
     LEFT JOIN compteurs co ON co.id = c.id_compteur
     LEFT JOIN clients cl ON cl.id = co.id_client
     ORDER BY c.date_mesure DESC
     LIMIT 200'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<article class="card">
    <h2>Dernieres mesures recues</h2>
    <p class="muted">Cette page permet de verifier que les donnees de l'ESP32 arrivent bien dans la base MySQL.</p>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Client</th>
                <th>Compteur</th>
                <th>Tension</th>
                <th>Courant</th>
                <th>Puissance</th>
                <th>Energie</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['date_mesure']); ?></td>
                    <td><?= e($row['client_nom']); ?></td>
                    <td><?= e($row['numero_compteur']); ?></td>
                    <td><?= number_value($row['tension'], 1); ?> V</td>
                    <td><?= number_value($row['courant'], 3); ?> A</td>
                    <td><?= number_value($row['puissance'], 1); ?> W</td>
                    <td><?= number_value($row['energie'], 3); ?> kWh</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>

