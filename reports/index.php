<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']); // employees have no reports access
$pageTitle = 'Reports'; $currentPage = 'reports'; $basePath = '..';
$pdo = db();
$role = $_SESSION['role'] ?? '';
$myEmp = (int)($_SESSION['employee_id'] ?? 0);

$tab = $_GET['tab'] ?? 'payroll';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$fDept = (int)($_GET['dept'] ?? 0);
$fEmp = (int)($_GET['employee'] ?? 0);
$fStatus = $_GET['status'] ?? '';
$generated = isset($_GET['generate']);

$emps = $pdo->query("SELECT id,employee_code,first_name,last_name FROM employees ORDER BY first_name")->fetchAll();
$depts = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$rows = [];

if ($generated) {
    if ($role === 'EMPLOYEE') $fEmp = $myEmp;
    try {
        if ($tab === 'payroll' || $tab === 'monthly') {
            $sql = "SELECT p.*, e.employee_code, e.first_name, e.last_name, d.name dept FROM payroll p JOIN employees e ON e.id=p.employee_id LEFT JOIN departments d ON d.id=e.department_id WHERE p.period_start>=? AND p.period_end<=?";
            $pr = [$from, $to];
            if ($fDept > 0) { $sql .= " AND e.department_id=?"; $pr[] = $fDept; }
            if ($fEmp > 0) { $sql .= " AND p.employee_id=?"; $pr[] = $fEmp; }
            if (in_array($fStatus,['Draft','Processed','Paid'])) { $sql .= " AND p.status=?"; $pr[] = $fStatus; }
            $sql .= " ORDER BY p.period_start DESC";
            $s = $pdo->prepare($sql); $s->execute($pr); $rows = $s->fetchAll();
        } elseif ($tab === 'history') {
            $s = $pdo->prepare("SELECT p.*, e.employee_code FROM payroll p JOIN employees e ON e.id=p.employee_id WHERE p.employee_id=? AND p.period_start>=? AND p.period_end<=? ORDER BY p.period_start DESC");
            $s->execute([$fEmp,$from,$to]); $rows = $s->fetchAll();
        } elseif ($tab === 'employees') {
            $sql = "SELECT e.*, d.name dept, p.title postitle FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions p ON p.id=e.position_id WHERE 1=1";
            $pr = [];
            if ($fDept > 0) { $sql .= " AND e.department_id=?"; $pr[] = $fDept; }
            if ($fStatus === 'active' || $fStatus === 'inactive') { $sql .= " AND e.status=?"; $pr[] = $fStatus; }
            $sql .= " ORDER BY e.last_name";
            $s = $pdo->prepare($sql); $s->execute($pr); $rows = $s->fetchAll();
        } elseif ($tab === 'attendance') {
            $sql = "SELECT a.*, e.employee_code, e.first_name, e.last_name FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.date BETWEEN ? AND ?";
            $pr = [$from,$to];
            if ($fEmp > 0) { $sql .= " AND a.employee_id=?"; $pr[] = $fEmp; }
            if ($fDept > 0) { $sql .= " AND e.department_id=?"; $pr[] = $fDept; }
            $sql .= " ORDER BY a.date DESC";
            $s = $pdo->prepare($sql); $s->execute($pr); $rows = $s->fetchAll();
        } elseif ($tab === 'deductions') {
            $sql = "SELECT ed.*, e.employee_code, dt.name tname FROM employee_deductions ed JOIN employees e ON e.id=ed.employee_id JOIN deduction_types dt ON dt.id=ed.deduction_type_id WHERE ed.deduction_date BETWEEN ? AND ?";
            $pr = [$from,$to];
            if ($fEmp > 0) { $sql .= " AND ed.employee_id=?"; $pr[] = $fEmp; }
            $sql .= " ORDER BY ed.deduction_date DESC";
            $s = $pdo->prepare($sql); $s->execute($pr); $rows = $s->fetchAll();
        }
    } catch (Exception $ex) { set_flash('error', $ex->getMessage()); }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
$tabs = ['payroll'=>'Payroll Summary','history'=>'Employee History','employees'=>'Employee List','attendance'=>'Attendance','deductions'=>'Deductions','monthly'=>'Monthly Payroll'];
?>
<div class="toolbar no-print" style="gap:6px">
<?php foreach ($tabs as $k=>$lbl): ?><a class="btn small <?= $tab===$k?'':'secondary' ?>" href="?tab=<?= $k ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&generate=1"><?= e($lbl) ?></a><?php endforeach; ?>
</div>
<div class="card no-print">
<form method="get">
<input type="hidden" name="tab" value="<?= e($tab) ?>">
<div style="display:flex;gap:8px;flex-wrap:wrap">
<div><label>From</label><input type="date" name="from" value="<?= e($from) ?>"></div>
<div><label>To</label><input type="date" name="to" value="<?= e($to) ?>"></div>
<?php if ($role!=='EMPLOYEE'): ?>
<div><label>Dept</label><select name="dept"><option value="0">All</option><?php foreach($depts as $d): ?><option value="<?= $d['id'] ?>" <?= $fDept==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Employee</label><select name="employee"><option value="0">All</option><?php foreach($emps as $x): ?><option value="<?= $x['id'] ?>" <?= $fEmp==$x['id']?'selected':'' ?>><?= e($x['employee_code'].' '.$x['first_name']) ?></option><?php endforeach; ?></select></div>
<?php endif; ?>
<div><label>Status</label><select name="status"><option value="">All</option>
<?php foreach(['Draft','Processed','Paid','active','inactive'] as $s): ?><option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
</div><br>
<button class="btn" type="submit" name="generate" value="1">Generate Report</button>
<?php if ($generated && $rows): ?>
<button class="btn secondary" type="button" onclick="window.print()">Print</button>
<a class="btn secondary" href="export.php?<?= http_build_query(array_merge($_GET,['generate'=>1])) ?>">Export CSV</a>
<?php endif; ?>
</form>
</div>
<br>
<div class="card">
<h3 style="margin-top:0"><?= e($tabs[$tab] ?? 'Report') ?> <?= $generated ? '— ' . count($rows) . ' row(s)' : '' ?></h3>
<?php if (!$generated): ?><div class="empty">Set filters and click Generate Reports.</div>
<?php elseif (!$rows): ?><div class="empty">No records found.</div>
<?php elseif ($tab==='employees'): ?>
<div class="table-responsive"><table><tr><th>Code</th><th>Name</th><th>Dept</th><th>Position</th><th>Status</th><th>Salary</th></tr>
<?php foreach($rows as $r): ?><tr><td><?= e($r['employee_code']) ?></td><td><?= e($r['first_name'].' '.$r['last_name']) ?></td><td><?= e($r['dept']??'-') ?></td><td><?= e($r['postitle']??'-') ?></td><td><?= e($r['status']) ?></td><td><?= peso($r['basic_salary']) ?></td></tr><?php endforeach; ?></table></div>
<?php elseif ($tab==='attendance'): ?>
<div class="table-responsive"><table><tr><th>Date</th><th>Employee</th><th>Status</th><th>Reg/OT/Late</th></tr>
<?php foreach($rows as $r): ?><tr><td><?= e($r['date']) ?></td><td><?= e($r['employee_code'].' '.$r['first_name']) ?></td><td><?= e($r['status']) ?></td><td><?= $r['regular_hours'] ?>/<?= $r['overtime_hours'] ?>/<?= $r['late_hours'] ?></td></tr><?php endforeach; ?></table></div>
<?php elseif ($tab==='deductions'): ?>
<div class="table-responsive"><table><tr><th>Date</th><th>Employee</th><th>Type</th><th>Amount</th><th>Status</th></tr>
<?php $t=0; foreach($rows as $r): $t+=$r['amount']; ?><tr><td><?= e($r['deduction_date']) ?></td><td><?= e($r['employee_code']) ?></td><td><?= e($r['tname']) ?></td><td><?= peso($r['amount']) ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?>
<tr><td colspan="3"><b>Total</b></td><td colspan="2"><b><?= peso($t) ?></b></td></tr></table></div>
<?php else: ?>
<div class="table-responsive"><table><tr><th>Employee</th><th>Period</th><th>Gross</th><th>Ded</th><th>Net</th><th>Status</th></tr>
<?php $g=$d=$n=0; foreach($rows as $r): $g+=$r['gross_pay']; $d+=$r['total_deductions']; $n+=$r['net_pay']; ?>
<tr><td><?= e(($r['employee_code']??'').' '.($r['first_name']??'')) ?></td><td><?= e($r['period_label'] ?? ($r['period_start'].'→'.$r['period_end'])) ?></td><td><?= peso($r['gross_pay']) ?></td><td><?= peso($r['total_deductions']) ?></td><td><b><?= peso($r['net_pay']) ?></b></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?>
<tr><td colspan="2"><b>Totals</b></td><td><b><?= peso($g) ?></b></td><td><b><?= peso($d) ?></b></td><td><b><?= peso($n) ?></b></td><td></td></tr></table></div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
