<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/telegram.php';

header('Content-Type: application/json; charset=utf-8');
smartgrid_require_login(['admin']);

$pdo = db();
$stmt = $pdo->query(
    'SELECT a.id, a.date_alerte, a.niveau, a.message, cp.numero_compteur, cl.nom AS client_nom, cl.telegram_chat_id
     FROM alertes a
     LEFT JOIN compteurs cp ON cp.id = a.id_compteur
     LEFT JOIN clients cl ON cl.id = cp.id_client
     ORDER BY a.date_alerte DESC, a.id DESC
     LIMIT 1'
);
$alert = $stmt->fetch();

if (!$alert) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Aucune alerte disponible a envoyer.'
    ]);
    exit;
}

$adminMessage = sprintf(
    "<b>SmartGrid - Alerte electrique</b>\nClient : %s\nCompteur : %s\nNiveau : %s\nMessage : %s\nDate : %s",
    (string) ($alert['client_nom'] ?? '-'),
    (string) ($alert['numero_compteur'] ?? '-'),
    (string) ($alert['niveau'] ?? '-'),
    (string) ($alert['message'] ?? '-'),
    (string) ($alert['date_alerte'] ?? '-')
);

$clientMessage = sprintf(
    "<b>SmartGrid - Alerte sur votre compteur</b>\nCompteur : %s\nAnomalie : %s\nDate : %s\nVeuillez verifier vos appareils ou contacter l'administrateur.",
    (string) ($alert['numero_compteur'] ?? '-'),
    (string) ($alert['message'] ?? '-'),
    (string) ($alert['date_alerte'] ?? '-')
);

$adminSent = send_telegram_message($adminMessage);
$clientSent = false;

if (!empty($alert['telegram_chat_id'])) {
    $clientSent = send_telegram_message($clientMessage, (string) $alert['telegram_chat_id']);
}

echo json_encode([
    'status' => ($adminSent || $clientSent) ? 'success' : 'error',
    'message' => $clientSent
        ? 'Derniere alerte envoyee au client et a l administrateur.'
        : ($adminSent ? 'Derniere alerte envoyee a l administrateur. Chat ID client manquant ou invalide.' : 'Echec de l envoi Telegram.'),
    'admin_sent' => $adminSent,
    'client_sent' => $clientSent,
]);