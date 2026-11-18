<?php
require "conn.php";
require "includes/functions.php";
requireAdmin();
$id = (int) ($_GET["id"] ?? 0);
$cat = getCategoryById($conn, $id);
if (!$cat) {
    http_response_code(404);
    exit("Category not found.");
}
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    checkCsrf();
    $name = trim($_POST["name"] ?? "");
    if (!$name || strlen($name) > 150) {
        $error = "Enter a name up to 150 bytes.";
    } else {
        // Slugs remain stable so existing questions never need renaming.
        $stmt = dbQuery(
            $conn,
            "UPDATE categories SET name=?,archived=?,version=version+1 WHERE id=? AND version=?",
            "siii",
            [
                $name,
                isset($_POST["archived"]) ? 1 : 0,
                $id,
                (int) ($_POST["version"] ?? 0),
            ],
        );
        if ($stmt->affected_rows !== 1) {
            http_response_code(409);
            $error =
                "This category changed. Reload, compare the latest version, and try again.";
        } else {
            flash("Category saved. Existing competitions are unchanged.");
            redirect("categories.php");
        }
    }
}
$pageTitle = "Edit category";
include "includes/header.php";
?>
<h1>Edit category</h1>
<?= $error
    ? showToast($error, "danger")
    : "" ?>
<form class="panel narrow" method="post">
<input type="hidden" name="csrf_token" value="<?= h(
    generateCsrfToken(),
) ?>">
<input type="hidden" name="version" value="<?= (int) $cat[
    "version"
] ?>">
<label>Name<input name="name" maxlength="150" value="<?= h(
    $_POST["name"] ?? $cat["name"],
) ?>" required>
</label>
<p>Category key: <?= h(
    $cat["slug"],
) ?>. This stays fixed to preserve question links.</p>
<label class="check">
<input type="checkbox" name="archived" <?= $cat[
    "archived"
]
    ? "checked"
    : "" ?>> Archived (hide from new session setup)</label>
<div class="actions">
<button class="primary">Save category</button>
<a class="button" href="categories.php">Cancel</a>
</div>
</form>
<?php include "includes/footer.php"; ?>
