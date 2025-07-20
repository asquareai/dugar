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

// Query to get the approval timeline details for each proposal
$query = "SELECT 
                p.id AS 'Proposal ID',
                p.borrower_name AS 'Borrower Name',
                p.product_type AS 'Product Type',
                CONCAT(p.vehicle_name, ' - ', p.model) AS 'Vehicle',
                p.loan_amount AS 'Loan Amount',
                ps.status_name AS 'Status',
                u.full_name AS 'Allocated User',
                p.created_at 'Created Date',
                p.approved_on AS 'Approval Date',
                        CONCAT(
                TIMESTAMPDIFF(DAY, p.created_at, p.approved_on), ' day(s) ',
                LPAD(HOUR(TIMEDIFF(p.approved_on, p.created_at)) % 24, 2, '0'), ' hr(s) ',
                LPAD(MINUTE(TIMEDIFF(p.approved_on, p.created_at)), 2, '0'), ' min(s)'
                ) AS `Time to Approve`
          FROM proposals p
          INNER JOIN proposal_status_master ps ON p.status = ps.status_id
          LEFT JOIN users u ON p.allocated_to_user_id = u.id
          WHERE p.status IN (9)"; 

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Timeline Report</title>
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
            <h3 class="mt-3">Approval Timeline Report</h3>
            
            <!-- Report Table -->
            <div class="mt-4">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Proposal ID</th>
                            <th>Borrower Name</th>
                            <th>Product</th>
                            <th>Vehicle</th>
                            <th>Loan Amount</th>
                            <th>Status</th>
                            <th>Allocated User</th>
                            <th>Created Date</th>
                            <th>Approved Date</th>
                            <th>Time to Approve (Days)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= $row['Proposal ID'] ?></td>
                                <td><?= $row['Borrower Name'] ?></td>
                                <td><?= $row['Product Type'] ?></td>
                                <td><?= $row['Vehicle'] ?></td>
                                <td><?= number_format($row['Loan Amount'], 2) ?></td>
                                <td><?= $row['Status'] ?></td>
                                <td><?= $row['Allocated User'] ?></td>
                                <td><?= $row['Created Date'] ? date("d M, Y h:i A", strtotime($row['Created Date'])) : 'N/A' ?></td>
                                <td><?= $row['Approval Date'] ? date("d M, Y h:i A", strtotime($row['Approval Date'])) : 'N/A' ?></td>

                                <td><?= $row['Time to Approve'] > 0 ? $row['Time to Approve'] : 'N/A' ?></td>

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
