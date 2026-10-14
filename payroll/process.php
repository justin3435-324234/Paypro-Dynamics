<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['MANAGER']);
require_once __DIR__ . '/../includes/csrf.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { set_flash('error','Invalid request.'); redirect('index.php'); }
// view.php forms use raw csrf; accept it
$sent = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'], $sent)) { set_flash('error','Invalid token.'); redirect('index.php'); }
$pdo = db();
$id = (int)$_POST['id'];
$to = $_POST['to'] ?? '';
if (!in_array($to, ['Draft','Processed','Paid'])) { set_flash('error','Invalid status.'); redirect('index.php'); }
$stmt = $pdo->prepare("SELECT * FROM payroll WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { set_flash('error','Payroll not found.'); redirect('index.php'); }
// Transition rules
$from = $p['status'];
$allowed = ($from==='Draft' && $to==='Processed') || ($from==='Processed' && in_array($to,['Paid','Draft'])) || ($from==='Paid' && $to==='Draft' && $_SESSION['role']==='MANAGER');
if (!$allowed) { set_flash('error',"Cannot move from $from to $to."); redirect('view.php?id='.$id); }
$pdo->prepare("UPDATE payroll SET status=?, processed_by=?, processed_at=NOW() WHERE id=?")->execute([$to, $_SESSION['user_id'], $id]);
log_activity('PAY_STATUS', "Payroll #$id $from → $to by " . $_SESSION['username']);
set_flash('success', $to==='Processed' ? 'Payroll successfully processed.' : ($to==='Paid' ? 'Payroll marked as paid.' : 'Payroll reopened to Draft.'));
redirect('view.php?id=' . $id);
