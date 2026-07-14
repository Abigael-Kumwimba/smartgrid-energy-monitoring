<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = db();
$pageTitle = 'Import predictions ML';
$activePage = 'predictions';
$message = '';
$errors = [];
$inserted = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['predictions_csv']) || $_FILES['predictions_csv']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Aucun fichier CSV valide n a ete envoye.';
    } else {
        $tmpPath = $_FILES['predictions_csv']['tmp_name'];
        $handle = fopen($tmpPath, 'rb');

        if ($handle === false) {
            $errors[] = 'Impossible de lire le fichier envoye.';
        } else {
            $header = fgetcsv($handle, 0, ',');

            if ($header === false) {
                $errors[] = 'Le fichier CSV est vide.';
            } else {
                $header = array_map(static fn(string $value): string => trim($value), $header);
                $required = ['numero_compteur', 'date_prediction', 'prediction'];
                $missing = array_diff($required, $header);

                if ($missing !== []) {
                    $errors[] = 'Colonnes manquantes : ' . implode(', ', $missing);
                } else {
                    $indexes = array_flip($header);
                    $findMeter = $pdo->prepare('SELECT id FROM compteurs WHERE numero_compteur = ? LIMIT 1');
                    $deleteExisting = $pdo->prepare(
                        'DELETE FROM predictions WHERE id_compteur = ? AND date_prediction = ?'
                    );
                    $insert = $pdo->prepare(
                        'INSERT INTO predictions (id_compteur, prediction, date_prediction)
                         VALUES (?, ?, ?)'
                    );

                    while (($row = fgetcsv($handle, 0, ',')) !== false) {
                        if (count($row) < count($header)) {
                            continue;
                        }

                        $numeroCompteur = trim((string) $row[$indexes['numero_compteur']]);
                        $datePrediction = trim((string) $row[$indexes['date_prediction']]);
                        $prediction = (float) str_replace(',', '.', trim((string) $row[$indexes['prediction']]));

                        if ($numeroCompteur === '' || $datePrediction === '') {
                            continue;
                        }

                        $findMeter->execute([$numeroCompteur]);
                        $idCompteur = $findMeter->fetchColumn();

                        if (!$idCompteur) {
                            $errors[] = 'Compteur introuvable : ' . $numeroCompteur;
                            continue;
                        }

                        $deleteExisting->execute([(int) $idCompteur, $datePrediction]);
                        $insert->execute([(int) $idCompteur, $prediction, $datePrediction]);
                        $inserted++;
                    }
                }
            }

            fclose($handle);
        }
    }

    if ($inserted > 0) {
        $message = $inserted . ' prediction(s) importee(s) avec succes.';
    }
}

require __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="notice"><?= e($message); ?></div><?php endif; ?>

<?php foreach ($errors as $error): ?>
    <div class="notice notice-error"><?= e($error); ?></div>
<?php endforeach; ?>

<section class="grid two-cols">
    <article class="card">
        <h2>Importer un fichier CSV</h2>
        <p class="muted">
            Le fichier doit provenir de Jupyter et contenir les colonnes
            <strong>numero_compteur</strong>, <strong>date_prediction</strong> et <strong>prediction</strong>.
        </p>
        <form class="form" method="post" enctype="multipart/form-data">
            <label>Fichier CSV
                <input type="file" name="predictions_csv" accept=".csv,text/csv" required>
            </label>
            <button class="btn" type="submit">Importer les predictions</button>
        </form>
    </article>

    <article class="card">
        <h2>Fichier attendu</h2>
        <p>
            Le fichier importe doit contenir trois informations principales :
        </p>
        <ul class="clean-list">
            <li>le numero du compteur ;</li>
            <li>la date de prediction ;</li>
            <li>la valeur predite de consommation.</li>
        </ul>
        <p class="muted">
            Cette page est reservee a l'administrateur pour importer les resultats obtenus apres
            l'entrainement du modele dans Jupyter.
        </p>
    </article>
</section>

<article class="card" style="margin-top: 16px;">
    <h2>Utilisation dans le projet</h2>
    <p>
        L'entrainement du modele se fait dans Jupyter. Une fois les predictions generees, elles sont
        exportees dans un fichier CSV puis importees ici pour etre affichees dans le tableau de bord ML.
    </p>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
