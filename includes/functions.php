<?php
// Shared helpers — keep code beginner-friendly, one function per job.
if (session_status() === PHP_SESSION_NONE) session_start();

function e($str) { return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8'); }
function peso($n) { return '₱' . number_format((float)$n, 2); }
function base_url($path = '') { return $path; } // app lives at htdocs root subfolder; use relative links

function redirect($path) { header('Location: ' . $path); exit; }

function set_flash($type, $msg) { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
function get_flash() { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

function log_activity($action, $details = '') {
    try {
        $pdo = db();
        $uid = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)");
        $stmt->execute([$uid, $action, $details, $ip]);
    } catch (Exception $ex) { /* logging must never break the app */ }
}

function get_setting($key, $default = '') {
    try {
        $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $ex) { return $default; }
}

function employee_full_name($row) {
    $parts = array_filter([$row['first_name'] ?? '', $row['middle_name'] ?? '', $row['last_name'] ?? '']);
    return trim(implode(' ', $parts));
}

/** Server-authoritative payroll math. Returns array with regular_pay, overtime_pay, gross_pay, total_deductions, net_pay. */
function calc_payroll($basic_salary, $regular_hours, $hourly_rate_fallback, $overtime_hours, $overtime_rate, $deductions) {
    $basic_salary   = max(0, (float)$basic_salary);
    $regular_hours  = max(0, (float)$regular_hours);
    $overtime_hours = max(0, (float)$overtime_hours);
    $overtime_rate  = max(0, (float)$overtime_rate);
    $deductions     = max(0, (float)$deductions);

    // Regular pay: if hourly context, hours × rate; caller passes pre-resolved regular_pay base.
    // Here generic: regular_pay is passed via $basic_salary only when monthly semi-monthly already resolved.
    $regular_pay  = round($basic_salary + ($regular_hours * $hourly_rate_fallback), 2);
    $overtime_pay = round($overtime_hours * $overtime_rate, 2);
    $gross        = round($regular_pay + $overtime_pay, 2);
    $net          = round($gross - $deductions, 2);
    return [
        'regular_pay' => $regular_pay, 'overtime_pay' => $overtime_pay,
        'gross_pay' => $gross, 'total_deductions' => $deductions, 'net_pay' => $net,
    ];
}

/** Resolve monthly/daily/hourly employee to semi-monthly base + hourly rate. */
function resolve_employee_pay($emp, $days_worked, $hours_per_day, $regular_hours_input) {
    $type = $emp['salary_type'];
    $basic = (float)$emp['basic_salary'];
    $hourly = (float)$emp['hourly_rate'];
    $days = max(0, (float)$days_worked);

    if ($type === 'monthly') {
        $semi = round($basic / 2, 2);            // semi-monthly cut-off
        $eff_hourly = $hourly > 0 ? $hourly : round(($basic / 26 / 8), 2); // fallback daily divisor
        return ['base' => $semi, 'hourly_fallback' => 0, 'eff_hourly' => $eff_hourly, 'regular_hours' => (float)$regular_hours_input];
    }
    if ($type === 'daily') {
        return ['base' => round($basic * $days, 2), 'hourly_fallback' => 0, 'eff_hourly' => $hourly > 0 ? $hourly : round($basic / 8, 2), 'regular_hours' => (float)$regular_hours_input];
    }
    // hourly
    $rh = (float)$regular_hours_input > 0 ? (float)$regular_hours_input : ($days * (float)$hours_per_day);
    return ['base' => 0, 'hourly_fallback' => 0, 'eff_hourly' => $hourly, 'regular_hours' => $rh, '_hourly_mode' => true];
}
