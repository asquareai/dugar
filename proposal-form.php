<?php
include 'config.php'; // Database connection
session_start(); // Start the session
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

$show_alert = 0;
$maxFileSize = 2 * 1024 * 1024;
$proposal_mode = "NEW"; // Default mode
$current_status ='';
$allow_edit = "EDIT";
$status_description="";
$show_internal_comments_to_agent = '0';
$show_user_documents_to_agent = '0';
$proposal_documents_json = json_encode(null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); 
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $proposal_id = intval($_GET['id']); // Get proposal ID from query string
    if($_SESSION['user_role'] =="user")
    {
        $query = "update proposals set allocated_to_user_id = '" . $_SESSION['user_id'] . "', status=3, allocated_on=now()  WHERE id='$proposal_id' and status in(1,2)";
        $result = mysqli_query($conn, $query);
    }
    else if ($_SESSION['user_role'] == 'sales' || $_SESSION['user_role'] == 'super agent') {

            // Fetch the proposal record to verify access
            $query = "SELECT ar_user_id, status FROM proposals WHERE id = $proposal_id LIMIT 1";
            $result = mysqli_query($conn, $query);
            $proposalinfo = mysqli_fetch_assoc($result);
            $proposal_ar_user_id = $proposalinfo['ar_user_id'];

            if ($_SESSION['user_role'] == 'sales') {
                // Direct match check for sales
                if ($proposal_ar_user_id != $_SESSION['user_id']) {
                    showUnauthorizedPage();
                }
            } else {
                // super agent logic
                // Get the super agent's linked agent list from DB
                $user_id = $_SESSION['user_id'];
                $userQuery = "SELECT linked_agents FROM users WHERE id = $user_id LIMIT 1";
                $userResult = mysqli_query($conn, $userQuery);
                $userInfo = mysqli_fetch_assoc($userResult);

                if (!$userInfo || empty($userInfo['linked_agents'])) {
                    showUnauthorizedPage();
                }

                $linkedAgents = array_map('trim', explode(',', $userInfo['linked_agents']));
                if (!in_array($proposal_ar_user_id, $linkedAgents)) {
                    showUnauthorizedPage();
                }

                
            }
        }

    $proposal_mode = "OPEN"; // Change mode to EDIT
    $query = "SELECT *, s.status_name, s.description FROM proposals p join proposal_status_master s on p.status = s.status_id  WHERE id='$proposal_id'";
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $selected_proposal_details = mysqli_fetch_assoc($result); // Fetch the row as an associative array
        $current_status = $selected_proposal_details["status"];
        $current_status_text = $selected_proposal_details["status_name"];
        $status_description = $selected_proposal_details["description"];

        $show_internal_comments_to_agent = $selected_proposal_details["showUserCommentToAgent"];
        $show_user_documents_to_agent = $selected_proposal_details["showUserDocumentsToAgent"];

        if ($_SESSION["user_role"] == "sales") {
            // Add condition to the query to filter by 'show_to_client = 1'
            $query = "SELECT *, u.full_name AS user 
                      FROM proposal_comments c 
                      JOIN users u ON c.user_id = u.id 
                      WHERE c.proposal_id = '$proposal_id' 
                      AND c.show_to_client = 1";
        } else {
            // If the user is not 'sales', run the query without the 'show_to_client' condition
            $query = "SELECT *, u.full_name AS user 
                      FROM proposal_comments c 
                      JOIN users u ON c.user_id = u.id 
                      WHERE c.proposal_id = '$proposal_id'";
        }
        $proposal_comments = mysqli_query($conn, $query);
        if ($_SESSION["user_role"] == "sales")
        {
            if($show_user_documents_to_agent == 1)
                $query = "SELECT * FROM proposal_documents WHERE proposal_id='$proposal_id'";
            else
                $query = "SELECT * FROM proposal_documents WHERE proposal_id='$proposal_id' and created_by = " . $_SESSION["user_id"];
        }
        else
        {
            $query = "SELECT * FROM proposal_documents WHERE proposal_id='$proposal_id'";
        }
        $result = mysqli_query($conn, $query);

        $proposal_documents = []; // Initialize an array
        $category_ids=[];
        while ($row = mysqli_fetch_assoc($result)) {
            
            if (!empty($row['category_id']) && $row['is_locked_for_agent'] == 1) {
                $category_ids[] = $row['category_id'];
            }
            $proposal_documents[] = $row; // Fetch each row as an associative array
        }
        $proposal_documents_json = json_encode($proposal_documents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); 
        
        
        $locked_category_ids_str = implode(',', $category_ids);
        

    } else {
        echo "Error: " . mysqli_error($conn); // Debugging in case of error
    }

} else {
    $proposal_id = ""; // No ID means a new proposal
}


$allowed_statuses  = ['Draft'];
// Allowed Status Names (Set dynamically based on your business logic)
if ( $_SESSION['user_role'] === "sales")
{
    if($proposal_mode === "NEW")
        $allowed_statuses = ['Submitted for Review']; 
    else if($proposal_mode === "OPEN")
    {
        if ($current_status == 1)
        {
            $allow_edit = "EDIT";
            $allowed_statuses = ['Submitted for Review']; 
        }
        else if ($current_status == 4 || $current_status == 5 )
        {
            $allow_edit = "EDIT";
            $allowed_statuses = ['Resubmitted']; 
        }
        else
        {
            $allow_edit = "VIEW ONLY";
        }
    }
}
else if ( $_SESSION['user_role'] === "user" || $_SESSION['user_role'] === "super agent")
{
    if($proposal_mode === "OPEN")
    {
        if ($current_status != 1 && $current_status != 8 && $current_status != 9 && $current_status != 10  && $current_status != 12)
        {
            $allow_edit = "EDIT";
        }
        else
        {
            $allow_edit = "VIEW ONLY";
        }
    }
    
        $allowed_statuses = ['Submitted for Review', 'Documents Requested', 'Sent for Approval', 'Cancelled']; 
}
else if ( $_SESSION['user_role'] === "approver")
{
    
        $allow_edit = "VIEW ONLY";
    
        $allowed_statuses = ['Approved', 'Documents Requested','Rejected', 'Ask for More Details']; 
}

