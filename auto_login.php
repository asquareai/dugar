<?php
session_start();
include 'config.php'; // Your database connection file

header('Content-Type: application/json'); // Respond with JSON

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['token'])) {
    $remember_me_token = $_POST['token'];

    // Validate the token against the database
    $query = "SELECT id, username, full_name, last_login, role, prefix, remember_me_expires FROM users WHERE remember_me_token = ?";
    $stmt = mysqli_prepare($conn, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $remember_me_token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            // Check token expiration
            if (strtotime($row['remember_me_expires']) > time()) {
                // Token is valid and not expired
                // Re-establish session
                $_SESSION['user'] = $row['username'];
                $_SESSION['session_token'] = bin2hex(random_bytes(32)); // Generate new session token for this session
                $_SESSION['user_fullname'] = $row['full_name'];
                $_SESSION['last_login_time'] = $row['last_login'];
                $_SESSION['user_role'] = $row['role'];
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_prefix'] = $row['prefix'];
                $_SESSION['last_activity'] = time(); // Set last activity for idle timeout

                // Optionally, rotate the remember_me_token for increased security
                // $new_remember_me_token = bin2hex(random_bytes(64));
                // $new_remember_me_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                // $updateTokenQuery = "UPDATE users SET remember_me_token = ?, remember_me_expires = ? WHERE id = ?";
                // $updateTokenStmt = mysqli_prepare($conn, $updateTokenQuery);
                // mysqli_stmt_bind_param($updateTokenStmt, "ssi", $new_remember_me_token, $new_remember_me_expires, $row['id']);
                // mysqli_stmt_execute($updateTokenStmt);
                // mysqli_stmt_close($updateTokenStmt);
                // Then, respond with the new token for the client to store:
                // $response['new_token'] = $new_remember_me_token;

                $response['success'] = true;
                $response['message'] = 'Auto-login successful.';

                // Redirect the user directly from the server
                if ($row['role'] == 'sales' || $row['role'] == 'user') {
                    header("Location: proposal.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit(); // Important to exit after redirect
            } else {
                // Token expired
                $response['message'] = 'Remember me token expired.';
                // Clear expired token from database
                $clearQuery = "UPDATE users SET remember_me_token = NULL, remember_me_expires = NULL WHERE id = ?";
                $clearStmt = mysqli_prepare($conn, $clearQuery);
                mysqli_stmt_bind_param($clearStmt, "i", $row['id']);
                mysqli_stmt_execute($clearStmt);
                mysqli_stmt_close($clearStmt);
            }
        } else {
            // Token not found in database (or user deleted)
            $response['message'] = 'Invalid remember me token.';
        }
        mysqli_stmt_close($stmt);
    } else {
        $response['message'] = 'Database query error during auto-login.';
        error_log("Failed to prepare auto-login select statement: " . mysqli_error($conn));
    }
} else {
    $response['message'] = 'Invalid request for auto-login.';
}

mysqli_close($conn); // Close connection

// If redirection didn't happen, return JSON response
echo json_encode($response);
?>