<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN']);
require_once __DIR__ . '/../includes/csrf.php';
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id=?");
$stmt->execute([$id]);
$emp = $stmt->fetch();
if (!$emp) { set_flash('error','Employee not found.'); redirect('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) redirect('index.php');
    $pdo->prepare("UPDATE employees SET status='inactive', employment_status='Resigned' WHERE id=?")->execute([$id]);
    log_activity('EMP_DEACTIVATE', "Deactivated employee #$id " . $emp['employee_code']);
    set_flash('success', 'Employee deactivated.');
    redirect('index.php');
}
$pageTitle = 'Deactivate Employee'; $currentPage = 'employees'; $basePath = '..';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="card">
<h3>Are you sure you want to deactivate this employee?</h3>
<p><b><?= e($emp['employee_code']) ?></b></p>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<button class="btn danger" type="submit">Yes, Deactivate</button>
<a class="btn secondary" href="index.php">Cancel</a></form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
