<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']); // employees have no deductions access
$pageTitle = 'Deductions'; $currentPage = 'deductions'; $basePath = '..';
$pdo = db();
require_once __DIR__ . '/../includes/csrf.php';
$role = $_SESSION['role'] ?? '';
$myEmp = (int)($_SESSION['employee_id'] ?? 0);

// Save handler (same page)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!in_array($role,['ADMIN','MANAGER'])) { set_flash('error','Forbidden.'); header('Location: index.php'); exit; }
    if (!csrf_check()) { set_flash('error','Invalid token.'); header('Location: index.php'); exit; }
    try {
        $a = $_POST['action'];
        if ($a === 'save') {
            $id=(int)($_POST['id']??0); $emp=(int)$_POST['employee_id']; $type=(int)$_POST['deduction_type_id'];
            $amt=(float)$_POST['amount']; $dd=$_POST['deduction_date']?:date('Y-m-d');
            $st=in_array($_POST['status']??'', ['pending','applied','cancelled'])?$_POST['status']:'pending';
            $desc=trim($_POST['description']??'');
            if ($emp<=0||$type<=0) throw new Exception('Employee and deduction type are required.');
            if ($amt<=0) throw new Exception('Amount must be greater than zero.');
            if ($id>0) {
                $pdo->prepare("UPDATE employee_deductions SET employee_id=?,deduction_type_id=?,amount=?,description=?,deduction_date=?,status=? WHERE id=?")
                    ->execute([$emp,$type,$amt,$desc,$dd,$st,$id]);
                log_activity('DED_UPDATE',"Updated deduction #$id");
                set_flash('success','Deduction updated.');
            } else {
                $pdo->prepare("INSERT INTO employee_deductions (employee_id,deduction_type_id,amount,description,deduction_date,status) VALUES (?,?,?,?,?,?)")
                    ->execute([$emp,$type,$amt,$desc,$dd,$st]);
                log_activity('DED_CREATE',"Deduction ₱$amt for employee #$emp");
                set_flash('success','Deduction added.');
            }
        } elseif ($a === 'delete') {
            if ($role!=='ADMIN') throw new Exception('Only ADMIN can delete.');
            $pdo->prepare("DELETE FROM employee_deductions WHERE id=?")->execute([(int)$_POST['id']]);
            log_activity('DED_DELETE','Deleted deduction #'.(int)$_POST['id']);
            set_flash('success','Deduction deleted.');
        }
    } catch (Exception $ex) { set_flash('error',$ex->getMessage()); }
    header('Location: index.php'); exit;
}

