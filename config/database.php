<?php
// Pay Pro Dynamics — PDO database connection (XAMPP: host=localhost, user=root, pass='')
define('DB_HOST', 'localhost');
define('DB_NAME', 'pay_pro_dynamics');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function db() {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    } catch (PDOException $e) {
        // Friendly message — never leak raw SQL details to normal users
        http_response_code(500);
        die('<h2>Database connection failed.</h2><p>Please check that XAMPP MySQL is running and the database <b>pay_pro_dynamics</b> is imported.</p>');
    }
    return $pdo;
}
