<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set("session.use_strict_mode", "1");
    session_set_cookie_params([
        "httponly" => true,
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "samesite" => "Lax",
    ]);
    session_start();
}
if (PHP_SAPI !== "cli") {
    header("Cache-Control: no-store");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: no-referrer");
    header("X-Frame-Options: SAMEORIGIN");
}
function dbQuery(
    mysqli $db,
    string $sql,
    string $types = "",
    array $args = [],
): mysqli_stmt {
    $stmt = $db->prepare($sql);
    if ($types) {
        $stmt->bind_param($types, ...$args);
    }
    $stmt->execute();
    return $stmt;
}
function h($value): string
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}
function generateCsrfToken(): string
{
    return $_SESSION["csrf_token"] ??= bin2hex(random_bytes(32));
}
function validateCsrfToken(string $token): bool
{
    return isset($_SESSION["csrf_token"]) &&
        hash_equals($_SESSION["csrf_token"], $token);
}
function checkCsrf(): void
{
    if (!validateCsrfToken($_POST["csrf_token"] ?? "")) {
        http_response_code(403);
        exit("Your form expired. Reload the page and try again.");
    }
}
function currentUser(): ?array
{
    global $conn;
    static $loaded = false,
        $user = null;
    if (!$loaded) {
        $loaded = true;
        $id = (int) ($_SESSION["user_id"] ?? 0);
        if ($id) {
            $row = dbQuery(
                $conn,
                "SELECT id,username,role,active,auth_version FROM admins WHERE id=?",
                "i",
                [$id],
            )
                ->get_result()
                ->fetch_assoc();
            if (
                $row &&
                $row["active"] &&
                (int) $row["auth_version"] ===
                    (int) ($_SESSION["auth_version"] ?? 0)
            ) {
                $user = $row;
            }
        }
    }
    return $user;
}
function isAdmin(): bool
{
    return (currentUser()["role"] ?? "") === "admin";
}
function requireRole(array $roles): array
{
    $user = currentUser();
    if (!$user) {
        header("Location: login.php");
        exit();
    }
    if (!in_array($user["role"], $roles, true)) {
        http_response_code(403);
        exit("This account does not have permission to access this page.");
    }
    return $user;
}
function requireAdmin(): void
{
    requireRole(["admin"]);
}
function flash(string $message): void
{
    $_SESSION["flash"] = $message;
}
function redirect(string $path): void
{
    header("Location: " . $path);
    exit();
}
function getCategories(mysqli $db): array
{
    return $db
        ->query("SELECT * FROM categories ORDER BY name")
        ->fetch_all(MYSQLI_ASSOC);
}
function getCategoryBySlug(mysqli $db, string $slug): ?array
{
    return dbQuery($db, "SELECT * FROM categories WHERE slug=?", "s", [$slug])
        ->get_result()
        ->fetch_assoc();
}
function getCategoryById(mysqli $db, int $id): ?array
{
    return dbQuery($db, "SELECT * FROM categories WHERE id=?", "i", [$id])
        ->get_result()
        ->fetch_assoc();
}
function getQuestionById(mysqli $db, int $id): ?array
{
    return dbQuery($db, "SELECT * FROM questions WHERE id=?", "i", [$id])
        ->get_result()
        ->fetch_assoc();
}
/** Normalize old relative prefixes, Windows slashes and absolute local URLs. Never allow arbitrary remote media or traversal. */
function imagePath(?string $path): string
{
    if (!$path) {
        return "";
    }
    $path = str_replace("\\", "/", $path);
    if (
        !preg_match(
            '~(?:^|/)uploads/questions/([a-zA-Z0-9_.-]+\.(?:jpe?g|jfif|png|webp|gif))$~i',
            $path,
            $m,
        )
    ) {
        return "";
    }
    return "uploads/questions/" . $m[1];
}
function uploadImage(array $file): string
{
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new DomainException(
            "Image upload failed. Check PHP upload_max_filesize and post_max_size (at least 8 MB).",
        );
    }
    if ($file["size"] > 5 * 1024 * 1024) {
        throw new DomainException("Choose an image no larger than 5 MB.");
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file["tmp_name"]);
    $types = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
        "image/gif" => "gif",
    ];
    $size = @getimagesize($file["tmp_name"]);
    if (!isset($types[$mime]) || !$size || $size[0] * $size[1] > 40000000) {
        throw new DomainException(
            "Use a valid JPG, PNG, WebP or GIF, up to 40 megapixels.",
        );
    }
    $dir = __DIR__ . "/../uploads/questions/";
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new DomainException(
            "The uploads/questions folder could not be created.",
        );
    }
    $path =
        "uploads/questions/question_" .
        bin2hex(random_bytes(16)) .
        "." .
        $types[$mime];
    if (!move_uploaded_file($file["tmp_name"], __DIR__ . "/../" . $path)) {
        throw new DomainException(
            "The server cannot write to uploads/questions. Check folder permissions.",
        );
    }
    return $path;
}
// Published images are immutable and retained: old sessions can still refer to them.
function showToast(string $message, string $type = "success"): string
{
    return '<div class="notice ' .
        ($type === "danger" ? "error" : "") .
        '" role="' .
        ($type === "danger" ? "alert" : "status") .
        '">' .
        h($message) .
        "</div>";
}
