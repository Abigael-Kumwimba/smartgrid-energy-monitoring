<?php
function smartgrid_session_start(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function smartgrid_demo_users(): array {
    return [
        'admin' => [
            'username' => 'admin',
            'password' => 'admin123',
            'label' => 'Administrateur',
            'role' => 'admin',
            'client_id' => null,
        ],
        'client' => [
            'username' => 'client',
            'password' => 'client123',
            'label' => 'Client Abigael',
            'role' => 'client',
            'client_id' => 1,
        ],
        'client2' => [
            'username' => 'client2',
            'password' => 'client2123',
            'label' => 'Client Alice',
            'role' => 'client',
            'client_id' => 4,
        ],
    ];
}

function smartgrid_first_client_id(): int {
    require_once __DIR__ . '/dashboard_data.php';
    $conn = dashboard_db();
    $stmt = $conn->prepare('SELECT id FROM clients ORDER BY id ASC LIMIT 1');
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return (int) ($row['id'] ?? 0);
}

function smartgrid_login(string $role, string $username, string $password): bool {
    smartgrid_session_start();
    $users = smartgrid_demo_users();
    $matchedUser = null;

    foreach ($users as $user) {
        if (
            ($user['role'] ?? '') === $role
            && $username === $user['username']
            && $password === $user['password']
        ) {
            $matchedUser = $user;
            break;
        }
    }

    if (!$matchedUser) {
        return false;
    }

    $_SESSION['smartgrid_auth'] = [
        'role' => $matchedUser['role'],
        'username' => $username,
        'label' => $matchedUser['label'],
        'client_id' => $matchedUser['client_id'],
    ];

    return true;
}

function smartgrid_user(): ?array {
    smartgrid_session_start();
    return $_SESSION['smartgrid_auth'] ?? null;
}

function smartgrid_require_login(array $roles = []): array {
    $user = smartgrid_user();
    if (!$user) {
        header('Location: /smartgrid_dashboard/login.php');
        exit;
    }

    if ($roles && !in_array($user['role'], $roles, true)) {
        header('Location: /smartgrid_dashboard/login.php');
        exit;
    }

    return $user;
}

function smartgrid_logout(): void {
    smartgrid_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
?>
