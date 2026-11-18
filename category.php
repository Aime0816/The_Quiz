<?php
// Do not run legacy public status-changing POST requests or embed answer keys.
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(410);
    exit("Classic quiz actions are retired. Use the marker dashboard.");
}
require "conn.php";
require "includes/functions.php";
requireRole(["admin", "setter"]);
redirect("questions.php?cat=" . urlencode($_GET["cat"] ?? ""));