// Prepare a parameterized query with placeholders
$placeholders = implode(',', array_fill(0, count($allowed_statuses), '?'));

// Construct query
$query = "SELECT status_id, status_name FROM proposal_status_master WHERE status_name IN ($placeholders)";


// Prepare the statement
$stmt = $conn->prepare($query);

// Bind parameters dynamically
$types = str_repeat('s', count($allowed_statuses)); // 's' for string parameters
$stmt->bind_param($types, ...$allowed_statuses);

// $final_query = vsprintf(str_replace("?", "'%s'", $query), $allowed_statuses);

// // Print the final query
// echo "Final query: " . $final_query . "\n";

// Execute query
$stmt->execute();
$result = $stmt->get_result();

// Fetch results
$statuses = [];
while ($row = $result->fetch_assoc()) {
    $statuses[] = $row;
}


// Close statement
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $proposal_mode = $_POST['hf_proposal_mode']; // NEW or OPEN
    $proposal_id = isset($_POST['hf_proposal_id']) ? (int)$_POST['hf_proposal_id'] : null;
    $deleted_files = isset($_POST['hf_deleted_documents']) ? explode(',', $_POST['hf_deleted_documents']) : [];
    $locked_categories = isset($_POST['hfSelectedLockCategoryId']) ? explode(',', $_POST['hfSelectedLockCategoryId']) : [];

    $proposal_status = isset($_POST['proposal_status']) ? (int)$_POST['proposal_status'] : null;
    // Create a mapping for fields that have different names in the form and database
        $field_mapping = [
            'email_id' => 'email',
            'borrower_name' => 'borrower_name',
            'initials' => 'initials',
            'mobile_number' => 'mobile_number',
            'city' => 'city',
            'vehicle_name' => 'vehicle_name',
            'vehicle_model' => 'model',
            'loan_amount' => 'loan_amount',
            'coapplicant_name' => 'co_applicant_name',
            'coapplicant_mobile' => 'co_applicant_mobile',
            'coapplicant_relationship' => 'co_applicant_relationship',
            'proposal_status' => 'status',
            'userComment' =>'userComment',
            'agentCommet' =>'agentComment'

        ];
    // Sanitize inputs
    $borrower_name = mysqli_real_escape_string($conn, $_POST['borrower_name']);
    // $initials = mysqli_real_escape_string($conn, $_POST['initials']);
    $initials="";
    $mobile_number = mysqli_real_escape_string($conn, $_POST['mobile_number']);
    // $email_id = mysqli_real_escape_string($conn, $_POST['email_id']);
    $email_id = "";
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $vehicle_name = mysqli_real_escape_string($conn, $_POST['vehicle_name']);
    $model = mysqli_real_escape_string($conn, $_POST['vehicle_model']);
    $loan_amount = mysqli_real_escape_string($conn, $_POST['loan_amount']);
    $comments = mysqli_real_escape_string($conn, $_POST['comments']);
    $agent_request_number = $_POST['agent_request_number'];
    $referred_by =  mysqli_real_escape_string($conn, $_POST['referred_by']); 
    $co_name = mysqli_real_escape_string($conn, $_POST['coapplicant_name']);
    $co_mobile = mysqli_real_escape_string($conn, $_POST['coapplicant_mobile']);
    $co_relationship = mysqli_real_escape_string($conn, $_POST['coapplicant_relationship']);
    $product_type = mysqli_real_escape_string($conn, $_POST['product_type']);
    $kilometers_driven = mysqli_real_escape_string($conn, $_POST['kilometers_driven']);
    $userComment = $_POST['internal_comments'];
    $agentComment = $_POST['agent_comments'];
    $show_internal_comments_to_agent = isset($_POST['showToAgent']) ? 1 : 0;
    $show_user_documents_to_agent = isset($_POST['show_user_documents_to_agent']) ? 1 : 0;
    
    
        
    $created_by = $_SESSION['user_id']; // Assuming user is logged in
    // INSERT or UPDATE proposal
    if ($proposal_mode === "NEW") {
        if($_SESSION['user_role'] =="user")
            $allocated_to_user_id = $_SESSION['user_id'];
        else
            $allocated_to_user_id = null;
        
        $sql = "INSERT INTO proposals 
                (borrower_name, initials, mobile_number, email, city, vehicle_name, model, loan_amount,  co_applicant_name, co_applicant_mobile, co_applicant_relationship, created_by, status, allocated_to_user_id, ar_user_id, referred_by,product_type, kilometers_driven,userComment, agentComment,showUserCommentToAgent, showUserDocumentsToAgent)
                VALUES 
                ('$borrower_name', '$initials', '$mobile_number', '$email_id', '$city', '$vehicle_name', '$model', '$loan_amount','$co_name', '$co_mobile', '$co_relationship', '$created_by','$proposal_status','$allocated_to_user_id','$agent_request_number', '$referred_by', '$product_type', '$kilometers_driven', '$userComment', '$agentComment', '$show_internal_comments_to_agent', '$show_user_documents_to_agent')";
        // file_put_contents('log.txt', "Insert Proposal " . $sql . "\n", FILE_APPEND);
        if (mysqli_query($conn, $sql)) {
            $proposal_id = mysqli_insert_id($conn); // Get last inserted proposal ID
        }
    } elseif ($proposal_mode === "OPEN" && $proposal_id) {
         // Fetch the current proposal data to log old values
            $select_sql = "SELECT * FROM proposals WHERE id = '$proposal_id'";
            $result = mysqli_query($conn, $select_sql);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $existing_data = mysqli_fetch_assoc($result);
                
                
                // Step 1: Insert the header record first (to get the header ID)
                $header_sql = "INSERT INTO audit_logs_header (proposal_id, action_type, changed_by) 
                VALUES ('$proposal_id', 'UPDATE', '$created_by')";
                if (mysqli_query($conn, $header_sql)) {
                $audit_header_id = mysqli_insert_id($conn);  // Get the ID of the inserted header
                } else {
                // Handle error if header insertion fails
                die('Error inserting audit log header: ' . mysqli_error($conn));
                }

                // Step 2: Initialize an array to hold modified fields for the header
                $modified_fields = [];

                foreach ($field_mapping as $form_field => $db_field) {
                $new_value = isset($_POST[$form_field]) ? $_POST[$form_field] : ''; // Get the new value from POST

                // Compare existing value and new value
                if ($existing_data[$db_field] !== $new_value) {
                // Log the change in file (for debugging purposes)
                file_put_contents('log.txt', $existing_data[$db_field] . " - " . $new_value . "\n", FILE_APPEND);

                // Insert the change into the audit_logs_details table
                $log_sql = "INSERT INTO audit_logs_details (audit_header_id, field_name, old_value, new_value) 
                    VALUES ('$audit_header_id', '$db_field', '" . mysqli_real_escape_string($conn, $existing_data[$db_field]) . "', '" . mysqli_real_escape_string($conn, $new_value) . "')";
                mysqli_query($conn, $log_sql);

                // Add this field to the list of modified fields
                $modified_fields[] = $db_field;
                }
                }

                // Step 3: Update the header with the modified fields
                if (!empty($modified_fields)) {
                $modified_fields_str = implode(',', $modified_fields); // Convert array to comma-separated string
                $update_header_sql = "UPDATE audit_logs_header 
                        SET modified_fields = '" . mysqli_real_escape_string($conn, $modified_fields_str) . "'
                        WHERE id = '$audit_header_id'";
                mysqli_query($conn, $update_header_sql);
                }
            }
        $sql = "UPDATE proposals 
                SET borrower_name='$borrower_name', initials='$initials', mobile_number='$mobile_number',
                    email='$email_id', city='$city', vehicle_name='$vehicle_name', model='$model',
                    loan_amount='$loan_amount', co_applicant_name='$co_name', 
                    co_applicant_mobile='$co_mobile', co_applicant_relationship='$co_relationship', 
                    status='$proposal_status',
                    referred_by = '$referred_by',
                    product_type = '$product_type',
                    kilometers_driven = '$kilometers_driven',
                    userComment = '$userComment',
                    agentComment = '$agentComment',
                    showUserCommentToAgent = '$show_internal_comments_to_agent',
                    showUserDocumentsToAgent = '$show_user_documents_to_agent'

                WHERE id = '$proposal_id'";
        file_put_contents('log.txt', "Update Proposal " . $sql . "\n", FILE_APPEND);
        mysqli_query($conn, $sql);
    }

    // Delete old files if any
    if (!empty($deleted_files)) {
        foreach ($deleted_files as $file_path) {
            $file_path = trim($file_path);
            if (!empty($file_path)) {
                $delete_sql = "DELETE FROM proposal_documents WHERE proposal_id = '$proposal_id' AND file_path = '$file_path'";
                mysqli_query($conn, $delete_sql);
                if (file_exists($file_path)) {
                    unlink($file_path); // Delete the file from storage
                }
            }
        }
    }
    
    // Handle file uploads
    if (isset($_FILES['files']) && $proposal_id) {
        
        $uploadDir = "uploads/";
        foreach ($_FILES['files']['name'] as $category => $files) { 
            foreach ($files as $key => $fileName) {
                if ($_FILES['files']['error'][$category][$key] === 0) {
                    $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
                    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                    $uniqueName = $baseName . '_' . time() . '_' . uniqid() . '.' . $fileExt;
                    $uploadFile = $uploadDir . $uniqueName;

                    if (move_uploaded_file($_FILES['files']['tmp_name'][$category][$key], $uploadFile)) {
                        $document_type = ($fileExt === 'pdf') ? 'pdf' : 'image';
                        $is_locked_for_agent = in_array($category, $locked_categories) ? 1 : 0;

                        $doc_sql = "INSERT INTO proposal_documents 
                                        (proposal_id, document_type, file_path, uploaded_at, created_by, category_id, is_locked_for_agent) 
                                    VALUES 
                                        ('$proposal_id', '$document_type', '$uploadFile', NOW(), '$created_by', '$category', '$is_locked_for_agent')";

                        mysqli_query($conn, $doc_sql);


                    }
                }
            }
        }
    }
    $locked_categories = $_POST['hfSelectedLockCategoryId'];
    

    $update_sql = "UPDATE proposal_documents 
    SET is_locked_for_agent = '0' 
    WHERE proposal_id = '$proposal_id'";

    mysqli_query($conn, $update_sql);
    file_put_contents('log.txt', "Lock values $locked_categories", FILE_APPEND);
    if($locked_categories!="")
    {
        $update_sql = "UPDATE proposal_documents 
                SET is_locked_for_agent = '1' 
                WHERE proposal_id = '$proposal_id' AND category_id in ($locked_categories)";
        file_put_contents('log.txt', "Lock query $update_sql", FILE_APPEND);
        mysqli_query($conn, $update_sql);
    }

    // Save comment if any
    
    if (!empty($comments) && $proposal_id) {
        $comment_sql = "INSERT INTO proposal_comments (proposal_id, comment, created_at, user_id, show_to_client) 
                        VALUES ('$proposal_id', '$comments', NOW(), '$created_by', 1)";
        mysqli_query($conn, $comment_sql);
    }

    $_SESSION['alert_message'] = "Proposal saved successfully!";
    $_SESSION['alert_type'] = "success";
    header("Location: proposal.php");
    exit;
}


