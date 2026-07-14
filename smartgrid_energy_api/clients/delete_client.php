<?php
include('../config.php');

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo 'ID client invalide';
    exit;
}

$check = $conn->prepare('SELECT COUNT(*) AS total FROM compteurs WHERE id_client = ?');
$check->bind_param('i', $id);
$check->execute();
$result = $check->get_result()->fetch_assoc();

if ((int) ($result['total'] ?? 0) > 0) {
    http_response_code(409);
    echo 'Impossible de supprimer ce client : des compteurs sont encore rattaches a son compte';
    exit;
}

$stmt = $conn->prepare('DELETE FROM clients WHERE id = ?');
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    echo 'Client supprime';
} else {
    http_response_code(500);
    echo 'Erreur lors de la suppression du client';
}