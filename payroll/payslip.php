<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pageTitle = 'Payslip'; $currentPage = 'payroll'; $basePath = '..';
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, e.*, d.name dept, pos.title postitle FROM payroll p JOIN employees e ON e.id=p.employee_id LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions pos ON pos.id=e.position_id WHERE p.id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) {
    $back = ($_SESSION['role'] ?? '') === 'EMPLOYEE' ? 'my_payslip.php' : 'index.php';
    die('<div style="font-family:sans-serif;padding:30px"><h2>Payslip not found.</h2><p>This payroll record may have been deleted.</p><p><a href="' . e($back) . '">Back</a></p></div>');
}
// Linked employee accounts see only their own payslip; unlinked (shared/global) accounts may open any.
$myEid = (int)($_SESSION['employee_id'] ?? 0);
if (($_SESSION['role'] ?? '') === 'EMPLOYEE' && $myEid > 0 && $myEid !== (int)$p['employee_id']) die('403 — Forbidden.');
$items = $pdo->prepare("SELECT * FROM payroll_items WHERE payroll_id=? ORDER BY item_type, id");
$items->execute([$id]);
$items = $items->fetchAll();
$earn = array_filter($items, fn($x)=>$x['item_type']==='earning');
$ded  = array_filter($items, fn($x)=>$x['item_type']==='deduction');
$company = get_setting('company_name','Pay Pro Dynamics Inc.');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="card payslip">
<div class="payslip-head">
<h2 style="margin:0"><?= e(strtoupper($company)) ?></h2>
<p style="margin:4px 0;color:#64748B"><?= e(get_setting('company_address','')) ?> · <?= e(get_setting('contact_number','')) ?></p>
<h3 style="margin:8px 0">PAYSLIP — <?= e($p['period_label']) ?></h3>
</div>
<div class="split">
<div><b>Employee:</b> <?= e($p['first_name'].' '.$p['last_name']) ?><br><b>ID:</b> <?= e($p['employee_code']) ?><br><b>Email:</b> <?= e($p['email']) ?></div>
<div><b>Department:</b> <?= e($p['dept'] ?? '-') ?><br><b>Position:</b> <?= e($p['postitle'] ?? '-') ?><br><b>Period:</b> <?= e($p['period_start']) ?> → <?= e($p['period_end']) ?> (<?= e($p['status']) ?>)</div>
</div>
<br>
<div class="split">
<div class="card" style="background:#F8FAFC"><b>EARNINGS</b><br>
Basic Pay: <?= peso($p['basic_salary']) ?><br>
Regular Hours: <?= peso($p['regular_pay']) ?><br>
Overtime Pay (<?= $p['overtime_hours'] ?>h): <?= peso($p['overtime_pay']) ?><br><b>Gross Pay: <?= peso($p['gross_pay']) ?></b></div>
<div class="card" style="background:#F8FAFC"><b>DEDUCTIONS</b><br>
<?php if (!$ded): ?>— none —<br><?php else: foreach ($ded as $d): ?><?= e($d['description']) ?>: <?= peso($d['amount']) ?><br><?php endforeach; endif; ?>
<b>TOTAL DEDUCTIONS: <?= peso($p['total_deductions']) ?></b></div>
</div>
<br>
<div class="net-box">NET PAY: <?= peso($p['net_pay']) ?></div>
<br>
<div class="no-print" style="display:flex;gap:8px">
<button class="btn" onclick="window.print()">Print Payslip</button>
<button class="btn secondary" onclick="window.print()">Download / Save as PDF (via print dialog)</button>
<a class="btn secondary" href="<?= ($_SESSION['role'] ?? '') === 'EMPLOYEE' ? 'my_payslip.php' : 'view.php?id=' . $p['id'] ?>">Back</a>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
