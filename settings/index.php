<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']); // employees have no settings access
$pageTitle = 'Settings'; $currentPage = 'settings'; $basePath = '..';
$pdo = db();
$role = $_SESSION['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $section = $_POST['section'] ?? '';
        if ($section === 'company' && $role === 'ADMIN') {
            foreach (['company_name','company_address','contact_number','company_email'] as $k) {
                $v = trim($_POST[$k] ?? '');
                $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$k,$v]);
            }
            log_activity('SET_COMPANY','Updated company info');
            set_flash('success','Company information saved.');
        } elseif ($section === 'payroll' && in_array($role,['ADMIN','MANAGER'])) {
            $h = max(1,min(16,(float)($_POST['default_working_hours'] ?? 8)));
            $r = max(0,(float)($_POST['default_overtime_rate'] ?? 150));
            $f = in_array($_POST['payroll_frequency'] ?? '', ['weekly','semi-monthly','monthly']) ? $_POST['payroll_frequency'] : 'semi-monthly';
            foreach ([['default_working_hours',$h],['default_overtime_rate',$r],['payroll_frequency',$f]] as [$k,$v])
                $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$k,$v]);
            log_activity('SET_PAYROLL','Updated payroll defaults');
            set_flash('success','Payroll settings saved.');
        } elseif ($section === 'password') {
            $cur = $_POST['current_password'] ?? ''; $new = $_POST['new_password'] ?? '';
            if (strlen($new) < 8) throw new Exception('New password must be at least 8 characters.');
            $st = $pdo->prepare("SELECT * FROM users WHERE id=?");
            $st->execute([$_SESSION['user_id']]);
            $u = $st->fetch();
            if (!password_verify($cur, $u['password_hash'])) throw new Exception('Current password is incorrect.');
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT), $_SESSION['user_id']]);
            log_activity('SET_PASSWORD','User changed own password');
            set_flash('success','Password updated.');
        } else { throw new Exception('Not allowed for your role.'); }
    } catch (Exception $ex) { set_flash('error',$ex->getMessage()); }
    header('Location: index.php'); exit;
}

function S($k,$d='') { return get_setting($k,$d); }
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="grid grid-2">
<div class="card"><h3>Company Information <?= $role!=='ADMIN' ? '<small>(Admin only)</small>' : '' ?></h3>
<form method="post"><input type="hidden" name="section" value="company">
<label>Company Name</label><input name="company_name" value="<?= e(S('company_name')) ?>" <?= $role!=='ADMIN'?'disabled':'' ?>>
<br><br><label>Address</label><input name="company_address" value="<?= e(S('company_address')) ?>" <?= $role!=='ADMIN'?'disabled':'' ?>>
<br><br><label>Contact</label><input name="contact_number" value="<?= e(S('contact_number')) ?>" <?= $role!=='ADMIN'?'disabled':'' ?>>
<br><br><label>Email</label><input name="company_email" value="<?= e(S('company_email')) ?>" <?= $role!=='ADMIN'?'disabled':'' ?>>
<br><br><?php if($role==='ADMIN'): ?><button class="btn" type="submit">Save Company Info</button><?php endif; ?></form></div>

<div class="card"><h3>Payroll Settings</h3>
<form method="post"><input type="hidden" name="section" value="payroll">
<label>Default Working Hours / day</label><input type="number" step="0.5" min="1" max="16" name="default_working_hours" value="<?= e(S('default_working_hours','8')) ?>">
<br><br><label>Default Overtime Rate (₱/hr)</label><input type="number" step="0.01" min="0" name="default_overtime_rate" value="<?= e(S('default_overtime_rate','150')) ?>">
<br><br><label>Payroll Frequency</label><select name="payroll_frequency">
<?php foreach(['weekly','semi-monthly','monthly'] as $f): ?><option <?= S('payroll_frequency')===$f?'selected':'' ?>><?= $f ?></option><?php endforeach; ?></select>
<br><br><button class="btn" type="submit">Save Payroll Settings</button></form></div>

<div class="card"><h3>User Profile</h3>
<p>Logged in as <b><?= e($_SESSION['username'] ?? '') ?></b> (<?= e($role) ?>)</p></div>

<div class="card"><h3>Security — Change Password</h3>
<form method="post" data-validate><input type="hidden" name="section" value="password">
<label>Current Password *</label><input type="password" name="current_password" required>
<br><br><label>New Password (8+ chars) *</label><input type="password" name="new_password" required minlength="8">
<br><br><button class="btn" type="submit">Update Password</button></form></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
