<?php
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
include 'config.php'; // Database connection
session_start(); // Start the session
$show_alert = 0;
$proposal_mode = "NEW"; // Default mode
$current_status ='';
$allow_edit = "EDIT";
$status_description="";



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Proposal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/proposal-form.css" rel="stylesheet">
    <!-- Bootstrap JS Bundle (including Popper.js) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    
</head>
<body>
    <div id="fileSizeAlert" class="alert alert-danger d-none position-fixed top-0 start-50 translate-middle-x mt-3" role="alert">
    <strong>File Size Error:</strong> <span id="fileSizeMessage"></span>
</div>

    <div class="container mt-4">
    <button type="button" class="close-fixed" onclick="window.location.href='proposal.php';">
        <i class="bi bi-x-circle"></i>
    </button>        
    <h4 class="mb-3">
    <h4 class="mb-3">
        <?php if ($proposal_mode === "OPEN") { ?>
            <span class="text-primary">
            <h4 class="mb-3">
                <i class="bi bi-pencil-square text-primary" style="font-size: 1.2rem;"></i> 
                <span class="<?php echo ($allow_edit === 'EDIT') ? 'text-success' : 'text-danger'; ?>" style="font-size: 1.2rem;">
                    <?php echo ($allow_edit === 'EDIT') ? 'Editable' : 'View Only'; ?>
                </span>  
                <span class="text-dark">ID: <em class="fw-bold"><?php echo htmlspecialchars($proposal_id); ?></em></span>  
                <span class="badge 
                    <?php echo ($allow_edit === 'EDIT') ? 'bg-success' : 'bg-danger'; ?>" 
                    style="font-size: 1rem;">
                    <?php echo htmlspecialchars($status_description); ?>
                </span>
            </h4>


            </span>
        <?php } else { ?>
            <span class="text-success">
                <i class="bi bi-plus-circle"></i> Create New Proposal
            </span>
        <?php } ?>
    </h4>
    <div>
    <form action="" method="POST" id="uploadForm" enctype="multipart/form-data">
    <input type="hidden" id="hf_proposal_id" name="hf_proposal_id" value="<?php echo htmlspecialchars($proposal_id); ?>">
    <input type="hidden" id="hf_proposal_mode" name="hf_proposal_mode" value="<?php echo htmlspecialchars($proposal_mode); ?>">
    <input type="hidden" id="hf_deleted_documents" name="hf_deleted_documents">
    <div class="<?php echo $is_disabled_class; ?>">
    <div class="row">
        <!-- Left Side: Applicant Details -->
        <div class="col-md-6">
            <h5>Applicant Details</h5>
            <div class="form-group">
                <label>Agent</label>
                
                <select class="form-control" name="agent_request_number" id="agent_request_number"
                    <?php 
                        $is_disabled = ($_SESSION['user_role'] === "user" || $_SESSION['user_role'] === "sales");
                        echo $is_disabled ? 'disabled' : ''; 
                    ?>
                    onchange="syncHiddenField(this)">
                    <option value="">-- Select Agent --</option>
                    <?php foreach ($agent_users as $user) { ?>
                        <option value="<?php echo $user['id']; ?>" 
                            <?php 
                                if ($proposal_mode === "OPEN" && $selected_proposal_details['ar_user_id'] == $user['id']) {
                                    echo 'selected';
                                }
                                elseif ($proposal_mode === "NEW" && $_SESSION['user_id'] == $user['id']) {
                                    echo 'selected';
                                }
                            ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php } ?>
                </select>

                <!-- Hidden field is only added if the dropdown is disabled -->
                <?php if ($is_disabled) { ?>
                    <input type="hidden" name="agent_request_number" id="hidden_agent_request_number" 
                        value="<?php echo $selected_proposal_details['ar_user_id'] ?? $_SESSION['user_id']; ?>">
                <?php } ?>
            </div>
            <div class="form-group">
                <label>Borrower Name</label>
                <input type="text" class="form-control" name="borrower_name" placeholder="Borrower name" value="<?php echo htmlspecialchars($selected_proposal_details['borrower_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>Initials</label>
                <input type="text" class="form-control" name="initials" placeholder="Only alphabets, space, or periods" value="<?php echo htmlspecialchars($selected_proposal_details['initials'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>Mobile Number</label>
                <input type="text" class="form-control" name="mobile_number" placeholder="10-digit number" required value="<?php echo htmlspecialchars($selected_proposal_details['mobile_number'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Email ID</label>
                <input type="email" class="form-control" name="email_id" placeholder="Borrower’s email id" required value="<?php echo htmlspecialchars($selected_proposal_details['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>City</label>
                <input type="text" class="form-control" name="city" placeholder="Borrower’s city" required value="<?php echo htmlspecialchars($selected_proposal_details['city'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Vehicle Name</label>
                <input type="text" class="form-control" name="vehicle_name" placeholder="Name of the vehicle" required value="<?php echo htmlspecialchars($selected_proposal_details['vehicle_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Model</label>
                <input type="text" class="form-control" name="vehicle_model" placeholder="Manufacturing year" required value="<?php echo htmlspecialchars($selected_proposal_details['model'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Loan Amount</label>
                <input type="text" class="form-control" name="loan_amount" placeholder="Requested loan amount" required value="<?php echo htmlspecialchars($selected_proposal_details['loan_amount'] ?? ''); ?>">
            </div>
            
            <h5>Co-Applicant Details</h5>
            <div class="form-group">
                <label>Name</label>
                <input type="text" class="form-control" name="coapplicant_name" placeholder="Co-applicant Name" value="<?php echo htmlspecialchars($selected_proposal_details['co_applicant_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Mobile Number</label>
                <input type="text" class="form-control" name="coapplicant_mobile" placeholder="Co-applicant contact number" value="<?php echo htmlspecialchars($selected_proposal_details['co_applicant_mobile'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Relationship</label>
                <select class="form-control" name="coapplicant_relationship">
                    <option value="">-- Relationship with borrower --</option>
                    <option value="spouse" <?php echo (isset($selected_proposal_details['co_applicant_relationship']) && $selected_proposal_details['co_applicant_relationship'] == 'spouse') ? 'selected' : ''; ?>>Spouse</option>
                    <option value="parent" <?php echo (isset($selected_proposal_details['co_applicant_relationship']) && $selected_proposal_details['co_applicant_relationship'] == 'parent') ? 'selected' : ''; ?>>Parent</option>
                    <option value="sibling" <?php echo (isset($selected_proposal_details['co_applicant_relationship']) && $selected_proposal_details['co_applicant_relationship'] == 'sibling') ? 'selected' : ''; ?>>Sibling</option>
                </select>
            </div>
        </div>

        
        <!-- Right Side: Comments Details -->
        <div class="col-md-6">
            <!-- Comments Section -->
            <h5 class="mt-4">Comments</h5>
            <div class="comments-box">
                <!-- Scrollable Comments List -->
                <div class="comments-list">
                    <?php if (!empty($proposal_comments)) { ?>
                        <?php foreach ($proposal_comments as $comment) { ?>
                            <div class="comment-entry">
                                <span class="comment-icon comment-user"></span>
                                <div class="comment-content">
                                    <small>
                                        <span><strong><?php echo htmlspecialchars($comment['user']); ?></strong></span>
                                        <span class="comment-time"><?php echo date("Y-m-d h:i A", strtotime($comment['created_at'])); ?></span>
                                    </small>
                                    <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <p>No comments yet.</p>
                    <?php } ?>
                </div>

                <!-- Fixed New Comment Input -->
                <div class="new-comment">
                    <label class="comment-label">New Comment</label>
                    <textarea class="form-control" name="comments" placeholder="Enter your comment"></textarea>
                </div>
            </div>
        </div>

    </div>
    </div>
    <!-- Upload Documents Section -->
    <div class="mt-4" style="padding-bottom:150px;" >
        <h5>Upload Documents</h5>
        <div class="row">
            <div id="documentCategories"></div>
        </div>
    </div>

   <!-- Submit Button -->
    <div class="mt-4 text-end" style="position: fixed; z-index:999; bottom:0px; right:0px; background-color:#fff; width:100%;">
        <!-- Status Dropdown -->
        <select id="statusDropdown" name="proposal_status" class="form-select d-inline-block w-auto me-2 <?php echo $is_disabled_class; ?>" onchange="toggleSubmitButton()">
            <option value="" disabled selected>-- Select Status --</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= htmlspecialchars($status['status_id']) ?>"><?= htmlspecialchars($status['status_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Submit Button -->
        <button type="submit" id="submitBtn" style="top:10px; position:relative" onclick="setAction()" class="btn btn-success <?php echo $is_disabled_class; ?>" >
            <i class="fas fa-paper-plane"></i> Submit Proposal
        </button>

        <input type="hidden" name="action" id="actionField">
        <input type="hidden" name="alert_flag" id="alert_flag">
        <input type="hidden" name="alert_message" id="alert_message">
    </div>


</form>
    </div>
    </div>
    <!-- Modal to display enlarged content -->
    <div id="previewModal" style="display:none;z-index:9999">
        <div id="modalContent">
            <span id="closeModal" style="cursor: pointer; font-size: 20px; color: red;">&times;</span>
            <div id="modalPreviewContainer"></div>
        </div>
    </div>
</body>
<div id="loader" style="
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    padding: 20px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    border-radius: 10px;
    text-align: center;
">Uploading...</div>


<script>
const user_role = "<?php echo $_SESSION['user_role']; ?>"; // Get user role from PHP

let documentCategories = [
        <?php
        foreach ($document_category as $doc) {
            if ($doc['isadditional'] == 0) { // Check if isadditional is 0
                echo "{ id: '{$doc['id']}', name: '{$doc['category']}', class: 'card-sales' },";
            }
        }
        ?>
    ];


    // If user_role is not 'sales', add additional documents
    if (user_role.toLowerCase() !== 'sales') {
        const additionalDocuments = [
            <?php
            foreach ($document_category as $doc) {
                if ($doc['isadditional'] == 1) { // Include only additional documents
                    echo "{ id: '{$doc['id']}', name: '{$doc['category']}', class: 'card-other' },";
                }
            }
            ?>
        ];

        documentCategories = documentCategories.concat(additionalDocuments);
    }
const filesToUpload = {}; // Store newly added files
const proposal_documents2 = { 
    "1": ["uploads/aadhar1.png", "uploads/aadhar2.pdf"], 
    "2": ["uploads/license1.jpg"], 
    "3": ["uploads/voter1.pdf"]
}; // Preloaded existing documents
 
const proposal_documents = <?php echo $proposal_documents_json ?: '[]'; ?>; // Ensure it's an array

const categorizedDocuments = {};

if (Array.isArray(proposal_documents)) { // Check if proposal_documents is a valid array
    proposal_documents.forEach(doc => {
        const categoryId = doc.category_id;
        
        if (!categorizedDocuments[categoryId]) {
            categorizedDocuments[categoryId] = [];
        }
        
        categorizedDocuments[categoryId].push(doc);
    });
}


const uploadForm = document.getElementById('uploadForm');
const uploadBtn = document.getElementById('uploadBtn');
const documentCategoriesContainer = document.getElementById('documentCategories');

// Function to create a category card with file input and preview section
function createCategoryCard(category) {
    const card = document.createElement('div');
    card.classList.add('category-card');
    
    card.id = category.id;

    const cardHeaderDiv = document.createElement('div');
    card.appendChild(cardHeaderDiv);

    const title = document.createElement('h3');
    title.classList.add(category.class);
    title.textContent = category.name;
    cardHeaderDiv.appendChild(title);

    const pasteButton = document.createElement('span');
    pasteButton.innerHTML = '<i class="fas fa-paste"></i> Paste Image'; // FontAwesome Icon
    pasteButton.classList.add('category-card-paste-button');
    var isDisabledClass = "<?php echo $is_disabled_class; ?>"; // Get PHP class
    if (isDisabledClass) {
        pasteButton.classList.add(isDisabledClass); // Add the PHP class dynamically
        }
    pasteButton.onclick = function () {
        triggerPaste(category.id);
    };
    cardHeaderDiv.appendChild(pasteButton);

    const cardContainer = document.createElement('div');
    card.appendChild(cardContainer);
    cardContainer.classList.add('category-card-container');

    isDisabledClass = "<?php echo $is_disabled_class; ?>"; // Get PHP class

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*,application/pdf';
        fileInput.multiple = true;

        if (isDisabledClass) {
            fileInput.classList.add(isDisabledClass); // Add PHP class dynamically
        }

        // Optional: Disable the input if the class represents a disabled state
        if (isDisabledClass === 'disabled') {
            fileInput.setAttribute('disabled', 'disabled');
        }
    
    fileInput.addEventListener('change', function () {
        handleFileSelect(category.id, fileInput);
    });
    cardContainer.appendChild(fileInput);

    const previewContainer = document.createElement('div');
    previewContainer.classList.add('preview-container');
    previewContainer.id = `${category.id}-preview`;
    card.appendChild(previewContainer);

    documentCategoriesContainer.appendChild(card);

    // Load existing documents if available
    if (categorizedDocuments[category.id]) {
        loadExistingDocuments(category.id, categorizedDocuments[category.id]);
    }
}

// Function to load existing documents
function loadExistingDocuments(categoryId, documents) {
    const previewContainer = document.getElementById(`${categoryId}-preview`);

    if (!filesToUpload[categoryId]) {
        filesToUpload[categoryId] = [];
    }

    documents.forEach(doc => {
        const fileDiv = document.createElement('div');
        fileDiv.classList.add('file-preview');

        let preview;
        if (doc.document_type === 'image') {
            preview = document.createElement('img');
            preview.src = doc.file_path; // Use file_path from docUrl
            preview.alt = 'Uploaded Image';
            preview.onclick = function () {
                openModal(preview.src, 'image');
            };
        } else if (doc.document_type === 'pdf') {
            preview = document.createElement('div');
            preview.classList.add('pdf-icon');
            preview.innerHTML = '<i class="fas fa-file-pdf"></i> View PDF';
            preview.onclick = function () {
                window.open(doc.file_path, '_blank');
            };
        }

        fileDiv.appendChild(preview);

        // Remove Button
        const removeBtn = document.createElement('div');
        removeBtn.classList.add('file-remove');
        removeBtn.innerHTML = '<i class="fas fa-trash"></i> Remove';
        const isDisabledClass = "<?php echo $is_disabled_class; ?>"; // Get PHP class
        if (isDisabledClass) {
                removeBtn.classList.add(isDisabledClass); // Add the PHP class dynamically
            }
        removeBtn.onclick = function () {
            let hfDeletedDocuments = document.getElementById("hf_deleted_documents");
            hfDeletedDocuments.value = doc.file_path + ",";
            fileDiv.remove();
        };
        fileDiv.appendChild(removeBtn);

        previewContainer.appendChild(fileDiv);
        filesToUpload[categoryId].push(doc);
    });

    
}


// Function to handle file selection and display previews
const maxFileSize = <?php echo $maxFileSize; ?>; // Get from PHP

function handleFileSelect(categoryId, input) {
    const files = input.files;
    const previewContainer = document.getElementById(`${categoryId}-preview`);

    if (!filesToUpload[categoryId]) {
        filesToUpload[categoryId] = [];
    }

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const fileDiv = document.createElement('div');
        fileDiv.classList.add('file-preview');

        let preview;
        if (file.type.startsWith('image/')) {
            preview = document.createElement('img');
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
            };
            preview.onclick = function () {
                openModal(preview.src, 'image');
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            preview = document.createElement('div');
            preview.classList.add('pdf-icon');
            preview.innerHTML = '<i class="fas fa-file-pdf"></i>';
            preview.onclick = function () {
                openModal(URL.createObjectURL(file), 'pdf');
            };
        }

        fileDiv.appendChild(preview);

        const removeBtn = document.createElement('div');
        removeBtn.classList.add('file-remove');
        removeBtn.innerHTML = '<i class="fas fa-trash"></i> Remove';
        removeBtn.onclick = function () {
            let hfDeletedDocuments = document.getElementById("hf_deleted_documents");
            hfDeletedDocuments.value = doc.file_path + ",";
            fileDiv.remove();
            const index = filesToUpload[categoryId].indexOf(file);
            if (index > -1) filesToUpload[categoryId].splice(index, 1);
        };
        fileDiv.appendChild(removeBtn);

        previewContainer.appendChild(fileDiv);
        filesToUpload[categoryId].push(file);
    }

    
}
// **Function to Show Bootstrap Alert and Auto-Hide After 5 Seconds**
function showAlert(message) {
    const alertBox = document.getElementById("fileSizeAlert");
    const alertMessage = document.getElementById("fileSizeMessage");

    alertMessage.innerText = message;
    alertBox.classList.remove("d-none");

    setTimeout(() => {
        alertBox.classList.add("d-none");
    }, 5000); // Auto-hide after 5 seconds
}

