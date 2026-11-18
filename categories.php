<?php
require "conn.php";
require "includes/functions.php";
requireAdmin();
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    checkCsrf();
    $name = trim($_POST["name"] ?? "");
    try {
        if (!$name || strlen($name) > 150) {
            throw new DomainException("Enter a category name up to 150 bytes.");
        }
        $slug = strtolower(
            trim(preg_replace("/[^a-zA-Z0-9]+/", "-", $name), "-"),
        );
        if (!$slug) {
            $slug = "category-" . bin2hex(random_bytes(4));
        }
        dbQuery($conn, "INSERT INTO categories(name,slug) VALUES(?,?)", "ss", [
            $name,
            $slug,
        ]);
        flash("Category created. Reuse it in any competition.");
        redirect("categories.php");
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() !== 1062) {
            throw $e;
        }
        $error =
            "That category already exists. Reuse or rename the existing category.";
    } catch (DomainException $e) {
        $error = $e->getMessage();
    }
}
$categories = getCategories($conn);
$pageTitle = "Categories";
include "includes/header.php";
?>
<div class="page-heading">
<div>
<p class="eyebrow">ORGANIZE YOUR COLLECTION</p>
<h1>Reusable categories</h1>
<p>Create each category once. Select it for any new session.</p>
</div>
</div>
<?= $error
    ? showToast($error, "danger")
    : "" ?>
<form class="panel inline-form" method="post">
<input type="hidden" name="csrf_token" value="<?= h(
    generateCsrfToken(),
) ?>">
<label>Category name<input name="name" maxlength="150" required>
</label>
<button class="primary">Add category</button>
</form>
<div class="category-grid">
<?php foreach (
    $categories
    as $cat
): ?>
<article class="panel">
<span class="pill">
<?= $cat["archived"]
    ? "Archived"
    : "Available" ?>
</span>
<h2>
<?= h(
    $cat["name"],
) ?>
</h2>
<a class="button" href="category_edit.php?id=<?= (int) $cat[
    "id"
] ?>">Edit category</a>
</article>
<?php endforeach; ?>
</div>
<?php include "includes/footer.php"; ?>
