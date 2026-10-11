<?php
// $pageTitle (string), $currentPage (string) should be set before including.
if (session_status() === PHP_SESSION_NONE) session_start();
$flash = null;
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Pay Pro Dynamics</title>
<link rel="stylesheet" href="<?= isset($basePath) ? e($basePath) : '.' ?>/assets/css/style.css">
</head>
<body>
<div class="app">
