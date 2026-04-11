<?php
// 1. Database Credentials
$host = "localhost";
$user = "root"; 
$pass = "";     
$dbname = "notice_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. THE MAGIC FIX: Set PHP Default Timezone to IST globally
date_default_timezone_set('Asia/Kolkata');

// 3. Sync the Database session to India (+05:30)
$conn->query("SET time_zone = '+05:30'");
?>