$query = "select id, full_name from users where role='sales'";
$agent_users = mysqli_query($conn, $query);

$is_disabled_class = ($allow_edit === "VIEW ONLY") ? 'disabled-div' : '';

$query = "select id, category, isadditional from document_category_master where id = 9";
$document_category = mysqli_query($conn, $query);


mysqli_close($conn);
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
    <button type="button" class="close-fixed" >
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
    <div class="row main-content-row">
        <div class="col-md-6 left-column">
            <div class="row custom-form-row">

                <div class="col-md-6"> <div class="form-group custom-form-group">
                        <label>Agent</label>
                        <select class="form-control custom-form-control" name="agent_request_number" id="agent_request_number"
                            <?php
                                $is_disabled = ($_SESSION['user_role'] === "sales");
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
                        <?php if ($is_disabled) { ?>
                            <input type="hidden" name="agent_request_number" id="hidden_agent_request_number"
                                value="<?php echo $selected_proposal_details['ar_user_id'] ?? $_SESSION['user_id']; ?>">
                        <?php } ?>
                    </div>
                </div>

                <div class="col-md-6"> <div class="form-group custom-form-group">
                        <label>Referred By</label>
                        <input type="text" class="form-control custom-form-control" name="referred_by" placeholder="Person who referred" value="<?php echo htmlspecialchars($selected_proposal_details['referred_by'] ?? ''); ?>">
                    </div>
                </div>

                <div class="col-md-6"> <div class="form-group custom-form-group">
                        <label>Borrower Name</label>
                        <input type="text" class="form-control custom-form-control" name="borrower_name" placeholder="Borrower name" value="<?php echo htmlspecialchars($selected_proposal_details['borrower_name'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="col-md-6"> <div class="form-group custom-form-group">
                        <label>Mobile Number</label>
                        <input type="text" class="form-control custom-form-control" name="mobile_number" placeholder="10-digit number" required value="<?php echo htmlspecialchars($selected_proposal_details['mobile_number'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6"> <div class="form-group custom-form-group">
                        <label>City</label>
                        <input type="text" class="form-control custom-form-control" name="city" placeholder="Borrower’s city" required value="<?php echo htmlspecialchars($selected_proposal_details['city'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group custom-form-group">
                        <label for="product_type">Product Type <span class="text-danger">*</span></label>
                        <select class="form-control custom-form-control" name="product_type" id="product_type" required>
                            <option value="">-- Select Product Type --</option>
                            <option value="vehicle_loan" <?php echo (isset($selected_proposal_details['product_type']) && $selected_proposal_details['product_type'] == 'vehicle_loan') ? 'selected' : ''; ?>>Vehicle Loan</option>
                            <option value="mortgage_loan" <?php echo (isset($selected_proposal_details['product_type']) && $selected_proposal_details['product_type'] == 'mortgage_loan') ? 'selected' : ''; ?>>Mortgage Loan</option>
                            <option value="personal_loan" <?php echo (isset($selected_proposal_details['product_type']) && $selected_proposal_details['product_type'] == 'personal_loan') ? 'selected' : ''; ?>>Personal Loan</option>
                            <option value="business_loan" <?php echo (isset($selected_proposal_details['product_type']) && $selected_proposal_details['product_type'] == 'business_loan') ? 'selected' : ''; ?>>Business Loan</option>
                            </select>
                    </div>
                </div>
                <div id="vehicleLoanFields" class="row custom-form-row mx-0 p-0" style="display: none;">
                    <div class="col-md-6">
                        <div class="form-group custom-form-group">
                            <label for="vehicle_name">Vehicle Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control custom-form-control" name="vehicle_name" id="vehicle_name" placeholder="Name of the vehicle" value="<?php echo htmlspecialchars($selected_proposal_details['vehicle_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group custom-form-group">
                            <label for="vehicle_model">Model <span class="text-danger">*</span></label>
                            <input type="text" class="form-control custom-form-control" name="vehicle_model" id="vehicle_model" placeholder="Manufacturing year / Model" value="<?php echo htmlspecialchars($selected_proposal_details['model'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group custom-form-group">
                            <label for="vehicle_km">Kilometers Driven <span class="text-danger">*</span></label>
                            <input type="text" class="form-control custom-form-control" name="kilometers_driven" id="kilometers_driven" placeholder="Total kilometers driven" value="<?php echo htmlspecialchars($selected_proposal_details['kilometers_driven'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

              
            </div> 
            <div class="row custom-form-row">
                <div class="col-md-6"> <div class="form-group custom-form-group">
                        <label>Loan Amount</label>
                        <input type="text" class="form-control custom-form-control" name="loan_amount" placeholder="Requested loan amount" required value="<?php echo htmlspecialchars($selected_proposal_details['loan_amount'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6"> <div class="form-group custom-form-group">
                        <label>Co-Applicant</label>
                        <input type="text" class="form-control custom-form-control" name="coapplicant_name" placeholder="Co-applicant Name" value="<?php echo htmlspecialchars($selected_proposal_details['co_applicant_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6"> <div class="form-group custom-form-group">
                        <label>Mobile Number</label>
                        <input type="text" class="form-control custom-form-control" name="coapplicant_mobile" placeholder="Co-applicant contact number" value="<?php echo htmlspecialchars($selected_proposal_details['co_applicant_mobile'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6"> <div class="form-group custom-form-group">
                        <label>Relationship</label>
                        <select class="form-control custom-form-control" name="coapplicant_relationship">
                            <option value="">-- Relationship with borrower --</option>
                            <option value="spouse" <?php echo (isset($selected_proposal_details['co_applicant_relationship']) && $selected_proposal_details['co_applicant_relationship'] == 'spouse') ? 'selected' : ''; ?>>Spouse</option>
                            <option value="parent" <?php echo (isset($selected_proposal_details['co_applicant_relationship']) && $selected_proposal_details['co_applicant_relationship'] == 'parent') ? 'selected' : ''; ?>>Parent</option>
                            <option value="sibling" <?php echo (isset($selected_proposal_details['co_applicant_relationship']) && $selected_proposal_details['co_applicant_relationship'] == 'sibling') ? 'selected' : ''; ?>>Sibling</option>
                            <option value="other" <?php echo (isset($selected_proposal_details['co_applicant_relationship']) && $selected_proposal_details['co_applicant_relationship'] == 'sibling') ? 'selected' : ''; ?>>Others</option>
                        </select>
                    </div>
                </div>
            </div> </div>
        <div class="col-md-6 right-column">
            <div class="new-comment" >
                <label class="comment-label">User Comments
                    <i class="fas fa-external-link-alt open-comment-modal" data-comment-type="User Comments" data-comment-id="userCommentContent" style="cursor: pointer; margin-left: 5px;"></i>
                </label>
              <textarea class="form-control" name="internal_comments" id="userCommentContent" placeholder="Enter your comment"
                    <?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'user') ? 'readonly' : ''; ?>
                ><?php echo htmlspecialchars($selected_proposal_details['userComment'] ?? '');?></textarea>

            </div>

            <div class="new-comment" >
                <label class="comment-label">Agent Comments
                    <i class="fas fa-external-link-alt open-comment-modal" data-comment-type="Agent Comments" data-comment-id="AgentCommentContent" style="cursor: pointer; margin-left: 5px;"></i>
                </label>
                <textarea class="form-control" name="agent_comments" id="AgentCommentContent" placeholder="Enter your comment"
                    <?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'sales') ? 'readonly' : ''; ?>
                ><?php echo htmlspecialchars($selected_proposal_details['agentComment'] ?? '');?></textarea>
            </div>

            <div class="new-comment">
                <label class="comment-label">Approver Comments
                    <i class="fas fa-external-link-alt open-comment-modal" data-comment-type="Approver Comments" data-comment-id="approverCommentContent" style="cursor: pointer; margin-left: 5px;"></i>
                </label>
                <div id="approverCommentContent" class="disabled-comments">
                    <?php
                        $showToClient = $selected_proposal_details['show_to_client'] ?? 0;
                        $approverComment = $selected_proposal_details['approver_comments'] ?? '';
                        $approverClosedComment = $selected_proposal_details['approver_closed_comments'] ?? '';
                        $userRole = $_SESSION['user_role'] ?? '';

                        if ($userRole === 'sales') {
                            if ($showToClient == 1) {
                                $combined = trim($approverComment . "\n" . $approverClosedComment);
                                echo empty($combined) ? 'No comments available' : nl2br(htmlspecialchars($combined));
                            } else {
                                echo empty($approverComment) ? 'No comments available' : nl2br(htmlspecialchars($approverComment));
                            }
                        } else {
                            $combined = trim($approverComment . "\n" . $approverClosedComment);
                            echo empty($combined) ? 'No comments available' : nl2br(htmlspecialchars($combined));
                        }
                    ?>
                </div>
            </div>
        </div>

        <div class="col-md-6 right-column" style="display:none">
            <h5 class="mt-4">Comments</h5>
            <div class="comments-box">
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

                <div class="new-comment">
                    <label class="comment-label">New Comment</label>
                    <textarea class="form-control" name="comments" placeholder="Enter your comment"></textarea>
                </div>
            </div>
        </div>
    </div>
