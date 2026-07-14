<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=UTF-8');
$result = $conn->query('SELECT * FROM factures ORDER BY date_facture DESC');
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>
