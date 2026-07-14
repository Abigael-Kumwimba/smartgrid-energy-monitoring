<?php
require_once __DIR__ . '/includes/auth.php';
smartgrid_logout();
header('Location: /smartgrid_dashboard/login.php');
exit;
?>
