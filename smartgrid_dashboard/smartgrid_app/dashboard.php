<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$pageTitle = 'Dashboard admin';
$activePage = 'dashboard';

$totalClients = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$totalCompteurs = (int) $pdo->query('SELECT COUNT(*) FROM compteurs')->fetchColumn();
$totalFacturesNonPayees = (int) $pdo->query("SELECT COUNT(*) FROM factures WHERE statut = 'non_payee'")->fetchColumn();
$totalAlertes = (int) $pdo->query('SELECT COUNT(*) FROM alertes')->fetchColumn();

$lastMeasure = $pdo->query(
    'SELECT c.*, co.numero_compteur
     FROM consommations c
     LEFT JOIN compteurs co ON co.id = c.id_compteur
     ORDER BY c.date_mesure DESC
     LIMIT 1'
)->fetch();

$recentMeasures = $pdo->query(
    'SELECT c.*, co.numero_compteur
     FROM consommations c
     LEFT JOIN compteurs co ON co.id = c.id_compteur
     ORDER BY c.date_mesure DESC
     LIMIT 8'
)->fetchAll();

$chartRows = $pdo->query(
    'SELECT c.tension, c.courant, c.puissance, c.energie, c.date_mesure
     FROM consommations c
     ORDER BY c.date_mesure DESC
     LIMIT 30'
)->fetchAll();
$chartRows = array_reverse($chartRows);

$chartLabels = array_map(static fn(array $row): string => date('H:i:s', strtotime((string) $row['date_mesure'])), $chartRows);
$chartVoltage = array_map(static fn(array $row): float => (float) $row['tension'], $chartRows);
$chartCurrent = array_map(static fn(array $row): float => (float) $row['courant'], $chartRows);
$chartPower = array_map(static fn(array $row): float => (float) $row['puissance'], $chartRows);
$chartEnergy = array_map(static fn(array $row): float => (float) $row['energie'], $chartRows);

require __DIR__ . '/includes/header.php';
?>

<section class="grid stats">
    <article class="card">
        <span class="muted">Clients</span>
        <p class="stat-value"><?= $totalClients; ?></p>
    </article>
    <article class="card">
        <span class="muted">Compteurs</span>
        <p class="stat-value"><?= $totalCompteurs; ?></p>
    </article>
    <article class="card">
        <span class="muted">Factures non payees</span>
        <p class="stat-value"><?= $totalFacturesNonPayees; ?></p>
    </article>
    <article class="card">
        <span class="muted">Alertes</span>
        <p class="stat-value"><?= $totalAlertes; ?></p>
    </article>
</section>

<section class="grid dashboard-grid" style="margin-top: 16px;">
    <article class="card chart-card">
        <div class="card-title-row">
            <div>
                <h2>Evolution de la tension</h2>
                <p class="muted">Dernieres mesures recues par le compteur.</p>
            </div>
            <span class="badge">Volt</span>
        </div>
        <canvas id="voltageChart" height="110"></canvas>
    </article>

    <article class="card chart-card">
        <div class="card-title-row">
            <div>
                <h2>Puissance et courant</h2>
                <p class="muted">Suivi instantane des charges branchees.</p>
            </div>
            <span class="badge">Live</span>
        </div>
        <canvas id="powerChart" height="110"></canvas>
    </article>
</section>

<section class="grid dashboard-grid" style="margin-top: 16px;">
    <article class="card chart-card">
        <div class="card-title-row">
            <div>
                <h2>Energie cumulee</h2>
                <p class="muted">Base de calcul de la facturation.</p>
            </div>
            <span class="badge">kWh</span>
        </div>
        <canvas id="energyChart" height="110"></canvas>
    </article>

    <article class="card highlight-card">
        <h2>Etat du systeme</h2>
        <?php if ($lastMeasure): ?>
            <?php if ((float) $lastMeasure['tension'] < 180): ?>
                <p class="system-state warning-state">Sous-tension detectee</p>
                <p class="muted">La tension mesuree est inferieure au seuil de 180 V.</p>
            <?php elseif ((float) $lastMeasure['tension'] > 255): ?>
                <p class="system-state danger-state">Surtension detectee</p>
                <p class="muted">La tension mesuree depasse le seuil de securite.</p>
            <?php else: ?>
                <p class="system-state success-state">Fonctionnement normal</p>
                <p class="muted">Aucune anomalie critique detectee sur la derniere mesure.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="muted">Aucune donnee disponible.</p>
        <?php endif; ?>
    </article>
