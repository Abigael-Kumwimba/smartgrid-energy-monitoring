<?php
include('../config.php');

$nom = trim($_POST['nom'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');
$adresse = trim($_POST['adresse'] ?? '');

if ($nom === '' || $telephone === '' || $adresse === '') {
    http_response_code(400);
    echo 'Nom, telephone et adresse sont obligatoires';
    exit;
}

$stmt = $conn->prepare('INSERT INTO clients (nom, telephone, telegram_chat_id, adresse) VALUES (?, ?, ?, ?)');
$stmt->bind_param('ssss', $nom, $telephone, $telegram_chat_id, $adresse);

if ($stmt->execute()) {
    echo 'Client ajoute';
} else {
    http_response_code(500);
    echo 'Erreur lors de l\'ajout du client';
}
?>
