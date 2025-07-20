<?php
session_start();
include 'config.php';


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
// --- PHP Logic for Alerts ---
if (isset($_GET['status_code'])) {
    $status_code = $_GET['status_code'];
    $message = "Proposal submitted for review successfully!";
    // Bootstrap alert class based on status_code
    $alert_class = ($status_code == '1024') ? 'alert-success' : 'alert-danger';

    echo '<div class="custom-alert">'; // Custom wrapper for positioning
    echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show small-alert" role="alert">';
    echo htmlspecialchars($message); // Prevent XSS attack
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    echo '</div>';
}


// --- PHP Logic for Allocation Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hf_proposal_id']) && isset($_POST['hf_allocated_user_id'])) {

    $proposal_id = (int) $_POST['hf_proposal_id'];
    $allocated_user_id = (int) $_POST['hf_allocated_user_id'];
    
    // Ensure only admins can perform this action
    if ($_SESSION['user_role'] === "admin") {
        // Use prepared statements for security
        $sql = "UPDATE proposals SET allocated_to_user_id = ?, allocated_on = now() WHERE id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $allocated_user_id, $proposal_id); // 'i' for integer
            
            if ($stmt->execute()) {
                echo '<div class="custom-alert alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i> Allocation updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
            } else {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i> Error updating allocation: ' . $stmt->error . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
            }
            $stmt->close();
        } else {
            echo "Error preparing query: " . $conn->error;
        }
    } else {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> Unauthorized action!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    }
}

// --- PHP Logic for Filtering and Data Retrieval ---

// Include 'Allocated To' field only for admin users
$filterContext = ""; // Initialize filter condition

if (isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === "sales") {
        // Sales users see only their records
        $filterContext = " p.ar_user_id = " . (int) $_SESSION['user_id'];
    }
    if ($_SESSION['user_role'] === "super agent") {
        $query = "select linked_agents from users where id=" . $_SESSION['user_id'];
        $linked_agents_data = mysqli_query($conn, $query); // Changed variable name from $linked_agents to $result for clarity
        $row = mysqli_fetch_assoc($linked_agents_data);
        $linked_agents = $row['linked_agents'];
        
        // Sales users see only their records
        $filterContext = " p.ar_user_id in(" . $linked_agents . ")";
        
    }
}
if ($filterContext =="") $filterContext = "1 = 1";
// Get selected status from query string (default to 'In Progress' if not set)
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : 'In Progress';

if($_SESSION['user_role'] === "approver" &&  $selectedStatus !="In Progress" && $selectedStatus !="Processed")
{
$selectedStatus = "In Progress";
}
// Mapping of status for filtering and display
if($_SESSION['user_role'] === "approver")
{
    $statusMapping = [
        'New' => [1, 2],
        'In Progress' => [8],
        'Processed' => [9, 10, 12, 13] // Approved (9), Rejected (10), Hold (12), Cancelled (13)
    ];    
}
else
{
    $statusMapping = [
        'New' => [1, 2],
        'In Progress' => [3, 4, 5, 6, 7, 8, 11, 14],
        'Processed' => [9, 10, 12, 13] // Approved (9), Rejected (10), Hold (12), Cancelled (13)
    ];
}
// Get the status ID array for the selected status. Default to 'In Progress' IDs if status not found.
$statusIds = isset($statusMapping[$selectedStatus]) ? $statusMapping[$selectedStatus] : $statusMapping['In Progress'];


// Include 'Allocated To' field only for admin and 'user' roles for specific statuses
$allocatedToField = "";
$joinAllocatedTo = "";

if ($_SESSION['user_role'] === "admin") {
    $allocatedToField = ", COALESCE(u2.full_name, 'NOT ALLOCATED') AS 'Allocated To'"; // Show "NOT ALLOCATED" if NULL
    $joinAllocatedTo = " LEFT JOIN users u2 ON p.allocated_to_user_id = u2.id"; // Join users table to get name
}
// For 'user' role, if status is not 'New', show their allocated proposals
if ($_SESSION['user_role'] === "user" && $selectedStatus !== "New") {
    $allocatedToField = ", COALESCE(u2.full_name, 'NOT ALLOCATED') AS 'Allocated To'";
    $joinAllocatedTo = " JOIN users u2 ON p.allocated_to_user_id = u2.id AND u2.id = " . (int)$_SESSION['user_id'];
}