</section>

<section class="grid two-cols" style="margin-top: 16px;">
    <article class="card">
        <h2>Derniere mesure</h2>
        <?php if ($lastMeasure): ?>
            <p class="muted">Compteur <?= e($lastMeasure['numero_compteur']); ?></p>
            <p><strong>Tension :</strong> <?= number_value($lastMeasure['tension'], 1); ?> V</p>
            <p><strong>Courant :</strong> <?= number_value($lastMeasure['courant'], 3); ?> A</p>
            <p><strong>Puissance :</strong> <?= number_value($lastMeasure['puissance'], 1); ?> W</p>
            <p><strong>Energie :</strong> <?= number_value($lastMeasure['energie'], 3); ?> kWh</p>
            <p class="muted"><?= e($lastMeasure['date_mesure']); ?></p>
        <?php else: ?>
            <p class="muted">Aucune mesure enregistree.</p>
        <?php endif; ?>
    </article>

    <article class="card">
        <h2>Mesures recentes</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Compteur</th>
                    <th>V</th>
                    <th>A</th>
                    <th>W</th>
                    <th>kWh</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentMeasures as $row): ?>
                    <tr>
                        <td><?= e($row['date_mesure']); ?></td>
                        <td><?= e($row['numero_compteur']); ?></td>
                        <td><?= number_value($row['tension'], 1); ?></td>
                        <td><?= number_value($row['courant'], 3); ?></td>
                        <td><?= number_value($row['puissance'], 1); ?></td>
                        <td><?= number_value($row['energie'], 3); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($chartLabels); ?>;
const voltageData = <?= json_encode($chartVoltage); ?>;
const currentData = <?= json_encode($chartCurrent); ?>;
const powerData = <?= json_encode($chartPower); ?>;
const energyData = <?= json_encode($chartEnergy); ?>;

const gridColor = "rgba(102, 112, 133, 0.18)";
const tickColor = "#667085";

function lineOptions(unit) {
    return {
        responsive: true,
        plugins: {
            legend: { labels: { color: tickColor } },
            tooltip: {
                callbacks: {
                    label: (context) => `${context.dataset.label}: ${context.parsed.y} ${unit}`
                }
            }
        },
        scales: {
            x: { ticks: { color: tickColor, maxRotation: 0 }, grid: { color: gridColor } },
            y: { ticks: { color: tickColor }, grid: { color: gridColor } }
        }
    };
}

new Chart(document.getElementById("voltageChart"), {
    type: "line",
    data: {
        labels,
        datasets: [{
            label: "Tension",
            data: voltageData,
            borderColor: "#0f766e",
            backgroundColor: "rgba(15, 118, 110, 0.12)",
            tension: 0.35,
            fill: true
        }]
    },
    options: lineOptions("V")
});

new Chart(document.getElementById("powerChart"), {
    type: "line",
    data: {
        labels,
        datasets: [
            {
                label: "Puissance",
                data: powerData,
                borderColor: "#f59e0b",
                backgroundColor: "rgba(245, 158, 11, 0.10)",
                tension: 0.35,
                fill: true
            },
            {
                label: "Courant",
                data: currentData,
                borderColor: "#2563eb",
                backgroundColor: "rgba(37, 99, 235, 0.08)",
                tension: 0.35,
                fill: false
            }
        ]
    },
    options: lineOptions("W / A")
});

new Chart(document.getElementById("energyChart"), {
    type: "bar",
    data: {
        labels,
        datasets: [{
            label: "Energie",
            data: energyData,
            backgroundColor: "rgba(15, 118, 110, 0.72)",
            borderRadius: 5
        }]
    },
    options: lineOptions("kWh")
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
