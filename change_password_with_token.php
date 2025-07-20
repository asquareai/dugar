<?php
session_start();
require 'config.php'; // Include database connection

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = trim($_POST['reset_token']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($token) || empty($new_password) || empty($confirm_password)) {
        $message = "<div class='alert alert-danger'>All fields are required!</div>";
    } elseif ($new_password !== $confirm_password) {
        $message = "<div class='alert alert-danger'>Passwords do not match!</div>";
    } else {
        // Check if the token exists in the users table
        $stmt = $conn->prepare("SELECT id FROM users WHERE password_reset_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($user_id);
            $stmt->fetch();

            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update the password and reset the token
            $update_stmt = $conn->prepare("UPDATE users SET password = ?, password_reset_token = '' WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);

            if ($update_stmt->execute()) {
                $message = "<div class='alert alert-success'>Password updated successfully! You can now <a href='login.php'>Login</a>.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Something went wrong. Please try again.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Invalid or expired token!</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
    <div class="card p-4 shadow" style="width: 350px;">
        <h4 class="text-center text-primary">Reset Password</h4>
        <?= $message ?>
        <form action="" method="POST">
            <div class="mb-3">
                <label class="form-label">Password Reset Token</label>
                <input type="text" name="reset_token" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Update Password</button>
        </form>
    </div>
</body>
</html>
