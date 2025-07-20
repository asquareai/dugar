<?php
session_start();

// Check if the user is an approver
if ($_SESSION['user_role'] !== 'approver') {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'config.php';

// Get proposal ID from URL
$proposal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($proposal_id === 0) {
    echo "Invalid Proposal ID";
    exit;
}
// Ensure you have a database connection established ($conn)

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capture form values
    $proposal_id = $_POST['proposal_id'];
    $status = $_POST['status'];
    //$reject_reason = isset($_POST['reject_reason']) ? $_POST['reject_reason'] : null;
    $category = $_POST['category'];
    $comments = isset($_POST['comments']) ? $_POST['comments'] : null;
    $show_to_client = isset($_POST['show_to_client']) ? 1 : 0; // Checkbox value (0 or 1)
    $user_id = $_SESSION['user_id']; // Assuming user_id is stored in session (adjust as needed)
    $approved_amount = $_POST['approved_amount'];
    $approver_comments = $_POST['comments'];
    $approver_closed_comments = $_POST['closed_comments'];
    // Step 1: Update the `proposals` table with status, reason, and category
    $sql_update_proposal = "UPDATE proposals SET 
        status = '$status', 
        reject_reason_text = '', 
        category = '$category',
        approved_on = now(),
        show_to_client = '$show_to_client',
        approved_amount = '$approved_amount',
        approver_comments ='$approver_comments',
        approver_closed_comments ='$approver_closed_comments'

        WHERE proposal_id = '$proposal_id'";
    file_put_contents('log.txt', "Approve Proposal update " . $sql_update_proposal . "\n", FILE_APPEND);
    if (mysqli_query($conn, $sql_update_proposal)) {
        // Step 2: Insert a new comment in the `proposal_comments` table if there is a comment
        if ($comments) {
            $created_at = date('Y-m-d H:i:s');
            $sql_insert_comment = "INSERT INTO proposal_comments 
                (proposal_id, user_id, comment, created_at, show_to_client) 
                VALUES 
                ('$proposal_id', '$user_id', '$comments', '$created_at', '$show_to_client')";

            if (mysqli_query($conn, $sql_insert_comment)) {
                // Comment inserted successfully
                echo "Proposal and comment updated successfully! <a href='proposal.php'>Go Back</a>";
                header("Location: proposal.php");
                exit(); // Ensure no further code is executed after the redirect
            } else {
                // Handle comment insert error
                echo "Error inserting comment: " . mysqli_error($conn);
            }
        } else {
            // Redirect to proposal.php after success
            header("Location: proposal.php");
            exit(); // Ensure no further code is executed after the redirect
        }
    } else {
        // Handle proposal update error
        echo "Error updating proposal: " . mysqli_error($conn);
    }
}
// Fetch proposal details
$query = "SELECT 
    p.id, 
    u.full_name AS 'User', 
    COALESCE(u3.full_name, 'No Agent') AS 'Agent Name',
    p.created_by AS 'Created By', 
    p.borrower_name AS 'Borrower Name', 
    p.city AS 'City', 
    CONCAT(p.vehicle_name, ' - ', p.model,'  Kms ', p.kilometers_driven) AS 'Vehicle', 
    p.loan_amount AS 'Loan Amount', 
    p.approved_amount AS 'Approved Amount',
    ps.status_name AS 'Status',
    COALESCE(u2.full_name, 'NOT ALLOCATED') AS 'Allocated To',
    ps.description, 
    p.userComment,
    p.agentComment,
    p.approver_comments,
    p.approver_closed_comments,
    p.status status_id,
    p.referred_by,
    p.product_type,
    CONCAT(p.co_applicant_name, ' - ', co_applicant_relationship,' - ', p.co_applicant_mobile) AS 'CoApplicant'
FROM proposals p 
INNER JOIN users u ON p.created_by = u.id
INNER JOIN proposal_status_master ps ON p.status = ps.status_id
LEFT JOIN users u3 ON p.ar_user_id = u3.id 
LEFT JOIN users u2 ON p.allocated_to_user_id = u2.id

where p.id = '$proposal_id'";
$result = mysqli_query($conn, $query);
$proposal = mysqli_fetch_assoc($result);

if (!$proposal) {
    echo "Proposal not found!";
    exit;
}

$status_description = htmlspecialchars($proposal["description"]);

$comments_query = "select *, u.full_name user from proposal_comments c join users u on c.user_id = u.id where c.proposal_id='$proposal_id'";
$comments_result = mysqli_query($conn, $comments_query);
$proposal_comments = mysqli_fetch_all($comments_result, MYSQLI_ASSOC);

