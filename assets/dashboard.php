<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user']) || !isset($_SESSION['session_token'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['user'];
$query = "SELECT session_token FROM users WHERE username = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// If session token doesn't match, force logout (another login occurred)
if ($row['session_token'] !== $_SESSION['session_token']) {
    $_SESSION['multi_login_detected'] = true; // Store warning message
    header("Location: logout.php"); // Redirect to logout page
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@100&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2D568F;
            --bg-color: #f4f4f4;
            --text-color: #333;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
            background: linear-gradient(180deg, #fff,rgb(236, 232, 232),rgb(214, 214, 214));
        }
        .dark-mode {
            --bg-color: #1a1a2e;
            --text-color: #fff;
        }
        
        /* Navbar */
        .navbar {
            background-color: #e0e0e0; /* Light Gray Background */
            padding: 12px;
            font-family: 'Poppins', sans-serif; /* Matches the existing font */
            border-bottom:1px solid rgba(0,0,0,.5);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            height:100px!important;
        }
        .navbar .toggle-switch {
            cursor: pointer;
            color: var(--primary-color);
            font-size: 22px;
        }
        .user-info {
                font-size: 14px;
            }

        .welcome-text,
        .user-role,
        .login-time {
            font-family: 'Poppins', sans-serif;
        }

        .user-role {
            font-weight: 600;
            color: #007bff; /* Blue color for better visibility */
        }

        .welcome-text strong {
            color: #0056b3; /* Darker Blue */
        }
        .login-time {
            color:rgb(18, 4, 4); /* Dark Red */
        }
        .fa-user-circle {
            font-size: 16px;
            color: #343a40; /* Dark Icon */
        }
        .fa-clock {
            font-size: 16px;
            color:rgb(23, 4, 4); /* Darker Red */
        }
        /* Sidebar */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: var(--primary-color);
            color: white;
            position: fixed;
            padding-top: 10px;
            transition: transform 0.3s ease-in-out;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #ECF0F1; /* Light text */
            font-size: 16px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease-in-out;
        }
        .sidebar a i {
            margin-right: 12px;
            font-size: 18px;
            transition: transform 0.3s ease-in-out;
        }
        .sidebar a.active {
            background: #34495E; /* Darker shade */
            color: #fff;
            font-weight: bold;
            transform: translateX(5px);
        }

        /* Hover Effects */
        .sidebar a:hover {
            background: #34495E; /* Darker shade */
            transform: translateX(5px); /* Smooth slide effect */
            color: #fff;
        }
        
        .sidebar a:hover i {
            transform: rotate(15deg) scale(1.1); /* Icon animation */
        }
        /* Hide sidebar on mobile */
        .sidebar.hide {
            transform: translateX(-100%);
        }

        /* Content */
        .content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease-in-out;
            
        }
        .content.shrink {
            margin-left: 0;
        }

        /* Logo */
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo img {
            width: 200px;
            height: auto;
        }
        .frame-set
        {
            height:calc(100vh - 148px);
            min-height:400px;
            border:1px solid rgba(0,0,0,.5);
        }
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 250px;
                transform: translateX(-100%);
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 1000;
                transition: all 0.3s ease-in-out;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .content {
                margin-left: 0;
            }
            .logo {
                text-align: center;
                width: 100%;
                justify-content: center; /* Center logo */
            }
            .toggle-switch
            {
                position: fixed;
                right: 20px;
                top: 15px;
                cursor: pointer;
                font-size: 22px;
                color: var(--primary-color);
                z-index: 1100;
            }
            .user-info {
                text-align: center !important;
                width: 100%;
            }
            .d-flex {
                justify-content: center !important;
            }
            .navbar
            {
                height:180px!important;
                
            }
            .content
            {
                height:calc(100vh - 180px)!important;
            }
            .frame-set
            {
                height:calc(100vh - 220px);
                min-height:200px;
                border:1px solid rgba(0,0,0,.5);
            }
        }
    </style>