// Define the main status field for the table data
$mainStatusField = "
    CASE
        WHEN p.status IN (1, 2) THEN 'New'
        WHEN p.status IN (3, 4, 5, 6, 7, 8, 11, 14) THEN 'In Progress'
        WHEN p.status IN (9, 10, 12, 13) THEN 'Processed'
        ELSE 'Other'
    END AS 'Main Status'
";

// if($_SESSION['user_role'] != "sales" && $_SESSION['user_role'] != "super agent"  )
if($_SESSION['user_role'] == 'user' ||  $_SESSION['user_role'] == "approver"  )
{
// Build the query dynamically with selected status filter
$query = "SELECT
    p.id,
    u.full_name AS 'User',
    COALESCE(u3.full_name, 'No Agent') AS 'Agent Name',
    DATE_FORMAT(p.created_at, '%d-%b-%Y %H:%i:%s') AS created_at,
    p.created_by AS 'Created By',
    p.borrower_name AS 'Borrower Name',
    p.city AS 'City',
    p.product_type AS 'Product Type',
    CONCAT(p.vehicle_name, ' - ', p.model) AS 'Vehicle',
    p.loan_amount AS 'Loan Amount',
    p.approved_amount AS 'Approved Amount',
    ps.status_name AS 'Status',
    p.status AS 'StatusID',
    approved_category,
    $mainStatusField
    $allocatedToField
FROM proposals p
INNER JOIN users u ON p.created_by = u.id
INNER JOIN proposal_status_master ps ON p.status = ps.status_id
LEFT JOIN users u3 ON p.ar_user_id = u3.id
$joinAllocatedTo
WHERE p.status IN (" . implode(",", $statusIds) . ") and " .
$filterContext; // Filter based on selected status
}
else if($_SESSION['user_role'] == 'sales' ||  $_SESSION['user_role'] == "super agent"  )
{
    // Build the query dynamically with selected status filter
$query = "SELECT
    p.id,
    u.full_name AS 'User',
    COALESCE(u3.full_name, 'No Agent') AS 'Agent Name',
    DATE_FORMAT(p.created_at, '%d-%b-%Y %H:%i:%s') AS created_at,
    p.created_by AS 'Created By',
    p.borrower_name AS 'Borrower Name',
    p.city AS 'City',
    p.product_type AS 'Product Type',
    CONCAT(p.vehicle_name, ' - ', p.model) AS 'Vehicle',
    p.loan_amount AS 'Loan Amount',
    p.approved_amount AS 'Approved Amount',
    ps.status_name AS 'Status',
    p.status AS 'StatusID',
    approved_category,
    $mainStatusField
    $allocatedToField
FROM proposals p
INNER JOIN users u ON p.created_by = u.id
INNER JOIN proposal_status_master ps ON p.status = ps.status_id
LEFT JOIN users u3 ON p.ar_user_id = u3.id
$joinAllocatedTo
WHERE p.status IN (" . implode(",", $statusIds) . ") and " .
$filterContext; // Filter based on selected status
}
//file_put_contents('log.txt', "Proposal Query " . $query . "\n", FILE_APPEND);
$result = mysqli_query($conn, $query);

$query = "select id, full_name from users where role='user'";
$allocation_users = mysqli_query($conn, $query);


$isSales = ($_SESSION['user_role'] === 'sales') ? 1 : 0;

// SQL query to count records based on new status mapping
if ($_SESSION['user_role'] === 'sales' || $_SESSION['user_role'] === 'super agent') {
    $sql = "
        SELECT
             CASE
                WHEN status IN (1, 2) THEN 'New'
                WHEN status IN (3, 4, 5, 6, 7, 8, 11, 14) THEN 'In Progress'
                WHEN status IN (9, 10, 12, 13) THEN 'Processed'
                ELSE 'Other'
            END AS category,
            COUNT(*) as count
        FROM proposals p where $filterContext and hide_from_agent = 0 GROUP BY category ";
} else if ($_SESSION['user_role'] === 'user') {
    $sql = "
        SELECT
            CASE
                WHEN status IN (1, 2) THEN 'New'
                WHEN status IN (3, 4, 5, 6, 7, 8, 11, 14) THEN 'In Progress'
                WHEN status IN (9, 10, 12, 13) THEN 'Processed'
                ELSE 'Other'
            END AS category,
            COUNT(*) as count
        FROM proposals
        WHERE
            (status IN (1, 2))
            OR
            (status NOT IN (1, 2) AND allocated_to_user_id = " . (int)$_SESSION['user_id'] . ")
        GROUP BY category
    ";
}
 else if ($_SESSION['user_role'] === 'approver') {
   $sql = "
        SELECT
            CASE
                WHEN status IN (8) THEN 'In Progress'
                WHEN status IN (9, 10, 12, 13) THEN 'Processed'
                ELSE 'Other'
            END AS category,
            COUNT(*) as count
        FROM proposals GROUP BY category ";
}

