<?php
session_start();
include 'config.php';

if (isset($_SESSION['user']) && isset($_SESSION['session_token'])) {
    if (!isset($_POST['auto_login_check'])) {
        header("Location: dashboard.php");
        exit();
    }
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['auto_login_check'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $remember_me = isset($_POST['remember_me']);

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $query = "SELECT id, username, password, full_name, last_login, role, prefix FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                if (password_verify($password, $row['password'])) {
                    $session_token = bin2hex(random_bytes(32));
                    $updateFields = "last_login = NOW(), session_token = ?";
                    $bindParams = "s";
                    $bindValues = [$session_token];

                    $remember_me_token = null;
                    if ($remember_me) {
                        $remember_me_token = bin2hex(random_bytes(64));
                        $remember_me_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                        $updateFields .= ", remember_me_token = ?, remember_me_expires = ?";
                        $bindParams .= "ss";
                        $bindValues[] = $remember_me_token;
                        $bindValues[] = $remember_me_expires;
                    } else {
                        $updateFields .= ", remember_me_token = NULL, remember_me_expires = NULL";
                    }

                    $bindParams .= "s";
                    $bindValues[] = $username;

                    $updateQuery = "UPDATE users SET {$updateFields} WHERE username = ?";
                    $updateStmt = mysqli_prepare($conn, $updateQuery);

                    if ($updateStmt) {
                        $params = array();
                        $params[] = $bindParams;
                        foreach ($bindValues as $key => $value) {
                            $params[] = &$bindValues[$key];
                        }
                        call_user_func_array('mysqli_stmt_bind_param', array_merge([$updateStmt], $params));

                        mysqli_stmt_execute($updateStmt);
                        mysqli_stmt_close($updateStmt);

                        $_SESSION['user'] = $username;
                        $_SESSION['session_token'] = $session_token;
                        $_SESSION['user_fullname'] = $row['full_name'];
                        $_SESSION['last_login_time'] = $row['last_login'];
                        $_SESSION['user_role'] = $row['role'];
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['user_prefix'] = $row['prefix'];
                        $_SESSION['last_activity'] = time();

                        if ($remember_me_token) {
                            $_SESSION['remember_me_token_for_js'] = $remember_me_token;
                        }

                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Database update error. Please try again.";
                        error_log("Failed to prepare update statement: " . mysqli_error($conn));
                    }
                } else {
                    $error = "Invalid username or password!";
                }
            } else {
                $error = "Invalid username or password!";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Database query error. Please try again.";
            error_log("Failed to prepare select statement: " . mysqli_error($conn));
        }
    }
    mysqli_close($conn);
}

$multi_login_message = '';
if (isset($_SESSION['multi_login_detected'])) {
    $multi_login_message = '<div class="alert-container"><div class="alert alert-warning alert-dismissible fade show" role="alert">⚠️ Detected multiple device logins. You have been logged out.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>';
    unset($_SESSION['multi_login_detected']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Proposal Tracker</title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/loader.css">
</head>
<body style="position: relative; margin: 0;">
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: url('assets/images/bg.png') no-repeat center center fixed; background-size: cover; opacity: 0.3; z-index: -1;"></div>

    <div class="logo">
        <img src="assets/images/logo.png" alt="Logo">
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert-container">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php echo $multi_login_message; ?>

    <div class="login-container">
        <h3 class="mb-4">Proposal Tracker 1.0</h3>
        <hr class="mb-4">

        <form method="POST" action="" id="loginForm">
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required>
            </div>

            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                <label class="form-check-label" for="remember_me">Remember Me</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="fa-solid fa-sign-in-alt"></i> Login
            </button>
            <div class="text-center mt-3">
                <a href="change_password_with_token.php">Forgot Password?</a>
            </div>
        </form>
    </div>

    <div class="loader-container" id="loader">
        <div class="loader"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const loader = document.getElementById("loader");
            const loginForm = document.getElementById("loginForm");
            loader.style.display = "none";

            const rememberMeToken = localStorage.getItem('remember_me_token');
            if (rememberMeToken && !document.body.classList.contains('logged-in')) {
                loader.style.display = "flex";
                fetch('auto_login.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'token=' + encodeURIComponent(rememberMeToken) + '&auto_login_check=1'
                })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                        return new Promise(() => {});
                    }
                    return response.json();
                })
                .then(data => {
                    loader.style.display = "none";
                    if (data && data.success === false) {
                        localStorage.removeItem('remember_me_token');
                    }
                })
                .catch(error => {
                    console.error('Error during auto-login:', error);
                    loader.style.display = "none";
                    localStorage.removeItem('remember_me_token');
                });
            }

            loginForm.addEventListener("submit", function () {
                loader.style.display = "flex";
            });

            <?php if (isset($_SESSION['remember_me_token_for_js'])): ?>
                localStorage.setItem('remember_me_token', '<?php echo htmlspecialchars($_SESSION['remember_me_token_for_js']); ?>');
                <?php unset($_SESSION['remember_me_token_for_js']); ?>
            <?php endif; ?>
        });

        window.addEventListener("pageshow", function (event) {
            if (event.persisted) {
                location.reload();
            }
        });
    </script>
</body>
</html>