</div>
    
    <!-- Upload Documents Section -->
    <div class="mt-4" style="margin-bottom:200px;">
        <?php if ($_SESSION['user_role'] != "sales"):?>
            <div style="margin-lefT:10px">
                <label for="showUserDocumentsToAgentCheckbox" style="color:blue;">
                <input type="checkbox" id="showUserDocumentsToAgentCheckbox" name="show_user_documents_to_agent">
                    Show user uploaded documents to Agent
                </label>
            </div>
        <?php endif; ?>
        <div class="row">
            <div id="documentCategories"></div>
        </div>
    </div>

   <!-- Submit Button -->
<div class="mt-4" style="position: fixed; z-index:999; bottom:0px; right:0px; background-color:#fff; width:100%;">
    <div class="d-flex justify-content-between align-items-center p-3">
        <button type="button" id="previewAllDocsBtn" class="btn btn-info" style="top:10px; position:relative;display:none" onclick="previewAllDocuments()">
            <i class="fas fa-eye"></i> Preview All Documents
        </button>

        <div class="d-flex align-items-center">
            <select id="statusDropdown" name="proposal_status" class="form-select d-inline-block w-auto me-2 align-middle custom-select-align <?php echo $is_disabled_class; ?>" onchange="toggleSubmitButton()">
                <option value="" disabled selected>-- Select Status --</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= htmlspecialchars($status['status_id']) ?>"><?= htmlspecialchars($status['status_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" id="submitBtn" onclick="setAction()" style="top:10px; position:relative" class="btn btn-success <?php echo $is_disabled_class; ?>" disabled>
                <i class="fas fa-paper-plane"></i> Submit Proposal
            </button>
        </div>
    </div>

    <input type="hidden" name="action" id="actionField">
    <input type="hidden" name="alert_flag" id="alert_flag">
    <input type="hidden" name="alert_message" id="alert_message">
    <input type="hidden" id="hfSelectedLockCategoryId" name="hfSelectedLockCategoryId" value="<?php echo $locked_category_ids_str; ?>">