else 
 { // Admin or other roles
    $sql = "
        SELECT
            CASE
                WHEN status IN (1, 2) THEN 'New'
                WHEN status IN (3, 4, 5, 6, 7, 8, 11, 14) THEN 'In Progress'
                WHEN status IN (9, 10, 12, 13) THEN 'Processed'
                ELSE 'Other'
            END AS category,
            COUNT(*) as count
        FROM proposals GROUP BY category ";
}

$statuscoountresult = $conn->query($sql);

// Initialize counts for the new categories
$counts = [
    "New" => 0,
    "In Progress" => 0,
    "Processed" => 0
];

// Assign counts dynamically
while ($row = $statuscoountresult->fetch_assoc()) {
    $category = $row['category'];
    if (isset($counts[$category])) {
        $counts[$category] = $row['count'];
    }
}
?>

<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<link rel="stylesheet" href="assets/css/proposal.css">
<link rel="stylesheet" href="assets/css/global.css">
<link rel="stylesheet" href="assets/css/loader.css">

<div style="width:100%; text-align:center">
<!-- <?php if ($_SESSION["user_role"] != "admin" && $_SESSION["user_role"] != "approver") : ?>
    <img src="assets/images/logo.png" style="width:250px;">
    <h3>Loan Proposal Management</h3>
    <div><?php echo $_SESSION['user_fullname'] . " (" . $_SESSION['user_role'] . ")";?></div>
    <a href="logout.php">Logout</a>
    
<?php endif; ?> -->

