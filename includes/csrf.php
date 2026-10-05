<?php
// Simple CSRF protection for important forms (payroll process, deletes, user save).
if (session_status() === PHP_SESSION_NONE) session_start();
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check() {
    $sent = $_POST['csrf'] ?? '';
    if (empty($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        set_flash('error', 'Invalid request token. Please try again.');
        return false;
    }
    return true;
}
