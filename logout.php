<?php
require "conn.php";
require "includes/functions.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Sign out using the workspace button.");
}
checkCsrf();
$_SESSION = [];
session_destroy();
redirect("login.php");
