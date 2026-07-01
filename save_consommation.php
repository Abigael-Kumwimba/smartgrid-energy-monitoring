<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=UTF-8');

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}

function input_value(array $jsonInput, string ...$keys) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        if (array_key_exists($key, $jsonInput)) {
            return $jsonInput[$key];
        }
    }
    return null;
}

$tension = input_value($jsonInput, 'tension', 'voltage', 'V');
$courant = input_value($jsonInput, 'courant', 'current', 'I');
$puissance = input_value($jsonInput, 'puissance', 'power', 'P');
$energie = input_value($jsonInput, 'energie', 'energy', 'E');
$idCompteur = input_value($jsonInput, 'idCompteur', 'id_compteur');
$numeroCompteur = input_value($jsonInput, 'numero_compteur', 'numeroCompteur', 'device_id');
$source = input_value($jsonInput, 'source') ?? 'api';

if ($idCompteur === null && $numeroCompteur !== null) {
    $lookup = $conn->prepare('SELECT id FROM compteurs WHERE numero_compteur = ? LIMIT 1');
    $lookup->bind_param('s', $numeroCompteur);
    $lookup->execute();
    $lookupResult = $lookup->get_result();
    $lookupRow = $lookupResult->fetch_assoc();
    $idCompteur = $lookupRow['id'] ?? null;
}

if ($tension === null || $courant === null || $puissance === null || $energie === null || $idCompteur === null) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Parametres manquants : tension, courant, puissance, energie, idCompteur ou numero_compteur'
    ]);
    exit;
}

$idCompteur = (int) $idCompteur;
$tension = (float) $tension;
$courant = (float) $courant;
$puissance = (float) $puissance;
$energie = (float) $energie;

$check = $conn->prepare('SELECT id, numero_compteur FROM compteurs WHERE id = ? LIMIT 1');
$check->bind_param('i', $idCompteur);
$check->execute();
$result = $check->get_result();
$compteur = $result->fetch_assoc();

if (!$compteur) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Le compteur specifie n existe pas',
        'idCompteur' => $idCompteur
    ]);
    exit;
}

$stmt = $conn->prepare('INSERT INTO consommations (id_compteur, tension, courant, puissance, energie) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('idddd', $idCompteur, $tension, $courant, $puissance, $energie);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Consommation enregistree',
        'id' => $stmt->insert_id,
        'id_compteur' => $idCompteur,
        'numero_compteur' => $compteur['numero_compteur'],
        'source' => $source
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors de l enregistrement',
        'details' => $stmt->error
    ]);
}
?>
