<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']); // employees have no attendance access
$pageTitle = 'Attendance'; $currentPage = 'attendance'; $basePath = '..';
$pdo = db();
require_once __DIR__ . '/../includes/csrf.php';
$role = $_SESSION['role'] ?? '';
$myEmp = (int)($_SESSION['employee_id'] ?? 0);

// Filters
$fEmp = (int)($_GET['employee'] ?? 0);
$fDate = $_GET['date'] ?? '';
$fMonth = $_GET['month'] ?? ''; // YYYY-MM
$fStatus = $_GET['status'] ?? '';

$where = []; $params = [];
if ($role === 'EMPLOYEE') { $where[] = "a.employee_id=?"; $params[] = $myEmp; }
elseif ($fEmp > 0) { $where[] = "a.employee_id=?"; $params[] = $fEmp; }
if ($fDate !== '') { $where[] = "a.date=?"; $params[] = $fDate; }
if ($fMonth !== '') { $where[] = "DATE_FORMAT(a.date,'%Y-%m')=?"; $params[] = $fMonth; }
if (in_array($fStatus, ['Present','Absent','Late','Leave'])) { $where[] = "a.status=?"; $params[] = $fStatus; }
$w = $where ? 'WHERE '.implode(' AND ',$where) : '';

$stmt = $pdo->prepare("SELECT a.*, e.employee_code, e.first_name, e.last_name FROM attendance a JOIN employees e ON e.id=a.employee_id $w ORDER BY a.date DESC LIMIT 200");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$emps = $pdo->query("SELECT id, employee_code, first_name, last_name FROM employees WHERE status='active' ORDER BY first_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="toolbar">
<form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
<?php if ($role !== 'EMPLOYEE'): ?>
<select name="employee"><option value="0">All employees</option>
<?php foreach ($emps as $x): ?><option value="<?= $x['id'] ?>" <?= $fEmp==$x['id']?'selected':'' ?>><?= e($x['employee_code'].' '.$x['first_name'].' '.$x['last_name']) ?></option><?php endforeach; ?></select>
<?php endif; ?>
<input type="date" name="date" value="<?= e($fDate) ?>">
<input type="month" name="month" value="<?= e($fMonth) ?>">
<select name="status"><option value="">All status</option>
<?php foreach (['Present','Absent','Late','Leave'] as $s): ?><option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
<button class="btn secondary" type="submit">Filter</button>
<a class="btn secondary" href="index.php">Clear</a>
</form>
<?php if (in_array($role,['ADMIN','MANAGER'])): ?><button class="btn" onclick="document.getElementById('attModal').classList.remove('hidden');document.getElementById('amId').value=0;">+ Add Attendance</button><?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table>
<tr><th>Employee</th><th>Date</th><th>In / Out</th><th>Reg / OT / Late</th><th>Status</th><?php if(in_array($role,['ADMIN','MANAGER'])): ?><th>Actions</th><?php endif; ?></tr>
<?php if (!$rows): ?><tr><td colspan="6"><div class="empty">No attendance records found.</div></td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr>
<td><?= e($r['employee_code']) ?> <?= e($r['first_name'].' '.$r['last_name']) ?></td>
<td><?= e($r['date']) ?></td>
<td><?= e($r['time_in'] ?: '-') ?> / <?= e($r['time_out'] ?: '-') ?></td>
<td><?= $r['regular_hours'] ?> / <?= $r['overtime_hours'] ?> / <?= $r['late_hours'] ?></td>
<td><span class="badge <?= $r['status']==='Present'?'b-green':($r['status']==='Late'?'b-yellow':($r['status']==='Absent'?'b-red':'b-grey')) ?>"><?= e($r['status']) ?></span></td>
<?php if (in_array($role,['ADMIN','MANAGER'])): ?><td style="white-space:nowrap">
<button class="btn small secondary" onclick='attEdit(<?= json_encode($r) ?>)'>Edit</button>
<form method="post" action="delete.php" style="display:inline" onsubmit="return confirmAction('Delete this attendance record?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn small danger">Delete</button></form>
</td><?php endif; ?></tr><?php endforeach; ?></table></div></div>

<?php if (in_array($role,['ADMIN','MANAGER'])): ?>
<div id="attModal" class="modal-wrap hidden"><div class="modal">
<h3>Attendance Entry</h3>
<form method="post" action="save.php" data-validate><?= csrf_field() ?><input type="hidden" name="id" id="amId" value="0">
<label>Employee *</label><select name="employee_id" id="amEmp" required>
<?php foreach ($emps as $x): ?><option value="<?= $x['id'] ?>"><?= e($x['employee_code'].' '.$x['first_name'].' '.$x['last_name']) ?></option><?php endforeach; ?></select>
<br><br><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
<div><label>Date *</label><input type="date" name="date" id="amDate" required value="<?= date('Y-m-d') ?>"></div>
<div><label>Status</label><select name="status" id="amStatus"><option>Present</option><option>Late</option><option>Absent</option><option>Leave</option></select></div>
<div><label>Time In</label><input type="time" name="time_in" id="time_in"></div>
<div><label>Time Out</label><input type="time" name="time_out" id="time_out"></div>
<div><label>Regular Hours</label><input type="number" step="0.01" min="0" name="regular_hours" id="regular_hours" value="8"></div>
<div><label>Overtime Hours</label><input type="number" step="0.01" min="0" name="overtime_hours" id="overtime_hours" value="0"></div>
<div><label>Late Hours</label><input type="number" step="0.01" min="0" name="late_hours" id="late_hours" value="0"></div>
</div>
<br><label>Notes</label><input name="notes" id="amNotes">
<br><br><button class="btn" type="submit">Save</button>
<button type="button" class="btn secondary" onclick="document.getElementById('attModal').classList.add('hidden')">Cancel</button></form>
</div></div>
<script>
function attEdit(r){
  document.getElementById('amId').value=r.id;
  document.getElementById('amEmp').value=r.employee_id;
  document.getElementById('amDate').value=r.date;
  document.getElementById('amStatus').value=r.status;
  document.getElementById('time_in').value=r.time_in||'';
  document.getElementById('time_out').value=r.time_out||'';
  document.getElementById('regular_hours').value=r.regular_hours;
  document.getElementById('overtime_hours').value=r.overtime_hours;
  document.getElementById('late_hours').value=r.late_hours||0;
  document.getElementById('amNotes').value=r.notes||'';
  document.getElementById('attModal').classList.remove('hidden');
}
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
