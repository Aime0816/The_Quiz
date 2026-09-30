<?php
include 'conn.php';
include 'includes/functions.php';
requireAdmin();

$message = '';
$error = '';

// Toggle category status
if (isset($_POST['toggle_status']) && isset($_POST['category_id'])) {
    $catId = intval($_POST['category_id']);
    $cat = getCategoryById($conn, $catId);
    if ($cat) {
        $newStatus = ($cat['status'] ?? 'active') === 'active' ? 'inactive' : 'active';
        setCategoryStatus($conn, $catId, $newStatus);
    }
}

// Add new category
if (isset($_POST['add_category'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));

        if (empty($name) || empty($slug)) {
            $error = 'Category name is required.';
        } elseif (strlen($slug) < 2) {
            $error = 'Category name too short.';
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO categories (name, slug) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "ss", $name, $slug);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Category added successfully!';
            } else {
                $error = 'Category already exists or error occurred.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get all categories
$categories = getCategories($conn);

$pageTitle = 'Manage Categories';
include 'includes/header.php';
?>

<a href="dashboard.php" class="back-btn"><i class="bi bi-arrow-left"></i> Back</a>



<div class="container mt-4" style="max-width: 900px;">
    <h1><i class="bi bi-tags"></i> Manage Categories</h1>

    <?php if ($message) echo showToast($message, 'success'); ?>
    <?php if ($error) echo showToast($error, 'danger'); ?>

<!-- Add Category Form -->
    <div class="card mt-4 text-white" style="background: linear-gradient(135deg, rgba(255,192,203,0.15), rgba(255,255,255,0.1)); backdrop-filter: blur(10px); border: 1px solid rgba(255,192,203,0.4);">
        <div class="card-body">
            <h5 class="card-title" style="color: #ffb6c1;"><i class="bi bi-plus-circle"></i> Add New Category</h5>
            <form method="POST" class="row g-3 mt-2">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="col-md-8">
                    <label class="form-label" for="category-name">Category name</label><input id="category-name" type="text" name="name" class="form-control" placeholder="e.g. Science" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="add_category" class="btn btn-success w-100">
                        <i class="bi bi-plus-lg"></i> Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>

<!-- Categories List -->
    <div class="card mt-4 text-white" style="background: linear-gradient(135deg, rgba(255,192,203,0.15), rgba(255,255,255,0.1)); backdrop-filter: blur(10px); border: 1px solid rgba(255,192,203,0.4);">
        <div class="card-body">
            <h5 class="card-title" style="color: #fff;"><i class="bi bi-list"></i> All Categories</h5>
            <div class="table-responsive mt-3">
                <table class="table table-dark table-hover">
<thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
<td><?= $cat['id'] ?></td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                            <td>
                                <?php if (($cat['status'] ?? 'active') === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($cat['created_at'])) ?></td>
<td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-sm <?= ($cat['status'] ?? 'active') === 'active' ? 'btn-outline-secondary' : 'btn-outline-success' ?>" title="<?= ($cat['status'] ?? 'active') === 'active' ? 'Disable' : 'Enable' ?>">
                                        <i class="bi <?= ($cat['status'] ?? 'active') === 'active' ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                        <?= ($cat['status'] ?? 'active') === 'active' ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                                <a href="category_edit.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <a href="category_delete.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure? This will also delete all questions in this category!')">
                                    <i class="bi bi-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

