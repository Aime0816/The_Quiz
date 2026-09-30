<?php
include 'conn.php';
include 'includes/functions.php';
requireAdmin();

$message = '';
$error = '';
$categories = getCategories($conn);

if (isset($_POST['submit'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $question = trim($_POST['question'] ?? '');
        $category = $_POST['category'] ?? '';
        $answer = trim($_POST['answer'] ?? '');

        if (empty($question) || empty($category) || empty($answer)) {
            $error = 'All fields are required.';
        } elseif (strlen($question) < 3) {
            $error = 'Question must be at least 3 characters.';
        } elseif (strlen($answer) < 1) {
            $error = 'Answer is required.';
        } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== 0) {
            $error = 'Image upload failed.';
        } else {
            $imagePath = uploadImage($_FILES['image']);
            if (!$imagePath) {
                $error = 'Invalid image. Only JPG, PNG, WEBP under 2MB allowed.';
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO questions (category, question, answer, image) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssss", $category, $question, $answer, $imagePath);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'New question created successfully!';
                    $_POST = []; // clear form
                } else {
                    $error = 'Database error: ' . mysqli_stmt_error($stmt);
                    deleteImage($imagePath); // cleanup on failure
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$pageTitle = 'Add New Question';
?>
<?php include 'includes/header.php'; ?>
<div class="page-heading"><div><p class="eyebrow">YOUR QUESTION COLLECTION</p><h1>Create a new question</h1><p>Give the next round something worth thinking about.</p></div><a class="btn btn-outline-info" href="questions.php">← Question bank</a></div><div class="editor-grid">



<form class="editor-form" action="insert.php" method="post" enctype="multipart/form-data">
<h2><i class="bi bi-journal-plus"></i> Add New Question</h2>

<?php if ($message) echo showToast($message, 'success'); ?>
<?php if ($error) echo showToast($error, 'danger'); ?>

<input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

<div class="input-group"><label class="field-label" for="field-question">Question</label>
    <span class="input-group-text"><i class="bi bi-question-circle"></i></span>
    <input id="field-question" type="text" name="question" placeholder="Enter question" value="<?= htmlspecialchars($_POST['question'] ?? '') ?>" required>
</div>

<div class="input-group"><label class="field-label" for="imageInput">Question image</label>
    <span class="input-group-text"><i class="bi bi-image"></i></span>
    <input type="file" name="image" accept="image/*" id="imageInput" required>
</div>

<img style="display:none" id="previewImage" alt="Image Preview">

<div class="input-group"><label class="field-label" for="field-category">Category</label>
    <span class="input-group-text"><i class="bi bi-list"></i></span>
    <select id="field-category" name="category" required>
        <option value="" disabled <?= empty($_POST['category']) ? 'selected' : '' ?>>Select category</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= htmlspecialchars($cat['slug']) ?>" <?= ($_POST['category'] ?? '') === $cat['slug'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="input-group"><label class="field-label" for="field-answer">Answer</label>
    <span class="input-group-text"><i class="bi bi-check2-square"></i></span>
    <input id="field-answer" type="text" name="answer" placeholder="Enter answer" value="<?= htmlspecialchars($_POST['answer'] ?? '') ?>" required>
</div>

<button type="submit" name="submit"><i class="bi bi-plus-circle"></i> Submit</button>

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
    } else {
        previewImage.src = '';
        previewImage.style.display = 'none';
    }
});
</script>





<?php include 'includes/footer.php'; ?>
