<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=UTF-8');

$sql = 'SELECT cp.id, cp.numero_compteur, cp.id_client, cp.date_installation,
               lm.derniere_mesure,
               COALESCE(lm.total_mesures, 0) AS total_mesures,
               CASE
                   WHEN lm.derniere_mesure >= (NOW() - INTERVAL 5 MINUTE) THEN "actif"
                   ELSE "inactif"
               END AS statut,
               CASE
                   WHEN lm.derniere_mesure IS NULL THEN "Aucune mesure recue"
                   WHEN lm.derniere_mesure >= (NOW() - INTERVAL 5 MINUTE) THEN "Mesures recues en temps reel"
                   ELSE "Derniere mesure trop ancienne"
               END AS statut_detail,
               COALESCE(unpaid.factures_non_payees, 0) AS factures_non_payees
        FROM compteurs cp
        LEFT JOIN clients cl ON cl.id = cp.id_client
        LEFT JOIN (
            SELECT id_compteur, MAX(date_mesure) AS derniere_mesure, COUNT(*) AS total_mesures
            FROM consommations
            GROUP BY id_compteur
        ) lm ON lm.id_compteur = cp.id
        LEFT JOIN (
            SELECT id_client, COUNT(*) AS factures_non_payees
            FROM factures
            WHERE statut = "non_payee"
            GROUP BY id_client
        ) unpaid ON unpaid.id_client = cl.id
        ORDER BY cp.id DESC';
$result = $conn->query($sql);
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>