</div>


</form>
    </div>
    </div>
    <!-- Modal to display enlarged content -->
    <!-- Modal HTML -->
    <!-- Modal Structure -->
<div id="previewModal" class="modal" style="display: none; align-items: center; justify-content: center;">
  <div class="modal-content" style="position: relative; background: white; padding: 20px; border-radius: 10px; max-width: 90%; max-height: 90vh; overflow: hidden;">

    <!-- 🛠 Toolbar (now floating) -->
    <div id="toolbar" style="
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 1000;
      background: rgba(255, 255, 255, 0.9);
      padding: 6px 10px;
      border-radius: 6px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    ">
      <button onclick="zoomIn()">🔍+</button>
      <button onclick="zoomOut()">🔍−</button>
      <button onclick="rotate()">🔄</button>
      <button onclick="closeModal()" id="closeModal">❌</button>
    </div>

    <!-- 📄 Content viewer -->
    <div id="modalPreviewContainer" style="
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100%;
      height: 100%;
    "></div>
    
  </div>
</div>

<div class="modal fade" id="fullScreenDocumentPreviewModal" tabindex="-1" aria-labelledby="fullScreenDocumentPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fullScreenDocumentPreviewModalLabel">All Documents Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body d-flex flex-column align-items-center justify-content-start overflow-auto">
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="commentModal" tabindex="-1" aria-labelledby="commentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="commentModalLabel">Comment Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="modalCommentContent"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>

