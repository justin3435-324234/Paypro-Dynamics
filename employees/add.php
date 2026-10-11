<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']);
$pageTitle = 'Add Employee'; $currentPage = 'employees'; $basePath = '..';
$pdo = db();
$form = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $req = ['employee_code','first_name','last_name','email','date_hired','department_id','position_id'];
        foreach ($req as $f) if (trim($_POST[$f] ?? '') === '') throw new Exception('Missing required field: ' . $f);
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email address.');
        if ((float)($_POST['basic_salary'] ?? 0) < 0 || (float)($_POST['hourly_rate'] ?? 0) < 0) throw new Exception('Salary values cannot be negative.');
        $stmt = $pdo->prepare("INSERT INTO employees (employee_code,first_name,middle_name,last_name,email,phone,address,date_of_birth,date_hired,department_id,position_id,employment_status,status,salary_type,basic_salary,hourly_rate)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['employee_code']), trim($_POST['first_name']), trim($_POST['middle_name'] ?: ''),
            trim($_POST['last_name']), trim($_POST['email']), trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? ''),
            $_POST['date_of_birth'] ?: null, $_POST['date_hired'], (int)$_POST['department_id'], (int)$_POST['position_id'],
            $_POST['employment_status'] ?? 'Regular', $_POST['status'] ?? 'active', $_POST['salary_type'] ?? 'monthly',
            (float)$_POST['basic_salary'], (float)($_POST['hourly_rate'] ?? 0),
        ]);
        log_activity('EMP_CREATE', 'Added employee ' . trim($_POST['employee_code']));
        set_flash('success', 'Employee successfully added.');
        redirect('index.php');
    } catch (PDOException $ex) {
        $msg = $ex->getMessage();
        set_flash('error', str_contains($msg,'employees.email') ? 'Email already exists.' : (str_contains($msg,'employee_code') ? 'Employee code already exists.' : 'Database error: duplicate or invalid reference.'));
    } catch (Exception $ex) { set_flash('error', $ex->getMessage()); }
}
$submitLabel = 'Save Employee';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
echo '<div class="card">';
require __DIR__ . '/form.php';
echo '</div>';
require_once __DIR__ . '/../includes/footer.php';