// Function to handle paste events for images
async function triggerPaste(categoryId) {
    try {
        const permission = await navigator.permissions.query({ name: "clipboard-read" });
        if (permission.state === "denied") {
            console.error("Clipboard access denied. Please allow permissions.");
            return;
        }

        const clipboardItems = await navigator.clipboard.read();
        const previewContainer = document.getElementById(`${categoryId}-preview`);

        if (!filesToUpload[categoryId]) {
            filesToUpload[categoryId] = [];
        }

        for (const item of clipboardItems) {
            for (const type of item.types) {
                if (type.startsWith("image/")) {
                    const blob = await item.getType(type);
                    const file = new File([blob], `pasted-image-${Date.now()}.png`, { type });

                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const img = document.createElement("img");
                        img.src = e.target.result;
                        img.style.maxWidth = "200px";

                        const fileDiv = document.createElement("div");
                        fileDiv.classList.add("file-preview");
                        fileDiv.appendChild(img);

                        const removeBtn = document.createElement("div");
                        removeBtn.classList.add("file-remove");
                        removeBtn.innerHTML = '<i class="fas fa-trash"></i> Remove';
                        removeBtn.onclick = function () {
                            let hfDeletedDocuments = document.getElementById("hf_deleted_documents");
                            hfDeletedDocuments.value = doc.file_path + ",";
                            fileDiv.remove();
                            const index = filesToUpload[categoryId].indexOf(file);
                            if (index > -1) filesToUpload[categoryId].splice(index, 1);
                        };
                        fileDiv.appendChild(removeBtn);

                        previewContainer.appendChild(fileDiv);
                        filesToUpload[categoryId].push(file);
                        
                    };
                    reader.readAsDataURL(file);
                }
            }
        }
    } catch (err) {
        console.error("Failed to read clipboard contents:", err);
    }
}

