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

// Query to get monthly proposal trend data
$query = "
SELECT 
    DATE_FORMAT(p.created_at, '%M %Y') AS 'Month',
    COUNT(p.id) AS 'Total Proposals',
    SUM(p.loan_amount) AS 'Total Loan Amount',
    SUM(CASE WHEN p.status = 9 THEN 1 ELSE 0 END) AS 'Approved Proposals',
    SUM(CASE WHEN p.status = 10 THEN 1 ELSE 0 END) AS 'Rejected Proposals'
FROM proposals p
GROUP BY YEAR(p.created_at), MONTH(p.created_at)
ORDER BY YEAR(p.created_at) DESC, MONTH(p.created_at) DESC";

// Execute the query
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Proposal Trend Report</title>
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
        <h2 class="text-center mb-4">Monthly Proposal Trend Report</h2>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Total Proposals</th>
                        <th>Total Loan Amount</th>
                        <th>Approved Proposals</th>
                        <th>Rejected Proposals</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                        <tr>
                            <td><?= $row['Month'] ?></td>
                            <td><?= $row['Total Proposals'] ?></td>
                            <td><?= number_format($row['Total Loan Amount'], 2) ?></td>
                            <td><?= $row['Approved Proposals'] ?></td>
                            <td><?= $row['Rejected Proposals'] ?></td>
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
