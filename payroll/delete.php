<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { set_flash('error','Invalid request.'); redirect('index.php'); }
$pdo = db();
$id = (int)$_POST['id'];
$stmt = $pdo->prepare("SELECT * FROM payroll WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { set_flash('error','Payroll not found.'); redirect('index.php'); }
if ($p['status'] !== 'Draft') { set_flash('error','Only Draft payroll can be deleted. Reopen it first.'); redirect('view.php?id='.$id); }
$pdo->prepare("DELETE FROM payroll WHERE id=?")->execute([$id]);
log_activity('PAY_DELETE', "Deleted draft payroll #$id");
set_flash('success','Draft payroll deleted.');
redirect('index.php');
