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

// Query to get turnaround time data
$query = "
SELECT 
    p.id AS 'Proposal ID',
    p.borrower_name AS 'Borrower Name',
    DATE_FORMAT(p.created_at, '%d %b, %Y') AS 'Created Date',
    IFNULL(DATE_FORMAT(p.approved_on, '%d %b, %Y'), 'N/A') AS 'Approval Date',
    IFNULL(DATEDIFF(p.approved_on, p.created_at), 'N/A') AS 'Turnaround Time'
FROM proposals p
WHERE p.approved_on IS NOT NULL
ORDER BY p.created_at DESC";

// Execute the query
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turnaround Time Report</title>
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
        <h2 class="text-center mb-4">Turnaround Time Report</h2>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Proposal ID</th>
                        <th>Borrower Name</th>
                        <th>Created Date</th>
                        <th>Approval Date</th>
                        <th>Turnaround Time (Days)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                        <tr>
                            <td><?= $row['Proposal ID'] ?></td>
                            <td><?= $row['Borrower Name'] ?></td>
                            <td><?= $row['Created Date'] ?></td>
                            <td><?= $row['Approval Date'] ?></td>
                            <td><?= $row['Turnaround Time'] === 'N/A' ? 'N/A' : $row['Turnaround Time'] ?></td>
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
