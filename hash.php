<?php
if (isset($_GET['password'])) {
    $password = $_GET['password']; // Get password from query string
    $hashed_password = password_hash($password, PASSWORD_BCRYPT); // Generate bcrypt hash
    echo "Hashed Password: " . $hashed_password;
} else {
    echo "Please provide a password in the query string, e.g., ?password=sales@123";
}
?>
