<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/telegram.php';

$idFacture = (int) ($_POST['id_facture'] ?? 0);

if ($idFacture <= 0) {
    header('Location: /smartgrid_dashboard/admin/factures.php');
    exit;
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT f.*, cl.nom AS client_nom, cl.telephone, cl.telegram_chat_id
     FROM factures f
     LEFT JOIN clients cl ON cl.id = f.id_client
     WHERE f.id = ?'
);
$stmt->execute([$idFacture]);
$facture = $stmt->fetch();

if (!$facture || empty($facture['telegram_chat_id'])) {
    header('Location: /smartgrid_dashboard/smartgrid_app/facture_detail.php?id=' . $idFacture . '&telegram=fail');
    exit;
}

$message = sprintf(
    "<b>SmartGrid - Facture d'electricite</b>\n" .
    "Bonjour %s,\n\n" .
    "Votre facture #%d a ete generee.\n" .
    "Energie consommee : %.3f kWh\n" .
    "Montant a payer : %.2f $\n" .
    "Statut : %s\n" .
    "Date : %s\n\n" .
    "Merci de consulter votre espace client pour le detail.",
    (string) $facture['client_nom'],
    (int) $facture['id'],
    (float) $facture['energie_totale'],
    (float) $facture['montant'],
    (string) $facture['statut'],
    (string) $facture['date_facture']
);

$sent = send_telegram_message($message, (string) $facture['telegram_chat_id']);

header('Location: /smartgrid_dashboard/smartgrid_app/facture_detail.php?id=' . $idFacture . '&telegram=' . ($sent ? 'ok' : 'fail'));
exit;
