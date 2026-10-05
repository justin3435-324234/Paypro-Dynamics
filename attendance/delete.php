<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']);
require_once __DIR__ . '/../includes/csrf.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) { set_flash('error','Invalid request.'); redirect('index.php'); }
$pdo = db();
$pdo->prepare("DELETE FROM attendance WHERE id=?")->execute([(int)$_POST['id']]);
log_activity('ATT_DELETE', 'Deleted attendance #' . (int)$_POST['id']);
set_flash('success','Attendance deleted.');
redirect('index.php');
