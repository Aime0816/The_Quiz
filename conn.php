<?php
// Keep your existing XAMPP settings here, or override them with environment variables.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$host = getenv("QUIZ_DB_HOST") ?: "127.0.0.1";
$server = getenv("QUIZ_DB_USER") ?: "root";
$password =
    getenv("QUIZ_DB_PASSWORD") !== false ? getenv("QUIZ_DB_PASSWORD") : "";
$database = getenv("QUIZ_DB_NAME") ?: "the_quiz_v2";
$port = (int) (getenv("QUIZ_DB_PORT") ?: 3307);
$conn = new mysqli($host, $server, $password, $database, $port);
$conn->set_charset("utf8mb4");
