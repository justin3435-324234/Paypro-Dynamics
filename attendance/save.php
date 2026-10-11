<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']);
require_once __DIR__ . '/../includes/csrf.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) { set_flash('error','Invalid request.'); redirect('index.php'); }
$pdo = db();
try {
    $id = (int)($_POST['id'] ?? 0);
    $emp = (int)($_POST['employee_id'] ?? 0);
    $date = $_POST['date'] ?? '';
    $status = in_array($_POST['status'] ?? '', ['Present','Absent','Late','Leave']) ? $_POST['status'] : 'Present';
    $ti = $_POST['time_in'] ?: null; $to = $_POST['time_out'] ?: null;
    $reg = max(0, (float)($_POST['regular_hours'] ?? 0));
    $ot  = max(0, (float)($_POST['overtime_hours'] ?? 0));
    $late= max(0, (float)($_POST['late_hours'] ?? 0));
    $notes = trim($_POST['notes'] ?? '');
    if ($emp <= 0 || $date === '') throw new Exception('Employee and date are required.');
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE attendance SET employee_id=?, date=?, time_in=?, time_out=?, regular_hours=?, overtime_hours=?, late_hours=?, status=?, notes=? WHERE id=?");
        $stmt->execute([$emp,$date,$ti,$to,$reg,$ot,$late,$status,$notes,$id]);
        log_activity('ATT_UPDATE', "Updated attendance #$id");
        set_flash('success','Attendance updated.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO attendance (employee_id,date,time_in,time_out,regular_hours,overtime_hours,late_hours,status,notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$emp,$date,$ti,$to,$reg,$ot,$late,$status,$notes]);
        log_activity('ATT_CREATE', "Attendance for employee #$emp on $date");
        set_flash('success','Attendance saved.');
    }
} catch (PDOException $ex) {
    set_flash('error', str_contains($ex->getMessage(),'Duplicate') ? 'Attendance for this employee and date already exists. Edit it instead.' : 'Database error.');
} catch (Exception $ex) { set_flash('error', $ex->getMessage()); }
redirect('index.php');
