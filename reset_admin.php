<?php
/**
 * Admin Password Reset Utility
 * Run this once to reset admin password to admin123
 * Delete this file after use for security
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Run this utility from the command line only.'); }
include __DIR__.'/conn.php';

$username = 'admin';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);

// Check if admins table exists
$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'admins'");
if (mysqli_num_rows($tableCheck) == 0) {
    // Create admins table
    mysqli_query($conn, "CREATE TABLE `admins` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(100) NOT NULL,
        `password_hash` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Created admins table.<br>";
}

// Insert or update admin
$stmt = mysqli_prepare($conn, "INSERT INTO admins (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = ?");
mysqli_stmt_bind_param($stmt, "sss", $username, $hash, $hash);

if (mysqli_stmt_execute($stmt)) {
    echo "<div style='color:green; font-family:sans-serif; padding:20px;'>
        <h2>✅ Admin Password Reset Successful</h2>
        <p><strong>Username:</strong> admin</p>
        <p><strong>Password:</strong> admin123</p>
        <p style='color:red;'><strong>Important:</strong> Delete this file (reset_admin.php) immediately for security!</p>
        <a href='login.php' style='display:inline-block; margin-top:10px; padding:10px 20px; background:#3b82f6; color:#fff; text-decoration:none; border-radius:8px;'>Go to Login</a>
    </div>";
} else {
    echo "<div style='color:red; font-family:sans-serif; padding:20px;'>
        <h2>❌ Error</h2>
        <p>" . mysqli_stmt_error($stmt) . "</p>
    </div>";
}
mysqli_stmt_close($stmt);

