<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
try {
    if (!empty($_SESSION['user_id'])) {
        $stmt = db()->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        log_activity('LOGOUT', 'User logged out');
    }
} catch (Exception $ex) {}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
setcookie('ppd_remember', '', time() - 3600, '/');
session_destroy();
session_start();
set_flash('success', 'You have been logged out.');
header('Location: login.php');
exit;
