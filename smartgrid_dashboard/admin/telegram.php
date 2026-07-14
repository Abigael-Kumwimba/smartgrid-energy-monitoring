<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../smartgrid_app/config/telegram.php';
$user = smartgrid_require_login(['admin']);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = send_telegram_message(
        "<b>SmartGrid - Test Telegram</b>\nLe module de notification est connecte avec succes."
    );
    $message = $sent
        ? 'Message de test envoye avec succes.'
        : 'Echec du test. Verifie le token, le chat ID et la connexion internet.';
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notifications Telegram</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="/smartgrid_dashboard/assets/smartgrid-theme.css">
  <style>
    body{background:#0f1020;color:#fff;font-family:'Source Sans 3',sans-serif}.wrap{max-width:900px;margin:0 auto;padding:32px}.panel{background:#181a2f;border:1px solid rgba(255,255,255,.07);border-radius:24px;padding:24px;box-shadow:0 18px 48px rgba(0,0,0,.28)}.muted{color:#a4acc4}.step{background:rgba(73,184,255,.08);border-radius:18px;padding:16px;margin-bottom:12px}
  </style>
</head>
<body class="sg-admin">
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Notifications Telegram</h1><div class="muted">Module prevu pour les alertes critiques</div></div>
    <a class="btn btn-outline-light" href="/smartgrid_dashboard/admin/index.php">Dashboard</a>
  </div>
  <div class="panel">
    <?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <h2 class="h5">Role du module</h2>
    <p class="muted">Telegram servira a notifier automatiquement l'administrateur lorsqu'une anomalie importante est detectee.</p>
    <div class="mb-3">
      <span class="badge <?= telegram_is_configured() ? 'text-bg-success' : 'text-bg-warning' ?>">
        <?= telegram_is_configured() ? 'Configuration detectee' : 'Configuration a completer' ?>
      </span>
    </div>
    <div class="step">Sous-tension ou surtension detectee</div>
    <div class="step">Surcharge ou surintensite</div>
    <div class="step">Facture generee ou facture non payee</div>
    <form method="post" class="mt-3">
      <button class="btn btn-primary" type="submit">Envoyer un message test</button>
    </form>
    <p class="mt-3 muted">Les notifications automatiques sont envoyees lorsqu'une nouvelle alerte est creee par l'API ESP32.</p>
  </div>
</div>
</body>
</html>
