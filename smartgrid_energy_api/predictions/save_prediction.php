<?php
require_once __DIR__ . '/../config.php';
header("Content-Type: application/json; charset=UTF-8");

$id_compteur = $_POST['id_compteur'] ?? null;
$prediction = $_POST['prediction'] ?? null;

if ($id_compteur === null || $prediction === null) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Parametres manquants : id_compteur, prediction"
    ]);
    exit;
}

$id_compteur = (int) $id_compteur;
$prediction = (float) $prediction;

$check = $conn->prepare("SELECT id FROM compteurs WHERE id = ? LIMIT 1");
$check->bind_param("i", $id_compteur);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Le compteur specifie n'existe pas",
        "id_compteur" => $id_compteur
    ]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO predictions (id_compteur, prediction, date_prediction) VALUES (?, ?, CURDATE())");
$stmt->bind_param("id", $id_compteur, $prediction);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Prediction sauvegardee",
        "id" => $stmt->insert_id,
        "prediction" => $prediction
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Erreur lors de la sauvegarde de la prediction",
        "details" => $stmt->error
    ]);
}
?>