let zoomLevel = 1;
let rotation = 0;
let currentViewer = null;
let currentType = null;

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
// NEW JAVASCRIPT FOR CONDITIONAL FIELDS
document.addEventListener('DOMContentLoaded', function() {
    const productTypeSelect = document.getElementById('product_type');
    const vehicleLoanFields = document.getElementById('vehicleLoanFields');
    const vehicleNameInput = document.getElementById('vehicle_name');
    const vehicleModelInput = document.getElementById('vehicle_model');
    const vehicleKmInput = document.getElementById('vehicle_km');

    function toggleVehicleFields() {
        if (productTypeSelect.value === 'vehicle_loan') {
            vehicleLoanFields.style.display = 'flex'; // Use flex to maintain row layout
            // Add 'required' attribute when visible
            vehicleNameInput.setAttribute('required', 'required');
            vehicleModelInput.setAttribute('required', 'required');
            vehicleKmInput.setAttribute('required', 'required');
        } else {
            vehicleLoanFields.style.display = 'none'; // Hide the fields
            // Remove 'required' attribute when hidden
            vehicleModelInput.removeAttribute('required');
            vehicleNameInput.removeAttribute('required');
            vehicleKmInput.removeAttribute('required');
            // Optionally, clear the values when hidden to prevent accidental submission of old data
            vehicleNameInput.value = '';
            vehicleModelInput.value = '';
            vehicleKmInput.value = '';
        }
    }

    // Attach event listener to the product type dropdown
    productTypeSelect.addEventListener('change', toggleVehicleFields);

    // Call it once on page load to set initial state based on pre-selected value (if any)
    toggleVehicleFields();
});
// END NEW JAVASCRIPT FOR CONDITIONAL FIELDS
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
    
    // Create the checkbox element
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = 'myCheckbox'; // Optional: set an ID
    checkbox.style.marginLeft = '10px'; // Optional: spacing before the button
    checkbox.style.position = 'relative'; // Optional: spacing before the button
    checkbox.style.display = "none" ;



    const selectLockCategoryId = category.id;
    // Optionally add a label
    const checkboxLabel = document.createElement('label');
    checkboxLabel.htmlFor = 'myCheckbox';
    checkboxLabel.textContent = 'Lock in agent login';
    checkboxLabel.style.marginLeft = '10px'; // Optional: spacing before the button
    checkboxLabel.style.position = 'relative'; // Optional: spacing before the button
    checkboxLabel.style.display = "none" ;

        // Get the existing hidden field
    const hiddenField = document.getElementById('hfSelectedLockCategoryId');
    
    // Handle checkbox click
    checkbox.addEventListener('change', function () {
        let currentValues = hiddenField.value ? hiddenField.value.split(',') : [];

        if (this.checked) {
            // Add category ID if not already present
            if (!currentValues.includes(selectLockCategoryId.toString())) {
                currentValues.push(selectLockCategoryId);
            }
        } else {
            // Remove category ID
            currentValues = currentValues.filter(id => id !== selectLockCategoryId.toString());
        }

        hiddenField.value = currentValues.join(',');
        
        
    });

    // Append checkbox and label before the paste button
    
    if (Array.isArray(proposal_documents)) {
            matchedDoc = proposal_documents.find(doc => 
            parseInt(doc.category_id) === parseInt(selectLockCategoryId)
        );

        if (matchedDoc && parseInt(matchedDoc.is_locked_for_agent) === 1) {
            checkbox.checked = true;
        }
    }

    
    
    <?php if ($_SESSION["user_role"] != "sales"): ?>
    
        title.appendChild(checkbox);
        title.appendChild(checkboxLabel);
    <?php endif; ?>


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

        <?php if ($_SESSION["user_role"] === "sales"): ?>
    
            if (Array.isArray(proposal_documents)) {
                const matchedDoc = proposal_documents.find(doc => 
                    parseInt(doc.category_id) === parseInt(category.id)
                );

                if (matchedDoc && parseInt(matchedDoc.is_locked_for_agent) === 1) {
                    fileInput.classList.add("disabled-div");
                    fileInput.setAttribute('disabled', 'disabled');
                }
            } else {
                console.warn("proposal_documents is not loaded or not an array.");
            }
        
        <?php endif; ?>

    
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
                //openModal(preview.src, 'image');
                window.open(preview.src, '_blank');
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
        <?php if ($_SESSION["user_role"] === "sales"): ?>
        // disable remove agent lock 
        var matchedDoc = proposal_documents.find(doc => parseInt(doc.category_id) === parseInt(categoryId));

        if (matchedDoc && parseInt(matchedDoc.is_locked_for_agent) === 1) {
            removeBtn.classList.add("disabled-div"); // Add PHP class dynamically ;
            removeBtn.setAttribute('disabled', 'disabled');
        }
        <?php endif; ?>

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
    const alertContainer = document.getElementById("alert-container");

    if (!filesToUpload[categoryId]) {
        filesToUpload[categoryId] = [];
    }

    for (let i = 0; i < files.length; i++) {
        const file = files[i];

        // ✅ File Size Validation
        if (file.size > maxFileSize) {
            showAlert(`🚫 File "${file.name}" exceeds the maximum size of ${maxFileSize / (1024 * 1024)}MB.`, "danger");
            continue; // Skip this file
        }

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
                //openModal(preview.src, 'image');
                window.open(preview.src, "_blank");
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            preview = document.createElement('div');
            preview.classList.add('pdf-icon');
            preview.innerHTML = '<i class="fas fa-file-pdf"></i>';
            preview.onclick = function () {
                //openModal(URL.createObjectURL(file), 'pdf');
                window.open(URL.createObjectURL(file), "_blank");
            };
        }

        fileDiv.appendChild(preview);

        const removeBtn = document.createElement('div');
        removeBtn.classList.add('file-remove');
        removeBtn.innerHTML = '<i class="fas fa-trash"></i> Remove';
        removeBtn.onclick = function () {
            let hfDeletedDocuments = document.getElementById("hf_deleted_documents");
            hfDeletedDocuments.value = file.file_path + ",";
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
            document.getElementById('loader').style.display = 'flex'; // To show

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

                    location.href="proposal.php?status_code=1024";
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
// function openModal(contentUrl, type) {
//     const modal = document.getElementById('previewModal');
//     const modalPreviewContainer = document.getElementById('modalPreviewContainer');
//     modal.style.display = 'flex';

//     // Clear any existing content in the modal
//     modalPreviewContainer.innerHTML = '';

//     if (type === 'image') {
//         const img = document.createElement('img');
//         img.src = contentUrl;
//         modalPreviewContainer.appendChild(img);
//     } else if (type === 'pdf') {
//         const pdfViewer = document.createElement('iframe');
//         pdfViewer.src = contentUrl;
//         pdfViewer.style.width = '100%';
//         pdfViewer.style.height = '500px'; // Adjust height as needed
//         modalPreviewContainer.appendChild(pdfViewer);
//     }
// }

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

// modal zoom/rotate 



function openModal(contentUrl, type) {
    const modal = document.getElementById('previewModal');
    const modalPreviewContainer = document.getElementById('modalPreviewContainer');
    modal.style.display = 'flex';

    // Reset states
    zoomLevel = 1;
    rotation = 0;
    currentType = type;

    // Clear existing content
    modalPreviewContainer.innerHTML = '';

    if (type === 'image') {
        const img = document.createElement('img');
        img.src = contentUrl;
        img.style.maxWidth = '90vw';
        img.style.maxHeight = '80vh';
        img.style.transition = 'transform 0.3s ease';
        img.style.transformOrigin = 'center';
        img.style.display = 'block';
        currentViewer = img;
        modalPreviewContainer.appendChild(img);
    } else if (type === 'pdf') {
        const iframe = document.createElement('iframe');
        iframe.src = contentUrl;
        iframe.style.transition = 'transform 0.3s ease';
        iframe.style.transformOrigin = 'center';
        iframe.style.border = 'none';
        currentViewer = iframe;
        modalPreviewContainer.appendChild(iframe);
    }

    updateTransform();
}

function updateTransform() {
    if (!currentViewer) return;

    // For images: no size changes, only scale + rotate
    if (currentType === 'image') {
        currentViewer.style.transform = `scale(${zoomLevel}) rotate(${rotation}deg)`;
    }
    // For PDFs (iframe): adjust size
    else if (currentType === 'pdf') {
        const baseWidth = 800;
        const baseHeight = 600;
        const isRotated = rotation % 180 !== 0;

        const width = isRotated ? baseHeight : baseWidth;
        const height = isRotated ? baseWidth : baseHeight;

        currentViewer.style.width = `${width * zoomLevel}px`;
        currentViewer.style.height = `${height * zoomLevel}px`;
        currentViewer.style.transform = `rotate(${rotation}deg)`;
    }
}


function zoomIn() {
    zoomLevel += 0.1;
    updateTransform();
}

function zoomOut() {
    zoomLevel = Math.max(0.1, zoomLevel - 0.1);
    updateTransform();
}

function rotate() {
    rotation = (rotation + 90) % 360;
    updateTransform();
}

function closeModal() {
    document.getElementById('previewModal').style.display = 'none';
    document.getElementById('modalPreviewContainer').innerHTML = '';
    currentViewer = null;
}
 function previewAllDocuments()
 {
    const fullScreenDocumentPreviewModal = new bootstrap.Modal(document.getElementById('fullScreenDocumentPreviewModal'));
    const modalBody = document.querySelector('#fullScreenDocumentPreviewModal .modal-body');
    
    
        modalBody.innerHTML = ''; // Clear previous content

        // Check if categorizedDocuments exists and has data
        if (typeof categorizedDocuments !== 'undefined' && categorizedDocuments[9].length > 0) {
            categorizedDocuments[9].forEach(doc => {
                // IMPORTANT: Ensure 'file_path' and 'document_type' exist for each doc
                if (!doc.file_path || !doc.document_type) {
                    console.warn('Skipping document due to missing file_path or document_type:', doc);
                    return; // Skip this document if data is incomplete
                }

                const docContainer = document.createElement('div');
                docContainer.classList.add('full-width-document-item'); // For custom styling

                if (doc.document_type === 'image') {
                    const img = document.createElement('img');
                    img.src = doc.file_path;
                    img.alt = 'Document Image';
                    docContainer.appendChild(img);
                } else if (doc.document_type === 'pdf') {
                    const iframe = document.createElement('iframe');
                    iframe.src = doc.file_path;
                    // iframe.width and iframe.height will be primarily controlled by CSS
                    // but you can set a min-height here too if desired.
                    iframe.setAttribute('frameborder', '0');
                    iframe.setAttribute('loading', 'lazy'); // Good for performance

                    docContainer.appendChild(iframe);

                    // Add a link to open PDF in new tab if iframe doesn't render well
                    const pdfLink = document.createElement('a');
                    pdfLink.href = doc.file_path;
                    pdfLink.target = "_blank";
                    pdfLink.textContent = "Open PDF in new tab (if preview fails)";
                    pdfLink.classList.add('mt-2', 'd-block', 'text-muted'); // Bootstrap classes for spacing/styling
                    docContainer.appendChild(pdfLink);

                } else {
                    // Fallback for unknown document types
                    const unknownDoc = document.createElement('p');
                    unknownDoc.innerHTML = `Cannot preview <strong>${doc.document_type}</strong> file. <a href="${doc.file_path}" target="_blank">Open file in new tab</a>`;
                    unknownDoc.classList.add('text-danger'); // Highlight unknown types
                    docContainer.appendChild(unknownDoc);
                }
                modalBody.appendChild(docContainer);
            });
        } else {
            modalBody.innerHTML = '<p class="text-center text-muted">No documents available for preview.</p>';
        }

        fullScreenDocumentPreviewModal.show(); // Show the modal
}
</script>
<style>
    .disabled-div {
        pointer-events: none;  /* Prevents clicks */
        opacity: 0.6;          /* Makes it look disabled */
        background: #f8f9fa;   /* Light grey background */
    }
</style>
<div id="loader" class="loader-overlay" style="display: none;">
  <div class="spinner"></div>
</div>

<style>
  .loader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.7);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .spinner {
    border: 6px solid #f3f3f3;
    border-top: 6px solid #007bff; /* Bootstrap Blue */
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
  }

  @keyframes spin {
    0%   { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
  /* Styles for documents inside the full-screen modal */
#fullScreenDocumentPreviewModal .modal-body .full-width-document-item {
    width: 100%; /* Ensure each document item takes full width */
    margin-bottom: 20px; /* Space between documents */
    text-align: center; /* Center images/content */
}

#fullScreenDocumentPreviewModal .modal-body .full-width-document-item img {
    max-width: 100%; /* Make images responsive within the modal */
    height: auto; /* Maintain aspect ratio */
    display: block; /* Remove extra space below image */
    margin: 0 auto; /* Center image if it's smaller than 100% width */
    border: 1px solid #ddd; /* Optional: Add a subtle border */
    box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Optional: Add a subtle shadow */
}

