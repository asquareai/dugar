<?php
// Enable error reporting for development (should be disabled or logged in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('config.php'); // Use require_once for critical includes

// --- Access Control and Session Management ---
// Check if user is logged in and has appropriate role ('admin' or 'approver')
// 'super_agent' role will also have access to this page for managing their linked agents
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'approver'])) {
    // If the page is in an iframe, redirect the parent window for security
    echo "<script>
            if (window.top !== window) {
                window.top.location.href = 'login.php';
            } else {
                window.location.href = 'login.php';
            }
          </script>";
    exit();
}

// Security: Re-check session token against database to prevent multi-login
$current_username = $_SESSION['username'] ?? ''; // Using 'username' from session
if (!empty($current_username)) {
    $stmt = mysqli_prepare($conn, "SELECT session_token FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $current_username);
    mysqli_stmt_execute($stmt);
    $result_token = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($result_token);
    mysqli_stmt_close($stmt);

    // If the session token in the DB doesn't match the current session's token, force logout
    if ($user_data && ($user_data['session_token'] !== ($_SESSION['session_token'] ?? ''))) {
        $_SESSION['multi_login_detected'] = true;
        header("Location: logout.php");
        exit();
    }
}

// Function to generate password reset token
function generateToken() {
    return bin2hex(random_bytes(32)); // Generate a longer, more secure 64-character token
}

$token_generated = ""; // Initialize this to prevent undefined variable notices
$full_name_for_token = ""; // Initialize this for clarity when displaying token message

// --- Handle Form Submissions (Add, Edit, Deactivate, Generate Token, Link Agents) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        // --- Add a new user (Prepared Statements for Security) ---
        $username = trim($_POST['username']); // Trim whitespace
        $full_name = trim($_POST['full_name']); // Trim whitespace
        $role = trim($_POST['role']); // Trim whitespace
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Input validation for roles
        $allowed_roles = ['admin', 'user', 'sales', 'approver', 'super agent'];
        if (!in_array($role, $allowed_roles)) {
            $_SESSION['error_message'] = "Invalid user role selected.";
            header("Location: user_management.php");
            exit();
        }

        // Check if username already exists
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $username);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $_SESSION['error_message'] = "Username already exists.";
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, full_name, role, password, status) VALUES (?, ?, ?, ?, 'active')");
            mysqli_stmt_bind_param($stmt, "ssss", $username, $full_name, $role, $password);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "User added successfully.";
            } else {
                $_SESSION['error_message'] = "Error adding user: " . mysqli_error($conn);
            }
        }
        mysqli_stmt_close($stmt_check);
        if (isset($stmt) && $stmt) mysqli_stmt_close($stmt); // Close if it was prepared
        header("Location: user_management.php"); // Redirect to prevent re-submission
        exit();

    } elseif (isset($_POST['edit_user'])) {
        // --- Edit user details (Prepared Statements) ---
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $role = trim($_POST['role']);

        $allowed_roles = ['admin', 'user', 'sales', 'approver', 'super agent'];
        if (!in_array($role, $allowed_roles)) {
            $_SESSION['error_message'] = "Invalid user role selected.";
            header("Location: user_management.php");
            exit();
        }

        // Prevent admin from deactivating/editing themselves if they are the only admin
        if ($_SESSION['user_role'] == 'admin' && $user_id == $_SESSION['user_id']) {
            $admin_count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
            mysqli_stmt_execute($admin_count_stmt);
            mysqli_stmt_bind_result($admin_count_stmt, $num_admins);
            mysqli_stmt_fetch($admin_count_stmt);
            mysqli_stmt_close($admin_count_stmt);

            if ($num_admins == 1 && $role !== 'admin') { // If trying to change own role from admin
                $_SESSION['error_message'] = "You cannot change your own role from admin if you are the only active administrator. Ensure another admin exists first.";
                header("Location: user_management.php");
                exit();
            }
        }

        // Check if username already exists for *another* user
        $stmt_check_username = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
        mysqli_stmt_bind_param($stmt_check_username, "si", $username, $user_id);
        mysqli_stmt_execute($stmt_check_username);
        mysqli_stmt_store_result($stmt_check_username);
        if (mysqli_stmt_num_rows($stmt_check_username) > 0) {
            $_SESSION['error_message'] = "Username already taken by another user.";
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET username = ?, full_name = ?, role = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $username, $full_name, $role, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "User updated successfully.";
            } else {
                $_SESSION['error_message'] = "Error updating user: " . mysqli_error($conn);
            }
        }
        mysqli_stmt_close($stmt_check_username);
        if (isset($stmt) && $stmt) mysqli_stmt_close($stmt);
        header("Location: user_management.php");
        exit();

    } elseif (isset($_POST['deactivate_user'])) {
        // --- Mark user as inactive (Prepared Statements) ---
        $user_id = $_POST['user_id'];

        // Prevent admin from deactivating themselves if they are the only admin
        if ($_SESSION['user_role'] == 'admin' && $user_id == $_SESSION['user_id']) {
            $admin_count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
            mysqli_stmt_execute($admin_count_stmt);
            mysqli_stmt_bind_result($admin_count_stmt, $num_admins);
            mysqli_stmt_fetch($admin_count_stmt);
            mysqli_stmt_close($admin_count_stmt);

            if ($num_admins == 1) {
                $_SESSION['error_message'] = "You cannot deactivate your own account if you are the only active administrator. Ensure another admin exists first.";
                header("Location: user_management.php");
                exit();
            }
        }
        
        $stmt = mysqli_prepare($conn, "UPDATE users SET status = 'inactive' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "User deactivated successfully.";
        } else {
            $_SESSION['error_message'] = "Error deactivating user: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
        header("Location: user_management.php");
        exit();

    } elseif (isset($_POST['generate_token'])) {
        // --- Generate password reset token (Prepared Statements) ---
        $user_id = $_POST['user_id'];
        $full_name_for_token = htmlspecialchars(trim($_POST['full_name'])); // For display
        $token_generated = generateToken(); // Use the new token variable

        $stmt = mysqli_prepare($conn, "UPDATE users SET password_reset_token = ?, password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?"); // Token expires in 1 hour
        mysqli_stmt_bind_param($stmt, "si", $token_generated, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Password reset token generated successfully for " . $full_name_for_token . ".";
            $_SESSION['generated_token'] = $token_generated; // Store in session to display after redirect
            $_SESSION['token_user_full_name'] = $full_name_for_token; // Store full name for message
        } else {
            $_SESSION['error_message'] = "Error generating token: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
        header("Location: user_management.php");
        exit();
    } elseif (isset($_POST['save_linked_agents'])) {
        $super_agent_id = $_POST['super_agent_id'];
        $linked_agent_ids = isset($_POST['linked_agent_ids']) ? implode(',', $_POST['linked_agent_ids']) : '';

        $stmt = mysqli_prepare($conn, "UPDATE users SET linked_agents = ? WHERE id = ? AND role = 'super agent'");
        mysqli_stmt_bind_param($stmt, "si", $linked_agent_ids, $super_agent_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Linked agents updated successfully for Super Agent ID: " . $super_agent_id;
        } else {
            $_SESSION['error_message'] = "Error updating linked agents: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
        header("Location: user_management.php");
        exit();
    }
}

// --- Display Messages (Success/Error) ---
$message = '';
$alert_type = '';

if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $alert_type = 'success';
    unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $alert_type = 'danger';
    unset($_SESSION['error_message']);
}

// For displaying the generated token after redirect
if (isset($_SESSION['generated_token'])) {
    $token_generated = $_SESSION['generated_token'];
    $full_name_for_token = $_SESSION['token_user_full_name'];
    unset($_SESSION['generated_token']);
    unset($_SESSION['token_user_full_name']);
}

// Fetch all users from the database for the table display
$query = "SELECT id, username, full_name, role, status, linked_agents FROM users ORDER BY full_name ASC";
$result = mysqli_query($conn, $query);

// Fetch all 'sales' (Agent) users for the modal dropdowns
$sales_agents_query = "SELECT id, full_name FROM users WHERE role = 'sales' AND status = 'active' ORDER BY full_name ASC";
$sales_agents_result = mysqli_query($conn, $sales_agents_query);
$sales_agents = [];
while ($agent = mysqli_fetch_assoc($sales_agents_result)) {
    $sales_agents[] = $agent;
}


// Close connection at the end of the script
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        .container {
            max-width: 1200px; /* Wider container for better table display */
        }
        h2, h4 {
            color: #2D568F;
            margin-bottom: 20px;
        }
        .form-label {
            font-weight: 500;
        }
        .table thead {
            background-color: #2D568F;
            color: white;
        }
        .table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .btn-sm {
            margin-right: 5px;
            margin-bottom: 5px; /* Added for better spacing on small screens */
        }
        .alert {
            margin-top: 20px;
            font-weight: bold;
        }
        .super-agent-icon {
            color: #007bff; /* Blue color for the link icon */
            cursor: pointer;
            margin-left: 5px;
            font-size: 0.9em; /* Slightly smaller icon */
        }
        .list-group-item-success {
            background-color: #d1e7dd !important; /* Green background for selected agents */
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>User Management</h2>

        <?php if (!empty($message)) : ?>
            <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($token_generated)) : ?>
            <div class="alert alert-info d-flex justify-content-between align-items-center">
                <span><strong>Token:</strong> <span id="tokenText"><?= htmlspecialchars($token_generated) ?></span></span>
                <button class="btn btn-sm btn-primary" onclick="copyTokenToClipboard()">Copy Token</button>
            </div>
            <p class="text-secondary fw-bold">Copy this token and share it with <span style="color:#2D568F"><?= htmlspecialchars($full_name_for_token); ?></span> to reset their password. This token expires in 1 hour.</p>
        <?php endif; ?>

        <h4 class="mt-4">Add New User</h4>
        <form action="" method="POST" class="mb-4 needs-validation" novalidate>
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" id="username" class="form-control" required pattern="[a-zA-Z0-9_]+" title="Username can only contain letters, numbers, and underscores.">
                    <div class="invalid-feedback">
                        Please provide a unique username (letters, numbers, and underscores only).
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required>
                    <div class="invalid-feedback">
                        Please provide a full name.
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="role" class="form-label">Role</label>
                    <select name="role" id="role" class="form-select" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="approver">Approver</option>
                        <option value="super agent">Super Agent</option> <option value="user">User</option>
                        <option value="sales">Agent</option>
                    </select>
                    <div class="invalid-feedback">
                        Please select a role.
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required minlength="8" title="Password must be at least 8 characters long.">
                    <div class="invalid-feedback">
                        Password must be at least 8 characters long.
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="add_user" class="btn btn-success w-100">Add User</button>
                </div>
            </div>
        </form>

        <h4 class="mt-5">All Users</h4>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($user = mysqli_fetch_assoc($result)) : ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td>
                                    <?= htmlspecialchars($user['username']) ?>
                                    <?php if ($user['role'] == 'super agent'): ?>
                                        <i class="fa-solid fa-link super-agent-icon"
                                           data-bs-toggle="modal"
                                           data-bs-target="#linkAgentsModal"
                                           data-super_agent_id="<?= htmlspecialchars($user['id']) ?>"
                                           data-super_agent_name="<?= htmlspecialchars($user['full_name']) ?>"
                                           data-linked_agents="<?= htmlspecialchars($user['linked_agents']) ?>"
                                           title="Link Agents">
                                        </i>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td><?= htmlspecialchars($user['role']) ?></td>
                                <td><?= htmlspecialchars($user['status']) ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal"
                                            data-id="<?= htmlspecialchars($user['id']) ?>"
                                            data-username="<?= htmlspecialchars($user['username']) ?>"
                                            data-full_name="<?= htmlspecialchars($user['full_name']) ?>"
                                            data-role="<?= htmlspecialchars($user['role']) ?>">
                                        Edit
                                    </button>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <form action="" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to deactivate this user?');">
                                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                            <button type="submit" name="deactivate_user" class="btn btn-warning btn-sm">Deactivate</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>Inactive</button>
                                    <?php endif; ?>
                                    <form action="" method="POST" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                        <input type="hidden" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>">
                                        <button type="submit" name="generate_token" class="btn btn-info btn-sm">Generate Token</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required pattern="[a-zA-Z0-9_]+" title="Username can only contain letters, numbers, and underscores.">
                            <div class="invalid-feedback">
                                Please provide a unique username (letters, numbers, and underscores only).
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                            <div class="invalid-feedback">
                                Please provide a full name.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="admin">Admin</option>
                                <option value="approver">Approver</option>
                                <option value="super agent">Super Agent</option> <option value="user">User</option>
                                <option value="sales">Agent</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select a role.
                            </div>
                        </div>
                        <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="linkAgentsModal" tabindex="-1" aria-labelledby="linkAgentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content" style="zoom:0.75;">
                <div class="modal-header">
                    <h5 class="modal-title" id="linkAgentsModalLabel">Link Agents to <span id="superAgentName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST" id="linkAgentsForm">
                    <div class="modal-body">
                        <input type="hidden" name="super_agent_id" id="modal_super_agent_id">
                        <ul class="list-group" id="salesAgentsList">
                            <?php foreach ($sales_agents as $agent): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= htmlspecialchars($agent['full_name']) ?></span>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="linked_agent_ids[]" value="<?= htmlspecialchars($agent['id']) ?>" id="agent_<?= htmlspecialchars($agent['id']) ?>">
                                        <label class="form-check-label" for="agent_<?= htmlspecialchars($agent['id']) ?>">
                                            &nbsp;
                                        </label>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                         <!-- <div class="text-end mt-3" style="position: absolute;padding-bottom:25px;">
                            <button type="submit" name="save_linked_agents" class="btn btn-primary">Save Linked Agents</button>
                        </div> -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="save_linked_agents" class="btn btn-primary">Save Linked Agents</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Pre-fill the edit user modal with user data
        const editUserModal = document.getElementById('editUserModal');
        editUserModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Button that triggered the modal
            const userId = button.getAttribute('data-id');
            const username = button.getAttribute('data-username');
            const fullName = button.getAttribute('data-full_name');
            const role = button.getAttribute('data-role');

            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_role').value = role;
        });

        // Function to copy token to clipboard
        function copyTokenToClipboard() {
            var token = document.getElementById("tokenText").innerText;
            navigator.clipboard.writeText(token).then(function() {
                alert("Token copied to clipboard successfully!");
            }).catch(function(err) {
                console.error("Failed to copy token:", err);
                alert("Could not copy token. Please copy manually.");
            });
        }

        // --- Link Agents Modal Logic (New JavaScript) ---
        const linkAgentsModal = document.getElementById('linkAgentsModal');
        linkAgentsModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // The icon that triggered the modal
            const superAgentId = button.getAttribute('data-super_agent_id');
            const superAgentName = button.getAttribute('data-super_agent_name');
            const linkedAgentsStr = button.getAttribute('data-linked_agents'); // Comma-separated string

            document.getElementById('modal_super_agent_id').value = superAgentId;
            document.getElementById('superAgentName').innerText = superAgentName;

            const salesAgentsList = document.getElementById('salesAgentsList');
            const checkboxes = salesAgentsList.querySelectorAll('input[type="checkbox"]');
            
            // Clear previous selections and highlighting
            checkboxes.forEach(cb => {
                cb.checked = false;
                cb.closest('.list-group-item').classList.remove('list-group-item-success');
            });

            // Set checkboxes based on linked_agents data
            if (linkedAgentsStr) {
                const linkedAgentsArray = linkedAgentsStr.split(',').map(id => parseInt(id.trim(), 10));
                linkedAgentsArray.forEach(agentId => {
                    const checkbox = document.getElementById(`agent_${agentId}`);
                    if (checkbox) {
                        checkbox.checked = true;
                        checkbox.closest('.list-group-item').classList.add('list-group-item-success');
                    }
                });
            }
        });

        // Add event listener to dynamically update highlighting on checkbox change
        document.getElementById('salesAgentsList').addEventListener('change', function(event) {
            if (event.target.type === 'checkbox') {
                const listItem = event.target.closest('.list-group-item');
                if (event.target.checked) {
                    listItem.classList.add('list-group-item-success');
                } else {
                    listItem.classList.remove('list-group-item-success');
                }
            }
        });


        // Bootstrap form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')

            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>