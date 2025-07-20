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

// Query to get client proposal summary data
$query = "
SELECT 
    u.full_name AS 'Client Name',
    COUNT(CASE WHEN p.status not in(9,10) THEN 1 END) AS 'Progress',
    COUNT(CASE WHEN p.status = 9 THEN 1 END) AS 'Approved',
    COUNT(CASE WHEN p.status = 10 THEN 1 END) AS 'Rejected',
    COUNT(CASE WHEN p.status = 12 THEN 1 END) AS 'Hold'
FROM proposals p join users u on p.ar_user_id = u.id
GROUP BY u.full_name
ORDER BY u.full_name";

// Execute the query
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Proposal Summary</title>
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
        <h2 class="text-center mb-4">Client Proposal Summary Report</h2>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Client Name</th>
                        <th>Progress</th>
                        <th>Approved</th>
                        <th>Rejected</th>
                        <th>Hold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                        <tr>
                            <td><?= $row['Client Name'] ?></td>
                            <td><?= $row['Progress'] ?></td>
                            <td><?= $row['Approved'] ?></td>
                            <td><?= $row['Rejected'] ?></td>
                            <td><?= $row['Hold'] ?></td>
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
