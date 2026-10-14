<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['MANAGER']); // admins have no payroll access; employees use My Payslip instead
$pageTitle = 'Payroll'; $currentPage = 'payroll'; $basePath = '..';
$pdo = db();
$role = $_SESSION['role'] ?? '';
$myEmp = (int)($_SESSION['employee_id'] ?? 0);

$fEmp = (int)($_GET['employee'] ?? 0);
$fStatus = $_GET['status'] ?? '';
$where = []; $params = [];
if ($role === 'EMPLOYEE') { $where[] = "p.employee_id=?"; $params[] = $myEmp; }
elseif ($fEmp > 0) { $where[] = "p.employee_id=?"; $params[] = $fEmp; }
if (in_array($fStatus, ['Draft','Processed','Paid'])) { $where[] = "p.status=?"; $params[] = $fStatus; }
$w = $where ? 'WHERE '.implode(' AND ',$where) : '';

$stmt = $pdo->prepare("SELECT p.*, e.employee_code, e.first_name, e.last_name FROM payroll p JOIN employees e ON e.id=p.employee_id $w ORDER BY p.period_start DESC, p.created_at DESC LIMIT 200");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$emps = $pdo->query("SELECT id,employee_code,first_name,last_name FROM employees WHERE status='active' ORDER BY first_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="toolbar">
<form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
<?php if($role!=='EMPLOYEE'): ?><select name="employee"><option value="0">All employees</option>
<?php foreach($emps as $x): ?><option value="<?= $x['id'] ?>" <?= $fEmp==$x['id']?'selected':'' ?>><?= e($x['employee_code'].' '.$x['first_name'].' '.$x['last_name']) ?></option><?php endforeach; ?></select><?php endif; ?>
<select name="status"><option value="">All status</option>
<?php foreach(['Draft','Processed','Paid'] as $s): ?><option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
<button class="btn secondary" type="submit">Filter</button></form>
<?php if($role==='MANAGER'): ?><a class="btn" href="create.php">+ New Payroll</a><?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table>
<tr><th>ID</th><th>Employee</th><th>Period</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th><th>Actions</th></tr>
<?php if(!$rows): ?><tr><td colspan="8"><div class="empty">No payroll records. <?= $role==='MANAGER'?'<a href="create.php">Create the first payroll</a>.':'' ?></div></td></tr><?php endif; ?>
<?php foreach($rows as $p): ?><tr>
<td><?= $p['id'] ?></td><td><?= e($p['employee_code'].' '.$p['first_name'].' '.$p['last_name']) ?></td>
<td><?= e($p['period_label']) ?><br><small><?= e($p['period_start'].' → '.$p['period_end']) ?></small></td>
<td><?= peso($p['gross_pay']) ?></td><td><?= peso($p['total_deductions']) ?></td><td><b><?= peso($p['net_pay']) ?></b></td>
<td><span class="badge <?= $p['status']==='Paid'?'b-green':($p['status']==='Processed'?'b-blue':'b-grey') ?>"><?= e($p['status']) ?></span></td>
<td style="white-space:nowrap"><a class="btn small secondary" href="view.php?id=<?= $p['id'] ?>">Review</a>
<a class="btn small secondary" href="payslip.php?id=<?= $p['id'] ?>">Payslip</a></td>
</tr><?php endforeach; ?></table></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
