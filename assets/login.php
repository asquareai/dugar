<?php
session_start();
include 'config.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Secure query
    $query = "SELECT * FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {
            // Generate a new unique session token
            $session_token = bin2hex(random_bytes(32)); // Secure random token
            
            // Update last_login and store session_token in database
            $updateQuery = "UPDATE users SET last_login = NOW(), session_token = ? WHERE username = ?";
            $updateStmt = mysqli_prepare($conn, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "ss", $session_token, $username);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            $_SESSION['user'] = $username;
            $_SESSION['session_token'] = $session_token;
            $_SESSION['user_fullname'] = $row['full_name'];
            $_SESSION['last_login_time'] = $row['last_login'];
            $_SESSION['user_role'] = $row['role'];
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_prefix'] = $row['prefix'];
            if($row['role'] == 'sales')
                header("Location: proposal.php");
            else
                header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    } else {
        $error = "Invalid username or password!";
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}


?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Proposal Tracker</title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">


    <link rel="manifest" href="/site.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/loader.css">

</head>
<body style="position: relative; margin: 0;">
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                background: url('assets/images/bg.png') no-repeat center center fixed; 
                background-size: cover; opacity: 0.3; z-index: -1;">
    </div>

<!-- Logo Outside the Login Box -->
<div class="logo">
    <img src="assets/images/logo.png" alt="Logo"> 
</div>

<!-- Error Message Alert -->
<?php if (!empty($error)): ?>
    <div class="alert-container">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error!</strong> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<?php 
if (session_status() === PHP_SESSION_NONE) { 
    session_start();
}

if (!empty($_SESSION['multi_login_detected'])) { 
    echo '<div class="alert alert-warning">⚠️ Detected multiple device logins. You have been logged out.</div>';
    unset($_SESSION['multi_login_detected']); // Remove message after displaying
} 
?>




<!-- Login Box -->
<div class="login-container">
    <h3 class="mb-4">Proposal Tracker 1.0</h3>
    <form method="POST" action="" id="loginForm">
        <div class="mb-3 input-group">
            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
            <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required>
        </div>

        <!-- Password Input -->
        <div class="mb-3 input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
        </div>

        <!-- Elegant Login Button -->
        <button type="submit" class="btn btn-primary w-100">
            <i class="fa-solid fa-sign-in-alt"></i> Login
        </button>
        <div class="text-center mt-3">
            <a href="change_password_with_token.php">Forgot Password?</a>
        </div>
    </form>
</div>
<!-- Loader -->
<div class="loader-container" id="loader">
    <div class="loader"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("loader").style.display = "none"; // Ensure loader is hidden initially

    document.getElementById("loginForm").addEventListener("submit", function () {
        document.getElementById("loader").style.display = "flex"; // Show loader on form submit
    });
});
window.addEventListener("pageshow", function (event) {
    if (event.persisted) {
        location.reload(); // Force reload when navigating back
    }
});

</script>
</body>
</html>
