<?php
include 'conn.php';
include 'includes/functions.php';

/* ========= AJAX: DISABLE QUESTION ========= */
if (isset($_POST['ajax_disable'])) {
    $id = intval($_POST['id']);
    $stmt = mysqli_prepare($conn, "UPDATE questions SET status='inactive' WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo "success";
    exit;
}

/* ========= DELETE QUESTION (Admin only) ========= */
if (isset($_POST['delete_question']) && isAdmin()) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $deleteId = intval($_POST['delete_id'] ?? 0);
        if ($deleteId > 0) {
            // Get image path before deleting
            $q = getQuestionById($conn, $deleteId);
            if ($q) {
                // Delete the question
                $stmt = mysqli_prepare($conn, "DELETE FROM questions WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $deleteId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                
                // Delete associated image
                if (!empty($q['image'])) {
                    deleteImage($q['image']);
                }
            }
        }
    }
    // Redirect to avoid form resubmission
    $redirectCat = $_GET['cat'] ?? '';
    header("Location: category.php?cat=" . urlencode($redirectCat));
    exit;
}

$slug = $_GET['cat'] ?? '';
$category = getCategoryBySlug($conn, $slug);

if (!$category) {
    die("Category not found");
}

// Handle inactive category access for non-admin users
$isInactive = ($category['status'] ?? 'active') === 'inactive';
if ($isInactive && !isAdmin()) {
    die("This category is currently unavailable.");
}

$questions = getQuestionsByCategory($conn, $slug);
$pageTitle = htmlspecialchars($category['name']) . ' Questions';
?>
<?php include 'includes/header.php'; ?>


<a href="cat.php" class="back-btn"><i class="bi bi-arrow-left"></i> Back</a>

<?php if (isAdmin()): ?>

<?php endif; ?>

<h1><?= strtoupper(htmlspecialchars($category['name'])) ?> QUESTIONS</h1>

<button id="fullscreenBtn" class="btn btn-outline-info px-4 py-2 mb-4">⛶</button>

<div class="d-flex justify-content-center gap-3 flex-wrap my-5">
<?php foreach ($questions as $index => $q): ?>
  <div class="question-wrapper">
    <button
      class="question-btn"
      <?= $q['status'] === 'inactive' ? 'disabled' : '' ?>
      data-bs-toggle="modal"
      data-bs-target="#questionModal"
      data-id="<?= $q['id'] ?>"
      data-question="<?= htmlspecialchars($q['question']) ?>"
      data-image="<?= htmlspecialchars($q['image']) ?>"
      data-answer="<?= htmlspecialchars($q['answer']) ?>"
    >
      <?= $index + 1 ?>
    </button>
    <?php if (isAdmin()): ?>
    <div class="admin-actions">
        <a href="update.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit">
            <i class="bi bi-pencil"></i>
        </a>
        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this question?');">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="delete_id" value="<?= $q['id'] ?>">
            <button type="submit" name="delete_question" class="btn btn-sm btn-outline-danger" title="Delete">
                <i class="bi bi-trash"></i>
            </button>
        </form>
    </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if (empty($questions)): ?>
  <p class="text-muted mt-4">No questions available in this category yet.</p>
<?php endif; ?>
</div>

<!-- QUESTION MODAL -->
<div class="modal fade" id="questionModal">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content text-white">
      <div class="modal-header">
        <h5 class="modal-title">Question</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <p id="modalQuestion" class="fs-4"></p>
        <img id="modalImage" class="img-fluid mt-3">
      </div>
      <div class="modal-footer">
        <button id="showAnswerBtn" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#answerModal">
          Show Answer
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ANSWER MODAL -->
<div class="modal fade" id="answerModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-white">
      <div class="modal-header">
        <h5 class="modal-title">Answer</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <p id="modalAnswer"></p>
      </div>
    </div>
  </div>
</div>



<script>
let currentBtn = null;
let currentId = null;

document.getElementById('questionModal').addEventListener('show.bs.modal', e => {
  const btn = e.relatedTarget;
  currentBtn = btn;
  currentId = btn.dataset.id;
  modalQuestion.textContent = btn.dataset.question;
  modalImage.src = btn.dataset.image;
  modalAnswer.textContent = btn.dataset.answer;
});

document.getElementById('showAnswerBtn').addEventListener('click', () => {
  fetch('', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `ajax_disable=1&id=${currentId}`
  }).then(() => {
    currentBtn.disabled = true;
  });
});

fullscreenBtn.onclick = () => {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen();
    document.body.classList.add('fullscreen-active');
    fullscreenBtn.innerHTML = '⛶ Exit';
  } else {
    document.exitFullscreen();
    document.body.classList.remove('fullscreen-active');
    fullscreenBtn.innerHTML = '⛶';
  }
};

document.addEventListener('fullscreenchange', () => {
  if (!document.fullscreenElement) {
    document.body.classList.remove('fullscreen-active');
    fullscreenBtn.innerHTML = '⛶';
  }
});
</script>




<?php include 'includes/footer.php'; ?>
