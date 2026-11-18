<?php
require "conn.php";
require "includes/functions.php";
requireRole(["admin", "setter"]);
$pageTitle = "Question bank";
$categories = getCategories($conn);
$questions = $conn
    ->query(
        "SELECT q.*,c.name category_name,u.username editor FROM questions q LEFT JOIN categories c ON c.slug=q.category LEFT JOIN admins u ON u.id=q.updated_by ORDER BY q.id DESC",
    )
    ->fetch_all(MYSQLI_ASSOC);
include "includes/header.php";
?>
<div class="page-heading">
<div>
<p class="eyebrow">BUILD A BRILLIANT ROUND</p>
<h1>Question bank</h1>
<p>Reusable questions for every competition.</p>
</div>
<a class="button primary" href="insert.php">+ Add question</a>
</div>
<section class="panel">
<div class="question-toolbar">
<label>Search your collection<input id="question-search" type="search" placeholder="Question, answer, or category">
</label>
<label>Category<select id="category-filter">
<option value="">All categories</option>
<?php foreach (
    $categories
    as $cat
): ?>
<option value="<?= h($cat["slug"]) ?>" <?= ($_GET["cat"] ?? "") ===
$cat["slug"]
    ? "selected"
    : "" ?>>
<?= h(
    $cat["name"],
) ?>
</option>
<?php endforeach; ?>
</select>
</label>
<button id="clear-filters">Clear</button>
</div>
<p id="result-count" role="status">
<?= count(
    $questions,
) ?> questions</p>
<div class="table-scroll">
<table>
<thead>
<tr>
<th>Question & answer</th>
<th>Category</th>
<th>Points</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach (
     $questions
     as $q
 ): ?>
<tr data-question-row data-category="<?= h(
    $q["category"],
) ?>">
<td>
<b>
<?= h($q["question"]) ?>
</b>
<small>Answer: <?= h(
    $q["answer"],
) ?>
</small>
<small>Version <?=
(int) $q["version"] . ($q["editor"] ? " · " . h($q["editor"]) : "")
?>
</small>
<?php if (
    $q["image"] &&
    (!imagePath($q["image"]) ||
        !is_file(__DIR__ . "/" . imagePath($q["image"])))
): ?>
<small class="danger-text">Image needs attention</small>
<?php endif; ?>
</td>
<td>
<?= h(
    $q["category_name"] ?? $q["category"],
) ?>
</td>
<td>
<?= (int) $q["points"] ?>
</td>
<td>
<span class="pill">
<?= $q[
    "archived"
]
    ? "Archived"
    : "Available" ?>
</span>
</td>
<td>
<a class="button" href="update.php?id=<?= (int) $q[
    "id"
] ?>">Edit #<?= (int) $q[
    "id"
] ?>
</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<p id="search-empty" hidden>No questions match your filters.</p>
</section>
<?php include "includes/footer.php"; ?>
