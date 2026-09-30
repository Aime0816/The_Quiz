<?php
include 'conn.php'; include 'includes/functions.php'; requireAdmin();
$activationError='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['activate_question'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $activationError='Your session expired. Please reload the page and try again.';
    } else {
        $questionId=(int)($_POST['question_id'] ?? 0);
        $question=getQuestionById($conn,$questionId);
        if (!$question) {
            $activationError='Question not found.';
        } else {
            $stmt=mysqli_prepare($conn,"UPDATE questions SET status='active' WHERE id=?");
            mysqli_stmt_bind_param($stmt,'i',$questionId);
            if (mysqli_stmt_execute($stmt)) {
                $category=getCategoryBySlug($conn,$question['category']);
                if ($category && ($category['status'] ?? 'active')==='inactive') setCategoryStatus($conn,(int)$category['id'],'active');
                mysqli_stmt_close($stmt);
                header('Location: questions.php?activated='.$questionId);
                exit;
            }
            $activationError='Could not activate the question. Please try again.';
            mysqli_stmt_close($stmt);
        }
    }
}
$pageTitle='Question bank';$categories=getCategories($conn);
$questions=mysqli_fetch_all(mysqli_query($conn,'SELECT q.*,c.name AS category_name FROM questions q LEFT JOIN categories c ON c.slug=q.category ORDER BY q.id DESC'),MYSQLI_ASSOC);
include 'includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">BUILD A BRILLIANT ROUND</p><h1>Question bank</h1><p>Find, review and refine the questions in your collection.</p></div><a class="btn btn-primary" href="insert.php">+ Add question</a></div>
<?php if (isset($_GET['activated'])) echo showToast('Question activated successfully.','success'); ?>
<?php if ($activationError) echo showToast($activationError,'danger'); ?>
<section class="panel"><div class="question-toolbar"><div class="toolbar-field"><label for="question-search">Search your collection</label><input id="question-search" class="form-control" type="search" placeholder="Search questions, answers or categories…"></div><div class="toolbar-field"><label for="category-filter">Category</label><select id="category-filter" class="form-select"><option value="">All categories</option><?php foreach ($categories as $cat): ?><option value="<?= htmlspecialchars($cat['slug']) ?>" <?= ($_GET['cat'] ?? '')===$cat['slug'] ? 'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?></select></div><button id="clear-filters" class="btn btn-outline-info" type="button">Clear</button></div><div class="panel-heading"><h2>Your questions</h2><span class="text-muted" id="result-count" aria-live="polite"><?= count($questions) ?> questions</span></div><div class="table-responsive"><table class="table table-hover question-bank-table"><thead><tr><th scope="col">Question</th><th scope="col">Category</th><th scope="col">Legacy status</th><th scope="col">Action</th></tr></thead><tbody>
<?php foreach ($questions as $q): ?><tr data-question-row data-category="<?= htmlspecialchars($q['category'] ?? '') ?>"><td class="question-text"><b><?= htmlspecialchars($q['question'] ?? '') ?></b><small>Answer: <?= htmlspecialchars($q['answer'] ?? '') ?></small></td><td data-label="Category"><?= htmlspecialchars($q['category_name'] ?? $q['category'] ?? 'Uncategorized') ?></td><td data-label="Legacy status"><span class="badge bg-<?= $q['status']==='active'?'success':'secondary' ?>"><?= $q['status']==='active'?'Active':'Inactive' ?></span></td><td class="table-actions"><?php if ($q['status']!=='active'): ?><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>"><input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>"><button class="btn btn-sm btn-success" type="submit" name="activate_question">Make active</button></form><?php endif; ?><a class="btn btn-sm btn-outline-info" href="update.php?id=<?= (int)$q['id'] ?>" aria-label="Edit question <?= (int)$q['id'] ?>">Edit →</a></td></tr><?php endforeach; ?>
</tbody></table></div><div id="search-empty" class="empty-state" <?= count($questions)?'hidden':'' ?>><span>⌕</span><h3>No questions found</h3><p>Try another search or add a question to your collection.</p></div></section>
<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('category-filter').dispatchEvent(new Event('change')));</script>
<?php include 'includes/footer.php'; ?>
