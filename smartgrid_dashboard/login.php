<?php
require_once __DIR__ . '/includes/auth.php';
smartgrid_session_start();

$existingUser = smartgrid_user();
if ($existingUser) {
    $destination = $existingUser['role'] === 'client' ? '/smartgrid_dashboard/client/index.php' : '/smartgrid_dashboard/admin/index.php';
    header('Location: ' . $destination);
    exit;
}

$error = null;
$selectedRole = $_POST['role'] ?? 'admin';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'admin';
    $selectedRole = $role;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (smartgrid_login($role, $username, $password)) {
        $destination = $role === 'client' ? '/smartgrid_dashboard/client/index.php' : '/smartgrid_dashboard/admin/index.php';
        header('Location: ' . $destination);
        exit;
    }

    $error = 'Identifiants invalides. Verifie le profil, le nom d\'utilisateur et le mot de passe.';
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connexion SmartGrid</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/AdminLTE-4.0.0-rc7/dist/css/adminlte.min.css">
  <style>
    :root{--green:#29d391;--blue:#49b8ff;--violet:#7c5cff;--orange:#ffb44d;--ink:#0f1020;--glass:rgba(18,20,36,.74);--line:rgba(255,255,255,.12)}
    *{box-sizing:border-box}body{min-height:100vh;margin:0;font-family:'Source Sans 3',sans-serif;color:#fff;background:#0f1020;overflow-x:hidden}.login-bg{min-height:100vh;display:grid;place-items:center;padding:28px;background:radial-gradient(circle at 12% 8%,rgba(124,92,255,.32),transparent 30rem),radial-gradient(circle at 88% 12%,rgba(73,184,255,.22),transparent 26rem),radial-gradient(circle at 84% 88%,rgba(41,211,145,.18),transparent 25rem),linear-gradient(145deg,#1f1b3d 0%,#0f1020 48%,#0b0c16 100%);position:relative}.login-bg::before{content:"";position:absolute;inset:18px;border-radius:28px;background:linear-gradient(135deg,rgba(255,255,255,.05),rgba(255,255,255,.015)),linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(180deg,rgba(255,255,255,.03) 1px,transparent 1px);background-size:100% 100%,72px 72px,72px 72px;border:1px solid rgba(255,255,255,.08);box-shadow:inset 0 0 90px rgba(124,92,255,.12);opacity:1}.login-bg::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 18% 78%,rgba(41,211,145,.18),transparent 18rem),radial-gradient(circle at 74% 24%,rgba(124,92,255,.22),transparent 22rem);pointer-events:none}.login-frame{position:relative;z-index:1;width:min(1120px,100%);min-height:660px;display:grid;grid-template-columns:1.05fr .95fr;border:1px solid rgba(255,255,255,.18);border-radius:28px;overflow:hidden;box-shadow:0 34px 90px rgba(0,0,0,.52);background:rgba(18,20,36,.58);backdrop-filter:blur(8px)}.welcome-panel{padding:54px;display:flex;flex-direction:column;justify-content:space-between;background:linear-gradient(135deg,rgba(31,33,59,.86),rgba(20,22,42,.56))}.brand-mark{width:52px;height:52px;display:grid;place-items:center;border-radius:16px;background:rgba(255,255,255,.11);border:1px solid var(--line);font-weight:900;font-size:1.2rem}.kicker{display:inline-flex;gap:8px;align-items:center;width:max-content;background:rgba(20,211,139,.16);border:1px solid rgba(20,211,139,.35);color:#d9ffed;border-radius:999px;padding:9px 14px;font-weight:700}.welcome-title{font-size:clamp(2.6rem,5vw,4.8rem);line-height:.94;font-weight:900;letter-spacing:0;margin:24px 0 18px;text-shadow:0 10px 34px rgba(0,0,0,.35)}.welcome-copy{max-width:520px;color:#dcece3;font-size:1.05rem}.mini-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:28px}.mini-stat{background:rgba(255,255,255,.09);border:1px solid var(--line);border-radius:18px;padding:14px}.mini-stat strong{display:block;font-size:1.1rem}.login-panel{padding:54px;display:flex;align-items:center;background:rgba(15,16,32,.74);border-left:1px solid rgba(255,255,255,.12);backdrop-filter:blur(14px)}.login-card{width:100%;max-width:430px;margin:auto}.login-heading{font-size:2rem;font-weight:900;margin-bottom:8px}.muted{color:#b9c6c0}.role-switch{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:22px 0}.role-option{position:relative;display:flex;align-items:center;justify-content:center;gap:8px;border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:13px 12px;background:rgba(255,255,255,.07);cursor:pointer;transition:.18s}.role-option input{position:absolute;opacity:0}.role-option:has(input:checked){background:linear-gradient(135deg,rgba(20,211,139,.28),rgba(123,44,255,.18));border-color:rgba(20,211,139,.68);box-shadow:0 12px 28px rgba(20,211,139,.16)}.form-field{position:relative;margin-bottom:18px}.form-field label{font-size:.86rem;color:#dcece3;font-weight:700;margin-bottom:7px}.form-control{height:52px;border-radius:999px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.09);color:#fff;padding-left:44px}.form-control:focus{background:rgba(255,255,255,.13);color:#fff;border-color:rgba(20,211,139,.75);box-shadow:0 0 0 .2rem rgba(20,211,139,.14)}.form-control::placeholder{color:#aebbb5}.field-icon{position:absolute;left:17px;bottom:14px;color:#b9c6c0}.submit-btn{width:100%;height:54px;border:0;border-radius:999px;color:#fff;font-weight:900;background:linear-gradient(90deg,#7c5cff,#49b8ff 48%,#29d391);box-shadow:0 18px 42px rgba(20,211,139,.22);transition:.18s}.submit-btn:hover{transform:translateY(-1px);filter:saturate(1.1)}.credentials{margin-top:20px;padding:16px;border:1px solid rgba(255,255,255,.12);border-radius:18px;background:rgba(255,255,255,.07)}code{color:#aaf7d3;background:rgba(0,0,0,.24);border-radius:8px;padding:2px 7px}.alert{border-radius:16px;border:0}.footer-pill{display:inline-flex;gap:8px;align-items:center;width:max-content;background:#fff;color:#123124;border-radius:999px;padding:10px 18px;font-weight:800;box-shadow:0 14px 34px rgba(0,0,0,.24)}@media(max-width:920px){.login-frame{grid-template-columns:1fr;min-height:auto}.login-panel{border-left:0;border-top:1px solid rgba(255,255,255,.12)}.welcome-panel,.login-panel{padding:34px}.mini-stats{grid-template-columns:1fr}}@media(max-width:540px){.login-bg{padding:14px}.login-bg::before{inset:8px;border-radius:22px}.login-frame{border-radius:22px}.role-switch{grid-template-columns:1fr}.welcome-title{font-size:2.4rem}}
  </style>
</head>
<body>
  <main class="login-bg">
    <section class="login-frame">
      <div class="welcome-panel">
        <div>
          <div class="brand-mark"><i class="bi bi-lightning-charge-fill"></i></div>
          <div class="kicker mt-4"><i class="bi bi-cpu"></i> ESP32 + PZEM + SmartGrid</div>
          <h1 class="welcome-title">Bienvenue<br>sur SmartGrid</h1>
          <div class="mini-stats">
            <div class="mini-stat"><strong>Temps reel</strong><span class="muted small">Mesures ESP32</span></div>
            <div class="mini-stat"><strong>Factures</strong><span class="muted small">Calcul kWh</span></div>
            <div class="mini-stat"><strong>Alertes</strong><span class="muted small">Telegram</span></div>
          </div>
        </div>
        <div class="footer-pill"><i class="bi bi-shield-check"></i> Acces securise</div>
      </div>

      <div class="login-panel">
        <div class="login-card">
          <div class="mb-4">
            <div class="muted text-uppercase small fw-bold">Authentification</div>
            <h2 class="login-heading">Se connecter</h2>
            <div class="muted">Choisissez votre espace puis entrez vos identifiants.</div>
          </div>

          <?php if ($error): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="post">
            <div class="role-switch">
              <label class="role-option">
                <input type="radio" name="role" value="admin" <?= $selectedRole === 'admin' ? 'checked' : '' ?>>
                <i class="bi bi-grid"></i><span>Admin</span>
              </label>
              <label class="role-option">
                <input type="radio" name="role" value="client" <?= $selectedRole === 'client' ? 'checked' : '' ?>>
                <i class="bi bi-person"></i><span>Client</span>
              </label>
            </div>

            <div class="form-field">
              <label for="username">Nom d'utilisateur</label>
              <i class="bi bi-person field-icon"></i>
              <input id="username" type="text" name="username" class="form-control" placeholder="admin ou client" required>
            </div>

            <div class="form-field">
              <label for="password">Mot de passe</label>
              <i class="bi bi-lock field-icon"></i>
              <input id="password" type="password" name="password" class="form-control" placeholder="Votre mot de passe" required>
            </div>

            <button class="submit-btn" type="submit">Acceder au dashboard</button>
          </form>

          <div class="credentials">
            <div class="fw-bold mb-2">Comptes de test</div>
            <div class="small mb-1">Admin : <code>admin</code> / <code>admin123</code></div>
            <div class="small mb-1">Client 1 : <code>client</code> / <code>client123</code></div>
            <div class="small">Client 2 : <code>client2</code> / <code>client2123</code></div>
          </div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>

