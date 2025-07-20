<?php
session_start();
include('config.php'); // Include your database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login page if not logged in
    exit();
}

$user_id = $_SESSION['user_id']; // Get the logged-in user ID

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = mysqli_real_escape_string($conn, $_POST['current_password']);
    $new_password = mysqli_real_escape_string($conn, $_POST['new_password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);

    // Validate that new password and confirm password match
    if ($new_password !== $confirm_password) {
        $error_message = "New password and confirm password do not match.";
    } else {
        // Fetch current password of the user from the database
        $query = "SELECT password FROM users WHERE id = '$user_id'";
        $result = mysqli_query($conn, $query);
        $user = mysqli_fetch_assoc($result);

        // Verify the current password entered by the user
        if (password_verify($current_password, $user['password'])) {
            // Hash the new password before updating
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update the password in the database
            $update_query = "UPDATE users SET password = '$hashed_password' WHERE id = '$user_id'";
            if (mysqli_query($conn, $update_query)) {
                $success_message = "Password updated successfully!";
            } else {
                $error_message = "Error updating password. Please try again later.";
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .container {
            max-width: 500px;
            margin-top: 50px;
        }
        .form-control {
            border-radius: 25px;
        }
        .btn-custom {
            border-radius: 25px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4">Change Password</h2>
        <form action="change_password.php" method="POST" id="changePasswordForm">
            <!-- Display error or success message -->
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="currentPassword" class="form-label">Current Password</label>
                <input type="password" class="form-control" id="currentPassword" name="current_password" required>
            </div>
            <div class="mb-3">
                <label for="newPassword" class="form-label">New Password</label>
                <input type="password" class="form-control" id="newPassword" name="new_password" required>
            </div>
            <div class="mb-3">
                <label for="confirmPassword" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-custom w-100">Update Password</button>
        </form>
    </div>

    <script>
        document.getElementById('changePasswordForm').addEventListener('submit', function(event) {
            var newPassword = document.getElementById('newPassword').value;
            var confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                alert("Passwords do not match.");
                event.preventDefault();
            }
        });
    </script>
</body>
</html>
