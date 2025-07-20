<?php
session_start();
if (!isset($_SESSION['user_id'])) {
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

// Include the config.php for database connection
include 'config.php';

// Query to get the list of users and their associated proposal details
$query = "SELECT u.full_name AS 'User', 
                 ps.status_name AS 'Current Status',
                 COUNT(p.id) AS 'Number of Proposals'
          FROM proposals p 
          INNER JOIN users u ON p.allocated_to_user_id = u.id
          INNER JOIN proposal_status_master ps ON p.status = ps.status_id
          GROUP BY u.id, ps.status_name";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Workload Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px; /* Small font size for the entire page */
        }
        table th, table td {
            vertical-align: middle;
            font-size: 14px; /* Small font size for table content */
        }
        .back-btn {
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="row">
        <div class="col-12">
            <!-- Back Button -->
            <a href="javascript:history.back()" class="btn btn-secondary back-btn">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <h3 class="mt-3">User Workload Report</h3>
            
            <!-- Report Table -->
            <div class="mt-4">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Current Status</th>
                            <th>Number of Proposals</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= $row['User'] ?></td>
                                <td><?= $row['Current Status'] ?></td>
                                <td><?= $row['Number of Proposals'] ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
