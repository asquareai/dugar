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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Proposal Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .report-card {
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            background-color: white;
            border: 1px solid #ddd;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }
        .report-card:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .icon {
            font-size: 24px;
            margin-bottom: 5px;
        }
        h5 {
            font-size: 16px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center mb-4">Loan Proposal Reports</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="report-card text-primary" onclick="openReport('proposal_status')">
                    <i class="fas fa-tasks icon text-primary"></i>
                    <h5 class="text-primary">Proposal Status Report</h5>
                </div>
            </div>
            <!-- <div class="col-md-4">
                <div class="report-card text-success" onclick="openReport('user_workload')">
                    <i class="fas fa-user icon text-success"></i>
                    <h5 class="text-success">User Workload Report</h5>
                </div>
            </div> -->
            <div class="col-md-4">
                <div class="report-card text-warning" onclick="openReport('approval_timeline')">
                    <i class="fas fa-clock icon text-warning"></i>
                    <h5 class="text-warning">Approval Timeline Report</h5>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card text-danger" onclick="openReport('rejected_hold')">
                    <i class="fas fa-ban icon text-danger"></i>
                    <h5 class="text-danger">All Status Report</h5>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card text-info" onclick="openReport('client_summary')">
                    <i class="fas fa-clipboard icon text-info"></i>
                    <h5 class="text-info">Client Proposal Summary</h5>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="report-card text-secondary" onclick="openReport('monthly_trend')">
                    <i class="fas fa-chart-line icon text-secondary"></i>
                    <h5 class="text-secondary">Monthly Proposal Trend</h5>
                </div>
            </div>
            <!-- <div class="col-md-4">
                <div class="report-card text-dark" onclick="openReport('turnaround_time')">
                    <i class="fas fa-hourglass-half icon text-dark"></i>
                    <h5 class="text-dark">Turnaround Time Report</h5>
                </div>
            </div> -->
            <!-- <div class="col-md-4">
                <div class="report-card text-primary" onclick="openReport('proposal_allocation')">
                    <i class="fas fa-folder-open icon text-primary"></i>
                    <h5 class="text-primary">Proposal Allocation Report</h5>
                </div>
            </div> -->
            <div class="col-md-4">
                <div class="report-card text-success" onclick="openReport('approved_proposal_summary')">
                    <i class="fas fa-check icon text-success"></i>
                    <h5 class="text-success">Approved Proposal Summary</h5>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card text-warning" onclick="openReport('review_pending')">
                    <i class="fas fa-sync icon text-warning"></i>
                    <h5 class="text-warning">Review Pending Report</h5>
                </div>
            </div>
            
        </div>
    </div>

    <script>
        function openReport(reportName) {
            window.location.href = "rpt-" + reportName + ".php";
        }
    </script>
</body>
</html>