<div class="container-fluid mt-4" style="padding-bottom:100px"> <div class="d-flex flex-wrap gap-2 mb-3">
        <?php if ($_SESSION['user_role'] != "approver"): ?>
            <button class="btn btn-primary" onclick="proposalForm()">Create New Proposal</button>
        <?php endif; ?>
    </div>
    
    <div id="statusTabs" class="d-flex justify-content-around mb-3">
        <?php if ($_SESSION['user_role'] != "approver"): ?>
        <button class="status-tab btn btn-outline-primary btn-sm" onclick="selectTab(this, 'New')" data-status="New" >
            <span class="tab-label">New</span>
        </button>
        <?php endif; ?>
        <button class="status-tab btn btn-outline-warning btn-sm" onclick="selectTab(this, 'In Progress')" data-status="In Progress">
            <span class="tab-label">In Progress</span>
        </button>
        <button class="status-tab btn btn-outline-info btn-sm" onclick="selectTab(this, 'Processed')" data-status="Processed">
            <span class="tab-label">Processed</span>
        </button>
    </div>

    <div class="row d-none d-md-flex" >
        <div class="col-md-2 status-col" <?php if ($_SESSION['user_role'] == "approver"):?>style="display:none"<?php endif; ?>>
            <div class="card border-primary shadow-lg <?php if ($selectedStatus == "New"):?>active-category<?php endif; ?> position-relative">
                <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                    New <?php if ($selectedStatus == "New"): ?><span class="selected-icon">✔</span> <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="card-text">Total: <span class="fw-bold"><?php echo $counts['New']; ?></span></p>
                    <button class="btn btn-outline-primary btn-sm" onclick="filterByStatus('New')">View Details</button>
                </div>
            </div>
        </div>

        <div class="col-md-2 status-col">
            <div class="card border-warning shadow-lg <?php if ($selectedStatus == "In Progress"):?>active-category<?php endif; ?> position-relative">
                <div class="card-header bg-warning text-white fw-bold d-flex justify-content-between align-items-center">
                    In Progress <?php if ($selectedStatus == "In Progress"): ?><span class="selected-icon">✔</span> <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="card-text">Total: <span class="fw-bold"><?php echo $counts['In Progress']; ?></span></p>
                    <button class="btn btn-outline-warning btn-sm" onclick="filterByStatus('In Progress')">View Details</button>
                </div>
            </div>
        </div>

        <div class="col-md-2 status-col">
            <div class="card border-info shadow-lg <?php if ($selectedStatus == "Processed"):?>active-category<?php endif; ?> position-relative">
                <div class="card-header bg-info text-white fw-bold d-flex justify-content-between align-items-center">
                    Processed <?php if ($selectedStatus == "Processed"): ?><span class="selected-icon">✔</span> <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="card-text">Total: <span class="fw-bold"><?php echo $counts['Processed']; ?></span></p>
                    <button class="btn btn-outline-info btn-sm" onclick="filterByStatus('Processed')">View Details</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (mysqli_num_rows($result) > 0) { ?>
        <div class="table-responsive w-100" style="max-width: 100%;"> <table id="proposalTable" class="table table-hover table-striped table-bordered nowrap" style="width:100%">
                <thead class="table-row-header">
                    <tr>
                        <th>Proposal #</th>
                        <th>Created at</th>
                        <th>Agent Name</th>
                        <th>Borrower Name</th>
                        <th>City</th>
                        <th>Product</th>
                        <th>Vehicle</th>
                        <?php if ($selectedStatus != 'Processed') { ?>
                        <th>Loan Amount</th>
                        <?php } ?>
                        <th>Status</th>
                        <?php if ($selectedStatus == 'Processed') { ?>
                        <th>Approved Amount</th>
                        
                        <th>Category</th>
                        <?php } ?>
                        <!-- <?php if ($_SESSION['user_role'] === "admin" || ($_SESSION['user_role'] === "user" && $selectedStatus != "New")) { ?>
                            <th>Allocated To</th>
                        <?php } ?> -->
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['created_at']; ?></td>
                            <td><?php echo $row['Agent Name']; ?></td>
                            <td><?php echo $row['Borrower Name']; ?></td>
                            <td><?php echo $row['City']; ?></td>
                            <td><?php echo $row['Product Type']; ?></td>
                            <td><?php echo $row['Vehicle']; ?></td>
                            <?php if ($selectedStatus != 'Processed') { ?>
                            <td><?php echo $row['Loan Amount']; ?></td>
                            <?php } ?>
                            <td>
                            <span class="badge <?php 
                                switch ($row['Status']) {
                                    case 'Draft': echo 'bg-secondary'; break; // Gray
                                    case 'Submitted for Review': echo 'bg-warning text-dark'; break; // Yellow
                                    case 'Under Review': echo 'bg-orange text-white'; break; // Orange (custom)
                                    case 'Documents Requested': echo 'bg-info text-dark'; break; // Light Blue
                                    case 'More Details Required': echo 'bg-light text-dark border'; break; // Soft Gray/White
                                    case 'Documents Uploaded': echo 'bg-primary'; break; // Blue
                                    case 'Re-Submitted for Review': echo 'bg-dark'; break; // Dark Gray
                                    case 'Sent for Approval': echo 'bg-purple text-white'; break; // Purple (custom)
                                    case 'Approved': echo 'bg-success'; break; // Green
                                    case 'Rejected': echo 'bg-danger'; break; // Red
                                    case 'Ask for More Details': echo 'bg-teal text-white'; break; // Teal (custom)
                                    case 'Closed': echo 'bg-dark'; break; // Dark Gray
                                    default: echo 'bg-secondary'; // Default Gray
                                }
                            ?>">
                                <?php echo htmlspecialchars($row['Status']); ?>
                            </span>
                            </td>
                                <?php if ($selectedStatus == 'Processed') { ?>
                                <td><?php echo $row['Approved Amount']; ?></td>
                                
                            <td>
                                <?php echo $row['approved_category']; ?>
                            </td>
                            <?php } ?>
                            <!-- <?php if ($_SESSION['user_role'] === "admin" || ($_SESSION['user_role'] === "user" && $selectedStatus != "New")) { ?>
                                <td>
                                    <span class="allocation-cell text-<?php echo ($row['Allocated To'] == 'NOT ALLOCATED') ? 'danger' : 'primary'; ?>" 
                                        data-proposal-id="<?php echo $row['id']; ?>" 
                                        data-agent-name="<?php echo htmlspecialchars($row['Agent Name']); ?>"
                                        data-borrower="<?php echo htmlspecialchars($row['Borrower Name']); ?>"
                                        data-allocated="<?php echo htmlspecialchars($row['Allocated To']); ?>">
                                        <?php echo $row['Allocated To']; ?>
                                    </span>
                                </td>
                            <?php } ?> -->
                            <td>
                                <button class="btn btn-info btn-sm" 
                                        onclick="openProposal(<?php echo $row['id']; ?>)">
                                        Open
                                </button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <div class="alert alert-info text-center">No proposals found.</div>
    <?php } ?>
