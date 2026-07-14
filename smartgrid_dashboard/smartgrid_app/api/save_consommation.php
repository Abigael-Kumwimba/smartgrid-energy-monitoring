<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/telegram.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Methode non autorisee'], 405);
}

$rawBody = file_get_contents('php://input') ?: '';
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    json_response(['success' => false, 'message' => 'JSON invalide'], 400);
}

$numeroCompteur = trim((string) ($data['numero_compteur'] ?? ''));
$source = trim((string) ($data['source'] ?? ''));
$isTestSource = str_starts_with($source, 'test_');

if ($numeroCompteur === '') {
    json_response(['success' => false, 'message' => 'numero_compteur manquant'], 400);
}

$tension = isset($data['tension']) ? (float) $data['tension'] : null;
$courant = isset($data['courant']) ? (float) $data['courant'] : null;
$puissance = isset($data['puissance']) ? (float) $data['puissance'] : null;
$energie = isset($data['energie']) ? (float) $data['energie'] : null;

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT cp.id, cp.id_client, cl.nom AS client_nom, cl.telegram_chat_id
         FROM compteurs cp
         LEFT JOIN clients cl ON cl.id = cp.id_client
         WHERE cp.numero_compteur = ?
         LIMIT 1'
    );
    $stmt->execute([$numeroCompteur]);
    $compteur = $stmt->fetch();
    $idCompteur = $compteur['id'] ?? null;

    if (!$idCompteur) {
        json_response([
            'success' => false,
            'message' => 'Compteur introuvable',
            'numero_compteur' => $numeroCompteur,
        ], 404);
    }

    $insert = $pdo->prepare(
        'INSERT INTO consommations (id_compteur, tension, courant, puissance, energie)
         VALUES (?, ?, ?, ?, ?)'
    );
    $insert->execute([
        (int) $idCompteur,
        $tension,
        $courant,
        $puissance,
        $energie,
    ]);
    $idConsommation = (int) $pdo->lastInsertId();

    $alert = detect_alert($tension, $courant, $puissance);
    $telegramAdminSent = null;
    $telegramClientSent = null;

    if ($alert !== null) {
        $recentAlertStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM alertes
             WHERE id_compteur = ?
               AND message = ?
               AND date_alerte >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
        );
        $recentAlertStmt->execute([(int) $idCompteur, $alert['message']]);

        if ($isTestSource || (int) $recentAlertStmt->fetchColumn() === 0) {
            $alertStmt = $pdo->prepare(
                'INSERT INTO alertes (id_compteur, message, niveau)
                 VALUES (?, ?, ?)'
            );
            $alertStmt->execute([(int) $idCompteur, $alert['message'], $alert['niveau']]);

            $telegramMessage = "<b>SmartGrid - Alerte electrique</b>\n"
                . "Client : " . ($compteur['client_nom'] ?? '-') . "\n"
                . "Compteur : " . $numeroCompteur . "\n"
                . "Niveau : " . $alert['niveau'] . "\n"
                . "Message : " . $alert['message'] . "\n"
                . "Tension : " . number_format((float) $tension, 1) . " V\n"
                . "Courant : " . number_format((float) $courant, 3) . " A\n"
                . "Puissance : " . number_format((float) $puissance, 1) . " W";

            $telegramAdminSent = send_telegram_message($telegramMessage);

            if (!empty($compteur['telegram_chat_id'])) {
                $clientMessage = "<b>SmartGrid - Alerte sur votre compteur</b>\n"
                    . "Compteur : " . $numeroCompteur . "\n"
                    . "Anomalie : " . $alert['message'] . "\n"
                    . "Tension : " . number_format((float) $tension, 1) . " V\n"
                    . "Courant : " . number_format((float) $courant, 3) . " A\n"
                    . "Puissance : " . number_format((float) $puissance, 1) . " W\n"
                    . "Veuillez verifier vos appareils ou contacter l'administrateur.";

                $telegramClientSent = send_telegram_message($clientMessage, (string) $compteur['telegram_chat_id']);
            }
        }
    }

    json_response([
        'success' => true,
        'message' => 'Consommation enregistree',
        'id_consommation' => $idConsommation,
        'alerte' => $alert,
        'telegram_admin' => $telegramAdminSent,
        'telegram_client' => $telegramClientSent,
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Erreur serveur',
        'detail' => $e->getMessage(),
    ], 500);
}

function detect_alert(?float $tension, ?float $courant, ?float $puissance): ?array
{
    if ($tension !== null && $tension > 255) {
        return ['niveau' => 'critique', 'message' => 'Surtension detectee'];
    }

    if ($tension !== null && $tension > 0 && $tension < 180) {
        return ['niveau' => 'warning', 'message' => 'Sous-tension detectee'];
    }

    if ($courant !== null && $courant > 10) {
        return ['niveau' => 'critique', 'message' => 'Surintensite detectee'];
    }

    if ($puissance !== null && $puissance > 2000) {
        return ['niveau' => 'critique', 'message' => 'Surcharge de puissance detectee'];
    }

    return null;
}

