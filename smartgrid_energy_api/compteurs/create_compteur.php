<?php
include('../config.php');

$numero = trim($_POST['numero_compteur'] ?? '');
$id_client = (int) ($_POST['id_client'] ?? 0);

if ($numero === '' || $id_client <= 0) {
    http_response_code(400);
    echo 'Numero compteur et client obligatoires';
    exit;
}

$stmt = $conn->prepare('INSERT INTO compteurs (numero_compteur, id_client, statut) VALUES (?, ?, "inactif")');
$stmt->bind_param('si', $numero, $id_client);

if ($stmt->execute()) {
    echo 'Compteur ajoute en attente d\'activation';
} else {
    http_response_code(500);
    echo 'Erreur lors de l\'ajout du compteur';
}
?>
