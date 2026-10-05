<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']);
$pageTitle = 'Departments'; $currentPage = 'departments'; $basePath = '..';
$pdo = db();
require_once __DIR__ . '/../includes/csrf.php';

// Handle create/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_check()) { set_flash('error','Invalid token.'); header('Location: index.php'); exit; }
    $action = $_POST['action'];
    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';
            if ($name === '') throw new Exception('Department name is required.');
            if ($id > 0) {
                if (!is_admin()) throw new Exception('Only ADMIN can edit departments.');
                $stmt = $pdo->prepare("UPDATE departments SET name=?, description=?, status=? WHERE id=?");
                $stmt->execute([$name, $desc, $status, $id]);
                log_activity('DEPT_UPDATE', "Updated department #$id $name");
                set_flash('success','Department updated.');
            } else {
                if (!is_admin()) throw new Exception('Only ADMIN can create departments.');
                $stmt = $pdo->prepare("INSERT INTO departments (name, description, status) VALUES (?,?,?)");
                $stmt->execute([$name, $desc, $status]);
                log_activity('DEPT_CREATE', "Created department $name");
                set_flash('success','Department successfully added.');
            }
        } elseif ($action === 'delete') {
            if (!is_admin()) throw new Exception('Only ADMIN can delete.');
            $id = (int)$_POST['id'];
            $c = $pdo->prepare("SELECT COUNT(*) c FROM employees WHERE department_id=?");
            $c->execute([$id]);
            if ((int)$c->fetch()['c'] > 0) throw new Exception('Cannot delete: employees are assigned to this department. Reassign them first.');
            $pdo->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
            log_activity('DEPT_DELETE', "Deleted department #$id");
            set_flash('success','Department deleted.');
        }
    } catch (PDOException $ex) {
        set_flash('error', str_contains($ex->getMessage(),'Duplicate') ? 'Department name already exists.' : 'Database error. Please try again.');
    } catch (Exception $ex) { set_flash('error', $ex->getMessage()); }
    header('Location: index.php'); exit;
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT d.*, (SELECT COUNT(*) FROM employees e WHERE e.department_id=d.id) emp_count FROM departments d";
$params = [];
if ($q !== '') { $sql .= " WHERE d.name LIKE ?"; $params[] = "%$q%"; }
$sql .= " ORDER BY d.name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="toolbar">
  <form method="get" style="display:flex;gap:8px"><input type="search" name="q" placeholder="Search departments…" value="<?= e($q) ?>"><button class="btn secondary" type="submit">Search</button></form>
  <?php if (is_admin()): ?>
  <button class="btn" onclick="deptForm(0,'','','active')">+ Add Department</button>
  <?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table>
<tr><th>ID</th><th>Name</th><th>Description</th><th>Employees</th><th>Status</th><?php if(is_admin()): ?><th>Actions</th><?php endif; ?></tr>
<?php if (!$rows): ?><tr><td colspan="6"><div class="empty">No departments found. <?= is_admin() ? 'Click “Add Department”.' : '' ?></div></td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
<tr><td><?= $r['id'] ?></td><td><b><?= e($r['name']) ?></b></td><td><?= e($r['description'] ?: '-') ?></td>
<td><?= (int)$r['emp_count'] ?></td>
<td><span class="badge <?= $r['status']==='active'?'b-green':'b-grey' ?>"><?= e($r['status']) ?></span></td>
<?php if (is_admin()): ?><td>
<button class="btn small secondary" onclick="deptForm(<?= $r['id'] ?>,'<?= e(addslashes($r['name'])) ?>','<?= e(addslashes($r['description'] ?? '')) ?>','<?= $r['status'] ?>')">Edit</button>
<form method="post" style="display:inline" onsubmit="return confirmAction('Delete this department?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn small danger" type="submit">Delete</button></form>
</td><?php endif; ?></tr>
<?php endforeach; ?></table></div></div>

<div id="deptModal" class="modal-wrap hidden"><div class="modal">
<h3 id="dmTitle">Add Department</h3>
<form method="post" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="dmId" value="0">
<label>Department Name *</label><input name="name" id="dmName" required maxlength="100">
<br><br><label>Description</label><textarea name="description" id="dmDesc" rows="3"></textarea>
<br><br><label>Status</label><select name="status" id="dmStatus"><option value="active">active</option><option value="inactive">inactive</option></select>
<br><br><button class="btn" type="submit">Save</button> <button type="button" class="btn secondary" onclick="document.getElementById('deptModal').classList.add('hidden')">Cancel</button></form>
</div></div>
<script>
function deptForm(id,name,desc,status){
  document.getElementById('dmId').value=id;
  document.getElementById('dmName').value=name;
  document.getElementById('dmDesc').value=desc;
  document.getElementById('dmStatus').value=status||'active';
  document.getElementById('dmTitle').textContent=id>0?'Edit Department':'Add Department';
  document.getElementById('deptModal').classList.remove('hidden');
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
