<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Employees land on My Payslip (no dashboard access); staff land on Dashboard.
function home_for($role) { return $role === 'EMPLOYEE' ? 'payroll/my_payslip.php' : 'dashboard.php'; }

// Already logged in?
if (!empty($_SESSION['user_id'])) redirect(home_for($_SESSION['role'] ?? ''));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($login === '' || $pass === '') {
        $error = 'Please enter username/email and password.';
    } else {
        try {
            $stmt = db()->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($pass, $user['password_hash'])) {
                $error = 'Invalid credentials. Please try again.';
            } elseif ($user['status'] !== 'active') {
                $error = 'Account is disabled. Contact administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['employee_id'] = $user['employee_id'];
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $upd = db()->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $upd->execute([hash('sha256', $token), $user['id']]);
                    setcookie('ppd_remember', $user['id'] . ':' . $token, time() + 30*86400, '/');
                }
                log_activity('LOGIN', 'User ' . $user['username'] . ' logged in');
                redirect(home_for($user['role']));
            }
        } catch (Exception $ex) {
            $error = 'Login failed. Please try again later.';
        }
    }
}
// Auto-login via remember cookie
if (empty($_SESSION['user_id']) && !empty($_COOKIE['ppd_remember'])) {
    [$uid, $tok] = explode(':', $_COOKIE['ppd_remember'] . ':');
    if ($uid && $tok) {
        try {
            $stmt = db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$uid]);
            $u = $stmt->fetch();
            if ($u && $u['remember_token'] === hash('sha256', $tok) && $u['status'] === 'active') {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $u['id'];
                $_SESSION['username'] = $u['username'];
                $_SESSION['role'] = $u['role'];
                $_SESSION['employee_id'] = $u['employee_id'];
                redirect(home_for($u['role']));
            }
        } catch (Exception $ex) {}
    }
}
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Pay Pro Dynamics</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<div class="auth-wrap">
  <form class="auth-card" method="post" data-validate>
    <h1>PAY PRO DYNAMICS</h1>
    <p class="auth-sub">Employee Payroll System<br><small>Smarter Payroll. Simpler Management.</small></p>
    <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <label>Username / Email</label>
    <input type="text" name="login" required value="<?= e($_POST['login'] ?? '') ?>" placeholder="admin">
    <br><br>
    <label>Password</label>
    <div style="display:flex;gap:6px"><input type="password" id="pw" name="password" required placeholder="••••••••">
    <button type="button" class="btn secondary" onclick="togglePw('pw')">Show</button></div>
    <br>
    <label style="font-weight:400"><input type="checkbox" name="remember" style="width:auto"> Remember Me</label>
    <br>
    <button class="btn" style="width:100%" type="submit">LOGIN</button>
    <p style="text-align:center"><a href="#" onclick="alert('Contact your administrator to reset your password.');return false;">Forgot Password?</a></p>
  </form>
</div>
<script src="assets/js/app.js"></script>
</body></html>
