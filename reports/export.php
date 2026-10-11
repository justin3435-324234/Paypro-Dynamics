<?php
// CSV export — same filters as reports/index.php, data straight from DB.
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']); // employees have no reports access
$pdo = db();
$role = $_SESSION['role'] ?? '';
$tab = $_GET['tab'] ?? 'payroll';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$fDept = (int)($_GET['dept'] ?? 0);
$fEmp = (int)($_GET['employee'] ?? 0);
if ($role === 'EMPLOYEE') $fEmp = (int)($_SESSION['employee_id'] ?? 0);
$fStatus = $_GET['status'] ?? '';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="paypro-' . $tab . '-' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');

if ($tab === 'employees') {
    fputcsv($out, ['Code','First','Last','Email','Department','Position','Status','Salary']);
    $sql = "SELECT e.*, d.name dept, p.title postitle FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions p ON p.id=e.position_id WHERE 1=1";
    $pr = [];
    if ($fDept > 0) { $sql .= " AND e.department_id=?"; $pr[] = $fDept; }
    $s = $pdo->prepare($sql); $s->execute($pr);
    foreach ($s->fetchAll() as $r) fputcsv($out, [$r['employee_code'],$r['first_name'],$r['last_name'],$r['email'],$r['dept']??'',$r['postitle']??'',$r['status'],$r['basic_salary']]);
} elseif ($tab === 'attendance') {
    fputcsv($out, ['Date','Code','Name','Status','Regular','Overtime','Late']);
    $sql = "SELECT a.*, e.employee_code, e.first_name, e.last_name FROM attendance a JOIN employees e ON e.id=a.employee_id WHERE a.date BETWEEN ? AND ?";
    $pr = [$from,$to];
    if ($fEmp > 0) { $sql .= " AND a.employee_id=?"; $pr[] = $fEmp; }
    $s = $pdo->prepare($sql); $s->execute($pr);
    foreach ($s->fetchAll() as $r) fputcsv($out, [$r['date'],$r['employee_code'],$r['first_name'].' '.$r['last_name'],$r['status'],$r['regular_hours'],$r['overtime_hours'],$r['late_hours']]);
} elseif ($tab === 'deductions') {
    fputcsv($out, ['Date','Code','Type','Amount','Status']);
    $s = $pdo->prepare("SELECT ed.*, e.employee_code, dt.name tname FROM employee_deductions ed JOIN employees e ON e.id=ed.employee_id JOIN deduction_types dt ON dt.id=ed.deduction_type_id WHERE ed.deduction_date BETWEEN ? AND ?" . ($fEmp>0 ? " AND ed.employee_id=$fEmp" : ""));
    $s->execute([$from,$to]);
    foreach ($s->fetchAll() as $r) fputcsv($out, [$r['deduction_date'],$r['employee_code'],$r['tname'],$r['amount'],$r['status']]);
} else {
    fputcsv($out, ['Employee','Period','Gross','Deductions','Net','Status']);
    $sql = "SELECT p.*, e.employee_code, e.first_name, e.last_name FROM payroll p JOIN employees e ON e.id=p.employee_id WHERE p.period_start>=? AND p.period_end<=?";
    $pr = [$from,$to];
    if ($fEmp > 0) { $sql .= " AND p.employee_id=?"; $pr[] = $fEmp; }
    if (in_array($fStatus,['Draft','Processed','Paid'])) { $sql .= " AND p.status=?"; $pr[] = $fStatus; }
    $s = $pdo->prepare($sql); $s->execute($pr);
    foreach ($s->fetchAll() as $r) fputcsv($out, [$r['employee_code'].' '.$r['first_name'].' '.$r['last_name'],$r['period_label'],$r['gross_pay'],$r['total_deductions'],$r['net_pay'],$r['status']]);
}
fclose($out);
exit;
