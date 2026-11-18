<?php
require "conn.php";
require "includes/functions.php";
$user = requireRole(["admin", "setter", "marker"]);
if ($user["role"] === "marker") {
    redirect("play/");
}
$pageTitle = isAdmin() ? "Admin dashboard" : "Question setter dashboard";
$total = (int) $conn->query("SELECT COUNT(*) FROM questions")->fetch_row()[0];
$available = (int) $conn
    ->query("SELECT COUNT(*) FROM questions WHERE archived=0")
    ->fetch_row()[0];
$categories = getCategories($conn);
include "includes/header.php";
?>
<div class="page-heading">
<div>
<p class="eyebrow">
<?= h(
    strtoupper($pageTitle),
) ?>
</p>
<h1>Welcome, <?= h(
    $user["username"],
) ?>.</h1>
<p>Prepare a competition your students will remember.</p>
</div>
<a class="button primary" href="insert.php">+ Add question</a>
</div>
<section class="hero-banner">
<p class="eyebrow">A LITTLE KNOWLEDGE. A LOT OF TEAM SPIRIT.</p>
<h2>Good questions.<br>Great conversations.</h2>
<p>Organize your collection, check the answers, and give every team a chance to shine.</p>
<a class="button" href="questions.php">Explore your question bank →</a>
</section>
<div class="stat-grid">
<?php foreach (
    [
        ["Questions", $total],
        ["Available in bank", $available],
        ["Categories", count($categories)],
    ]
    as [$label, $value]
): ?>
<article class="panel">
<p>
<?= $label ?>
</p>
<strong class="stat-value">
<?= $value ?>
</strong>
</article>
<?php endforeach; ?>
</div>
<section class="panel">
<p class="eyebrow">YOUR WORKSPACE</p>
<h2>
<?= isAdmin()
    ? "Prepare, organize, and delegate."
    : "Build together with confidence." ?>
</h2>
<p>Each question keeps an edit version. If a colleague saves first, your draft stays on screen so you can compare it before trying again. Existing competitions keep the questions and images they started with.</p>
<?php if (
    isAdmin()
): ?>
<div class="actions">
<a class="button" href="users.php">Manage individual accounts</a>
<a class="button" href="categories.php">Manage reusable categories</a>
<a class="button" href="play/">Open marker dashboard ↗</a>
</div>
<?php endif; ?>
</section>
<?php include "includes/footer.php"; ?>
