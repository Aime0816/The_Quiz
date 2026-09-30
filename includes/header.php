<?php
$pageTitle = $pageTitle ?? 'THE QUIZ';
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$adminView = function_exists('isAdmin') && isAdmin();
$navItems = [['dashboard.php','▦','Overview'],['questions.php','▤','Question bank'],['categories.php','◇','Categories'],['insert.php','+','Add question']];
?>
<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#0b1426"><title><?= htmlspecialchars($pageTitle) ?> · THE QUIZ</title>
<link rel="stylesheet" href="assets/css/bootstrap.min.css"><link rel="stylesheet" href="assets/css/system.css">
<script src="assets/js/bootstrap.bundle.min.js"></script><script src="assets/js/system.js" defer></script></head>
<body class="quiz-system <?= $adminView ? 'has-sidebar' : 'guest-layout' ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<?php if ($adminView): ?>
<aside class="app-sidebar" id="app-sidebar"><a class="app-brand" href="dashboard.php"><span class="brand-mark">Q</span><span>THE QUIZ<small>HOST WORKSPACE</small></span></a><span class="nav-caption">WORKSPACE</span><nav aria-label="Main navigation">
<?php foreach ($navItems as [$href,$icon,$label]): $selected=$currentPage===$href || ($href==='questions.php' && in_array($currentPage,['update.php','category.php'])) || ($href==='categories.php' && $currentPage==='category_edit.php'); ?>
<a href="<?= $href ?>" <?= $selected ? 'class="selected" aria-current="page"' : '' ?>><span aria-hidden="true"><?= $icon ?></span><?= $label ?></a>
<?php endforeach; ?></nav><div class="sidebar-live"><span class="live-dot"></span>READY WHEN YOU ARE<h3>Your next great quiz<br>starts here.</h3><a class="btn btn-primary w-100" href="play/">Open live quiz ↗</a></div><div class="sidebar-bottom"><div class="avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['admin_username'] ?? 'H',0,1))) ?></div><div><strong><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Host') ?></strong><small>Quiz administrator</small></div><a href="logout.php" aria-label="Sign out" title="Sign out">↪</a></div></aside>
<?php endif; ?>
<div class="app-workspace"><header class="app-topbar"><?php if ($adminView): ?><button class="menu-toggle" aria-controls="app-sidebar" aria-expanded="false" aria-label="Open navigation">☰</button><span class="breadcrumb-label">Workspace <span>/</span> <?= htmlspecialchars($pageTitle) ?></span><?php else: ?><a class="app-brand" href="index.php"><span class="brand-mark">Q</span> THE QUIZ</a><?php endif; ?><div class="topbar-actions"><a href="<?= $adminView?'play/':'login.php' ?>"><?= $adminView?'Live quiz ↗':'Host sign in →' ?></a><button class="fullscreen-control" type="button" aria-label="Toggle fullscreen" title="Fullscreen"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/></svg></button></div></header><main id="main-content" class="app-content">
