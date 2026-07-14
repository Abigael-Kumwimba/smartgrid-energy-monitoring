<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$pageTitle = 'Gestion des clients';
$activePage = 'clients';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('INSERT INTO clients (nom, telephone, adresse) VALUES (?, ?, ?)');
    $stmt->execute([
        trim($_POST['nom'] ?? ''),
        trim($_POST['telephone'] ?? ''),
        trim($_POST['adresse'] ?? ''),
    ]);
    $message = 'Client ajoute avec succes.';
}

$clients = $pdo->query(
    'SELECT cl.*, COUNT(co.id) AS total_compteurs
     FROM clients cl
     LEFT JOIN compteurs co ON co.id_client = cl.id
     GROUP BY cl.id
     ORDER BY cl.id DESC'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="notice"><?= e($message); ?></div><?php endif; ?>

<section class="grid two-cols">
    <article class="card">
        <h2>Nouveau client</h2>
        <form class="form" method="post">
            <label>Nom
                <input type="text" name="nom" required>
            </label>
            <label>Telephone
                <input type="text" name="telephone">
            </label>
            <label>Adresse
                <input type="text" name="adresse">
            </label>
            <button class="btn" type="submit">Ajouter le client</button>
        </form>
    </article>

    <article class="card">
        <h2>Liste des clients</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Telephone</th>
                    <th>Adresse</th>
                    <th>Compteurs</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><?= (int) $client['id']; ?></td>
                        <td><?= e($client['nom']); ?></td>
                        <td><?= e($client['telephone']); ?></td>
                        <td><?= e($client['adresse']); ?></td>
                        <td><?= (int) $client['total_compteurs']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

