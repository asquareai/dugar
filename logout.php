<?php
session_start();
include 'config.php';

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Invalidate the session token in the database
    $updateQuery = "UPDATE users SET session_token = NULL, remember_me_token = NULL, remember_me_expires = NULL WHERE id = ?";
    $stmt = mysqli_prepare($conn, $updateQuery);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Destroy all session data
session_unset();
session_destroy();

// Clear remember_me_token from client-side (this needs JavaScript)
// You would redirect to login.php, and login.php's JS would handle clearing
// if the auto_login check fails. Or, you could have a dedicated logout confirmation
// page that explicitly clears it via JS.
// For simplicity, after logout, redirecting to login.php should cause the JS there
// to fail auto-login and clear the token if the DB entry was properly nulled.

header("Location: login.php");
exit();
?>