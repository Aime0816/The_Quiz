<?php
include 'conn.php'; include 'includes/functions.php'; requireAdmin();
$pageTitle='Overview';
$totalQuestions=(int)mysqli_fetch_assoc(mysqli_query($conn,'SELECT COUNT(*) AS count FROM questions'))['count'];
$activeQuestions=(int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS count FROM questions WHERE status='active'"))['count'];
$inactiveQuestions=$totalQuestions-$activeQuestions;
$totalCategories=(int)mysqli_fetch_assoc(mysqli_query($conn,'SELECT COUNT(*) AS count FROM categories'))['count'];
$catStats=mysqli_fetch_all(mysqli_query($conn,'SELECT c.name,c.slug,COUNT(q.id) AS total FROM categories c LEFT JOIN questions q ON c.slug=q.category GROUP BY c.id,c.name,c.slug ORDER BY total DESC,c.name'),MYSQLI_ASSOC);
include 'includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">YOUR QUIZ, AT A GLANCE</p><h1>Welcome back, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Host') ?>.</h1><p>Prepare your questions. Bring your teams together. Make it memorable.</p></div><a class="btn btn-outline-info" href="insert.php">+ Add question</a></div>
<section class="hero-banner"><div><p class="eyebrow">LET’S MAKE IT A GREAT ROUND</p><h2>A little knowledge.<br>A lot of team spirit.</h2><p>Your host desk is ready. Create a session, add your teams, and let the questions begin.</p><a class="btn btn-primary" href="play/">Open live quiz →</a></div><div class="hero-art" aria-hidden="true"><span>?</span><span>Q</span></div></section>
<div class="stat-grid">
<?php foreach ([['Question bank',$totalQuestions,'Questions in your collection','▤'],['Categories',$totalCategories,'Topics to explore','◇'],['Active questions',$activeQuestions,'Legacy question status','✓'],['Inactive questions',$inactiveQuestions,'Previously used in classic mode','↺']] as [$label,$value,$note,$icon]): ?>
<article class="stat-card"><div class="stat-label"><?= $label ?><span class="stat-icon" aria-hidden="true"><?= $icon ?></span></div><strong><?= $value ?></strong><small><?= $note ?></small></article>
<?php endforeach; ?></div>
<div class="dashboard-grid"><section class="panel"><div class="panel-heading"><div><h2>Your question collection</h2><p>Questions across all categories</p></div><a class="btn btn-sm btn-outline-info" href="questions.php">View all ↗</a></div>
<?php if (!$catStats): ?><div class="empty-state"><span>◇</span><h3>Start with a category</h3><p>Add your first topic to begin building your collection.</p><a class="btn btn-primary" href="categories.php">Create category</a></div><?php else: foreach ($catStats as $cat): ?>
<div class="category-bar"><div><a href="questions.php?cat=<?= urlencode($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></a><span><?= (int)$cat['total'] ?> questions</span></div><div class="bar-track"><span style="width:<?= $totalQuestions ? round(100*(int)$cat['total']/$totalQuestions,1) : 0 ?>%"></span></div></div>
<?php endforeach; endif; ?></section><section class="panel"><div class="panel-heading"><h2>Make it happen</h2></div><a class="quick-link" href="insert.php"><span>+</span><div><b>Add a question</b><small>Build your next great round</small></div>↗</a><a class="quick-link" href="categories.php"><span>◇</span><div><b>Organize categories</b><small>Give every topic its place</small></div>↗</a><a class="quick-link" href="play/"><span>▷</span><div><b>Host a team quiz</b><small>Sessions, scores and audience view</small></div>↗</a><a class="quick-link" href="cat.php"><span>▦</span><div><b>Classic question board</b><small>Browse the original quiz mode</small></div>↗</a></section></div>
<?php include 'includes/footer.php'; ?>
