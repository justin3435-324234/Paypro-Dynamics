<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['ADMIN','MANAGER']);
$pageTitle = 'Edit Employee'; $currentPage = 'employees'; $basePath = '..';
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id=?");
$stmt->execute([$id]);
$emp = $stmt->fetch();
if (!$emp) { set_flash('error','Employee not found.'); redirect('index.php'); }
$form = array_merge($emp, $_POST);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email address.');
        if ((float)$_POST['basic_salary'] < 0 || (float)($_POST['hourly_rate'] ?? 0) < 0) throw new Exception('Salary values cannot be negative.');
        $stmt = $pdo->prepare("UPDATE employees SET employee_code=?,first_name=?,middle_name=?,last_name=?,email=?,phone=?,address=?,date_of_birth=?,date_hired=?,department_id=?,position_id=?,employment_status=?,status=?,salary_type=?,basic_salary=?,hourly_rate=? WHERE id=?");
        $stmt->execute([
            trim($_POST['employee_code']), trim($_POST['first_name']), trim($_POST['middle_name'] ?: ''),
            trim($_POST['last_name']), trim($_POST['email']), trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? ''),
            $_POST['date_of_birth'] ?: null, $_POST['date_hired'], (int)$_POST['department_id'], (int)$_POST['position_id'],
            $_POST['employment_status'], $_POST['status'], $_POST['salary_type'],
            (float)$_POST['basic_salary'], (float)($_POST['hourly_rate'] ?? 0), $id,
        ]);
        log_activity('EMP_UPDATE', "Updated employee #$id");
        set_flash('success', 'Employee updated.');
        redirect('view.php?id=' . $id);
    } catch (PDOException $ex) {
        set_flash('error', 'Duplicate employee code or email. Please use unique values.');
    } catch (Exception $ex) { set_flash('error', $ex->getMessage()); }
}
$submitLabel = 'Update Employee';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
echo '<div class="card">';
require __DIR__ . '/form.php';
echo '</div>';
require_once __DIR__ . '/../includes/footer.php';