// Function to check if the upload button should be enabled




// Initialize category cards
documentCategories.forEach(category => {
    createCategoryCard(category);
});

        // Submit the form with all files
        uploadForm.addEventListener('submit', function(event) {
            
            event.preventDefault(); // Prevent the default form submission
            document.getElementById('loader').style.display = 'block'; // Show loader

            const formData = new FormData(uploadForm); // Capture all form fields
            
            // Get the clicked button value
            const submitButton = document.activeElement; // Get the button that triggered the event
            const buttonValue = submitButton ? encodeURIComponent(submitButton.value) : '';

            // Add all files from all categories to FormData
            for (const category in filesToUpload) {
                filesToUpload[category].forEach(file => {
                    //formData.append('files[]', file);
                    formData.append(`files[${category}][]`, file); // Include category in the key
                });
            }

            // Perform the AJAX request to submit the form data
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);

            xhr.onload = function() {
                document.getElementById('loader').style.display = 'none'; // Hide loader
                if (xhr.status === 200) {
                    document.getElementById('alert_flag').value = '1';
                    document.getElementById('alert_message').value = 'Saved Successfully';

                    location.href="proposal.php?status_code=1024&submit_type=${buttonValue}";
                } else {
                    alert('Error uploading files!');
                }
            };
            xhr.onerror = function() {
                document.getElementById('loader').style.display = 'none'; // Hide loader on error
                alert('Network error! Please try again.');
            };
            xhr.send(formData); // Send all files via AJAX
        });


