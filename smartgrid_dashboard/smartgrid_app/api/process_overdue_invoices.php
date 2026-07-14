<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/telegram.php';

header('Content-Type: application/json; charset=UTF-8');
smartgrid_require_login(['admin']);

$pdo = db();
$stmt = $pdo->query(
    'SELECT f.id, f.id_client, f.montant, f.date_echeance, f.rappel_envoye,
            cl.nom AS client_nom, cl.telegram_chat_id
     FROM factures f
     INNER JOIN clients cl ON cl.id = f.id_client
     WHERE f.statut = "non_payee"
       AND f.date_echeance IS NOT NULL
       AND f.date_echeance < NOW()'
);
$overdueInvoices = $stmt->fetchAll();
$notifications = 0;
$suspendedClients = [];

foreach ($overdueInvoices as $invoice) {
    $clientId = (int) $invoice['id_client'];
    $invoiceId = (int) $invoice['id'];

    $suspend = $pdo->prepare('UPDATE compteurs SET statut = "inactif" WHERE id_client = ?');
    $suspend->execute([$clientId]);
    $suspendedClients[$clientId] = true;

    $alert = $pdo->prepare(
        'INSERT INTO alertes (id_compteur, message, niveau)
         SELECT cp.id, ?, "warning"
         FROM compteurs cp
         WHERE cp.id_client = ?
           AND NOT EXISTS (
             SELECT 1 FROM alertes a
             WHERE a.id_compteur = cp.id
               AND a.message = ?
           )'
    );
    $message = 'Facture #' . $invoiceId . ' impayee apres echeance';
    $alert->execute([$message, $clientId, $message]);

    if ((int) ($invoice['rappel_envoye'] ?? 0) === 0 && !empty($invoice['telegram_chat_id'])) {
        $telegramMessage = sprintf(
            "<b>SmartGrid - Rappel de facture impayee</b>\n" .
            "Bonjour %s,\n\n" .
            "La facture #%d de %.2f $ est arrivee a echeance le %s.\n" .
            "Vos compteurs sont marques hors service dans l'application jusqu'au reglement.\n\n" .
            "Veuillez consulter votre espace client.",
            (string) $invoice['client_nom'],
            $invoiceId,
            (float) $invoice['montant'],
            (string) $invoice['date_echeance']
        );

        if (send_telegram_message($telegramMessage, (string) $invoice['telegram_chat_id'])) {
            $mark = $pdo->prepare('UPDATE factures SET rappel_envoye = 1 WHERE id = ?');
            $mark->execute([$invoiceId]);
            $notifications++;
        }
    }
}

echo json_encode([
    'status' => 'success',
    'overdue_invoices' => count($overdueInvoices),
    'suspended_clients' => count($suspendedClients),
    'telegram_notifications' => $notifications,
]);
