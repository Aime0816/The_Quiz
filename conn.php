<?php
$host = 'localhost';
$server = 'root';
$password = '';
$database = 'the_quiz_v2';
$port = 3307;

$conn = mysqli_connect($host, $server, $password, $database, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to handle special characters properly
mysqli_set_charset($conn, "utf8mb4");

