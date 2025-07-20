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

// Query to get the approved proposal summary data
$query = "
SELECT 
    p.id AS 'Proposal ID',
    p.borrower_name AS 'Borrower Name',
    p.loan_amount AS 'Loan Amount',
    DATE_FORMAT(p.approved_on, '%d %b, %Y') AS 'Approval Date',
    ps.status_name AS 'Status'
FROM proposals p
INNER JOIN proposal_status_master ps ON p.status = ps.status_id
WHERE p.status = 9
ORDER BY p.approved_on DESC";

// Execute the query
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Proposal Summary Report</title>
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
        <h2 class="text-center mb-4">Approved Proposal Summary Report</h2>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Proposal ID</th>
                        <th>Borrower Name</th>
                        <th>Loan Amount</th>
                        <th>Approval Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                        <tr>
                            <td><?= $row['Proposal ID'] ?></td>
                            <td><?= $row['Borrower Name'] ?></td>
                            <td><?= number_format($row['Loan Amount'], 2) ?></td>
                            <td><?= $row['Approval Date'] ?></td>
                            <td><?= $row['Status'] ?></td>
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
