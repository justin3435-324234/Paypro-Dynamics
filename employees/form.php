<?php
// Shared employee form fields (used by add.php and edit.php)
$depts = $pdo->query("SELECT * FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$poss  = $pdo->query("SELECT p.*, d.name dept FROM positions p JOIN departments d ON d.id=p.department_id WHERE p.status='active' ORDER BY d.name, p.title")->fetchAll();
$v = $form ?? [];
function fv($k, $d='') { global $v; return e($v[$k] ?? $d); }
?>
<form method="post" data-validate>
<div class="form-grid">
<div><label>Employee Code *</label><input name="employee_code" required maxlength="20" value="<?= fv('employee_code') ?>" placeholder="EMP-0006"></div>
<div><label>Email *</label><input type="email" name="email" required value="<?= fv('email') ?>"></div>
<div><label>First Name *</label><input name="first_name" required maxlength="60" value="<?= fv('first_name') ?>"></div>
<div><label>Middle Name</label><input name="middle_name" maxlength="60" value="<?= fv('middle_name') ?>"></div>
<div><label>Last Name *</label><input name="last_name" required maxlength="60" value="<?= fv('last_name') ?>"></div>
<div><label>Phone</label><input name="phone" maxlength="20" value="<?= fv('phone') ?>"></div>
<div class="full"><label>Address</label><textarea name="address" rows="2"><?= fv('address') ?></textarea></div>
<div><label>Date of Birth</label><input type="date" name="date_of_birth" value="<?= fv('date_of_birth') ?>"></div>
<div><label>Date Hired *</label><input type="date" name="date_hired" required value="<?= fv('date_hired', date('Y-m-d')) ?>"></div>
<div><label>Department *</label><select name="department_id" required>
<?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>" <?= (string)($v['department_id'] ?? '')===(string)$d['id']?'selected':'' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Position *</label><select name="position_id" required>
<?php foreach ($poss as $p): ?><option value="<?= $p['id'] ?>" <?= (string)($v['position_id'] ?? '')===(string)$p['id']?'selected':'' ?>><?= e($p['dept'].' — '.$p['title']) ?></option><?php endforeach; ?></select></div>
<div><label>Employment Status</label><select name="employment_status">
<?php foreach (['Regular','Probationary','Contractual','Resigned'] as $s): ?><option <?= ($v['employment_status'] ?? '')===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
<div><label>Account Status</label><select name="status">
<option value="active" <?= ($v['status'] ?? 'active')==='active'?'selected':'' ?>>active</option>
<option value="inactive" <?= ($v['status'] ?? '')==='inactive'?'selected':'' ?>>inactive</option></select></div>
<div><label>Salary Type</label><select name="salary_type" id="stype">
<option value="monthly" <?= ($v['salary_type'] ?? 'monthly')==='monthly'?'selected':'' ?>>monthly</option>
<option value="daily" <?= ($v['salary_type'] ?? '')==='daily'?'selected':'' ?>>daily</option>
<option value="hourly" <?= ($v['salary_type'] ?? '')==='hourly'?'selected':'' ?>>hourly</option></select></div>
<div><label>Basic Salary (₱) *</label><input type="number" step="0.01" min="0" name="basic_salary" required value="<?= fv('basic_salary','0') ?>"></div>
<div><label>Hourly Rate (₱)</label><input type="number" step="0.01" min="0" name="hourly_rate" value="<?= fv('hourly_rate','0') ?>"></div>
</div>
<br>
<button class="btn" type="submit"><?= e($submitLabel ?? 'Save Employee') ?></button>
<a class="btn secondary" href="index.php">Cancel</a>
<button class="btn secondary" type="reset">Clear</button>
</form>
