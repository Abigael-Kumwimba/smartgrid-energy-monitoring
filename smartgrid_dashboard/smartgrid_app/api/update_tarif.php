<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/dashboard_data.php';

smartgrid_require_login(['admin']);
header('Content-Type: application/json; charset=UTF-8');

$tarif = isset($_POST['tarif_kwh']) ? (float) $_POST['tarif_kwh'] : 0.0;

if ($tarif <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Le tarif doit etre superieur a zero.'
    ]);
    exit;
}

update_setting_value(
    'tarif_kwh_residentiel',
    number_format($tarif, 4, '.', ''),
    'Tarif residentiel de reference par kWh utilise pour le calcul automatique des factures'
);

echo json_encode([
    'status' => 'success',
    'message' => 'Tarif mis a jour',
    'tarif_kwh' => $tarif
]);
