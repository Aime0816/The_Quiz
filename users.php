<?php
require "conn.php";
require "includes/functions.php";
$actor = requireRole(["admin"]);
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    checkCsrf();
    try {
        $id = (int) ($_POST["id"] ?? 0);
        $username = trim($_POST["username"] ?? "");
        $role = $_POST["role"] ?? "";
        $password = $_POST["password"] ?? "";
        $active = isset($_POST["active"]) ? 1 : 0;
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/', $username)) {
            throw new DomainException(
                "Use a username with 3–100 letters, digits, dots, hyphens or underscores.",
            );
        }
        if (!in_array($role, ["admin", "setter", "marker"], true)) {
            throw new DomainException("Choose a valid role.");
        }
        if ((!$id || $password !== "") && strlen($password) < 10) {
            throw new DomainException(
                "New passwords must contain at least 10 characters.",
            );
        }
        $conn->begin_transaction();
        $users = $conn
            ->query("SELECT * FROM admins ORDER BY id FOR UPDATE")
            ->fetch_all(MYSQLI_ASSOC);
        $old = null;
        foreach ($users as $u) {
            if ((int) $u["id"] === $id) {
                $old = $u;
            }
        }
        if ($id && !$old) {
            throw new DomainException("User not found.");
        }
        if (
            $old &&
            (int) ($_POST["version"] ?? 0) !== (int) $old["auth_version"]
        ) {
            throw new DomainException(
                "This account changed. Reload before editing it.",
            );
        }
        if ($id === (int) $actor["id"] && (!$active || $role !== "admin")) {
            throw new DomainException(
                "You cannot disable or demote your own admin account.",
            );
        }
        if (
            $old &&
            $old["role"] === "admin" &&
            $old["active"] &&
            (!$active || $role !== "admin")
        ) {
            $count = count(
                array_filter(
                    $users,
                    fn($u) => $u["role"] === "admin" && $u["active"],
                ),
            );
            if ($count <= 1) {
                throw new DomainException(
                    "Keep at least one active administrator.",
                );
            }
        }
        $hash =
            $password !== ""
                ? password_hash($password, PASSWORD_DEFAULT)
                : $old["password_hash"] ?? "";
        if ($id) {
            dbQuery(
                $conn,
                "UPDATE admins SET username=?,password_hash=?,role=?,active=?,auth_version=auth_version+1 WHERE id=?",
                "sssii",
                [$username, $hash, $role, $active, $id],
            );
        } else {
            dbQuery(
                $conn,
                "INSERT INTO admins(username,password_hash,role,active) VALUES(?,?,?,?)",
                "sssi",
                [$username, $hash, $role, $active],
            );
        }
        $conn->commit();
        if ($id === (int) $actor["id"]) {
            $_SESSION["auth_version"] = (int) $old["auth_version"] + 1;
        }
        flash(
            "Account saved. Previous logins for an edited account have been invalidated.",
        );
        redirect("users.php");
    } catch (DomainException $e) {
        $conn->rollback();
        $error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        if ($e->getCode() !== 1062) {
            throw $e;
        }
        $error = "That username already exists.";
    }
}
$users = $conn
    ->query(
        "SELECT id,username,role,active,auth_version FROM admins ORDER BY username",
    )
    ->fetch_all(MYSQLI_ASSOC);
$edit = [
    "id" => 0,
    "username" => "",
    "role" => "setter",
    "active" => 1,
    "auth_version" => 1,
];
foreach ($users as $u) {
    if ((int) $u["id"] === (int) ($_GET["edit"] ?? 0)) {
        $edit = $u;
    }
}
$pageTitle = "Users & roles";
include "includes/header.php";
?>
<div class="page-heading">
<div>
<p class="eyebrow">INDIVIDUAL ACCOUNTS</p>
<h1>Users & roles</h1>
<p>Question setters prepare the bank. Markers run competitions. Admins manage access.</p>
</div>
</div>
<?= $error
    ? showToast($error, "danger")
    : "" ?>
<div class="editor-grid">
<section class="panel">
<table>
<thead>
<tr>
<th>Username</th>
<th>Role</th>
<th>Status</th>
<th>
</th>
</tr>
</thead>
<tbody>
<?php foreach (
    $users
    as $u
): ?>
<tr>
<td>
<?= h($u["username"]) ?>
</td>
<td>
<?= h(
    $u["role"],
) ?>
</td>
<td>
<?= $u["active"]
    ? "Active"
    : "Disabled" ?>
</td>
<td>
<a href="users.php?edit=<?= (int) $u[
    "id"
] ?>">Edit</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>
<form class="panel" method="post">
<h2>
<?= $edit[
    "id"
]
    ? "Edit account"
    : "New account" ?>
</h2>
<input type="hidden" name="csrf_token" value="<?= h(
    generateCsrfToken(),
) ?>">
<input type="hidden" name="id" value="<?= (int) $edit[
    "id"
] ?>">
<input type="hidden" name="version" value="<?= (int) $edit[
    "auth_version"
] ?>">
<label>Username<input name="username" value="<?= h(
    $edit["username"],
) ?>" required>
</label>
<label>Role<select name="role">
<?php foreach (
    ["setter" => "Question setter", "marker" => "Marker", "admin" => "Admin"]
    as $value => $label
): ?>
<option value="<?= $value ?>" <?= $edit["role"] === $value
    ? "selected"
    : "" ?>>
<?= $label ?>
</option>
<?php endforeach; ?>
</select>
</label>
<label>
<?= $edit[
    "id"
]
    ? "New password (leave blank to keep)"
    : "Password" ?>
<input name="password" type="password" minlength="10" autocomplete="new-password" <?= !$edit[
    "id"
]
    ? "required"
    : "" ?>>
</label>
<label class="check">
<input type="checkbox" name="active" <?= $edit[
    "active"
]
    ? "checked"
    : "" ?>> Account active</label>
<div class="actions">
<button class="primary">Save account</button>
<a href="users.php">New account</a>
</div>
</form>
</div>
<?php include "includes/footer.php"; ?>