#fullScreenDocumentPreviewModal .modal-body .full-width-document-item iframe {
    border: 1px solid #ddd; /* Optional: Add a border for PDFs */
    box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Optional: Add a subtle shadow */
    width: 100%; /* Ensure iframe takes full width */
    min-height: 500px; /* Give PDFs a decent minimum height */
    /* You might want to adjust iframe height dynamically based on content or modal height */
}
.disabled-comments
{
    width:100%;
    padding:10px;
    background-color:rgba(150,150,150,.1);
    border:1px solid #d7d7d7;
}
/* Your existing disabled-comments style (example) */
.disabled-comments {
    background-color: #e9ecef; /* Light gray background */
    cursor: not-allowed; /* Shows a disabled cursor */
    /* If you have 'pointer-events: none;' here, it's the culprit */
    /* pointer-events: none;  <-- REMOVE THIS if you want the div to be clickable,
                                    or override it for the icon as shown below */
    opacity: 0.7; /* Make it slightly transparent */
}

/* --- */

/* Add this new rule for your icons */
.open-comment-modal {
    cursor: pointer; /* Ensures the cursor indicates it's clickable */
    pointer-events: auto !important; /* Forces the icon to receive click events */
    /* You might want to adjust positioning slightly if needed */
    position: relative; /* Useful for precise positioning */
    z-index: 10; /* Ensures it's above other elements if there are overlaps */
}
</style>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    const commentModal = new bootstrap.Modal(document.getElementById('commentModal'));
    const modalCommentContent = document.getElementById('modalCommentContent');
    const commentModalLabel = document.getElementById('commentModalLabel');

    // Get all elements with the 'open-comment-modal' class (your new icons)
    document.querySelectorAll('.open-comment-modal').forEach(icon => {
        icon.addEventListener('click', function() {
            const commentType = this.dataset.commentType; // Get from data-comment-type attribute
            const commentContentId = this.dataset.commentId; // Get from data-comment-id attribute
            const sourceElement = document.getElementById(commentContentId);

            let commentText = '';
            if (sourceElement) {
                // If it's a textarea (User Comments)
                if (sourceElement.tagName === 'TEXTAREA') {
                    commentText = sourceElement.value; // Get the raw value
                    // Replace newlines with <br> for display in modal
                    commentText = commentText.replace(/\n/g, '<br>');
                } else {
                    // For div elements (Agent, Approver comments)
                    commentText = sourceElement.innerHTML; // Get the HTML content, preserving <br> tags
                }
            } else {
                commentText = 'No content available.';
            }

            commentModalLabel.textContent = commentType; // Set modal title
            modalCommentContent.innerHTML = commentText; // Use innerHTML to render HTML (like <br> tags)
            commentModal.show();
        });
    });
});

 document.addEventListener('DOMContentLoaded', function() {
        const textarea = document.getElementById('AgentCommentContent');

        function autoResize() {
            // Reset height to 'auto' to correctly calculate the scrollHeight
            textarea.style.height = 'auto'; 
            // Set height to scrollHeight, which is the full content height
            textarea.style.height = textarea.scrollHeight + 'px'; 
        }

        // 1. Listen for input events (user typing)
        textarea.addEventListener('input', autoResize);

        // 2. Call autoResize once on load to adjust for initial content
        autoResize(); 
    });
 document.addEventListener('DOMContentLoaded', function() {
        const userTextarea = document.getElementById('userCommentContent'); // Target this specific textarea

        function autoResizeUserComments() {
            userTextarea.style.height = 'auto'; 
            userTextarea.style.height = userTextarea.scrollHeight + 'px'; 
        }

        userTextarea.addEventListener('input', autoResizeUserComments);
        autoResizeUserComments(); // Call on load for initial content
    });    
</script>
<?php
function showUnauthorizedPage() {
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Unauthorized Access</title>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            color: #333;
        }
        .container {
            text-align: center;
            padding: 40px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        .container img {
            width: 100px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 2em;
            color: #dc3545;
        }
        p {
            font-size: 1.1em;
            margin-top: 10px;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        a:hover {
            background-color: #0056b3;
        }
    </style>
    </head>
    <body>
    <div class="container">
        <img src="assets/images/unauthorized.png" alt="Access Denied">
        <h1>Unauthorized Access</h1>
        <p>You do not have permission to view this page.</p>
        <a href="dashboard.php">Return to Dashboard</a>
    </div>
    </body>
    </html>
    ';
    exit;
}
?>