<?php
/**
 * THE QUIZ - Helper Functions
 */

session_start();

/**
 * Generate a CSRF token and store it in session
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from form submission
 */
function validateCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is logged in as admin
 */
function isAdmin(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Redirect if not admin
 */
function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Sanitize user input
 */
function sanitize(string $data): string {
    $data = trim($data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Upload image and return path, or false on failure
 */
function uploadImage(array $file, string $uploadDir = 'uploads/questions/'): string|false {
    if (!isset($file['tmp_name']) || $file['error'] !== 0) {
        return false;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        return false;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }

    $ext = ['image/jpeg'=>'jpg', 'image/jpg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'][$fileType];
    $fileName = uniqid("question_") . "." . $ext;
    $imagePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $imagePath)) {
        return false;
    }

    return $imagePath;
}

/**
 * Delete an image file safely
 */
function deleteImage(string $path): bool {
    if (!empty($path) && file_exists($path) && strpos($path, 'uploads/questions/') === 0) {
        return unlink($path);
    }
    return false;
}

/**
 * Get all categories from database
 */
function getCategories(mysqli $conn): array {
    $result = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Get category by slug
 */
function getCategoryBySlug(mysqli $conn, string $slug): array|null {
    $stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE slug = ?");
    mysqli_stmt_bind_param($stmt, "s", $slug);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/**
 * Get category by ID
 */
function getCategoryById(mysqli $conn, int $id): array|null {
    $stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/**
 * Set category status (active/inactive)
 */
function setCategoryStatus(mysqli $conn, int $id, string $status): bool {
    $stmt = mysqli_prepare($conn, "UPDATE categories SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $status, $id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Get active question count for a category
 */
function getActiveQuestionCount(mysqli $conn, string $slug): int {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM questions WHERE category = ? AND status = 'active'");
    mysqli_stmt_bind_param($stmt, "s", $slug);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row['cnt'] ?? 0;
}

/**
 * Check if all questions in a category are inactive
 */
function checkAllQuestionsInactive(mysqli $conn, string $slug): bool {
    return getActiveQuestionCount($conn, $slug) === 0;
}

/**
 * Auto-disable category if all questions are inactive
 */
function autoDisableCategory(mysqli $conn, string $slug): bool {
    $category = getCategoryBySlug($conn, $slug);
    if (!$category) return false;
    
    // Only disable if currently active
    if (($category['status'] ?? 'active') === 'inactive') return true;
    
    // Check if all questions are inactive
    if (checkAllQuestionsInactive($conn, $slug)) {
        return setCategoryStatus($conn, $category['id'], 'inactive');
    }
    return true;
}

/**
 * Get categories filtered by status
 */
function getCategoriesByStatus(mysqli $conn, string $status = 'active'): array {
    $stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE status = ? ORDER BY name ASC");
    mysqli_stmt_bind_param($stmt, "s", $status);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $categories;
}

/**
 * Get questions by category slug
 */
function getQuestionsByCategory(mysqli $conn, string $slug): array {
    $stmt = mysqli_prepare($conn, "SELECT * FROM questions WHERE category = ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt, "s", $slug);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $questions = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $questions;
}

/**
 * Get question by ID
 */
function getQuestionById(mysqli $conn, int $id): array|null {
    $stmt = mysqli_prepare($conn, "SELECT * FROM questions WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/**
 * Show toast notification
 */
function showToast(string $message, string $type = 'success'): string {
    $bgClass = $type === 'success' ? 'bg-success' : 'bg-danger';
    return "<div class='toast align-items-center text-white {$bgClass} border-0 position-fixed top-0 end-0 m-3' role='alert' aria-live='assertive' aria-atomic='true' id='liveToast'>
        <div class='d-flex'>
            <div class='toast-body'>" . htmlspecialchars($message) . "</div>
            <button type='button' class='btn-close btn-close-white me-2 m-auto' data-bs-dismiss='toast' aria-label='Close'></button>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toastEl = document.getElementById('liveToast');
            var toast = new bootstrap.Toast(toastEl);
            toast.show();
        });
    </script>";
}

