<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_FILES['files'])) {
        $uploadDir = "uploads/";  
        $uploadedFiles = [];

        // Loop through each uploaded file
        foreach ($_FILES['files']['error'] as $key => $error) {
            if ($error === 0) {
                $fileName = basename($_FILES['files']['name'][$key]);
                $uploadFile = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $uploadFile)) {
                    $uploadedFiles[] = "<a href='$uploadFile' target='_blank'>$fileName</a>";
                } else {
                    echo "<p style='color:red;'>❌ Error moving file: $fileName!</p>";
                }
            } else {
                echo "<p style='color:red;'>❌ Error uploading file!</p>";
            }
        }

        if (!empty($uploadedFiles)) {
            echo "<p style='color:green;'>✅ Files uploaded successfully: </p>";
            foreach ($uploadedFiles as $fileLink) {
                echo "<p>$fileLink</p>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Upload</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; }
        .category-card { display: inline-block; border: 1px solid #ccc; padding: 10px; margin: 10px; width: 300px; }
        .category-card h3 { margin-top: 0; }
        .file-preview { display: inline-block; margin: 10px; }
        .preview-container { margin-top: 20px; }
        .preview-container img { max-width: 100px; height: auto; margin: 5px; }
        .preview-container .file-remove { cursor: pointer; font-size: 14px; color: red; margin-top: 5px; }
        .preview-container .pdf-icon { width: 100px; height: 150px; background: url('pdf-icon.png') no-repeat center center; background-size: cover; margin: 5px; }
    </style>
</head>
<body>
    <h2>Upload Your Documents</h2>

    <form action="" method="POST" id="uploadForm" enctype="multipart/form-data">
        <!-- Dynamic document category cards will be added here -->
        <div id="documentCategories"></div>

        <br>
        <input type="submit" value="Upload All" id="uploadBtn" disabled>
    </form>

    <script>
        const documentCategories = [
            { id: 'aadhar', name: 'Aadhar Card' },
            { id: 'drivingLicense', name: 'Driving License' },
            { id: 'voterId', name: 'Voter ID' }
        ];

        const filesToUpload = {}; // Object to store files for each category

        const uploadForm = document.getElementById('uploadForm');
        const uploadBtn = document.getElementById('uploadBtn');
        const documentCategoriesContainer = document.getElementById('documentCategories');

        // Function to create a category card with file input and preview section
        function createCategoryCard(category) {
            const card = document.createElement('div');
            card.classList.add('category-card');
            card.id = category.id;

            const title = document.createElement('h3');
            title.textContent = category.name;
            card.appendChild(title);

            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = 'image/*,application/pdf';
            fileInput.multiple = true;
            fileInput.addEventListener('change', function() {
                handleFileSelect(category.id, fileInput);
            });
            card.appendChild(fileInput);

            const pasteButton = document.createElement('span');
            pasteButton.textContent = 'Paste Image';
            pasteButton.onclick = function() {
                // This is a fix: trigger paste event on the document
                triggerPaste(category.id);
            };
            card.appendChild(pasteButton);

            const previewContainer = document.createElement('div');
            previewContainer.classList.add('preview-container');
            previewContainer.id = `${category.id}-preview`;
            card.appendChild(previewContainer);

            documentCategoriesContainer.appendChild(card);
        }

        // Function to handle file selection and display previews
        function handleFileSelect(categoryId, input) {
            const files = input.files;
            const previewContainer = document.getElementById(`${categoryId}-preview`);

            // Add files to filesToUpload object
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
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    preview = document.createElement('div');
                    preview.classList.add('pdf-icon');
                }

                fileDiv.appendChild(preview);

                // Add remove button for the file
                const removeBtn = document.createElement('div');
                removeBtn.classList.add('file-remove');
                removeBtn.textContent = 'Remove';
                removeBtn.onclick = function() {
                    fileDiv.remove();
                    // Remove from filesToUpload object
                    const index = filesToUpload[categoryId].indexOf(file);
                    if (index > -1) filesToUpload[categoryId].splice(index, 1);
                };
                fileDiv.appendChild(removeBtn);

                previewContainer.appendChild(fileDiv);

                // Add to filesToUpload object
                filesToUpload[categoryId].push(file);
            }

            // Enable the upload button if there are any files in any category
            checkEnableUploadBtn();
        }

        // Function to handle paste events for images
        // Function to handle paste events for images
async function triggerPaste(categoryId) {
   
try{
      // Request clipboard permissions
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
                removeBtn.textContent = "Remove";
                removeBtn.onclick = function () {
                    fileDiv.remove();
                    const index = filesToUpload[categoryId].indexOf(file);
                    if (index > -1) filesToUpload[categoryId].splice(index, 1);
                };
                fileDiv.appendChild(removeBtn);

                previewContainer.appendChild(fileDiv);
                filesToUpload[categoryId].push(file);
                checkEnableUploadBtn();
            };
            reader.readAsDataURL(file);
        }
    }
}
} catch (err) {
console.error("Failed to read clipboard contents:", err);
}
     
   
}


        // Check if the upload button should be enabled
        function checkEnableUploadBtn() {
            let hasFiles = false;
            for (const category in filesToUpload) {
                if (filesToUpload[category].length > 0) {
                    hasFiles = true;
                    break;
                }
            }
            uploadBtn.disabled = !hasFiles;
        }

        // Initialize category cards
        documentCategories.forEach(category => {
            createCategoryCard(category);
        });

        // Submit the form with all files
        uploadForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent the default form submission

            const formData = new FormData();

            // Add all files from all categories to FormData
            for (const category in filesToUpload) {
                filesToUpload[category].forEach(file => {
                    formData.append('files[]', file);
                });
            }

            // Perform the AJAX request to submit the form data
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    // Successfully uploaded
                    alert('Files uploaded successfully!');
                    location.reload(); // Reload page to clear the form
                } else {
                    alert('Error uploading files!');
                }
            };

            xhr.send(formData); // Send all files via AJAX
        });
    </script>
</body>
</html>
