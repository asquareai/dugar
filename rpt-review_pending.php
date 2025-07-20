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


// Include the configuration file
include('config.php');

// Query to get the review pending proposals data
$query = "
SELECT 
    p.id AS 'Proposal ID',
    p.borrower_name AS 'Borrower Name',
    p.loan_amount AS 'Loan Amount',
    ps.status_name AS 'Status',
    u.full_name AS 'Created By',
    u2.full_name AS 'Assigned To'
FROM proposals p
INNER JOIN users u ON p.created_by = u.id
INNER JOIN proposal_status_master ps ON p.status = ps.status_id
LEFT JOIN users u2 ON p.allocated_to_user_id = u2.id
WHERE p.status = 2
ORDER BY p.created_at DESC";

// Execute the query
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Pending Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        body {
            font-size: 14px;
        }
        .table th, .table td {
            text-align: center;
        }
        .back-btn {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <a href="report_landing_page.php" class="btn btn-secondary back-btn">Back to Reports</a>
        <h2 class="text-center mb-4">Review Pending Report</h2>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Proposal ID</th>
                        <th>Borrower Name</th>
                        <th>Loan Amount</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Assigned To</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                        <tr>
                            <td><?= $row['Proposal ID'] ?></td>
                            <td><?= $row['Borrower Name'] ?></td>
                            <td><?= number_format($row['Loan Amount'], 2) ?></td>
                            <td><?= $row['Status'] ?></td>
                            <td><?= $row['Created By'] ?></td>
                            <td><?= $row['Assigned To'] ? $row['Assigned To'] : 'Not Assigned' ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        // Export button functionality (you can implement export logic based on your preferences)
    </script>
</body>
</html>

<?php
// Close the database connection
mysqli_close($conn);
?>
