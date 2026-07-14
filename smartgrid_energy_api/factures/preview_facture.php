<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=UTF-8');

$idClient = (int) ($_POST['id_client'] ?? 0);
$dateDebut = trim((string) ($_POST['date_debut'] ?? ''));
$dateFin = trim((string) ($_POST['date_fin'] ?? ''));
$tarifResult = $conn->query(
    "SELECT valeur FROM parametres_systeme
     WHERE cle = 'tarif_kwh_residentiel' LIMIT 1"
);
$tarifRow = $tarifResult ? $tarifResult->fetch_assoc() : null;
$tarifConfigure = (float) ($tarifRow['valeur'] ?? 0.15);
$tarifKwh = isset($_POST['tarif_kwh']) && $_POST['tarif_kwh'] !== ''
    ? (float) $_POST['tarif_kwh']
    : $tarifConfigure;

if ($idClient <= 0 || $tarifKwh <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Client ou tarif invalide',
    ]);
    exit;
}

if ($dateDebut !== '' && $dateFin !== '' && $dateDebut > $dateFin) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'La date de debut doit preceder la date de fin',
    ]);
    exit;
}

$clientStmt = $conn->prepare('SELECT id, nom FROM clients WHERE id = ? LIMIT 1');
$clientStmt->bind_param('i', $idClient);
$clientStmt->execute();
$client = $clientStmt->get_result()->fetch_assoc();

if (!$client) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Client introuvable',
    ]);
    exit;
}

$joinConditions = ['co.id_compteur = cp.id'];
$types = 'i';
$params = [$idClient];

if ($dateDebut !== '') {
    $joinConditions[] = 'co.date_mesure >= ?';
    $types .= 's';
    $params[] = $dateDebut . ' 00:00:00';
}

if ($dateFin !== '') {
    $joinConditions[] = 'co.date_mesure <= ?';
    $types .= 's';
    $params[] = $dateFin . ' 23:59:59';
}

$joinSql = implode(' AND ', $joinConditions);
$sql = "
    SELECT cp.id,
           cp.numero_compteur,
           COUNT(co.id) AS nb_mesures,
           MIN(co.energie) AS energie_debut,
           MAX(co.energie) AS energie_fin,
           CASE
             WHEN COUNT(co.id) >= 2
               THEN GREATEST(MAX(co.energie) - MIN(co.energie), 0)
             WHEN COUNT(co.id) = 1
               THEN COALESCE(MAX(co.energie), 0)
             ELSE 0
           END AS energie_facturable
    FROM compteurs cp
    LEFT JOIN consommations co ON $joinSql
    WHERE cp.id_client = ?
    GROUP BY cp.id, cp.numero_compteur
    ORDER BY cp.numero_compteur
";

// The client id belongs after the optional date parameters in the SQL.
$queryTypes = substr($types, 1) . 'i';
$queryParams = array_slice($params, 1);
$queryParams[] = $idClient;

$stmt = $conn->prepare($sql);
$stmt->bind_param($queryTypes, ...$queryParams);
$stmt->execute();
$meters = [];
$totalEnergy = 0.0;
$totalMeasurements = 0;

while ($row = $stmt->get_result()->fetch_assoc()) {
    $energy = round((float) $row['energie_facturable'], 3);
    $measurements = (int) $row['nb_mesures'];
    $meters[] = [
        'id' => (int) $row['id'],
        'numero_compteur' => $row['numero_compteur'],
        'nb_mesures' => $measurements,
        'energie_debut' => $row['energie_debut'] !== null
            ? round((float) $row['energie_debut'], 3)
            : null,
        'energie_fin' => $row['energie_fin'] !== null
            ? round((float) $row['energie_fin'], 3)
            : null,
        'energie_facturable' => $energy,
    ];
    $totalEnergy += $energy;
    $totalMeasurements += $measurements;
}

$totalEnergy = round($totalEnergy, 3);
$amount = round($totalEnergy * $tarifKwh, 2);

echo json_encode([
    'status' => 'success',
    'client' => $client,
    'periode_debut' => $dateDebut !== '' ? $dateDebut : null,
    'periode_fin' => $dateFin !== '' ? $dateFin : null,
    'tarif_kwh' => $tarifKwh,
    'compteurs' => $meters,
    'total_mesures' => $totalMeasurements,
    'energie_totale' => $totalEnergy,
    'montant' => $amount,
]);
