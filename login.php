<?php
require "conn.php";
require "includes/functions.php";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    checkCsrf();
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $key = hash(
        "sha256",
        strtolower($username) . "|" . ($_SERVER["REMOTE_ADDR"] ?? "local"),
    );
    $attempt = dbQuery(
        $conn,
        "SELECT failures, TIMESTAMPDIFF(SECOND,last_attempt,NOW()) age FROM quiz_login_attempts WHERE attempt_key=?",
        "s",
        [$key],
    )
        ->get_result()
        ->fetch_assoc();
    if ($attempt && $attempt["failures"] >= 10 && $attempt["age"] < 900) {
        $error = "Too many attempts. Please wait 15 minutes.";
    } else {
        $user = dbQuery($conn, "SELECT * FROM admins WHERE username=?", "s", [
            $username,
        ])
            ->get_result()
            ->fetch_assoc();
        if (
            $user &&
            $user["active"] &&
            password_verify($password, $user["password_hash"])
        ) {
            session_regenerate_id(true);
            $_SESSION = [
                "user_id" => (int) $user["id"],
                "auth_version" => (int) $user["auth_version"],
                "csrf_token" => bin2hex(random_bytes(32)),
            ];
            dbQuery(
                $conn,
                "DELETE FROM quiz_login_attempts WHERE attempt_key=?",
                "s",
                [$key],
            );
            redirect($user["role"] === "marker" ? "play/" : "dashboard.php");
        }
        dbQuery(
            $conn,
            "INSERT INTO quiz_login_attempts(attempt_key,failures) VALUES(?,1) ON DUPLICATE KEY UPDATE failures=IF(TIMESTAMPDIFF(SECOND,last_attempt,NOW())>=900,1,failures+1),last_attempt=NOW()",
            "s",
            [$key],
        );
        $error = "Invalid username or password, or this account is disabled.";
    }
}
$pageTitle = "Sign in";
include "includes/header.php";
?>
<section class="login-grid">
<div>
<p class="eyebrow">THINK TOGETHER. WIN TOGETHER.</p>
<h1>A great competition<br>starts here.</h1>
<p>One question bank. Your own account. A clear view for every team.</p>
<div class="feature-strip">
<article>
<span>01 / PREPARE</span>
<h3>Build the question bank</h3>
<p>Work together with individual accounts.</p>
</article>
<article>
<span>02 / PRESENT</span>
<h3>Bring the room together</h3>
<p>Keep the answer key on your private screen.</p>
</article>
</div>
</div>
<div class="panel login-card">
<p class="eyebrow">STAFF WORKSPACE</p>
<h2>Welcome back.</h2>
<?= $error ? showToast($error, "danger") : "" ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= h(
    generateCsrfToken(),
) ?>">
<label for="username">Username</label>
<input id="username" name="username" autocomplete="username" value="<?= h(
    $_POST["username"] ?? "",
) ?>" required maxlength="100">
<label for="password">Password</label>
<input id="password" type="password" name="password" autocomplete="current-password" required>
<button class="primary" type="submit">Sign in →</button>
</form>
<p>Ask your admin for a question setter or marker account.</p>
</div>
</section>
<?php include "includes/footer.php"; ?>
