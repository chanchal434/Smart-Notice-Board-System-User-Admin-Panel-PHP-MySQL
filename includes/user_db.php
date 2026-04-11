<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "notice_db"; // Updated to your new database

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Enforce UTF-8 for Hindi characters
$conn->set_charset("utf8mb4");
?>