<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$pageTitle = 'Alertes et anomalies';
$activePage = 'alertes';

$alertes = $pdo->query(
    'SELECT a.*, co.numero_compteur
     FROM alertes a
     LEFT JOIN compteurs co ON co.id = a.id_compteur
     ORDER BY a.date_alerte DESC
     LIMIT 100'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<article class="card">
    <h2>Alertes et anomalies</h2>
    <p class="muted">Cette page liste les anomalies detectees automatiquement par l'API a partir des seuils electriques.</p>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Compteur</th>
                <th>Niveau</th>
                <th>Message</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($alertes as $alerte): ?>
                <tr>
                    <td><?= e($alerte['date_alerte']); ?></td>
                    <td><?= e($alerte['numero_compteur']); ?></td>
                    <td class="level-<?= e($alerte['niveau']); ?>"><?= e($alerte['niveau']); ?></td>
                    <td><?= e($alerte['message']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
