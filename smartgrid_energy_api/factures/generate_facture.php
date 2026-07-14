<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=UTF-8');

$id_client = $_POST['id_client'] ?? null;
$tarifResult = $conn->query("SELECT valeur FROM parametres_systeme WHERE cle = 'tarif_kwh_residentiel' LIMIT 1");
$tarifRow = $tarifResult ? $tarifResult->fetch_assoc() : null;
$tarif_configure = (float) ($tarifRow['valeur'] ?? 0.15);
$tarif_kwh = isset($_POST['tarif_kwh']) && $_POST['tarif_kwh'] !== '' ? (float) $_POST['tarif_kwh'] : $tarif_configure;
$date_debut = $_POST['date_debut'] ?? null;
$date_fin = $_POST['date_fin'] ?? null;

if ($id_client === null) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Parametre manquant : id_client'
    ]);
    exit;
}

$id_client = (int) $id_client;
if ($tarif_kwh <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Le tarif par kWh doit etre superieur a zero'
    ]);
    exit;
}

if (!empty($date_debut) && !empty($date_fin) && $date_debut > $date_fin) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'La date de debut doit preceder la date de fin'
    ]);
    exit;
}

$check = $conn->prepare('SELECT id FROM clients WHERE id = ? LIMIT 1');
$check->bind_param('i', $id_client);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Le client specifie n\'existe pas',
        'id_client' => $id_client
    ]);
    exit;
}

$conditions = ['cp.id_client = ?'];
$types = 'i';
$params = [$id_client];

if (!empty($date_debut)) {
    $conditions[] = 'co.date_mesure >= ?';
    $types .= 's';
    $params[] = $date_debut . ' 00:00:00';
}

if (!empty($date_fin)) {
    $conditions[] = 'co.date_mesure <= ?';
    $types .= 's';
    $params[] = $date_fin . ' 23:59:59';
}

$where = implode(' AND ', $conditions);
$query = "
    SELECT COALESCE(SUM(
        CASE
            WHEN compteur_stats.nb_mesures >= 2 THEN GREATEST(compteur_stats.energie_max - compteur_stats.energie_min, 0)
            ELSE COALESCE(compteur_stats.energie_max, 0)
        END
    ), 0) AS energie_facturable,
    COALESCE(SUM(compteur_stats.nb_mesures), 0) AS nb_mesures
    FROM (
        SELECT cp.id,
               COUNT(co.id) AS nb_mesures,
               MIN(co.energie) AS energie_min,
               MAX(co.energie) AS energie_max
        FROM compteurs cp
        LEFT JOIN consommations co ON co.id_compteur = cp.id
        WHERE $where
        GROUP BY cp.id
    ) AS compteur_stats
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$energyResult = $stmt->get_result()->fetch_assoc();

$energie = round((float) ($energyResult['energie_facturable'] ?? 0), 3);
$nb_mesures = (int) ($energyResult['nb_mesures'] ?? 0);
$montant = round($energie * $tarif_kwh, 2);

if ($nb_mesures === 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Aucune mesure trouvee pour ce client. Impossible de generer une facture automatique.'
    ]);
    exit;
}

$insert = $conn->prepare(
    'INSERT INTO factures (
        id_client, periode_debut, periode_fin, energie_totale,
        tarif_kwh, montant, date_echeance
     )
     VALUES (?, NULLIF(?, ""), NULLIF(?, ""), ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))'
);
$date_debut_value = (string) ($date_debut ?? '');
$date_fin_value = (string) ($date_fin ?? '');
$insert->bind_param(
    'issddd',
    $id_client,
    $date_debut_value,
    $date_fin_value,
    $energie,
    $tarif_kwh,
    $montant
);

if ($insert->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Facture generee automatiquement selon la consommation',
        'id' => $insert->insert_id,
        'energie_totale' => $energie,
        'tarif_kwh' => $tarif_kwh,
        'montant' => $montant
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors de la generation de la facture',
        'details' => $insert->error
    ]);
}
?>
