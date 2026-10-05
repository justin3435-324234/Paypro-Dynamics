<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pageTitle = 'Employees'; $currentPage = 'employees'; $basePath = '..';
$pdo = db();
$role = $_SESSION['role'] ?? '';

// EMPLOYEE role: own profile only (no employee list access)
if ($role === 'EMPLOYEE') {
    $eid = (int)($_SESSION['employee_id'] ?? 0);
    if ($eid <= 0) die('<h2>Account not linked.</h2><p>Your login is not linked to an employee record. Contact your administrator.</p>');
    redirect('view.php?id=' . $eid);
}

$q = trim($_GET['q'] ?? '');
$fDept = (int)($_GET['dept'] ?? 0);
$fStatus = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 10; $off = ($page - 1) * $per;

$where = []; $params = [];
if ($q !== '') { $where[] = "(e.employee_code LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)"; foreach(range(1,4) as $i) $params[] = "%$q%"; }
if ($fDept > 0) { $where[] = "e.department_id=?"; $params[] = $fDept; }
if (in_array($fStatus, ['active','inactive'])) { $where[] = "e.status=?"; $params[] = $fStatus; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) c FROM employees e $w");
$total->execute($params);
$totalRows = (int)$total->fetch()['c'];
$pages = max(1, (int)ceil($totalRows / $per));

$stmt = $pdo->prepare("SELECT e.*, d.name dept, p.title postitle FROM employees e
  LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions p ON p.id=e.position_id
  $w ORDER BY e.created_at DESC LIMIT $per OFFSET $off");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$depts = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="toolbar">
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
    <input type="search" name="q" placeholder="Search name, code, email…" value="<?= e($q) ?>">
    <select name="dept"><option value="0">All departments</option>
      <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>" <?= $fDept==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
    <select name="status"><option value="">All status</option>
      <option value="active" <?= $fStatus==='active'?'selected':'' ?>>active</option>
      <option value="inactive" <?= $fStatus==='inactive'?'selected':'' ?>>inactive</option></select>
    <button class="btn secondary" type="submit">Filter</button>
  </form>
  <?php if (in_array($role, ['ADMIN','MANAGER'])): ?><a class="btn" href="add.php">+ Add Employee</a><?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table>
<tr><th>Code</th><th>Full Name</th><th>Department</th><th>Position</th><th>Status</th><th>Salary</th><th>Hired</th><th>Actions</th></tr>
<?php if (!$rows): ?><tr><td colspan="8"><div class="empty">No employees found. Try clearing filters.</div></td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['employee_code']) ?></td>
<td><a href="view.php?id=<?= $r['id'] ?>"><?= e(trim($r['first_name'].' '.$r['middle_name'].' '.$r['last_name'])) ?></a><br><small style="color:#64748B"><?= e($r['email']) ?></small></td>
<td><?= e($r['dept'] ?? '-') ?></td><td><?= e($r['postitle'] ?? '-') ?></td>
<td><span class="badge <?= $r['status']==='active'?'b-green':'b-grey' ?>"><?= e($r['status']) ?></span><br><small><?= e($r['employment_status']) ?></small></td>
<td><?= $r['salary_type']==='hourly' ? peso($r['hourly_rate']).'/hr' : peso($r['basic_salary']) ?></td>
<td><?= e($r['date_hired']) ?></td>
<td style="white-space:nowrap">
<a class="btn small secondary" href="view.php?id=<?= $r['id'] ?>">View</a>
<?php if (in_array($role,['ADMIN','MANAGER'])): ?><a class="btn small secondary" href="edit.php?id=<?= $r['id'] ?>">Edit</a><?php endif; ?>
<?php if ($role==='ADMIN'): ?><a class="btn small danger" href="delete.php?id=<?= $r['id'] ?>">Deactivate</a><?php endif; ?>
</td></tr>
<?php endforeach; ?></table></div>
<?php if ($pages > 1): ?><div class="pagination">
<?php for ($i=1;$i<=$pages;$i++): ?><a href="?q=<?= urlencode($q) ?>&dept=<?= $fDept ?>&status=<?= e($fStatus) ?>&page=<?= $i ?>" style="<?= $i==$page?'background:#22C55E;color:#fff':'' ?>"><?= $i ?></a><?php endfor; ?>
</div><?php endif; ?></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
