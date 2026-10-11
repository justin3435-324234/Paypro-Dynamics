<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']);
$pageTitle = 'Positions'; $currentPage = 'positions'; $basePath = '..';
$pdo = db();
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_check()) { set_flash('error','Invalid token.'); header('Location: index.php'); exit; }
    try {
        if ($_POST['action'] === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $dept = (int)($_POST['department_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';
            if ($dept <= 0 || $title === '') throw new Exception('Department and position title are required.');
            if ($id > 0) {
                if (!is_admin()) throw new Exception('Only ADMIN can edit positions.');
                $stmt = $pdo->prepare("UPDATE positions SET department_id=?, title=?, description=?, status=? WHERE id=?");
                $stmt->execute([$dept, $title, $desc, $status, $id]);
                log_activity('POS_UPDATE', "Updated position #$id $title");
                set_flash('success','Position updated.');
            } else {
                if (!is_admin()) throw new Exception('Only ADMIN can create positions.');
                $stmt = $pdo->prepare("INSERT INTO positions (department_id,title,description,status) VALUES (?,?,?,?)");
                $stmt->execute([$dept, $title, $desc, $status]);
                log_activity('POS_CREATE', "Created position $title");
                set_flash('success','Position successfully added.');
            }
        } elseif ($_POST['action'] === 'delete') {
            if (!is_admin()) throw new Exception('Only ADMIN can delete.');
            $id = (int)$_POST['id'];
            $c = $pdo->prepare("SELECT COUNT(*) c FROM employees WHERE position_id=?");
            $c->execute([$id]);
            if ((int)$c->fetch()['c'] > 0) throw new Exception('Cannot delete: employees hold this position.');
            $pdo->prepare("DELETE FROM positions WHERE id=?")->execute([$id]);
            log_activity('POS_DELETE', "Deleted position #$id");
            set_flash('success','Position deleted.');
        }
    } catch (PDOException $ex) {
        set_flash('error', str_contains($ex->getMessage(),'Duplicate') ? 'Position title already exists in this department.' : 'Database error.');
    } catch (Exception $ex) { set_flash('error', $ex->getMessage()); }
    header('Location: index.php'); exit;
}

$depts = $pdo->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$fDept = (int)($_GET['dept'] ?? 0);
$q = trim($_GET['q'] ?? '');
$sql = "SELECT p.*, d.name dept FROM positions p JOIN departments d ON d.id=p.department_id";
$where = []; $params = [];
if ($fDept > 0) { $where[] = "p.department_id=?"; $params[] = $fDept; }
if ($q !== '') { $where[] = "(p.title LIKE ? OR p.description LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY d.name, p.title";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="toolbar">
  <form method="get" style="display:flex;gap:8px">
    <input type="search" name="q" placeholder="Search positions…" value="<?= e($q) ?>">
    <select name="dept" onchange="this.form.submit()"><option value="0">All departments</option>
    <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>" <?= $fDept==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
    <button class="btn secondary" type="submit">Search</button>
  </form>
  <?php if (is_admin()): ?><button class="btn" onclick="posForm(0,'','','','active')">+ Add Position</button><?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table>
<tr><th>ID</th><th>Title</th><th>Department</th><th>Status</th><?php if(is_admin()): ?><th>Actions</th><?php endif; ?></tr>
<?php if (!$rows): ?><tr><td colspan="5"><div class="empty">No positions found.</div></td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr>
<td><?= $r['id'] ?></td><td><b><?= e($r['title']) ?></b><br><small style="color:#64748B"><?= e($r['description'] ?: '') ?></small></td>
<td><?= e($r['dept']) ?></td><td><span class="badge <?= $r['status']==='active'?'b-green':'b-grey' ?>"><?= e($r['status']) ?></span></td>
<?php if (is_admin()): ?><td>
<button class="btn small secondary" onclick="posForm(<?= $r['id'] ?>,<?= $r['department_id'] ?>,'<?= e(addslashes($r['title'])) ?>','<?= e(addslashes($r['description'] ?? '')) ?>','<?= $r['status'] ?>')">Edit</button>
<form method="post" style="display:inline" onsubmit="return confirmAction('Delete this position?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn small danger">Delete</button></form>
</td><?php endif; ?></tr><?php endforeach; ?></table></div></div>

<div id="posModal" class="modal-wrap hidden"><div class="modal">
<h3 id="pmTitle">Add Position</h3>
<form method="post" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="pmId" value="0">
<label>Department *</label><select name="department_id" id="pmDept" required>
<?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select>
<br><br><label>Position Title *</label><input name="title" id="pmTitle2" required maxlength="100">
<br><br><label>Description</label><textarea name="description" id="pmDesc" rows="3"></textarea>
<br><br><label>Status</label><select name="status" id="pmStatus"><option value="active">active</option><option value="inactive">inactive</option></select>
<br><br><button class="btn" type="submit">Save</button> <button type="button" class="btn secondary" onclick="document.getElementById('posModal').classList.add('hidden')">Cancel</button></form>
</div></div>
<script>
function posForm(id,dept,title,desc,status){
  document.getElementById('pmId').value=id;
  document.getElementById('pmDept').value=dept||document.getElementById('pmDept').value;
  document.getElementById('pmTitle2').value=title;
  document.getElementById('pmDesc').value=desc;
  document.getElementById('pmStatus').value=status||'active';
  document.getElementById('pmTitle').textContent=id>0?'Edit Position':'Add Position';
  document.getElementById('posModal').classList.remove('hidden');
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
