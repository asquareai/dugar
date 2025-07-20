<?php
// Database connection settings
$host = "asquareai.com"; // Change if using a remote DB
$username = "hivlhkhc_dugarproposaltracker";  // Your MySQL username
$password = "et_sI9SAFWPp";      // Your MySQL password (set it if required)
$database = "hivlhkhc_dugar_proposal_tracker"; // Your database name

// Create a connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character encoding to UTF-8
$conn->set_charset("utf8");
?>
