<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=UTF-8');

$id = (int) ($_POST['id'] ?? 0);
$statut = $_POST['statut'] ?? '';

if ($id <= 0 || !in_array($statut, ['actif', 'inactif'], true)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Parametres invalides'
    ]);
    exit;
}

$stmt = $conn->prepare('UPDATE compteurs SET statut = ? WHERE id = ?');
$stmt->bind_param('si', $statut, $id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Statut du compteur mis a jour'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors de la mise a jour du statut'
    ]);
}
