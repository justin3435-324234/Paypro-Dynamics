<?php
// Top header: page title and user profile.
$bp = isset($basePath) ? $basePath : '.';
$uname = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? '';
?>
<header class="topbar">
  <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">☰</button>
  <h1 class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
  <div class="top-right">
    <span class="profile"><?= e($uname) ?> <small><?= e($role) ?></small></span>
  </div>
</header>
<main class="content">
<?php if (!empty($flash)): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif; ?>
