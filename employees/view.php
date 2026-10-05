<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pageTitle = 'Employee Profile'; $currentPage = 'employees'; $basePath = '..';
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
// EMPLOYEE can only view self
if (($_SESSION['role'] ?? '') === 'EMPLOYEE' && (int)($_SESSION['employee_id'] ?? 0) !== $id) {
    die('<h2>403 — Forbidden.</h2><p>You can only view your own profile.</p>');
}
$stmt = $pdo->prepare("SELECT e.*, d.name dept, p.title postitle FROM employees e
  LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions p ON p.id=e.position_id WHERE e.id=?");
$stmt->execute([$id]);
$emp = $stmt->fetch();
if (!$emp) { set_flash('error','Employee not found.'); redirect('index.php'); }
$pay = $pdo->prepare("SELECT * FROM payroll WHERE employee_id=? ORDER BY period_start DESC LIMIT 10");
$pay->execute([$id]);
$payrolls = $pay->fetchAll();
$att = $pdo->prepare("SELECT * FROM attendance WHERE employee_id=? ORDER BY date DESC LIMIT 10");
$att->execute([$id]);
$atts = $att->fetchAll();
$ded = $pdo->prepare("SELECT ed.*, dt.name tname FROM employee_deductions ed JOIN deduction_types dt ON dt.id=ed.deduction_type_id WHERE employee_id=? ORDER BY deduction_date DESC LIMIT 10");
$ded->execute([$id]);
$deds = $ded->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
$full = trim($emp['first_name'].' '.$emp['middle_name'].' '.$emp['last_name']);
?>
<div class="card">
  <h2 style="margin-top:0"><?= e($full) ?> <small style="color:#64748B"><?= e($emp['employee_code']) ?></small></h2>
  <p><span class="badge <?= $emp['status']==='active'?'b-green':'b-grey' ?>"><?= e($emp['status']) ?></span>
  <span class="badge b-blue"><?= e($emp['employment_status']) ?></span>
  <span class="badge b-grey"><?= e($emp['salary_type']) ?></span></p>
  <div class="grid grid-3">
    <div><b>Contact</b><br><?= e($emp['email']) ?><br><?= e($emp['phone'] ?: '-') ?><br><small><?= e($emp['address'] ?: '') ?></small></div>
    <div><b>Employment</b><br>Dept: <?= e($emp['dept'] ?? '-') ?><br>Position: <?= e($emp['postitle'] ?? '-') ?><br>Hired: <?= e($emp['date_hired']) ?></div>
    <div><b>Salary</b><br><?= $emp['salary_type']==='hourly' ? peso($emp['hourly_rate']).' / hour' : peso($emp['basic_salary']).' / month' ?></div>
  </div>
  <br>
  <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (in_array($_SESSION['role'],['ADMIN','MANAGER'])): ?>
    <a class="btn small" href="edit.php?id=<?= $id ?>">Edit Employee</a>
    <?php if ($_SESSION['role']==='ADMIN'): ?><a class="btn small danger" href="delete.php?id=<?= $id ?>">Deactivate Employee</a><?php endif; ?>
    <?php endif; ?>
    <a class="btn small secondary" href="<?= ($_SESSION['role'] ?? '') === 'EMPLOYEE' ? '../payroll/my_payslip.php' : '../payroll/index.php?employee=' . $id ?>">View Payroll History</a>
    <button class="btn small secondary" onclick="window.print()">Print Employee Information</button>
  </div>
</div>
<br>
<div class="grid grid-2">
<div class="card"><h3>Payroll History</h3>
<?php if (!$payrolls): ?><div class="empty">No payroll yet.</div><?php else: ?>
<table style="min-width:0"><tr><th>Period</th><th>Net</th><th>Status</th></tr>
<?php foreach ($payrolls as $p): ?><tr><td><a href="../payroll/view.php?id=<?= $p['id'] ?>"><?= e($p['period_label']) ?></a></td><td><?= peso($p['net_pay']) ?></td><td><?= e($p['status']) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<div class="card"><h3>Attendance Summary</h3>
<?php if (!$atts): ?><div class="empty">No attendance yet.</div><?php else: ?>
<table style="min-width:0"><tr><th>Date</th><th>Status</th><th>Hrs</th></tr>
<?php foreach ($atts as $a): ?><tr><td><?= e($a['date']) ?></td><td><?= e($a['status']) ?></td><td><?= $a['regular_hours'] + $a['overtime_hours'] ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
</div>
<br>
<div class="card"><h3>Deduction Summary</h3>
<?php if (!$deds): ?><div class="empty">No deductions.</div><?php else: ?>
<table style="min-width:0"><tr><th>Date</th><th>Type</th><th>Amount</th><th>Status</th></tr>
<?php foreach ($deds as $d): ?><tr><td><?= e($d['deduction_date']) ?></td><td><?= e($d['tname']) ?></td><td><?= peso($d['amount']) ?></td><td><?= e($d['status']) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
