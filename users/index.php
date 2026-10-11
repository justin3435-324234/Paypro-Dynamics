<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN']);
$pageTitle = 'Users'; $currentPage = 'users'; $basePath = '..';
$pdo = db();
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_check()) { set_flash('error','Invalid token.'); header('Location: index.php'); exit; }
    try {
        if ($_POST['action'] === 'save') {
            $id=(int)($_POST['id']??0);
            $uname=trim($_POST['username']??''); $email=trim($_POST['email']??'');
            $role=in_array($_POST['role']??'', ['ADMIN','MANAGER','EMPLOYEE'])?$_POST['role']:'EMPLOYEE';
            $emp=null; // linking removed — accounts are never tied to an employee record
            $status=in_array($_POST['status']??'', ['active','disabled'])?$_POST['status']:'active';
            $pw=$_POST['password']??'';
            if ($uname===''||$email==='') throw new Exception('Username and email are required.');
            if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email.');
            if ($id>0) {
                if ($pw!=='') {
                    if (strlen($pw)<8) throw new Exception('Password must be at least 8 characters.');
                    $pdo->prepare("UPDATE users SET username=?,email=?,role=?,employee_id=?,status=?,password_hash=? WHERE id=?")
                        ->execute([$uname,$email,$role,$emp,$status,password_hash($pw,PASSWORD_DEFAULT),$id]);
                } else {
                    $pdo->prepare("UPDATE users SET username=?,email=?,role=?,employee_id=?,status=? WHERE id=?")
                        ->execute([$uname,$email,$role,$emp,$status,$id]);
                }
                // prevent self-lockout
                if ($id===$_SESSION['user_id'] && ($status==='disabled'||$role!==$_SESSION['role'])) {
                    $_SESSION['role']=$role;
                    if ($status==='disabled') throw new Exception('You cannot disable your own account while logged in. Status kept active.');
                }
                log_activity('USER_UPDATE',"Updated user #$id $uname");
                set_flash('success','User updated.');
            } else {
                if (strlen($pw)<8) throw new Exception('Password must be at least 8 characters.');
                $pdo->prepare("INSERT INTO users (username,email,password_hash,role,employee_id,status) VALUES (?,?,?,?,?,'active')")
                    ->execute([$uname,$email,password_hash($pw,PASSWORD_DEFAULT),$role,$emp]);
                // fix status if disabled chosen
                if ($status==='disabled') $pdo->prepare("UPDATE users SET status='disabled' WHERE username=?")->execute([$uname]);
                log_activity('USER_CREATE',"Created user $uname ($role)");
                set_flash('success','User created.');
            }
        } elseif ($_POST['action']==='delete') {
            $id=(int)$_POST['id'];
            if ($id===$_SESSION['user_id']) throw new Exception('You cannot delete your own account.');
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            log_activity('USER_DELETE',"Deleted user #$id");
            set_flash('success','User deleted.');
        }
    } catch (PDOException $ex) {
        set_flash('error', str_contains($ex->getMessage(),'Duplicate') ? 'Username or email already exists.' : 'Database error.');
    } catch (Exception $ex) { set_flash('error',$ex->getMessage()); }
    header('Location: index.php'); exit;
}

$rows = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="toolbar"><button class="btn" onclick="userForm(0)">+ Add User</button></div>
<div class="card"><div class="table-responsive"><table>
<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
<?php foreach($rows as $r): ?><tr>
<td><?= $r['id'] ?></td><td><b><?= e($r['username']) ?></b></td><td><?= e($r['email']) ?></td>
<td><span class="badge <?= $r['role']==='ADMIN'?'b-red':($r['role']==='MANAGER'?'b-blue':'b-grey') ?>"><?= e($r['role']) ?></span></td>
<td><span class="badge <?= $r['status']==='active'?'b-green':'b-red' ?>"><?= e($r['status']) ?></span></td>
<td style="white-space:nowrap">
<button class="btn small secondary" onclick='userEdit(<?= json_encode($r) ?>)'>Edit</button>
<?php if($r['id']!=$_SESSION['user_id']): ?>
<form method="post" style="display:inline" onsubmit="return confirmAction('Delete this user?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn small danger">Delete</button></form>
<?php endif; ?></td></tr><?php endforeach; ?></table></div></div>

<div id="userModal" class="modal-wrap hidden"><div class="modal">
<h3 id="uTitle">Add User</h3>
<form method="post" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="uId" value="0">
<div class="form-grid" style="grid-template-columns:1fr 1fr">
<div><label>Username *</label><input name="username" id="uName" required></div>
<div><label>Email *</label><input type="email" name="email" id="uEmail" required></div>
<div><label>Role</label><select name="role" id="uRole"><option>ADMIN</option><option>MANAGER</option><option selected>EMPLOYEE</option></select></div>
<div><label>Status</label><select name="status" id="uStatus"><option value="active">active</option><option value="disabled">disabled</option></select></div>
<div><label>Password</label><input type="password" name="password" id="uPw" placeholder="••••••••"></div>
</div><br><button class="btn" type="submit">Save</button>
<button type="button" class="btn secondary" onclick="document.getElementById('userModal').classList.add('hidden')">Cancel</button></form>
</div></div>
<script>
function userForm(){document.getElementById('uId').value=0;document.getElementById('uTitle').textContent='Add User';document.getElementById('userModal').classList.remove('hidden');}
function userEdit(r){document.getElementById('uId').value=r.id;document.getElementById('uName').value=r.username;document.getElementById('uEmail').value=r.email;document.getElementById('uRole').value=r.role;document.getElementById('uStatus').value=r.status;document.getElementById('uPw').value='';document.getElementById('uTitle').textContent='Edit User';document.getElementById('userModal').classList.remove('hidden');}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
