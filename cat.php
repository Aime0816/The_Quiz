<?php
include 'conn.php';
include 'includes/functions.php';

// Show all categories to admin, only active to regular users
if (isAdmin()) {
    $categories = getCategories($conn);
} else {
    $categories = getCategoriesByStatus($conn, 'active');
}
$pageTitle = 'Choose Category';
include 'includes/header.php';
?>

<div class="page-heading"><div><p class="eyebrow">CLASSIC QUIZ</p><h1>Follow your curiosity.</h1><p>Choose a topic and explore its numbered question board.</p></div><a class="btn btn-outline-info" href="play/">Switch to team quiz →</a></div><div class="category-grid">
<?php foreach ($categories as $i=>$cat): ?><a class="category-tile" href="category.php?cat=<?= urlencode($cat['slug']) ?>"><span aria-hidden="true"><?= ['◇','✦','◎','♫','▦','◈'][$i%6] ?></span><h2><?= htmlspecialchars($cat['name']) ?></h2><p>Explore questions →</p></a><?php endforeach; ?>
<?php if (!$categories): ?><div class="empty-state"><h2>No categories yet</h2><p>Your quiz topics will appear here.</p></div><?php endif; ?></div>
<?php include 'includes/footer.php'; ?>
