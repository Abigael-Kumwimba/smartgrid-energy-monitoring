<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/telegram.php';

header('Content-Type: application/json; charset=UTF-8');
smartgrid_require_login(['admin']);

$invoiceId = (int) ($_POST['id_facture'] ?? 0);
if ($invoiceId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Facture invalide']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT f.*, cl.nom AS client_nom, cl.telegram_chat_id
     FROM factures f
     INNER JOIN clients cl ON cl.id = f.id_client
     WHERE f.id = ?'
);
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice || $invoice['statut'] === 'payee') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Facture deja payee ou introuvable']);
    exit;
}

if (empty($invoice['telegram_chat_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Chat ID Telegram non configure pour ce client']);
    exit;
}

$message = sprintf(
    "<b>SmartGrid - Rappel de paiement</b>\n" .
    "Bonjour %s,\n\n" .
    "La facture #%d d'un montant de %.2f $ reste non payee.\n" .
    "Date d'echeance : %s\n\n" .
    "Veuillez consulter votre espace client pour effectuer le paiement.",
    (string) $invoice['client_nom'],
    (int) $invoice['id'],
    (float) $invoice['montant'],
    (string) ($invoice['date_echeance'] ?: 'non definie')
);

$sent = send_telegram_message($message, (string) $invoice['telegram_chat_id']);
if ($sent) {
    $update = $pdo->prepare('UPDATE factures SET rappel_envoye = 1 WHERE id = ?');
    $update->execute([$invoiceId]);
}

echo json_encode([
    'status' => $sent ? 'success' : 'error',
    'message' => $sent ? 'Rappel Telegram envoye' : 'Echec de l\'envoi Telegram',
]);
