<?php
include 'conn.php';
include 'includes/functions.php';

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, password_hash FROM admins WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $username;
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$pageTitle = 'Admin Login';
?>
<?php include 'includes/header.php'; ?>
<section class="login-grid"><div><p class="eyebrow">WELCOME TO YOUR HOST DESK</p><h1>Great quiz nights<br>start with you.</h1><p>Bring your questions, gather your teams, and give everyone something to think about.</p><div class="feature-strip"><article><span>PREPARE</span><h3>Your collection</h3><p>Questions for every kind of curious mind.</p></article><article><span>HOST</span><h3>Your session</h3><p>Teams, scores and a shared audience screen.</p></article></div></div><div class="login-card"><p class="eyebrow">HOST ACCESS</p><h2>Welcome back.</h2><p>Sign in to manage your quiz workspace.</p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST"><label class="form-label" for="username">Username</label><input id="username" name="username" class="form-control" autocomplete="username" placeholder="Your username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required><label class="form-label" for="password">Password</label><input id="password" type="password" name="password" class="form-control" autocomplete="current-password" placeholder="Your password" required><button class="btn btn-primary" type="submit" name="login">Sign in to workspace →</button></form><a class="back-link" href="index.php">← Back to THE QUIZ</a></div></section>
<?php include 'includes/footer.php'; ?>