</head>
<body>
   
<!-- Header -->
    <nav class="navbar d-flex justify-content-between align-items-center px-3">
        <div class="logo d-flex align-items-center">
            <img src="assets/images/logo.png" alt="Logo"> <!-- Logo -->
            <i class="fa fa-bars toggle-switch" id="sidebarToggle"></i>
        </div>
        
        <!-- Welcome Message -->
        <div class="user-info text-lg-end text-center">
            <!-- User Name and Role -->
            <div class="d-flex flex-column align-items-lg-end align-items-center">
                <div class="d-flex align-items-center">
                    <i class="fa fa-user-circle text-dark me-2"></i> <!-- User Icon -->
                    <span class="welcome-text">Welcome, <strong><?php echo $_SESSION['user_fullname']; ?></strong></span>
                </div>
                <div class="d-flex align-items-center mt-1">
                    <i class="fa fa-user-tag text-primary me-2"></i> <!-- Role Icon -->
                    <span class="user-role"><?php echo ucfirst($_SESSION['user_role']); ?></span>
                </div>
            </div>

            <!-- Last Login Time -->
            <div class="d-flex align-items-center justify-content-lg-end justify-content-center mt-1">
                <i class="fa fa-clock text-danger me-2"></i> <!-- Clock Icon -->
                <span class="login-time">Last Login: <?php echo date("d M Y, h:i A", strtotime($_SESSION['last_login_time'])); ?></span>
            </div>
        </div>

    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <a href="#" onclick="loadPage('proposal.php')" data-page="proposal"><i class="fa fa-home"></i> <span>Proposals</span></a>
        <a href="#" onclick="loadPage('report_landing_page.php')" data-page="analytics"><i class="fa fa-chart-line"></i> <span>Reports</span></a>
        <a href="#" onclick="loadPage('user_management.php')" data-page="settings">
            <i class="fa fa-users"></i> <span>User Management</span>
        </a>
        <a href="#" onclick="loadPage('change_password.php')" data-page="settings">
            <i class="fa fa-key"></i> <span>Change Password</span>
        </a>

        <a href="logout.php"><i class="fa fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>


    <!-- Content -->
        <!-- Content Area with Iframe -->
        <div class="content" id="content" >
            <iframe id="contentFrame" class="frame-set" src="proposal.php" frameborder="0" width="100%" height="100%"></iframe>
        </div>
        <!-- <span style="position:fixed;bottom:4px;left:calc(100% - 220px);font-size:0.8em;">© Copyright www.asquareai.com -->
        </span>


    <script>
       
        // Sidebar Toggle
        document.getElementById("sidebarToggle").addEventListener("click", function() {
            let sidebar = document.getElementById("sidebar");
            let content = document.getElementById("content");

            if (window.innerWidth <= 768) {
                sidebar.classList.toggle("show"); // For mobile (full hide/show)
            } else {
                sidebar.classList.toggle("hide"); // For desktop (only shrink)
                content.classList.toggle("shrink");
            }
        });


        function loadPage(page) {
            // Load the page inside your iframe or container
            document.getElementById("contentFrame").src = page;

            // Remove 'active' class from all menu items
            let menuLinks = document.querySelectorAll(".sidebar a");
            menuLinks.forEach(link => link.classList.remove("active"));
            
            // Add 'active' class to the clicked menu item
            let activeLink = document.querySelector(`.sidebar a[data-page="${page.replace('.php', '')}"]`);
            if (activeLink) {
                activeLink.classList.add("active");
            }

            // Store the active menu in local storage (so it stays active after reload)
            localStorage.setItem("activeMenu", page);
            
            
        }

        // Restore active menu from local storage when the page reloads
        // window.onload = function () {
        //     let savedPage = localStorage.getItem("activeMenu");
        //     if (savedPage) {
        //         loadPage(savedPage);
        //     }
        // };

    </script>
</body>
</html>