// Filters
$fEmp=(int)($_GET['employee']??0); $fType=(int)($_GET['type']??0); $fStatus=$_GET['status']??'';
$where=[]; $params=[];
if ($role==='EMPLOYEE') { $where[]="ed.employee_id=?"; $params[]=$myEmp; }
elseif ($fEmp>0) { $where[]="ed.employee_id=?"; $params[]=$fEmp; }
if ($fType>0) { $where[]="ed.deduction_type_id=?"; $params[]=$fType; }
if (in_array($fStatus,['pending','applied','cancelled'])) { $where[]="ed.status=?"; $params[]=$fStatus; }
$w=$where?'WHERE '.implode(' AND ',$where):'';
$stmt=$pdo->prepare("SELECT ed.*, e.employee_code, e.first_name, e.last_name, dt.name tname FROM employee_deductions ed JOIN employees e ON e.id=ed.employee_id JOIN deduction_types dt ON dt.id=ed.deduction_type_id $w ORDER BY ed.deduction_date DESC LIMIT 200");
$stmt->execute($params);
$rows=$stmt->fetchAll();
$emps=$pdo->query("SELECT id,employee_code,first_name,last_name FROM employees WHERE status='active' ORDER BY first_name")->fetchAll();
$types=$pdo->query("SELECT * FROM deduction_types ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="toolbar">
<form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
<?php if($role!=='EMPLOYEE'): ?><select name="employee"><option value="0">All employees</option>
<?php foreach($emps as $x): ?><option value="<?= $x['id'] ?>" <?= $fEmp==$x['id']?'selected':'' ?>><?= e($x['employee_code'].' '.$x['first_name'].' '.$x['last_name']) ?></option><?php endforeach; ?></select><?php endif; ?>
<select name="type"><option value="0">All types</option>
<?php foreach($types as $t): ?><option value="<?= $t['id'] ?>" <?= $fType==$t['id']?'selected':'' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select>
<select name="status"><option value="">All status</option>
<?php foreach(['pending','applied','cancelled'] as $s): ?><option <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select>
<button class="btn secondary" type="submit">Filter</button></form>
<?php if(in_array($role,['ADMIN','MANAGER'])): ?><button class="btn" onclick="dedForm(0)">+ Add Deduction</button><?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table>
<tr><th>ID</th><th>Employee</th><th>Type</th><th>Amount</th><th>Date</th><th>Status</th><?php if(in_array($role,['ADMIN','MANAGER'])): ?><th>Actions</th><?php endif; ?></tr>
<?php if(!$rows): ?><tr><td colspan="7"><div class="empty">No deductions found.</div></td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr>
<td><?= $r['id'] ?></td><td><?= e($r['employee_code'].' '.$r['first_name'].' '.$r['last_name']) ?><br><small><?= e($r['description']?:'') ?></small></td>
<td><span class="badge b-blue"><?= e($r['tname']) ?></span></td><td><?= peso($r['amount']) ?></td><td><?= e($r['deduction_date']) ?></td>
<td><span class="badge <?= $r['status']==='applied'?'b-green':($r['status']==='pending'?'b-yellow':'b-grey') ?>"><?= e($r['status']) ?></span></td>
<?php if(in_array($role,['ADMIN','MANAGER'])): ?><td style="white-space:nowrap">
<button class="btn small secondary" onclick='dedEdit(<?= json_encode($r) ?>)'>Edit</button>
<?php if($role==='ADMIN'): ?><form method="post" style="display:inline" onsubmit="return confirmAction('Delete this deduction?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn small danger">Delete</button></form><?php endif; ?>
</td><?php endif; ?></tr><?php endforeach; ?></table></div></div>

<?php if(in_array($role,['ADMIN','MANAGER'])): ?>
<div id="dedModal" class="modal-wrap hidden"><div class="modal">
<h3 id="ddTitle">Add Deduction</h3>
<form method="post" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="ddId" value="0">
<label>Employee *</label><select name="employee_id" id="ddEmp" required>
<?php foreach($emps as $x): ?><option value="<?= $x['id'] ?>"><?= e($x['employee_code'].' '.$x['first_name'].' '.$x['last_name']) ?></option><?php endforeach; ?></select>
<br><br><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
<div><label>Type *</label><select name="deduction_type_id" id="ddType"><?php foreach($types as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Amount (₱) *</label><input type="number" step="0.01" min="0.01" name="amount" id="ddAmt" required></div>
<div><label>Date *</label><input type="date" name="deduction_date" id="ddDate" value="<?= date('Y-m-d') ?>" required></div>
<div><label>Status</label><select name="status" id="ddStatus"><option value="pending">pending</option><option value="applied">applied</option><option value="cancelled">cancelled</option></select></div>
</div><br><label>Description</label><input name="description" id="ddDesc">
<br><br><button class="btn" type="submit">Save</button>
<button type="button" class="btn secondary" onclick="document.getElementById('dedModal').classList.add('hidden')">Cancel</button></form>
</div></div>
<script>
function dedForm(){document.getElementById('ddId').value=0;document.getElementById('ddTitle').textContent='Add Deduction';document.getElementById('dedModal').classList.remove('hidden');}
function dedEdit(r){document.getElementById('ddId').value=r.id;document.getElementById('ddEmp').value=r.employee_id;document.getElementById('ddType').value=r.deduction_type_id;document.getElementById('ddAmt').value=r.amount;document.getElementById('ddDate').value=r.deduction_date;document.getElementById('ddStatus').value=r.status;document.getElementById('ddDesc').value=r.description||'';document.getElementById('ddTitle').textContent='Edit Deduction';document.getElementById('dedModal').classList.remove('hidden');}
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
