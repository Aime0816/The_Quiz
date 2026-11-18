<?php
// CLI recovery only; no hard-coded password. Run after database/migrate.php.
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit("Command line only.");
}
require __DIR__ . "/conn.php";
$username = $argv[1] ?? "";
$password = getenv("QUIZ_RESET_PASSWORD") ?: "";
if (!$username || strlen($password) < 10) {
    exit(
        "Usage: set QUIZ_RESET_PASSWORD to a password of at least 10 characters, then php reset_admin.php username\n"
    );
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    "INSERT INTO admins(username,password_hash,role) VALUES(?,?,'admin') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),role='admin',active=1,auth_version=auth_version+1",
);
$stmt->bind_param("ss", $username, $hash);
$stmt->execute();
echo "Admin account updated. Clear QUIZ_RESET_PASSWORD now.\n";
