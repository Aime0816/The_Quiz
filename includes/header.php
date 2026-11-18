<?php
$pageTitle = $pageTitle ?? "THE QUIZ";
$user = currentUser();
$currentPage = basename($_SERVER["SCRIPT_NAME"]);
$navItems = [
    ["dashboard.php", "Overview"],
    ["questions.php", "Question bank"],
    ["insert.php", "Add question"],
];
if (isAdmin()) {
    $navItems[] = ["categories.php", "Categories"];
    $navItems[] = ["users.php", "Users & roles"];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="referrer" content="no-referrer">
<title>
<?= h(
    $pageTitle,
) ?> · THE QUIZ</title>
<script>try{document.documentElement.dataset.theme=localStorage.getItem('quiz-workspace-theme')||'light'}catch{}</script>
<link rel="stylesheet" href="assets/css/system.css">
<script src="assets/js/system.js" defer>
</script>
</head>
<body class="quiz-system <?= $user
    ? "has-sidebar"
    : "guest-layout" ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<?php if (
    $user
): ?>
<aside class="app-sidebar" id="app-sidebar">
<a class="app-brand" href="dashboard.php">
<span class="brand-mark">Q</span>
<span>THE QUIZ<small>
<?= h(
    strtoupper($user["role"] === "setter" ? "QUESTION SETTER" : $user["role"]),
) ?> WORKSPACE</small>
</span>
</a>
<p class="nav-caption">WORKSPACE</p>
<nav aria-label="Main navigation">
<?php
 foreach (
     $navItems
     as [$href, $label]
 ): ?>
<a href="<?= $href ?>" <?= $currentPage === $href
    ? 'aria-current="page"'
    : "" ?>>
<?= $label ?>
</a>
<?php endforeach;
 if (isAdmin()): ?>
<a href="play/">Marker dashboard ↗</a>
<?php endif;
 ?>
</nav>
<div class="sidebar-bottom">
<b>
<?= h(
    $user["username"],
) ?>
</b>
<small>
<?= h(
    $user["role"],
) ?>
</small>
<form action="logout.php" method="post">
<input type="hidden" name="csrf_token" value="<?= h(
    generateCsrfToken(),
) ?>">
<button>Sign out</button>
</form>
</div>
</aside>
<?php endif; ?>
<div class="app-workspace">
<header class="app-topbar">
<?php if (
    $user
): ?>
<button class="menu-toggle" aria-controls="app-sidebar" aria-expanded="false">Menu</button>
<span>
<?= h(
    $pageTitle,
) ?>
</span>
<?php else: ?>
<a class="app-brand" href="index.php">
<span class="brand-mark">Q</span>THE QUIZ</a>
<?php endif; ?>
<button class="theme-toggle" type="button">Switch theme</button>
</header>
<main class="app-content" id="main-content">
<?php if (isset($_SESSION["flash"])) {
    echo showToast($_SESSION["flash"]);
    unset($_SESSION["flash"]);
} ?>
