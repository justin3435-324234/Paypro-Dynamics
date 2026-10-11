<?php
require_once __DIR__ . '/includes/auth.php';
requireRole(['ADMIN','MANAGER']); // employees have no dashboard access
$pageTitle = 'Dashboard'; $currentPage = 'dashboard'; $basePath = '.';
$pdo = db();

// Company stats
$total    = (int)$pdo->query("SELECT COUNT(*) c FROM employees")->fetch()['c'];
$active   = (int)$pdo->query("SELECT COUNT(*) c FROM employees WHERE status='active'")->fetch()['c'];
$inactive = $total - $active;
$curTotal = (float)($pdo->query("SELECT COALESCE(SUM(net_pay),0) s FROM payroll WHERE status IN ('Processed','Paid') AND period_start >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetch()['s'] ?? 0);
$pending  = (int)$pdo->query("SELECT COUNT(*) c FROM payroll WHERE status='Draft'")->fetch()['c'];

$recentPay = $pdo->query("SELECT p.*, CONCAT(e.first_name,' ',e.last_name) emp, e.employee_code
  FROM payroll p JOIN employees e ON e.id=p.employee_id ORDER BY p.created_at DESC LIMIT 8")->fetchAll();
$recentEmp = $pdo->query("SELECT e.*, d.name dept, pos.title postitle FROM employees e
  LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions pos ON pos.id=e.position_id
  ORDER BY e.created_at DESC LIMIT 5")->fetchAll();
// Monthly payroll chart (last 6 months net)
$chart = $pdo->query("SELECT DATE_FORMAT(period_start,'%b %Y') m, SUM(net_pay) s FROM payroll
  WHERE status IN ('Processed','Paid') AND period_start >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
  GROUP BY DATE_FORMAT(period_start,'%Y-%m') ORDER BY MIN(period_start)")->fetchAll();
$chartLabels = json_encode(array_column($chart, 'm'));
$chartData = json_encode(array_map('floatval', array_column($chart, 's')));
// Dept distribution
$depts = $pdo->query("SELECT d.name, COUNT(e.id) c FROM departments d LEFT JOIN employees e ON e.department_id=d.id AND e.status='active' GROUP BY d.id, d.name")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="grid grid-4">
  <div class="card stat"><div class="lbl">Total Employees</div><div class="num"><?= $total ?></div><a href="employees/index.php">View all</a></div>
  <div class="card stat"><div class="lbl">Active / Inactive</div><div class="num"><?= $active ?> <small style="color:#64748B">/ <?= $inactive ?></small></div><a href="employees/index.php?status=active">Active list</a></div>
  <div class="card stat"><div class="lbl">Payroll This Month</div><div class="num"><?= peso($curTotal) ?></div><a href="reports/index.php">Reports</a></div>
  <div class="card stat"><div class="lbl">Pending (Draft)</div><div class="num"><?= $pending ?></div><a href="payroll/index.php?status=Draft">Review</a></div>
</div>
<br>
<div class="card no-print">
  <b>Quick Actions</b>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
    <a class="btn small" href="employees/add.php">+ Add Employee</a>
    <a class="btn small" href="attendance/index.php">Record Attendance</a>
    <a class="btn small" href="payroll/create.php">New Payroll</a>
    <a class="btn small secondary" href="reports/index.php?tab=payroll&from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-t') ?>&generate=1">View Reports</a>
  </div>
</div>
<br>
<div class="grid grid-2">
  <div class="card">
    <h3>Payroll Summary</h3>
    <?php if (!$chart): ?><div class="empty">No processed payroll yet. Create payroll to see the chart.</div>
    <?php else: ?><canvas id="payChart" height="140"></canvas><?php endif; ?>
  </div>
  <div class="card">
    <h3>Employee Distribution by Department</h3>
    <?php if (!$depts): ?><div class="empty">No departments yet.</div><?php else: ?>
    <table style="min-width:0"><tr><th>Department</th><th>Active</th></tr>
    <?php foreach ($depts as $d): ?><tr><td><?= e($d['name']) ?></td><td><?= (int)$d['c'] ?></td></tr><?php endforeach; ?></table>
    <?php endif; ?>
  </div>
</div>
<br>
<div class="card">
  <h3>Recent Payroll Transactions</h3>
  <?php if (!$recentPay): ?><div class="empty">No payroll records yet. <a href="payroll/create.php">Create payroll</a></div><?php else: ?>
  <div class="table-responsive"><table>
    <tr><th>Employee</th><th>Period</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th></tr>
    <?php foreach ($recentPay as $p): ?>
    <tr><td><?= e($p['emp']) ?> <small>(<?= e($p['employee_code']) ?>)</small></td>
      <td><?= e($p['period_label']) ?></td><td><?= peso($p['gross_pay']) ?></td>
      <td><?= peso($p['total_deductions']) ?></td><td><b><?= peso($p['net_pay']) ?></b></td>
      <td><span class="badge <?= $p['status']==='Paid'?'b-green':($p['status']==='Processed'?'b-blue':'b-grey') ?>"><?= e($p['status']) ?></span></td></tr>
    <?php endforeach; ?></table></div>
  <?php endif; ?>
</div>
<br>
<div class="card">
  <h3>Recent Employees</h3>
  <?php if (!$recentEmp): ?><div class="empty">No employees yet.</div><?php else: ?>
  <div class="table-responsive"><table>
    <tr><th>Code</th><th>Name</th><th>Department</th><th>Position</th><th>Status</th></tr>
    <?php foreach ($recentEmp as $x): ?><tr>
      <td><?= e($x['employee_code']) ?></td><td><a href="employees/view.php?id=<?= $x['id'] ?>"><?= e($x['first_name'].' '.$x['last_name']) ?></a></td>
      <td><?= e($x['dept'] ?? '-') ?></td><td><?= e($x['postitle'] ?? '-') ?></td>
      <td><span class="badge <?= $x['status']==='active'?'b-green':'b-grey' ?>"><?= e($x['status']) ?></span></td></tr>
    <?php endforeach; ?></table></div>
  <?php endif; ?>
</div>
<?php
$extraJs = $chart ? '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>new Chart(document.getElementById("payChart"),{type:"bar",data:{labels:'.$chartLabels.',datasets:[{data:'.$chartData.',backgroundColor:"#22C55E"}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});</script>' : '';
require_once __DIR__ . '/includes/footer.php';
