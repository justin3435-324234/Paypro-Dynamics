<?php
// Payslip finder — shared/global login: anyone logged in can search any
// employee name to generate and print payslips.
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pageTitle = 'My Payslip'; $currentPage = 'mypayslip'; $basePath = '..';
$pdo = db();
$role = $_SESSION['role'] ?? '';
$myEmp = (int)($_SESSION['employee_id'] ?? 0);

$q = trim($_GET['q'] ?? '');
$empId = (int)($_GET['emp'] ?? 0);
$notice = '';
$candidates = [];
$periods = [];
$selected = null;

if ($q !== '') {
    $st = $pdo->prepare("SELECT id, employee_code, first_name, middle_name, last_name FROM employees WHERE employee_code LIKE ? OR first_name LIKE ? OR last_name LIKE ? ORDER BY first_name LIMIT 10");
    $st->execute(["%$q%", "%$q%", "%$q%"]);
    $candidates = $st->fetchAll();
    if (!$candidates) $notice = 'No employee found. Try another name or code.';
    elseif (count($candidates) === 1 && $empId <= 0) $empId = (int)$candidates[0]['id'];
}

if ($empId > 0) {
    $st = $pdo->prepare("SELECT id, employee_code, first_name, middle_name, last_name FROM employees WHERE id=?");
    $st->execute([$empId]);
    $selected = $st->fetch();
    if ($selected) {
        $p = $pdo->prepare("SELECT id, period_label, period_start, period_end, net_pay, status FROM payroll WHERE employee_id=? ORDER BY period_start DESC");
        $p->execute([$empId]);
        $periods = $p->fetchAll();
    }
}

function cand_name($c) { return trim(($c['first_name'] ?? '') . ' ' . ($c['middle_name'] ?? '') . ' ' . ($c['last_name'] ?? '')); }

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="card">
  <h3 style="margin-top:0">Find Payslip</h3>
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
    <input type="search" name="q" placeholder="<?= $role === 'EMPLOYEE' ? 'Type your name or employee code…' : 'Search employee name or code…' ?>" value="<?= e($q) ?>" style="max-width:340px">
    <button class="btn" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="btn secondary" href="my_payslip.php">Clear</a><?php endif; ?>
  </form>
  <?php if ($role === 'EMPLOYEE' && $myEmp > 0): ?><a class="btn small secondary" href="../employees/view.php?id=<?= $myEmp ?>">View My Profile</a><?php endif; ?>
</div>
<br>
<?php if ($notice): ?><div class="alert alert-warning"><?= e($notice) ?></div><?php endif; ?>

<?php if ($q !== '' && count($candidates) > 1): ?>
<div class="card">
  <h3 style="margin-top:0">Matching employees (<?= count($candidates) ?>)</h3>
  <div class="table-responsive"><table>
    <tr><th>Code</th><th>Name</th><th></th></tr>
    <?php foreach ($candidates as $c): ?><tr>
      <td><?= e($c['employee_code']) ?></td><td><?= e(cand_name($c)) ?></td>
      <td><a class="btn small" href="my_payslip.php?q=<?= urlencode($q) ?>&emp=<?= $c['id'] ?>">Select</a></td>
    </tr><?php endforeach; ?>
  </table></div>
</div><br>
<?php endif; ?>

<?php if ($selected): ?>
<div class="card">
  <h3 style="margin-top:0">Payslips for <?= e(cand_name($selected)) ?> <small style="color:#64748B">(<?= e($selected['employee_code']) ?>)</small></h3>
  <?php if (!$periods): ?><div class="empty">No payroll records for this employee yet.</div><?php else: ?>
  <div class="table-responsive"><table>
    <tr><th>Period</th><th>Dates</th><th>Net Pay</th><th>Status</th><th></th></tr>
    <?php foreach ($periods as $p): ?><tr>
      <td><b><?= e($p['period_label']) ?></b></td>
      <td><?= e($p['period_start']) ?> → <?= e($p['period_end']) ?></td>
      <td><b><?= peso($p['net_pay']) ?></b></td>
      <td><span class="badge <?= $p['status']==='Paid'?'b-green':($p['status']==='Processed'?'b-blue':'b-grey') ?>"><?= e($p['status']) ?></span></td>
      <td style="white-space:nowrap"><a class="btn small" href="payslip.php?id=<?= $p['id'] ?>">Generate Payslip</a></td>
    </tr><?php endforeach; ?>
  </table></div>
  <p style="color:#64748B;font-size:13px">The payslip page has <b>Print</b> and <b>Save as PDF</b> buttons.</p>
  <?php endif; ?>
</div>
<?php elseif ($q === '' && !$notice): ?>
<div class="card"><div class="empty">Type a name above and click Search to find payslips.</div></div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
