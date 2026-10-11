<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) redirect(($_SESSION['role'] ?? '') === 'EMPLOYEE' ? 'payroll/my_payslip.php' : 'dashboard.php');
redirect('login.php');
