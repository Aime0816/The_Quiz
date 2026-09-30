<?php
include 'conn.php';
include 'includes/functions.php';
requireAdmin();

$message = '';
$error = '';
$categories = getCategories($conn);

// GET existing data
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid request");
}

$question = getQuestionById($conn, $id);
if (!$question) {
    die("Question not found");
}

// Handle update
if (isset($_POST['update'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $newQuestion = trim($_POST['question'] ?? '');
        $category = $_POST['category'] ?? '';
        $answer = trim($_POST['answer'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($newQuestion) || empty($category) || empty($answer)) {
            $error = 'All fields are required.';
        } elseif (strlen($newQuestion) < 3) {
            $error = 'Question must be at least 3 characters.';
        } elseif (strlen($answer) < 1) {
            $error = 'Answer is required.';
        } else {
            $imagePath = $question['image']; // keep old image by default
            $oldImage = $question['image']; // track for cleanup

            // if new image uploaded
            if (!empty($_FILES['image']['name'])) {
                $newImagePath = uploadImage($_FILES['image']);
                if (!$newImagePath) {
                    $error = 'Invalid image. Only JPG, PNG, WEBP under 2MB allowed.';
                } else {
                    $imagePath = $newImagePath;
                }
            }

            if (empty($error)) {
                $stmt = mysqli_prepare($conn, "UPDATE questions SET question=?, category=?, answer=?, image=?, status=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, "sssssi", $newQuestion, $category, $answer, $imagePath, $status, $id);
                
if (mysqli_stmt_execute($stmt)) {
                    // Delete old image if new one was uploaded successfully
                    if ($imagePath !== $oldImage && !empty($oldImage)) {
                        deleteImage($oldImage);
                    }
                    $message = 'Question updated successfully!';
                    
// Auto-disable category if all questions become inactive
                    if ($status === 'inactive') {
                        autoDisableCategory($conn, $category);
                    } elseif ($status === 'active') {
                        // Auto-enable category when a question becomes active
                        $categoryData = getCategoryBySlug($conn, $category);
                        if ($categoryData && ($categoryData['status'] ?? 'active') === 'inactive') {
                            setCategoryStatus($conn, $categoryData['id'], 'active');
                        }
                    }
                    
                    // Refresh question data
                    $question = getQuestionById($conn, $id);
                } else {
                    $error = 'Database error: ' . mysqli_stmt_error($stmt);
                    // Cleanup new image if update failed
                    if ($imagePath !== $question['image']) {
                        deleteImage($imagePath);
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$pageTitle = 'Update Question';
?>
<?php include 'includes/header.php'; ?>
<div class="page-heading"><div><p class="eyebrow">YOUR QUESTION COLLECTION</p><h1>Refine your question</h1><p>Give the next round something worth thinking about.</p></div><a class="btn btn-outline-info" href="questions.php">← Question bank</a></div><div class="editor-grid">



<form class="editor-form" method="POST" enctype="multipart/form-data">
<h2><i class="bi bi-pencil-square"></i> Update Question</h2>

<?php if ($message) echo showToast($message, 'success'); ?>
<?php if ($error) echo showToast($error, 'danger'); ?>

<input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
<input type="hidden" name="id" value="<?= $question['id'] ?>">

<div class="input-group"><label class="field-label" for="field-question">Question</label>
    <span class="input-group-text"><i class="bi bi-question-circle"></i></span>
    <input id="field-question" type="text" name="question" class="form-control" placeholder="Enter question" value="<?= htmlspecialchars($question['question']) ?>" required>
</div>

<div class="input-group"><label class="field-label" for="imageInput">Question image</label>
    <span class="input-group-text"><i class="bi bi-image"></i></span>
    <input type="file" name="image" class="form-control" accept="image/*" id="imageInput">
</div>

<img id="previewImage" src="<?= htmlspecialchars($question['image']) ?>" alt="Current Image">

<div class="input-group"><label class="field-label" for="field-category">Category</label>
    <span class="input-group-text"><i class="bi bi-list"></i></span>
    <select id="field-category" name="category" class="form-select" required>
        <option value="" disabled>Select category</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= htmlspecialchars($cat['slug']) ?>" <?= $question['category'] === $cat['slug'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="input-group"><label class="field-label" for="field-answer">Answer</label>
    <span class="input-group-text"><i class="bi bi-check2-square"></i></span>
    <input id="field-answer" type="text" name="answer" class="form-control" placeholder="Enter answer" value="<?= htmlspecialchars($question['answer']) ?>" required>
</div>

<div class="input-group"><label class="field-label" for="field-status">Legacy question status</label>
    <span class="input-group-text"><i class="bi bi-toggle-on"></i></span>
    <select id="field-status" name="status" class="form-select" required>
        <option value="active" <?= $question['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $question['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
</div>

<button type="submit" name="update"><i class="bi bi-check-lg"></i> Update Question</button>

<a href="dashboard.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</form><aside class="panel editor-hint"><p class="eyebrow">A GOOD QUESTION GOES A LONG WAY</p><h3>Make every question count</h3><ul><li>Keep the wording clear and easy to read aloud.</li><li>Choose the category that fits best.</li><li>Use a clear image when the question needs one.</li><li>Check the answer before saving.</li></ul><p>Edits apply to new team sessions. Existing sessions keep their original questions.</p></aside></div>

<script>
const imageInput = document.getElementById('imageInput');
const previewImage = document.getElementById('previewImage');

imageInput.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            previewImage.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
});
</script>





<?php include 'includes/footer.php'; ?>