$query = "select status_id, status_name  from proposal_status_master where status_id in(9, 10, 11, 12, 13);";
$approve_status = mysqli_query($conn, $query);

// $query = "select status_id, status_name  from proposal_status_master where status_id in(9, 10, 11, 12);";
// $approve_status = mysqli_query($conn, $query);

$query = "SELECT p.id, document_type, file_path, c.category FROM proposal_documents p join document_category_master c on p.category_id = c.id where proposal_id = '$proposal_id' order by category_id, id;";
$result  = mysqli_query($conn, $query);
$proposal_documents = [];
while ($row = mysqli_fetch_assoc($result)) {
    $proposal_documents[] = $row; // Convert result to an array
}

// Encode properly as JSON
echo "<script>const proposalDocuments = " . json_encode($proposal_documents) . ";</script>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal Approval</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/proposal-form.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-size: 14px; }
        .card { padding: 10px; }
        .border { padding: 5px; }
        .chat-container { max-height: 250px; overflow-y: auto; background: #f9f9f9; border-radius: 5px; padding: 8px; }
        .chat-message { padding: 6px; border-radius: 5px; margin-bottom: 5px; max-width: 80%; }
        .left-message {
            background: #e0e0e0;
            text-align: left;
            float: left;
            clear: both;
        }
        .right-message {
            background: #007bff;
            color: white;
            text-align: right;
            float: right;
            clear: both;
        }
        .chat-timestamp {
            font-size: 0.8rem;
            color: #666;
            display: block;
            margin-top: 4px;
        }
        .disabled-comments
        {
            width:100%;
            padding:10px;
            background-color:rgba(150,150,150,.1);
            border:1px solid #d7d7d7;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="card shadow-lg">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Proposal Approval -  ID <?php echo htmlspecialchars($proposal_id); ?></h5>
            <span class="badge bg-success" style="font-size: 1rem;"><?php echo $status_description; ?></span>
        </div>
        <div class="card-body" style="padding:10px!important;">
           <!-- Proposal Details -->
            <div class="container" style="font-size: 0.75rem;">
                <div class="row">
                    <div class="col-12">
                        <div class="row g-3">
                            <!-- Agent Name -->
                            <div class="col-md-3 d-flex align-items-center">
                                <label class="me-2 text-muted text-end w-50">Agent:</label>
                                <div class="fw-bold w-50"><?php echo htmlspecialchars($proposal['Agent Name']); ?></div>
                            </div>
                            <!-- Agent Name -->
                            <div class="col-md-3 d-flex align-items-center">
                                <label class="me-2 text-muted text-end w-50">Referral:</label>
                                <div class="fw-bold w-50"><?php echo htmlspecialchars($proposal['referred_by']); ?></div>
                            </div>

                            <!-- Borrower Name -->
                            <div class="col-md-3 d-flex align-items-center">
                                <label class="me-2 text-muted text-end w-50">Borrower:</label>
                                <div class="fw-bold w-50"><?php echo htmlspecialchars($proposal['Borrower Name']); ?></div>
                            </div>

                            <!-- City -->
                            <div class="col-md-3 d-flex align-items-center">
                                <label class="me-2 text-muted text-end w-50">City:</label>
                                <div class="fw-bold w-50"><?php echo htmlspecialchars($proposal['City']); ?></div>
                            </div>
                            <div class="col-md-3 d-flex align-items-center">
                                <label class="me-2 text-muted text-end w-50">Product:</label>
                                <div class="fw-bold w-50"><?php echo htmlspecialchars($proposal['product_type']); ?></div>
                            </div>
                            <!-- Vehicle -->
                            <div class="col-md-3 d-flex align-items-center">
                                <label class="me-2 text-muted text-end w-50">Vehicle:</label>
                                <div class="fw-bold w-50"><?php echo htmlspecialchars($proposal['Vehicle']); ?></div>
                            </div>

                            <!-- Loan Amount -->
                            <div class="col-md-3 d-flex align-items-center">
                                <label class="me-2 text-muted text-end w-50">Expected:</label>
                                <div class="fw-bold text-success w-50">₹<?php echo number_format($proposal['Loan Amount'], 2); ?></div>
                            </div>

                            <!-- Allocated To -->
                            <div class="col-md-3 d-flex align-items-center">
                                <label class="me-2 text-muted text-end w-50">Co-Applicant:</label>
                                <div class="fw-bold w-50"><?php echo htmlspecialchars($proposal['CoApplicant']); ?></div>
                            </div>

                            <!-- Add more fields below... -->
                        </div>
                    </div>
                </div>
            </div>


            <form method="POST" action="process_approval.php">
                <input type="hidden" name="proposal_id" value="<?php echo $proposal_id; ?>">

                <!-- Approval Action -->
                <div class="container mt-4" style="padding:10px!important;margin:0px!important">
                    <div class="row g-3">
                   <!-- Approval Status -->
                    <div class="col-md-4">
                        <label for="approval_status" class="form-label fw-bold">Approval Action</label>
                       <select class="form-select" id="approval_status" name="status" required onchange="toggleReasonField()">
                            <option value="">-- Select Action -- </option>
                            <?php
                            $currentStatusId = $proposal['status_id'] ?? '';
                            foreach ($approve_status as $status) {
                                $selected = ($status['status_id'] == $currentStatusId) ? 'selected' : '';
                                echo "<option value='{$status['status_id']}' $selected>{$status['status_name']}</option>";
                            }
                            ?>
                        </select>

                    </div>


                    <!-- <div class="col-md-4 d-none" id="reason_section" >
                        <label for="reject_reason" class="form-label fw-bold">Reason for Rejection</label>
                        <input type="text" class="form-control" id="reject_reason" name="reject_reason" placeholder="Enter reason for rejection">
                    </div> -->


                        <!-- Category Selection -->
                      <div class="col-md-8 d-none" id="approved_fields_section">
                        <div class="d-flex flex-wrap align-items-end">
                            <div class="flex-grow-1 me-3">
                                <label for="approved_amount" class="form-label fw-bold">Approved Amount</label>
                                <input type="number" step="0.01" class="form-control" id="approved_amount" name="approved_amount"
                                    placeholder="Enter approved amount" value="<?php echo htmlspecialchars($proposal['Approved Amount'] ?? ''); ?>">
                            </div>
                            <div class="flex-grow-1">
                                <label for="category" class="form-label fw-bold">Select Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">-- Select Category -- </option>
                                    <option value="A" <?php echo (($proposal['category'] ?? '') == 'A') ? 'selected' : ''; ?>>Category A</option>
                                    <option value="B" <?php echo (($proposal['category'] ?? '') == 'B') ? 'selected' : ''; ?>>Category B</option>
                                    <option value="C" <?php echo (($proposal['category'] ?? '') == 'C') ? 'selected' : ''; ?>>Category C</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
                <!-- <div class="container mt-4">
                    <div class="row ">
                        <div class="col-auto">
                            <a href="#" class="btn btn-secondary  btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#documentModal" style="width: 200px;">
                                <i class="fas fa-file-alt"></i> View Documents
                            </a>
                        </div>
                    </div>
                </div> -->
                 <div class="container mt-5">
                    <!-- <h1>Documents for Proposal #<?php echo htmlspecialchars($proposal_id); ?></h1> -->

                    <div class="row mt-4">
                        <div class="col-12">
                            <h4>Attached Documents:</h4>
                            <div class="d-flex flex-wrap gap-3">
                                <?php if (!empty($proposal_documents)): ?>
                                    <?php foreach ($proposal_documents as $document): ?>
                                        <?php
                                        $fileExtension = pathinfo($document['file_path'], PATHINFO_EXTENSION);
                                        $filename = basename($document['file_path']); // e.g., "image_6af876.png"
                                        $filenameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME); // e.g., "image_6af876"
                                        ?>
                                        <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" class="document-thumbnail d-block text-center border p-2 rounded bg-light" style="width: 120px; height: 120px; overflow: hidden; text-decoration: none; color: inherit;">
                                            <?php if (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                <img src="<?php echo htmlspecialchars($document['file_path']); ?>" alt="<?php echo htmlspecialchars($filenameWithoutExtension); ?>" class="img-fluid" style="max-height: 100%;">
                                            <?php elseif (strtolower($fileExtension) == 'pdf'): ?>
                                                <i class="fas fa-file-pdf fa-5x text-danger mt-1"></i>
                                                <div class="mt-1 small fw-bold text-truncate" title="<?php echo htmlspecialchars($filenameWithoutExtension); ?>"><?php echo htmlspecialchars($filenameWithoutExtension); ?></div>
                                            <?php else: ?>
                                                <i class="fas fa-file fa-5x text-secondary mt-1"></i>
                                                <div class="mt-1 small fw-bold text-truncate" title="<?php echo htmlspecialchars($filenameWithoutExtension); ?>"><?php echo htmlspecialchars($filenameWithoutExtension); ?></div>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No documents available for this proposal.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="container mt-4">
                    <h5 class="mb-3">Comments</h5>

                    <!-- Comments Box -->
                    <div class="comments-box border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto;display:none">
                        <?php if (!empty($proposal_comments)) { ?>
                            <?php foreach ($proposal_comments as $comment) { ?>
                                <div class="d-flex align-items-start mb-3">
                                    <!-- User Avatar/Icon -->
                                    <div class="me-2">
                                        <span class="badge bg-primary rounded-circle p-2">
                                            <?php echo strtoupper(substr($comment['user'], 0, 1)); ?>
                                        </span>
                                    </div>

                                    <!-- Comment Content -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo htmlspecialchars($comment['user']); ?></strong>
                                            <small class="text-muted">
                                                <?php echo date("Y-m-d h:i A", strtotime($comment['created_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="m-0 p-2 bg-white rounded border">
                                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <p class="text-muted">No comments yet.</p>
                        <?php } ?>
                    </div>

                    <!-- New Comment Input -->
                    <div class="mt-3">
                        <label class="form-label fw-bold">User Comment</label>
                       <div id="div_user_Comment" class="disabled-comments">
                            <?php echo empty($proposal['userComment']) ? 'No comments available' : htmlspecialchars($proposal['userComment']); ?>
                       </div>

                        <label class="form-label fw-bold">Agent Comment</label>
                       <div id="div_user_Comment" class="disabled-comments">
                            <?php echo empty($proposal['agentComment']) ? 'No comments available' : htmlspecialchars($proposal['agentComment']); ?>
                        </div>


                        <label class="form-label fw-bold">Approver Commens</label>
                        <textarea class="form-control" name="comments" rows="3" placeholder="Enter your comment"><?php echo htmlspecialchars($proposal['approver_comments'] ?? ''); ?></textarea>
                        <label class="form-label fw-bold">Approver Comments Closed</label>
                        <textarea class="form-control" name="closed_comments" rows="3" placeholder="Enter your closed comment"><?php echo htmlspecialchars($proposal['approver_closed_comments'] ?? ''); ?></textarea>
                        <!-- Show to Client Checkbox -->
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="show_to_client" name="show_to_client">
                            <label class="form-check-label" for="show_to_client">Show closed comments to Agent</label>
                        </div>
                    </div>
                </div>
                <!-- Submit Button -->
                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Submit</button>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen"> <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentModalLabel">Document Viewer</h5> <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 d-flex justify-content-center align-items-center"> <div id="documentViewerContent" style="width: 100%; height: 100%; overflow: auto;">
                    </div>
            </div>
            </div>
    </div>
</div>

<div class="modal fade" id="fileModal" tabindex="-1" aria-labelledby="fileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">File Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">

        <!-- Toolbar -->
        <div class="d-flex justify-content-end mb-3">
          <button class="btn btn-sm btn-outline-secondary me-2" onclick="zoomIn()">🔍+</button>
          <button class="btn btn-sm btn-outline-secondary me-2" onclick="zoomOut()">🔍−</button>
          <button class="btn btn-sm btn-outline-secondary" onclick="rotate()">🔄</button>
        </div>

        <!-- Centered Viewer with auto-resizing -->
        <div id="viewerWrapper" style="
          display: flex;
          justify-content: center;
          align-items: center;
          background: #f8f9fa;
          border: 1px solid #ddd;
          border-radius: 0.5rem;
          padding: 10px;
          overflow: hidden;  /* No scrollbars */
        ">
          <iframe id="fileViewer" src="" style="
            transform-origin: center;
            transition: all 0.3s ease;
            border: none;
          "></iframe>
        </div>

      </div>
    </div>
  </div>
</div>



<script>

document.addEventListener("DOMContentLoaded", function () {
    // Get references to the modal elements
    const documentModalElement = document.getElementById('documentModal');
    const documentViewerContent = document.getElementById("documentViewerContent");

    // Initialize Bootstrap's Modal object (useful for showing/hiding the modal programmatically)
    const documentModal = new bootstrap.Modal(documentModalElement);

    // Data from PHP (This part remains unchanged)
    const proposalDocuments = <?php echo json_encode($proposal_documents); ?>;

    // Function to load all documents into the viewer content area
    function loadAllDocuments() {
        documentViewerContent.innerHTML = ""; // Clear any existing content first

        if (proposalDocuments.length === 0) {
            documentViewerContent.innerHTML = "<p class='text-center text-muted p-4'>No documents available.</p>";
            return;
        }

        // Iterate through each document and append its HTML representation
        proposalDocuments.forEach((doc, index) => {
            const filePath = `${doc.file_path}`; // Construct the full file path
            const fileExtension = doc.file_path.split('.').pop().toLowerCase();

            let documentHtml = ''; // HTML content for the current document
            
            // Create a wrapper div for each document. This helps in styling and ensures
            // each document occupies its own full width within the scrollable container.
            const docWrapper = document.createElement('div');
            // Adding Bootstrap classes for margin-bottom, padding, and border for visual separation
            docWrapper.classList.add('document-item', 'mb-4', 'p-3', 'border', 'rounded', 'bg-light'); 

            // Determine how to display the document based on its file extension
            if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExtension)) {
                // For image files, use an <img> tag. 'img-fluid' makes it responsive.
                documentHtml = `
                    <h6 class="text-center mb-3 text-break">${doc.document_type || 'Image Document'}</h6>
                    <img src="${filePath}" class="img-fluid d-block mx-auto" alt="${doc.document_type || 'Document View'}" style="max-width: 100%; height: auto; object-fit: contain;">
                `;
            } else if (fileExtension === 'pdf') {
                // For PDF files, use an <iframe> for embedding.
                // Set a fixed height (e.g., 800px) or a height relative to viewport (e.g., 80vh) for PDFs
                // as they typically require an explicit height to be visible.
                documentHtml = `
                    <h6 class="text-center mb-3 text-break">${doc.document_type || 'PDF Document'}</h6>
                    <iframe src="${filePath}" width="100%" height="800px" frameborder="0" style="min-height: 600px;"></iframe>
                `;
            } else {
                // For unsupported file types, provide a message and a download link
                documentHtml = `
                    <div class="text-center p-4">
                        <p class="lead">Unsupported file type: <strong>.${fileExtension}</strong></p>
                        <p>Document: ${doc.document_type || 'Unknown Document Type'}</p>
                        <p>To view this document, please download it:</p>
                        <a href="${filePath}" target="_blank" class="btn btn-primary mt-2">
                            <i class="fas fa-download"></i> Download Document
                        </a>
                    </div>
                `;
            }

            docWrapper.innerHTML = documentHtml; // Set the HTML content for the wrapper
            documentViewerContent.appendChild(docWrapper); // Append the wrapper to the viewer container
        });
    }

    // Add an event listener to the modal: when it's about to be shown, load all documents
    documentModalElement.addEventListener('show.bs.modal', function () {
        loadAllDocuments(); // This function will now populate the modal with all documents
    });

    // Removed all JavaScript related to 'Previous' and 'Next' buttons,
    // as per your request "no need the next previous button".
});

function toggleReasonField() {
    let approvalStatus = document.getElementById("approval_status").value;
    let reasonSection = document.getElementById("reason_section");
    let approvedSection = document.getElementById("approved_fields_section");

    // if (approvalStatus === "10") {
    //     reasonSection.classList.remove("d-none");
    // } else {
    //     reasonSection.classList.add("d-none");
    // }

    if (approvalStatus === "9" || approvalStatus === "12") {
        approvedSection.classList.remove("d-none");
    } else {
        approvedSection.classList.add("d-none");
    }

}

// Open document modal


let zoomLevel = 1;
let rotation = 0;
const baseWidth = 800;   // Set base size for iframe
const baseHeight = 600;

function zoomIn() {
    zoomLevel += 0.1;
    updateViewerTransform();
}

function zoomOut() {
    zoomLevel = Math.max(0.1, zoomLevel - 0.1);
    updateViewerTransform();
}

function rotate() {
    rotation = (rotation + 90) % 360;
    updateViewerTransform();
}

function updateViewerTransform() {
    const viewer = document.getElementById('fileViewer');

    // Adjust width/height based on zoom and rotation
    const isRotated = rotation % 180 !== 0;
    const width = isRotated ? baseHeight : baseWidth;
    const height = isRotated ? baseWidth : baseHeight;

    viewer.style.width = `${width * zoomLevel}px`;
    viewer.style.height = `${height * zoomLevel}px`;
    viewer.style.transform = `rotate(${rotation}deg)`;
}

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('fileModal');

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const filePath = button.getAttribute('data-filepath');
        const viewer = document.getElementById('fileViewer');

        viewer.src = filePath;

        // Reset zoom and rotation
        zoomLevel = 1;
        rotation = 0;
        updateViewerTransform();
    });

    modal.addEventListener('hidden.bs.modal', function () {
        document.getElementById('fileViewer').src = '';
    });
});


</script>
<div id="loader" style="display: none;">Loading...</div>

</body>
</html>

