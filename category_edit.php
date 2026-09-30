<?php
include 'conn.php';
include 'includes/functions.php';
requireAdmin();

$id = intval($_GET['id'] ?? 0);
$message = '';
$error = '';

// Get category
$stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$category = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$category) {
    die("Category not found");
}

// Update category
if (isset($_POST['update_category'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        $status = $_POST['status'] ?? 'active';
        $oldSlug = $category['slug'];

        if (empty($name) || empty($slug)) {
            $error = 'Category name is required.';
        } else {
            mysqli_begin_transaction($conn);
            try {
                // Update category (include status)
                $stmt = mysqli_prepare($conn, "UPDATE categories SET name = ?, slug = ?, status = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "sssi", $name, $slug, $status, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // Update questions in this category
                $stmt = mysqli_prepare($conn, "UPDATE questions SET category = ? WHERE category = ?");
                mysqli_stmt_bind_param($stmt, "ss", $slug, $oldSlug);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                mysqli_commit($conn);
                $message = 'Category updated successfully!';

                // Refresh category data
                $stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $category = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = 'Error updating category: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Edit Category';
include 'includes/header.php';
?>

<a href="categories.php" class="back-btn"><i class="bi bi-arrow-left"></i> Back</a>



<div class="container mt-4" style="max-width: 600px;">
    <h1><i class="bi bi-pencil"></i> Edit Category</h1>

    <?php if ($message) echo showToast($message, 'success'); ?>
    <?php if ($error) echo showToast($error, 'danger'); ?>

    <div class="card mt-4 text-white" style="background: linear-gradient(135deg, rgba(255,192,203,0.15), rgba(255,255,255,0.1)); backdrop-filter: blur(10px); border: 1px solid rgba(255,192,203,0.4);">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="mb-3">
                    <label class="form-label">Category Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($category['name']) ?>" required>
                </div>
<div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($category['slug']) ?>" disabled>
                    <div class="form-text text-white-50">Slug is auto-generated from name</div>
                </div>
<div class="mb-3">
                    <label class="form-label" style="color: #ffb6c1;">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($category['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($category['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <div class="form-text text-white-50">Set to Inactive to hide category from users</div>
                </div>
                <button type="submit" name="update_category" class="btn btn-warning">
                    <i class="bi bi-check-lg"></i> Update Category
                </button>
                <a href="categories.php" class="btn btn-outline-light">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

