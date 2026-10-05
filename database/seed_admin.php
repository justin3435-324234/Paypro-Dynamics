<?php
// One-time admin seeder — visit http://localhost/pay-pro-dynamics/database/seed_admin.php once, then DELETE this file.
require_once __DIR__ . '/../config/database.php';
$users = [
  ['admin',   'admin@paypro.local',    'Admin123!',    'ADMIN',    null],
  ['manager', 'manager@paypro.local',  'Manager123!',  'MANAGER',  null],
  ['employee','employee@paypro.local', 'Employee123!', 'EMPLOYEE', null],
];
$pdo = db();
foreach ($users as [$u, $e, $p, $r, $emp]) {
    $hash = password_hash($p, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username,email,password_hash,role,employee_id,status)
      VALUES (?,?,?,?,?,'active') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role=VALUES(role)");
    $stmt->execute([$u, $e, $hash, $r, $emp]);
}
echo "Seeded OK. <b>Delete this file now.</b><br>admin / Admin123!<br>manager / Manager123!<br>employee / Employee123!";
