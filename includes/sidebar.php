<?php
// Expects $currentPage. $basePath = '..' from subfolders, '.' from root.
$bp = isset($basePath) ? $basePath : '.';
$nav = [
  'dashboard'   => ['Dashboard', "$bp/dashboard.php"],
  'employees'   => ['Employees', "$bp/employees/index.php"],
  'departments' => ['Departments', "$bp/departments/index.php"],
  'positions'   => ['Positions', "$bp/positions/index.php"],
  'attendance'  => ['Attendance', "$bp/attendance/index.php"],
  'payroll'     => ['Payroll', "$bp/payroll/index.php"],
  'deductions'  => ['Deductions', "$bp/deductions/index.php"],
  'reports'     => ['Reports', "$bp/reports/index.php"],
  'users'       => ['Users', "$bp/users/index.php"],
  'settings'    => ['Settings', "$bp/settings/index.php"],
];
$role = $_SESSION['role'] ?? '';
// Admins never see Payroll (backend requireRole(['MANAGER']) still enforces)
if ($role === 'ADMIN') {
    unset($nav['payroll']);
}
// Managers never see Users (backend requireRole(['ADMIN']) still enforces)
if ($role === 'MANAGER') {
    unset($nav['users']);
}
// Employees only see limited nav (backend still enforces)
if ($role === 'EMPLOYEE') {
    // Employees get only My Payslip + My Profile (no dashboard, attendance, payroll, reports, settings)
    $myEid = (int)($_SESSION['employee_id'] ?? 0);
    $nav = ['mypayslip' => ['My Payslip', "$bp/payroll/my_payslip.php"]];
    if ($myEid > 0) $nav['profile'] = ['My Profile', "$bp/employees/view.php?id=$myEid"];
}
?>
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="brand-mark">PP</div>
    <div><div class="brand-name">Pay Pro Dynamics</div><div class="brand-tag">Smarter Payroll. Simpler Management.</div></div>
  </div>
  <nav class="side-nav">
    <?php foreach ($nav as $key => [$label, $url]): ?>
      <a href="<?= e($url) ?>" class="<?= ($currentPage ?? '') === $key ? 'active' : '' ?>">
        <span class="dot"></span><?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot"><a class="logout" href="<?= e($bp) ?>/logout.php">Logout</a></div>
</aside>
<div class="main">
