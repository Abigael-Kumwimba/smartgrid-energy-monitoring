<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$pageTitle = 'Predictions ML';
$activePage = 'predictions';

$modelResults = [
    [
        'modele' => 'Regression Lineaire',
        'mae' => 0.402920,
        'rmse' => 0.552378,
        'retenu' => true,
    ],
    [
        'modele' => 'Random Forest',
        'mae' => 0.427127,
        'rmse' => 0.590505,
        'retenu' => false,
    ],
];

$predictions = $pdo->query(
    'SELECT p.*, co.numero_compteur
     FROM predictions p
     LEFT JOIN compteurs co ON co.id = p.id_compteur
     ORDER BY p.date_prediction ASC
     LIMIT 100'
)->fetchAll();

$labels = array_map(static fn(array $row): string => (string) $row['date_prediction'], $predictions);
$predictionValues = array_map(static fn(array $row): float => (float) $row['prediction'], $predictions);

require __DIR__ . '/includes/header.php';
?>

<section class="grid stats">
    <article class="card ml-summary-card">
        <span class="muted">Modele retenu</span>
        <p class="stat-value">Regression Lineaire</p>
    </article>
    <article class="card">
        <span class="muted">MAE</span>
        <p class="stat-value">0.402920</p>
    </article>
    <article class="card">
        <span class="muted">RMSE</span>
        <p class="stat-value">0.552378</p>
    </article>
    <article class="card">
        <span class="muted">Source</span>
        <p class="stat-value">Dataset public</p>
    </article>
</section>

<section class="grid dashboard-grid" style="margin-top: 16px;">
    <article class="card">
        <h2>Comparaison des modeles</h2>
        <p class="muted">
            Deux modeles ont ete entraines dans Jupyter. Le choix final repose sur les metriques MAE et RMSE :
            plus les valeurs sont faibles, meilleur est le modele.
        </p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Modele</th>
                    <th>MAE</th>
                    <th>RMSE</th>
                    <th>Decision</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($modelResults as $result): ?>
                    <tr>
                        <td><?= e($result['modele']); ?></td>
                        <td><?= number_value($result['mae'], 6); ?></td>
                        <td><?= number_value($result['rmse'], 6); ?></td>
                        <td>
                            <?php if ($result['retenu']): ?>
                                <span class="badge">Retenu</span>
                            <?php else: ?>
                                <span class="muted">Compare</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="card">
        <h2>Interpretation</h2>
        <p>
            La Regression Lineaire a obtenu une MAE de <strong>0.402920</strong> et une RMSE de
            <strong>0.552378</strong>. Elle est donc retenue comme modele de prediction dans cette
            experimentation.
        </p>
        <p class="muted">
            Pour rester honnete scientifiquement, le modele est d'abord valide sur un dataset public reel.
            Les donnees collectees par le prototype serviront ensuite a adapter progressivement le modele au contexte local.
        </p>
    </article>
</section>

<section class="grid dashboard-grid" style="margin-top: 16px;">
    <article class="card chart-card">
        <div class="card-title-row">
            <div>
                <h2>Courbe des predictions</h2>
                <p class="muted">Predictions enregistrees dans la base de donnees.</p>
            </div>
            <span class="badge">ML</span>
        </div>
        <canvas id="predictionChart" height="120"></canvas>
    </article>

    <article class="card">
        <h2>Predictions en base</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Compteur</th>
                    <th>Prediction</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (array_reverse($predictions) as $prediction): ?>
                    <tr>
                        <td><?= e($prediction['date_prediction']); ?></td>
                        <td><?= e($prediction['numero_compteur']); ?></td>
                        <td><?= number_value($prediction['prediction'], 3); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<article class="card" style="margin-top: 16px;">
    <h2>Import des predictions</h2>
    <p>
        Les predictions obtenues apres entrainement du modele peuvent etre ajoutees dans le systeme
        afin d'alimenter la courbe et l'historique ci-dessus.
    </p>
    <a class="btn" href="import_predictions.php">Importer des predictions CSV</a>
</article>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const predictionLabels = <?= json_encode($labels); ?>;
const predictionValues = <?= json_encode($predictionValues); ?>;

new Chart(document.getElementById("predictionChart"), {
    type: "line",
    data: {
        labels: predictionLabels,
        datasets: [{
            label: "Prediction de consommation",
            data: predictionValues,
            borderColor: "#0f766e",
            backgroundColor: "rgba(15, 118, 110, 0.12)",
            tension: 0.35,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { labels: { color: "#667085" } }
        },
        scales: {
            x: { ticks: { color: "#667085", maxRotation: 0 }, grid: { color: "rgba(102, 112, 133, 0.18)" } },
            y: { ticks: { color: "#667085" }, grid: { color: "rgba(102, 112, 133, 0.18)" } }
        }
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
