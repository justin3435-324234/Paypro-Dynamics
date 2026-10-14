<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/csrf.php';
$pageTitle = 'Payroll Review'; $currentPage = 'payroll'; $basePath = '..';
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, e.employee_code, e.first_name, e.middle_name, e.last_name, e.email, d.name dept, pos.title postitle
  FROM payroll p JOIN employees e ON e.id=p.employee_id LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions pos ON pos.id=e.position_id WHERE p.id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { set_flash('error','Payroll not found.'); redirect(($_SESSION['role'] ?? '') === 'EMPLOYEE' ? 'my_payslip.php' : 'index.php'); }
// Linked employee accounts see only their own payroll; unlinked (shared/global) accounts may open any.
$myEid = (int)($_SESSION['employee_id'] ?? 0);
if (($_SESSION['role'] ?? '') === 'EMPLOYEE' && $myEid > 0 && $myEid !== (int)$p['employee_id']) {
    die('<h2>403 — Forbidden.</h2><p>You can only view your own payroll.</p>');
}
$items = $pdo->prepare("SELECT * FROM payroll_items WHERE payroll_id=? ORDER BY item_type, id");
$items->execute([$id]);
$items = $items->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="card">
<h2 style="margin-top:0">Payroll #<?= $p['id'] ?> — <?= e(trim($p['first_name'].' '.$p['last_name'])) ?> <span class="badge <?= $p['status']==='Paid'?'b-green':($p['status']==='Processed'?'b-blue':'b-grey') ?>"><?= e($p['status']) ?></span></h2>
<p><?= e($p['employee_code']) ?> · <?= e($p['dept'] ?? '-') ?> · <?= e($p['postitle'] ?? '-') ?><br>Period: <b><?= e($p['period_label']) ?></b> (<?= e($p['period_start']) ?> → <?= e($p['period_end']) ?>)</p>
<div class="grid grid-3">
<div class="card" style="background:#F8FAFC"><b>Earnings</b><br>Basic Pay: <?= peso($p['basic_salary']) ?><br>Regular Hours: <?= peso($p['regular_pay']) ?><br>Overtime: <?= peso($p['overtime_pay']) ?><br><b>Gross: <?= peso($p['gross_pay']) ?></b></div>
<div class="card" style="background:#F8FAFC"><b>Deductions</b><br>
<?php $deds = array_filter($items, fn($x)=>$x['item_type']==='deduction'); ?>
<?php if (!$deds): ?>— none —<?php else: foreach ($deds as $d): ?><?= e($d['description']) ?>: <?= peso($d['amount']) ?><br><?php endforeach; endif; ?>
<b>Total: <?= peso($p['total_deductions']) ?></b></div>
<div class="net-box">NET<br><?= peso($p['net_pay']) ?></div>
</div>
<br>
<div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap">
<a class="btn secondary" href="payslip.php?id=<?= $p['id'] ?>">View Payslip</a>
<a class="btn secondary" href="index.php">Back</a>
<?php if (($_SESSION['role'] ?? '') === 'MANAGER'): ?>
<?php if ($p['status']==='Draft'): ?>
<form method="post" action="process.php" onsubmit="return confirmAction('Process this payroll? It will be locked.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="to" value="Processed"><button class="btn success">Process Payroll</button></form>
<form method="post" action="delete.php" onsubmit="return confirmAction('Delete this DRAFT payroll?')"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn danger">Delete Draft</button></form>
<?php elseif ($p['status']==='Processed'): ?>
<form method="post" action="process.php" onsubmit="return confirmAction('Mark as PAID?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="to" value="Paid"><button class="btn success">Mark as Paid</button></form>
<form method="post" action="process.php" onsubmit="return confirmAction('Reopen to Draft? This is logged.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="to" value="Draft"><button class="btn secondary">Reopen to Draft</button></form>
<?php elseif ($p['status']==='Paid'): ?>
<form method="post" action="process.php" onsubmit="return confirmAction('Reopen PAID payroll to Draft? Logged.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="to" value="Draft"><button class="btn secondary">Reopen to Draft</button></form>
<?php endif; ?>
<?php endif; ?>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
