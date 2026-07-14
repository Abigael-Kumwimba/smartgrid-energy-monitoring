<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$pageTitle = 'Gestion des compteurs';
$activePage = 'compteurs';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('INSERT INTO compteurs (numero_compteur, id_client, statut) VALUES (?, ?, ?)');
    $stmt->execute([
        trim($_POST['numero_compteur'] ?? ''),
        (int) ($_POST['id_client'] ?? 0),
        $_POST['statut'] ?? 'actif',
    ]);
    $message = 'Compteur ajoute avec succes.';
}

$clients = $pdo->query('SELECT id, nom FROM clients ORDER BY nom')->fetchAll();
$compteurs = $pdo->query(
    'SELECT co.*, cl.nom AS client_nom
     FROM compteurs co
     LEFT JOIN clients cl ON cl.id = co.id_client
     ORDER BY co.id DESC'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="notice"><?= e($message); ?></div><?php endif; ?>

<section class="grid two-cols">
    <article class="card">
        <h2>Nouveau compteur</h2>
        <form class="form" method="post">
            <label>Numero compteur
                <input type="text" name="numero_compteur" placeholder="Ex: SG001" required>
            </label>
            <label>Client
                <select name="id_client" required>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id']; ?>"><?= e($client['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Statut
                <select name="statut">
                    <option value="actif">Actif</option>
                    <option value="inactif">Inactif</option>
                </select>
            </label>
            <button class="btn" type="submit">Ajouter le compteur</button>
        </form>
    </article>

    <article class="card">
        <h2>Liste des compteurs</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Numero</th>
                    <th>Client</th>
                    <th>Statut</th>
                    <th>Installation</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($compteurs as $compteur): ?>
                    <tr>
                        <td><?= (int) $compteur['id']; ?></td>
                        <td><?= e($compteur['numero_compteur']); ?></td>
                        <td><?= e($compteur['client_nom']); ?></td>
                        <td><span class="badge"><?= e($compteur['statut']); ?></span></td>
                        <td><?= e($compteur['date_installation']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