</div>

<div class="modal fade" id="allocationModal" tabindex="-1" aria-labelledby="allocationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="allocateForm" method="POST">
            <input type="hidden" name="hf_proposal_id" id="hf_proposal_id">
            <input type="hidden" name="hf_allocated_user_id" id="hf_allocated_user_id">
            <div class="modal-header">
                <h5 class="modal-title" id="allocationModalLabel">Allocate Proposal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Proposal #:</strong> <span id="modalProposalNo"></span></p>
                <p><strong>Agent :</strong> <span id="modalProposalAgent"></span></p>
                <p><strong>Borrower Name:</strong> <span id="modalBorrower"></span></p>
                <p><strong>Current Allocation:</strong> <span id="modalAllocated"></span></p>
                <label for="allocatedUser">Select User:</label>
                <select id="allocatedUser" class="form-select">
                    <option value="">-- Select User --</option>
                    <?php foreach ($allocation_users as $user) { ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo $user['full_name']; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="submit" name="saveAllocation" id="saveAllocation" class="btn btn-primary">Allocate</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    let isMobile = window.innerWidth <= 768;

    $('#proposalTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        lengthMenu: [5, 10, 25, 50],
        pageLength: 50,
        responsive: {
            details: isMobile ? {
                type: 'inline',
                display: $.fn.dataTable.Responsive.display.childRowImmediate,
                target: ''
            } : true
        }
    });

    function proposalForm() {
        document.location.href="proposal-form.php";
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".allocation-cell").forEach(function (cell) {
            cell.addEventListener("click", function () {
                let proposalId = this.getAttribute("data-proposal-id");
                let borrowerName = this.getAttribute("data-borrower");
                let allocatedTo = this.getAttribute("data-allocated");
                let proposalAgent = this.getAttribute("data-agent-name");
                document.getElementById("hf_proposal_id").value = proposalId;
                document.getElementById("modalProposalNo").textContent = proposalId;
                document.getElementById("modalProposalAgent").textContent = proposalAgent;
                document.getElementById("modalBorrower").textContent = borrowerName;
                document.getElementById("modalAllocated").textContent = allocatedTo;
                
                new bootstrap.Modal(document.getElementById("allocationModal")).show();
            });
        });

        document.getElementById("saveAllocation").addEventListener("click", function () {
            let proposalId = document.getElementById("hf_proposal_id").value;
            let allocatedUserId = document.getElementById("allocatedUser").value;
            document.getElementById("hf_allocated_user_id").value = allocatedUserId;
        });
    });

    function openProposal(proposalId) {
        var user_role = "<?php echo $_SESSION['user_role']; ?>"; // Assuming stored in session

        if (user_role === 'approver') {
            window.location.href = "proposal-approval.php?id=" + proposalId;
        } else {
            window.location.href = "proposal-form.php?id=" + proposalId;
        }
    }

    function filterByStatus(status) {
        //localStorage.setItem('selectedStatus', status); // Save to local storage
        window.location.href = window.location.pathname + '?status=' + encodeURIComponent(status);
    }
    
</script>

<style>
/* 👇 Hide mobile tabs on desktop */
@media (min-width: 768px) {
    #statusTabs {
        display: none !important;
    }
}

/* 👇 Mobile-specific styles for compact tabs */
@media (max-width: 767.98px) {
    #statusTabs {
        display: flex;
        overflow-x: auto; /* Allows horizontal scrolling if content overflows */
        gap: 8px; /* Spacing between tabs */
        padding: 5px 0;
        justify-content: flex-start; /* Align tabs to the start */
    }

    #statusTabs button {
        flex-shrink: 0; /* Prevents buttons from shrinking */
        white-space: nowrap; /* Keeps text on one line */
        font-size: 13px; /* Slightly adjusted font size */
        padding: 8px 12px; /* Adjusted padding for a more compact look */
        border-radius: 20px; /* Makes buttons pill-shaped */
    }
}
</style>

