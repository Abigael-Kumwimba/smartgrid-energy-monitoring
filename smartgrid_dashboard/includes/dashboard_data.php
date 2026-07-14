<?php
function dashboard_db(): mysqli {
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli('127.0.0.1', 'root', '', 'smartgrid_energy', 3306);
    $conn->set_charset('utf8mb4');
    return $conn;
}

function scalar_query(mysqli $conn, string $sql, array $params = [], string $types = '') {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    return $row[0] ?? 0;
}

function fetch_all_assoc(mysqli_stmt $stmt): array {
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function fetch_clients_list(): array {
    $conn = dashboard_db();
    $stmt = $conn->prepare(
        'SELECT cl.id, cl.nom, cl.telephone, cl.telegram_chat_id, cl.adresse, cl.date_creation,
                COUNT(cp.id) AS total_compteurs,
                GROUP_CONCAT(cp.numero_compteur ORDER BY cp.numero_compteur SEPARATOR ", ") AS compteurs
         FROM clients cl
         LEFT JOIN compteurs cp ON cp.id_client = cl.id
         GROUP BY cl.id, cl.nom, cl.telephone, cl.telegram_chat_id, cl.adresse, cl.date_creation
         ORDER BY cl.id DESC'
    );
    return fetch_all_assoc($stmt);
}

function fetch_meters_list(): array {
    $conn = dashboard_db();
    $stmt = $conn->prepare(
        'SELECT cp.id, cp.numero_compteur, cp.date_installation, cl.nom AS client_nom, cp.id_client,
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
         ORDER BY cp.id DESC'
    );
    return fetch_all_assoc($stmt);
}

function fetch_factures_list(): array {
    $conn = dashboard_db();
    $stmt = $conn->prepare(
        'SELECT f.id, f.id_client, f.periode_debut, f.periode_fin,
                f.energie_totale, f.tarif_kwh, f.montant, f.statut,
                f.date_facture, f.date_echeance, f.date_paiement, f.rappel_envoye,
                CASE
                    WHEN f.statut = "non_payee"
                     AND f.date_echeance IS NOT NULL
                     AND f.date_echeance < NOW()
                    THEN 1 ELSE 0
                END AS en_retard,
                cl.nom AS client_nom
         FROM factures f
         LEFT JOIN clients cl ON cl.id = f.id_client
         ORDER BY f.date_facture DESC, f.id DESC'
    );
    return fetch_all_assoc($stmt);
}

function fetch_setting_value(string $key, string $default = ''): string {
    $conn = dashboard_db();
    $stmt = $conn->prepare('SELECT valeur FROM parametres_systeme WHERE cle = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return (string) ($row['valeur'] ?? $default);
}

function update_setting_value(string $key, string $value, string $description = ''): bool {
    $conn = dashboard_db();
    $stmt = $conn->prepare(
        'INSERT INTO parametres_systeme (cle, valeur, description)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), description = VALUES(description)'
    );
    $stmt->bind_param('sss', $key, $value, $description);
    return $stmt->execute();
}

function fetch_admin_dashboard_data(): array {
    $conn = dashboard_db();

    $totalClients = (int) scalar_query($conn, 'SELECT COUNT(*) FROM clients');
    $activeMeters = (int) scalar_query(
        $conn,
        'SELECT COUNT(*)
         FROM compteurs cp
         INNER JOIN (
             SELECT id_compteur, MAX(date_mesure) AS derniere_mesure
             FROM consommations
             GROUP BY id_compteur
         ) lm ON lm.id_compteur = cp.id
         WHERE lm.derniere_mesure >= (NOW() - INTERVAL 5 MINUTE)'
    );
    $totalPredictions = (int) scalar_query($conn, 'SELECT COUNT(*) FROM predictions');
    $totalRevenue = (float) scalar_query($conn, 'SELECT COALESCE(SUM(montant), 0) FROM factures');
    $avgVoltage = (float) scalar_query($conn, 'SELECT COALESCE(AVG(tension), 0) FROM consommations');
    $avgPower = (float) scalar_query($conn, 'SELECT COALESCE(AVG(puissance), 0) FROM consommations');
    $totalAlerts = (int) scalar_query($conn, 'SELECT COUNT(*) FROM alertes');
    $criticalAlerts = (int) scalar_query($conn, "SELECT COUNT(*) FROM alertes WHERE niveau = 'critique'");
    $unpaidInvoices = (int) scalar_query($conn, "SELECT COUNT(*) FROM factures WHERE statut = 'non_payee'");
    $inactiveMeters = (int) scalar_query($conn, "SELECT COUNT(*) FROM compteurs WHERE statut = 'inactif'");

    $historyStmt = $conn->prepare('SELECT DATE_FORMAT(date_mesure, "%d/%m %H:%i") AS label, tension, courant, puissance, energie FROM consommations ORDER BY date_mesure DESC LIMIT 8');
    $history = array_reverse(fetch_all_assoc($historyStmt));

    $recentStmt = $conn->prepare(
        'SELECT co.id, cl.nom AS client_nom, cp.numero_compteur, co.id_compteur, co.tension, co.courant, co.puissance, co.energie, co.date_mesure
         FROM consommations co
         LEFT JOIN compteurs cp ON cp.id = co.id_compteur
         LEFT JOIN clients cl ON cl.id = cp.id_client
         ORDER BY co.date_mesure DESC
         LIMIT 6'
    );
    $recentMeasurements = fetch_all_assoc($recentStmt);

    $billingStmt = $conn->prepare(
        'SELECT statut, COUNT(*) AS total, COALESCE(SUM(montant), 0) AS amount
         FROM factures
         GROUP BY statut'
    );
    $billingRows = fetch_all_assoc($billingStmt);
    $billingBreakdown = [
        'payee' => ['count' => 0, 'amount' => 0],
        'non_payee' => ['count' => 0, 'amount' => 0],
    ];
    foreach ($billingRows as $row) {
        $billingBreakdown[$row['statut']] = [
            'count' => (int) $row['total'],
            'amount' => (float) $row['amount'],
        ];
    }

    return [
        'totalClients' => $totalClients,
        'activeMeters' => $activeMeters,
        'totalPredictions' => $totalPredictions,
        'totalRevenue' => $totalRevenue,
        'avgVoltage' => $avgVoltage,
        'avgPower' => $avgPower,
        'totalAlerts' => $totalAlerts,
        'criticalAlerts' => $criticalAlerts,
        'unpaidInvoices' => $unpaidInvoices,
        'inactiveMeters' => $inactiveMeters,
        'history' => $history,
        'recentMeasurements' => $recentMeasurements,
        'billingBreakdown' => $billingBreakdown,
    ];
}

function fetch_client_dashboard_data(?int $clientId = null): array {
    $conn = dashboard_db();

    if ($clientId === null || $clientId <= 0) {
        $clientId = (int) scalar_query($conn, 'SELECT id FROM clients ORDER BY id ASC LIMIT 1');
    }

    $clientStmt = $conn->prepare('SELECT id, nom, telephone, adresse, date_creation FROM clients WHERE id = ? LIMIT 1');
    $clientStmt->bind_param('i', $clientId);
    $clientRows = fetch_all_assoc($clientStmt);
    $client = $clientRows[0] ?? null;

    if (!$client) {
        return [
            'client' => null,
            'meters' => [],
            'history' => [],
            'latestMeasurement' => null,
            'factures' => [],
            'latestPrediction' => null,
            'totals' => ['consumption' => 0, 'billed' => 0, 'meters' => 0],
        ];
    }

    $metersStmt = $conn->prepare('SELECT id, numero_compteur, statut, date_installation FROM compteurs WHERE id_client = ? ORDER BY id ASC');
    $metersStmt->bind_param('i', $clientId);
    $meters = fetch_all_assoc($metersStmt);

    $historyStmt = $conn->prepare(
        'SELECT co.id, co.id_compteur, DATE_FORMAT(co.date_mesure, "%d/%m %H:%i:%s") AS label,
                co.date_mesure, co.energie, co.puissance, co.tension, co.courant, cp.numero_compteur
         FROM consommations co
         INNER JOIN compteurs cp ON cp.id = co.id_compteur
         WHERE cp.id_client = ?
         ORDER BY co.date_mesure DESC
         LIMIT 20'
    );
    $historyStmt->bind_param('i', $clientId);
    $history = array_reverse(fetch_all_assoc($historyStmt));
    $latestMeasurement = $history ? $history[count($history) - 1] : null;

    $facturesStmt = $conn->prepare(
        'SELECT id, periode_debut, periode_fin, tarif_kwh,
                montant, energie_totale, statut, date_facture,
                date_echeance, date_paiement,
                CASE
                    WHEN statut = "non_payee"
                     AND date_echeance IS NOT NULL
                     AND date_echeance < NOW()
                    THEN 1 ELSE 0
                END AS en_retard
         FROM factures
         WHERE id_client = ?
         ORDER BY date_facture DESC
         LIMIT 5'
    );
    $facturesStmt->bind_param('i', $clientId);
    $factures = fetch_all_assoc($facturesStmt);

    $predictionStmt = $conn->prepare(
        'SELECT p.prediction, p.date_prediction, cp.numero_compteur
         FROM predictions p
         INNER JOIN compteurs cp ON cp.id = p.id_compteur
         WHERE cp.id_client = ?
         ORDER BY p.date_prediction DESC, p.id DESC
         LIMIT 1'
    );
    $predictionStmt->bind_param('i', $clientId);
    $predictionRows = fetch_all_assoc($predictionStmt);
    $latestPrediction = $predictionRows[0] ?? null;

    $totalConsumption = (float) scalar_query(
        $conn,
        'SELECT COALESCE(SUM(co.energie), 0) FROM consommations co INNER JOIN compteurs cp ON cp.id = co.id_compteur WHERE cp.id_client = ?',
        [$clientId],
        'i'
    );
    $totalBilled = (float) scalar_query($conn, 'SELECT COALESCE(SUM(montant), 0) FROM factures WHERE id_client = ?', [$clientId], 'i');

    return [
        'client' => $client,
        'meters' => $meters,
        'history' => $history,
        'latestMeasurement' => $latestMeasurement,
        'factures' => $factures,
        'latestPrediction' => $latestPrediction,
        'totals' => [
            'consumption' => $totalConsumption,
            'billed' => $totalBilled,
            'meters' => count($meters),
        ],
    ];
}
?>
