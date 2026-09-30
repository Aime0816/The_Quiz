<?php
include 'conn.php';
include 'includes/functions.php';
requireAdmin();

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: categories.php');
    exit;
}

// Get category slug
$stmt = mysqli_prepare($conn, "SELECT slug FROM categories WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$category = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$category) {
    header('Location: categories.php');
    exit;
}

$slug = $category['slug'];

// Delete images of questions in this category
$stmt = mysqli_prepare($conn, "SELECT image FROM questions WHERE category = ?");
mysqli_stmt_bind_param($stmt, "s", $slug);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    deleteImage($row['image']);
}
mysqli_stmt_close($stmt);

// Delete questions in this category
$stmt = mysqli_prepare($conn, "DELETE FROM questions WHERE category = ?");
mysqli_stmt_bind_param($stmt, "s", $slug);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Delete category
$stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Location: categories.php');
exit;