<script>
// --- JavaScript for Table Header Color and Tab Management ---
function updateTableHeaderColor(status) {
    const tableHeader = document.querySelector('#proposalTable thead');
    if (!tableHeader) return;

    // Define colors matching the status cards
    const headerColors = {
        "New": "bg-primary",
        "In Progress": "bg-warning",
        "Processed": "bg-info"
    };

    // Remove all existing background and text color classes
    tableHeader.classList.remove('bg-primary', 'bg-warning', 'bg-info', 'text-white', 'text-dark'); 

    // Add the appropriate background and text color class
    const colorClass = headerColors[status];
    if (colorClass) {
        tableHeader.classList.add(colorClass);
        // Special handling for bg-warning as it needs text-dark for readability
        if (status === "In Progress") {
            tableHeader.classList.add('text-dark');
        } else {
            tableHeader.classList.add('text-white');
        }
    }
}

// document.addEventListener("DOMContentLoaded", function () {
//     let savedStatus = localStorage.getItem('selectedStatus');
    
//     // Set 'In Progress' as the default if no status is saved or in URL
//     if (!savedStatus) {
//         savedStatus = "In Progress";
//         localStorage.setItem('selectedStatus', savedStatus);
//     }

//     const urlParams = new URLSearchParams(window.location.search);
//     const statusFromUrl = urlParams.get('status');
//     const activeStatus = statusFromUrl || savedStatus;

//     // Ensure that the URL always reflects the correct, allowed status.
//     const allowedStatuses = ["New", "In Progress", "Processed"];
//     if (!statusFromUrl || !allowedStatuses.includes(statusFromUrl)) {
//         window.location.href = window.location.pathname + '?status=' + encodeURIComponent(savedStatus);
//         return; // Stop execution to prevent further rendering with incorrect status
//     }

//     // Highlight selected tab
//     const matchingBtn = document.querySelector(`.status-tab[data-status="${activeStatus}"]`);
//     if (matchingBtn) {
//         const label = matchingBtn.querySelector('.tab-label');
//         if (label) label.classList.remove('d-none');

//         applyActiveStyle(matchingBtn, activeStatus);
//     }

//     // Update table header color on initial load
//     updateTableHeaderColor(activeStatus);
// });
document.addEventListener("DOMContentLoaded", function () {
 const initialStatus = "<?php echo $selectedStatus; ?>";

    // 1. Style the active mobile tab button
    const matchingMobileBtn = document.querySelector(`#statusTabs button[data-status="${initialStatus}"]`);
    if (matchingMobileBtn) {
        applyActiveStyle(matchingMobileBtn, initialStatus);
    }

    // 2. Style the active desktop status card
    // Note: Your PHP already applies 'active-category' to the desktop cards,
    // but we're ensuring the `updateTableHeaderColor` function is called
    // to match the table header to the active status.
    updateTableHeaderColor(initialStatus);
});

function selectTab(button, status) {
    // These lines are removed as labels should always be visible:
    // document.querySelectorAll('.status-tab .tab-label').forEach(label => label.classList.add('d-none'));

    // Remove previous active styles from all tabs
    document.querySelectorAll('.status-tab').forEach(tab => {
        tab.classList.remove('active-category', 'bg-primary', 'bg-success', 'bg-warning', 'bg-secondary', 'bg-danger', 'bg-dark', 'bg-info', 'text-white', 'text-dark');
    });

    // This line is removed as labels should always be visible:
    // const label = button.querySelector('.tab-label');
    // if (label) label.classList.remove('d-none');

    applyActiveStyle(button, status);
    localStorage.setItem('selectedStatus', status);

    updateTableHeaderColor(status);

    // Redirect after a small delay to allow immediate visual update
    setTimeout(() => {
        filterByStatus(status);
    }, 50);
}

function applyActiveStyle(button, status) {
    const statusColors = {
        "New": "bg-primary",
        "In Progress": "bg-warning",
        "Processed": "bg-info"
    };

    const bgClass = statusColors[status] || "bg-secondary"; // Default to secondary if status not found.

    button.classList.add('active-category', bgClass);
    if (status === "In Progress") {
        button.classList.add('text-dark');
    } else {
        button.classList.add('text-white');
    }
}
</script>

<div id="loader" style="display: none;">Loading...</div>

<script>
if (window.top === window.self) {
    // Not inside an iframe
    window.location.href = "dashboard.php";
}
</script>