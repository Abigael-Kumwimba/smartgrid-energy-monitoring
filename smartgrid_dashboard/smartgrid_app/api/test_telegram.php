<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../config/telegram.php';

header('Content-Type: application/json; charset=utf-8');
smartgrid_require_login(['admin']);

$message = "<b>SmartGrid - Test Telegram</b>\n" .
    "La notification Telegram fonctionne correctement.\n" .
    "Heure du test : " . date('Y-m-d H:i:s');

$sent = send_telegram_message($message);

echo json_encode([
    'status' => $sent ? 'success' : 'error',
    'message' => $sent
        ? 'Message test envoye sur Telegram.'
        : 'Echec du test Telegram. Verifiez le token, le chat ID et la connexion Internet.'
]);
