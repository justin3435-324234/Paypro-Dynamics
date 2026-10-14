<?php
// Auth + RBAC guard. Include at top of every protected page:
//   require_once __DIR__ . '/../includes/auth.php'; requireLogin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function current_user() {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        set_flash('error', 'Please log in first.');
        redirect('../login.php');
    }
    // Re-validate account still active
    $u = current_user();
    if (!$u || $u['status'] !== 'active') {
        session_unset(); session_destroy();
        if (session_status() === PHP_SESSION_NONE) session_start();
        set_flash('error', 'Account is disabled. Contact admin.');
        redirect('../login.php');
    }
}

function requireRole($roles) {
    requireLogin();
    $roles = (array)$roles;
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        die('<h2>403 — Forbidden.</h2><p>Your role (' . e($role) . ') cannot access this page.</p><p><a href="../dashboard.php">Back to dashboard</a></p>');
    }
}

function is_admin()   { return ($_SESSION['role'] ?? '') === 'ADMIN'; }
function is_manager() { return ($_SESSION['role'] ?? '') === 'MANAGER'; }