document.querySelector('.close-fixed').addEventListener('click', function () {
        window.location.href = 'proposal.php';
    });
   

// Function to open the modal with the enlarged content
function openModal(contentUrl, type) {
    const modal = document.getElementById('previewModal');
    const modalPreviewContainer = document.getElementById('modalPreviewContainer');
    modal.style.display = 'flex';

    // Clear any existing content in the modal
    modalPreviewContainer.innerHTML = '';

    if (type === 'image') {
        const img = document.createElement('img');
        img.src = contentUrl;
        modalPreviewContainer.appendChild(img);
    } else if (type === 'pdf') {
        const pdfViewer = document.createElement('iframe');
        pdfViewer.src = contentUrl;
        pdfViewer.style.width = '100%';
        pdfViewer.style.height = '500px'; // Adjust height as needed
        modalPreviewContainer.appendChild(pdfViewer);
    }
}

// Close the modal when the close button is clicked
document.getElementById('closeModal').onclick = function() {
    const modal = document.getElementById('previewModal');
    modal.style.display = 'none';
};

function setAction(value) {
        document.getElementById('actionField').value = value; // Set hidden input value
}
function syncHiddenField(selectElement) {
        let hiddenField = document.getElementById('hidden_agent_request_number');
        if (hiddenField) {
            hiddenField.value = selectElement.value;
        }
    }
    
    function toggleSubmitButton() {
    var statusDropdown = document.getElementById('statusDropdown');
    var submitBtn = document.getElementById('submitBtn');
    
    // Check if a valid status is selected (not the default "-- Select Status --")
    if (statusDropdown.value) {
        // Enable the submit button if a status is selected
        submitBtn.disabled = false;
    } else {
        // Keep the submit button disabled if no status is selected
        submitBtn.disabled = true;
    }
}
</script>
<style>
    .disabled-div {
        pointer-events: none;  /* Prevents clicks */
        opacity: 0.6;          /* Makes it look disabled */
        background: #f8f9fa;   /* Light grey background */
    }
</style>