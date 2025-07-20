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

// Include database configuration file
include 'config.php'; // Assuming this is your database connection file

// Capture the From and To dates from the GET parameters
$fromDate = isset($_GET['fromDate']) ? $_GET['fromDate'] : '';
$toDate = isset($_GET['toDate']) ? $_GET['toDate'] : '';

// Add WHERE condition for date filter
$dateCondition = '';
if ($fromDate && $toDate) {
    $dateCondition = "AND p.created_at BETWEEN '$fromDate' AND '$toDate'";
} elseif ($fromDate) {
    $dateCondition = "AND p.created_at >= '$fromDate'";
} elseif ($toDate) {
    $dateCondition = "AND p.created_at <= '$toDate'";
}
    $filterContext = ""; // Initialize filter condition
    if ($_SESSION['user_role'] === "sales") {
        // Sales users see only their records
        $filterContext = " and p.ar_user_id = " . (int) $_SESSION['user_id'];
    }
    if ($_SESSION['user_role'] === "super agent") {
        $query = "select linked_agents from users where id=" . $_SESSION['user_id'];
        $linked_agents_data = mysqli_query($conn, $query); // Changed variable name from $linked_agents to $result for clarity
        $row = mysqli_fetch_assoc($linked_agents_data);
        $linked_agents = $row['linked_agents'];
        
        // Sales users see only their records
        $filterContext = " and p.ar_user_id in(" . $linked_agents . ")";
        
    }

// Modified query to include the date range condition
$query = "
    SELECT 
        p.id, 

        COALESCE(u3.full_name, 'No Agent') AS 'Agent Name',
        p.created_by AS 'Created By', 
        p.borrower_name AS 'Borrower Name', 
        p.product_type as 'Product',
        p.city AS 'City', 
        CONCAT(p.vehicle_name, ' - ', p.model) AS 'Vehicle', 
        p.kilometers_driven AS 'KM Driven',
        p.loan_amount AS 'Requested Amount', 
        p.approved_amount AS 'Approved Amount', 
        p.approved_category AS 'Category', 
        ps.status_name AS 'Status',
        p.status AS 'StatusID'
    FROM proposals p 
    INNER JOIN users u ON p.created_by = u.id
    INNER JOIN proposal_status_master ps ON p.status = ps.status_id
    LEFT JOIN users u3 ON p.ar_user_id = u3.id
    LEFT JOIN users u4 ON p.allocated_to_user_id = u4.id
    WHERE 1=1
    $dateCondition $filterContext
";

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal Status Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        body {
            font-size: 0.9rem; /* Reduced font size */
        }
        .table th, .table td {
            text-align: center;
            vertical-align: middle;
        }
        .table-container {
            margin-top: 20px;
        }
        .btn-back {
            margin-bottom: 20px;
        }
        .filter-input {
            margin-bottom: 15px;
        }
        .export-btns {
            margin-bottom: 20px;
        }
        .export-btns button {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="btn-back">
            <a href="report_landing_page.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Reports</a>
        </div>
        <h2 class="text-center mb-4">Proposal Status Report</h2>

        <!-- Filter Section -->
        <div class="filter-input">
            <label for="fromDate">From Date:</label>
            <input type="date" id="fromDate" class="form-control" value="<?php echo $fromDate; ?>" onchange="applyDateFilter()">
        </div>
        <div class="filter-input">
            <label for="toDate">To Date:</label>
            <input type="date" id="toDate" class="form-control" value="<?php echo $toDate; ?>" onchange="applyDateFilter()">
        </div>

        <!-- Export Buttons -->
        <div class="export-btns">
            <button class="btn btn-success" onclick="exportToExcel()">Export to Excel</button>
            <button class="btn btn-info" onclick="exportToCSV()">Export to CSV</button>
        </div>

        <!-- Table Section -->
        <div class="table-container">
            <table class="table table-bordered table-striped w-100" id="proposalTable">
                <thead class="table-light">
                    <tr>
                        <th>Proposal ID</th>
                        <th>Agent Name</th>
                        <th>Borrower Name</th>
                        <th>Product</th>
                        <th>City</th>
                        <th>Vehicle</th>
                        <th>KM Driven</th>
                        <th>Requested Amount</th>
                        <th>Approved Amount</th>
                        <th>Category</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetching data and displaying the report
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['Agent Name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['Borrower Name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['Product']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['City']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['Vehicle']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['KM Driven']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['Requested Amount']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['Approved Amount']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['Category']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['Status']) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- JavaScript for table search, export functions, and date filtering -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to apply date filter
        function applyDateFilter() {
            const fromDate = document.getElementById("fromDate").value;
            const toDate = document.getElementById("toDate").value;

            // Construct the new URL with date parameters
            const url = new URL(window.location.href);
            if (fromDate) {
                url.searchParams.set('fromDate', fromDate);
            }
            if (toDate) {
                url.searchParams.set('toDate', toDate);
            }

            // Reload the page with the new parameters
            window.location.href = url.toString();
        }

        // Function to export to Excel
        function exportToExcel() {
            const table = document.getElementById("proposalTable");
            let html = table.outerHTML;
            let blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            let link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'proposal_status_report.xlsx';
            link.click();
        }

        // Function to export to CSV
        function exportToCSV() {
            const table = document.getElementById("proposalTable");
            let rows = table.rows;
            let csv = [];
            for (let i = 0; i < rows.length; i++) {
                let row = rows[i];
                let cols = row.querySelectorAll('td, th');
                let csvRow = [];
                cols.forEach(col => csvRow.push(col.innerText));
                csv.push(csvRow.join(","));
            }
            let csvFile = new Blob([csv.join("\n")], { type: 'text/csv' });
            let link = document.createElement('a');
            link.href = URL.createObjectURL(csvFile);
            link.download = 'proposal_status_report.csv';
            link.click();
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.11/jspdf.plugin.autotable.min.js"></script>
</body>
</html>
