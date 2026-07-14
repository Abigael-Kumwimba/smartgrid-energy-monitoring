<?php
require_once __DIR__ . '/../config.php';
header("Content-Type: application/json; charset=UTF-8");

$result = $conn->query("SELECT id FROM compteurs ORDER BY id ASC LIMIT 1");
if (!$result || $result->num_rows === 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Aucun compteur disponible pour la simulation"
    ]);
    exit;
}

$row = $result->fetch_assoc();
$idCompteur = (int) $row['id'];

$tension = rand(210, 240);
$courant = rand(1, 10);
$puissance = $tension * $courant;
$energie = rand(1, 5);

$stmt = $conn->prepare("INSERT INTO consommations (id_compteur, tension, courant, puissance, energie, date_mesure) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("idddd", $idCompteur, $tension, $courant, $puissance, $energie);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Simulation envoyee",
        "id" => $stmt->insert_id,
        "id_compteur" => $idCompteur,
        "tension" => $tension,
        "courant" => $courant,
        "puissance" => $puissance,
        "energie" => $energie
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Erreur lors de la simulation",
        "details" => $stmt->error
    ]);
}
?>
