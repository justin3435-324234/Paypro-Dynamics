<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']);
$pageTitle = 'New Payroll'; $currentPage = 'payroll'; $basePath = '..';
$pdo = db();
$emps = $pdo->query("SELECT e.*, d.name dept, p.title postitle FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN positions p ON p.id=e.position_id WHERE e.status='active' ORDER BY e.first_name")->fetchAll();
$types = $pdo->query("SELECT * FROM deduction_types ORDER BY name")->fetchAll();
$defOT = (float)get_setting('default_overtime_rate', '150'); // per-hour peso default if hourly unknown
$defHrs = (float)get_setting('default_working_hours', '8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $empId = (int)($_POST['employee_id'] ?? 0);
        $ps = $_POST['period_start'] ?? ''; $pe = $_POST['period_end'] ?? '';
        if ($empId <= 0 || $ps === '' || $pe === '') throw new Exception('Employee and payroll period are required.');
        if ($ps > $pe) throw new Exception('Invalid payroll period: start must be before end.');
        $st = $pdo->prepare("SELECT * FROM employees WHERE id=?");
        $st->execute([$empId]);
        $emp = $st->fetch();
        if (!$emp) throw new Exception('Employee not found.');

        $daysWorked = max(0,(float)($_POST['days_worked'] ?? 0));
        $hoursDay   = max(0,(float)($_POST['hours_per_day'] ?? $defHrs)) ?: $defHrs;
        $regHoursIn = max(0,(float)($_POST['regular_hours'] ?? 0));
        $otHours    = max(0,(float)($_POST['overtime_hours'] ?? 0));
        $otRate     = max(0,(float)($_POST['overtime_rate'] ?? $defOT));

        // Manual deduction lines: deduction_type_id[] + amount[]
        $dedLines = [];
        $dedTotal = 0;
        $dTypes = $_POST['ded_type'] ?? []; $dAmts = $_POST['ded_amount'] ?? [];
        foreach ($dAmts as $i => $a) {
            $a = (float)$a;
            if ($a <= 0) continue;
            $tid = (int)($dTypes[$i] ?? 0);
            if ($tid <= 0) continue;
            $trow = $pdo->prepare("SELECT name FROM deduction_types WHERE id=?");
            $trow->execute([$tid]);
            $t = $trow->fetch();
            $dedLines[] = ['label' => $t ? $t['name'] : 'Deduction', 'amount' => round($a,2)];
            $dedTotal += $a;
        }
        // Plus any pending employee_deductions inside period → auto-include
        $pend = $pdo->prepare("SELECT ed.amount, dt.name FROM employee_deductions ed JOIN deduction_types dt ON dt.id=ed.deduction_type_id WHERE ed.employee_id=? AND ed.status='pending' AND ed.deduction_date BETWEEN ? AND ?");
        $pend->execute([$empId,$ps,$pe]);
        foreach ($pend->fetchAll() as $pr) { $dedLines[] = ['label'=>$pr['name'],'amount'=>(float)$pr['amount']]; $dedTotal += (float)$pr['amount']; }
        $dedTotal = round($dedTotal, 2);

        // Resolve base pay by salary type (manual Regular Base overrides for monthly/daily)
        $basicSnapshot = (float)$emp['basic_salary'];
        $manualBase = isset($_POST['regular_pay_in']) ? max(0,(float)$_POST['regular_pay_in']) : 0;
        if ($manualBase > 0) {
            $regularPay = round($manualBase, 2);
        } elseif ($emp['salary_type'] === 'monthly') {
            $regularPay = round($basicSnapshot / 2, 2); // semi-monthly
        } elseif ($emp['salary_type'] === 'daily') {
            $regularPay = round($basicSnapshot * $daysWorked, 2);
        } else {
            $rh = $regHoursIn > 0 ? $regHoursIn : ($daysWorked * $hoursDay);
            $regularPay = round($rh * (float)$emp['hourly_rate'], 2);
            $regHoursIn = $rh;
        }
        $otPay = round($otHours * $otRate, 2);
        $gross = round($regularPay + $otPay, 2);
        $net = round($gross - $dedTotal, 2);
        if ($net < 0) throw new Exception('Net pay cannot be negative. Reduce deductions.');
        if ($gross < 0) throw new Exception('Invalid amounts.');

        $label = date('M j', strtotime($ps)) . ' – ' . date('M j, Y', strtotime($pe));
        $pdo->beginTransaction();
        $ins = $pdo->prepare("INSERT INTO payroll (employee_id,period_start,period_end,period_label,basic_salary,days_worked,hours_per_day,regular_hours,regular_pay,overtime_hours,overtime_rate,overtime_pay,gross_pay,total_deductions,net_pay,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Draft')");
        $ins->execute([$empId,$ps,$pe,$label,$basicSnapshot,$daysWorked,$hoursDay,$regHoursIn,$regularPay,$otHours,$otRate,$otPay,$gross,$dedTotal,$net]);
        $pid = (int)$pdo->lastInsertId();
        $it = $pdo->prepare("INSERT INTO payroll_items (payroll_id,item_type,description,amount) VALUES (?,?,?,?)");
        $it->execute([$pid,'earning','Basic / Regular Pay',$regularPay]);
        if ($otPay > 0) $it->execute([$pid,'earning',"Overtime ($otHours hrs)",$otPay]);
        foreach ($dedLines as $dl) $it->execute([$pid,'deduction',$dl['label'],$dl['amount']]);
        // mark pending deductions in period as applied
        $pdo->prepare("UPDATE employee_deductions SET status='applied' WHERE employee_id=? AND status='pending' AND deduction_date BETWEEN ? AND ?")->execute([$empId,$ps,$pe]);
        $pdo->commit();
        log_activity('PAY_CREATE', "Payroll #$pid for employee #$empId $label net ₱$net");
        set_flash('success','Payroll successfully calculated and saved as Draft.');
        redirect('view.php?id=' . $pid);
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', str_contains($ex->getMessage(),'uq_pay_emp_period') ? 'Payroll for this employee and period already exists.' : 'Database error. Check inputs.');
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', $ex->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="card">
<form method="post" data-validate>
<div class="form-grid">
<div><label>Employee *</label><select name="employee_id" required>
<?php foreach($emps as $x): ?><option value="<?= $x['id'] ?>" <?= ($_POST['employee_id'] ?? '')==$x['id']?'selected':'' ?>><?= e($x['employee_code'].' '.$x['first_name'].' '.$x['last_name'].' ('.$x['salary_type'].' '.($x['salary_type']==='hourly'?$x['hourly_rate']:$x['basic_salary']).')') ?></option><?php endforeach; ?></select></div>
<div><label>Period Start *</label><input type="date" name="period_start" required value="<?= e($_POST['period_start'] ?? date('Y-m-01')) ?>"></div>
<div><label>Period End *</label><input type="date" name="period_end" required value="<?= e($_POST['period_end'] ?? date('Y-m-15')) ?>"></div>
<div><label>Days Worked</label><input type="number" step="0.5" min="0" name="days_worked" value="<?= e($_POST['days_worked'] ?? '11') ?>"></div>
<div><label>Hours Per Day</label><input type="number" step="0.5" min="0" name="hours_per_day" value="<?= e($_POST['hours_per_day'] ?? $defHrs) ?>"></div>
<div><label>Regular Pay Base (₱) — half-month salary</label><input type="number" step="0.01" min="0" name="regular_pay_in" id="regular_pay_in" value="0"><small style="color:#64748B">Monthly staff: enter half of the monthly salary here (e.g. ₱22,500 for a ₱45,000 salary). Leave 0 to auto-compute from salary type.</small></div>
<div><label>Overtime Rate (₱/hr)</label><input type="number" step="0.01" min="0" name="overtime_rate" id="overtime_rate" value="<?= e($_POST['overtime_rate'] ?? $defOT) ?>"></div>
<div><label>Regular Hours (hourly staff)</label><input type="number" step="0.01" min="0" name="regular_hours" value="<?= e($_POST['regular_hours'] ?? '0') ?>"></div>
<div><label>Overtime Hours</label><input type="number" step="0.01" min="0" name="overtime_hours" id="overtime_hours" value="<?= e($_POST['overtime_hours'] ?? '0') ?>"></div>
</div>
<br>
<h4>Deductions (manual lines — pending employee deductions in period auto-added)</h4>
<div id="dedLines"><div style="display:flex;gap:8px;margin-bottom:6px">
<select name="ded_type[]"><?php foreach($types as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select>
<input type="number" step="0.01" min="0" name="ded_amount[]" id="deductions_in" placeholder="Amount ₱" value="0">
</div></div>
<button type="button" class="btn secondary small" onclick="addDedLine()">+ Add line</button>
<br><br>
<div class="card" style="background:#F1F5F9">
<b>Live preview (PHP recalculates on save):</b>
<div>Regular: <span id="pv-regular">₱0.00</span> · Overtime: <span id="pv-ot">₱0.00</span> · Gross: <span id="pv-gross">₱0.00</span> · Deductions: <span id="pv-ded">₱0.00</span> · <b>Net: <span id="pv-net">₱0.00</span></b></div>
</div>
<br>
<button class="btn" type="submit">Calculate & Save (Draft)</button>
<a class="btn secondary" href="index.php">Cancel</a>
</form>
</div>
<script>
const DED_OPTS = `<?php foreach($types as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>`;
function addDedLine(){
  const d=document.createElement('div');
  d.style.cssText='display:flex;gap:8px;margin-bottom:6px';
  d.innerHTML=`<select name="ded_type[]">${DED_OPTS}</select><input type="number" step="0.01" min="0" name="ded_amount[]" placeholder="Amount ₱" value="0">`;
  document.getElementById('dedLines').appendChild(d);
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
