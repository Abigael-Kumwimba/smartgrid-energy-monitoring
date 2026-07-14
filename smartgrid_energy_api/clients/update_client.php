<?php
include('../config.php');

$id = (int) ($_POST['id'] ?? 0);
$nom = trim($_POST['nom'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');
$adresse = trim($_POST['adresse'] ?? '');

if ($id <= 0 || $nom === '' || $telephone === '' || $adresse === '') {
    http_response_code(400);
    echo 'ID, nom, telephone et adresse sont obligatoires';
    exit;
}

$stmt = $conn->prepare('UPDATE clients SET nom = ?, telephone = ?, telegram_chat_id = ?, adresse = ? WHERE id = ?');
$stmt->bind_param('ssssi', $nom, $telephone, $telegram_chat_id, $adresse, $id);

if ($stmt->execute()) {
    echo 'Client modifie';
} else {
    http_response_code(500);
    echo 'Erreur lors de la modification du client';
}