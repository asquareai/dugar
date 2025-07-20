<?php
session_start();
include 'config.php'; // Make sure this path is correct

if (!isset($_SESSION['user']) || !isset($_SESSION['session_token'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['user'];
$query = "SELECT session_token, role user_role FROM users WHERE username = ?"; // Fetch user_role as well
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// This 'mvk' hardcoded session token check seems unusual and potentially insecure.
// It might be for a specific debugging purpose. In a production environment,
// you'd typically want to rely solely on the database token for multi-login detection.
if($_SESSION['session_token']!="mvk") {
    if ($row['session_token'] !== $_SESSION['session_token']) {
        $_SESSION['multi_login_detected'] = true;
        header("Location: logout.php");
        exit();
    }
}

// Store user role in a variable for easier use in HTML
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';

// Define which roles have access to User Management
$canManageUsers = ($userRole === 'admin' || $userRole === 'approver');

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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2D568F;
            --bg-color: #f4f4f4;
            --text-color: #333;
            --sidebar-width: 280px; /* Slightly wider sidebar to accommodate content */
            --navbar-height-mobile: 60px; /* Height for the top bar on mobile */
            --navbar-height-desktop: 120px; /* Height when acting as full top menu */
            --content-padding: 20px; /* Define content padding as a variable for easier calculation */
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
            background: linear-gradient(180deg, #fff, rgb(236, 232, 232), rgb(214, 214, 214));
            margin: 0;
            overflow-x: hidden;
            /* Adjust body padding for fixed navbar based on mobile default */
            padding-top: var(--navbar-height-mobile);
        }
        body.no-scroll {
            overflow: hidden;
        }

        /* Navbar - Main top bar */
        .navbar {
            background-color: #e0e0e0;
            padding: 12px 20px;
            font-family: 'Poppins', sans-serif;
            border-bottom: 1px solid rgba(0,0,0,.5);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between; /* Space out title and hamburger on mobile */
            min-height: var(--navbar-height-mobile); /* Minimal height by default */
            position: fixed; /* Keep navbar fixed at the top */
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000; /* Ensure navbar is above content, below sidebar/overlay when active */
        }

        /* Dugar Finance Title in Navbar */
        .navbar h1 {
            margin: 0;
            font-size: 24px;
            color: var(--primary-color);
        }

        /* Hamburger Menu Icon */
        .hamburger-icon {
            font-size: 28px;
            color: var(--primary-color);
            cursor: pointer;
            display: block; /* Always visible in the navbar on mobile */
        }

        /* Sidebar Menu - Holds content that slides out on mobile */
        .sidebar-menu {
            position: fixed;
            top: 0; /* Starts from the very top */
            left: 0;
            height: 100%;
            width: var(--sidebar-width);
            background-color: #343a40;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.5);
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            z-index: 1050; /* Higher than overlay */
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Align contents to the left */
            gap: 15px; /* Space between menu items */
            overflow-y: auto;
        }

        .sidebar-menu.active {
            transform: translateX(0);
        }

        /* Logo inside sidebar (for mobile/sidebar view) */
        .sidebar-menu .logo {
            width: 100%;
            display: flex; /* Ensure it's displayed */
            justify-content: center;
            margin-bottom: 20px;
        }
        .sidebar-menu .logo img {
            width: 180px;
            height: auto;
        }

        /* User Info inside sidebar */
        .sidebar-menu .user-info {
            width: 100%;
            font-size: 14px;
            color: #f8f9fa;
            text-align: left;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            padding-bottom: 20px;
            white-space: normal; /* Allow text to wrap */
        }
        .sidebar-menu .user-info .d-flex {
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        .sidebar-menu .user-info .welcome-text strong,
        .sidebar-menu .user-info .user-role,
        .sidebar-menu .user-info .login-time {
            color: #f8f9fa;
        }
        .sidebar-menu .user-info .fa-user-circle,
        .sidebar-menu .user-info .fa-user-tag,
        .sidebar-menu .user-info .fa-clock {
            color: #ced4da;
            flex-shrink: 0;
        }

        /* Navigation Links within sidebar */
        .sidebar-menu .nav-links {
            display: flex;
            flex-direction: column; /* Stack vertically in sidebar */
            width: 100%;
            gap: 5px; /* Adjust gap for vertical items */
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 12px 15px;
            color: #f8f9fa;
            font-size: 17px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease-in-out;
            font-weight: 500;
        }

        .sidebar-menu a i {
            margin-right: 12px;
            font-size: 20px;
            transition: transform 0.3s ease-in-out;
        }

        .sidebar-menu a.active {
            background: var(--primary-color);
            color: #fff;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateY(-2px);
        }
        .sidebar-menu a:hover i {
            transform: scale(1.1);
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0; /* Cover the entire screen including the header */
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040; /* Lower than sidebar, higher than navbar */
            display: none;
            cursor: pointer;
        }

        .overlay.active {
            display: block;
        }

        /* Content Area - Adjusted height calculation */
        .content {
            padding: var(--content-padding); /* Use variable for consistent padding */
            /* Calculate height: 100% of viewport height - navbar height - (2 * content padding) */
            height: calc(100vh - var(--navbar-height-mobile) - (2 * var(--content-padding))); /* Initial height for mobile */
            overflow-y: auto;
        }

        .frame-set {
            height: 100%; /* Make iframe fill its parent (.content) */
            width: 100%;
            border: 1px solid rgba(0,0,0,.5);
        }

        /* Responsive Behavior */

        /* Desktop: Navbar becomes the main multi-row top bar */
        @media (min-width: 993px) {
            body {
                /* Adjust body padding for desktop navbar height */
                padding-top: var(--navbar-height-desktop);
            }
            .navbar {
                flex-direction: column; /* Stack items vertically: h1 then sidebar-menu */
                align-items: flex-start; /* Align contents to the left */
                min-height: var(--navbar-height-desktop); /* Taller navbar for desktop */
                padding: 10px 20px; /* Adjust padding */
                justify-content: flex-start; /* Align items to the top */
            }

            .navbar h1 {
                font-size: 28px; /* Larger title for desktop */
                width: 100%; /* Take full width of navbar */
                margin-bottom: 10px; /* Space before the next row (sidebar-menu) */
            }

            .hamburger-icon {
                display: none; /* Hide hamburger icon on desktop */
            }

            .sidebar-menu { /* This becomes the desktop horizontal menu content within the navbar */
                position: static; /* Remove fixed positioning */
                transform: translateX(0); /* Always visible */
                height: auto; /* No fixed height */
                width: 100%; /* Take full width within navbar */
                background-color: transparent; /* No background */
                box-shadow: none; /* No shadow */
                padding: 0; /* Remove padding */
                display: flex;
                flex-direction: row; /* Horizontal layout for user info and nav links */
                justify-content: space-between; /* Push user info left, nav links right */
                align-items: center; /* Vertically align items */
                gap: 20px; /* Space between user info and nav links */
            }

            .sidebar-menu .logo { /* Hide logo that was specifically for mobile sidebar */
                display: none;
            }
            .sidebar-menu .user-info {
                width: auto; /* Allow content to dictate width */
                font-size: 14px;
                color: var(--text-color); /* Dark text for light background */
                text-align: left; /* Align text to left in desktop bar */
                margin-bottom: 0; /* No bottom margin */
                padding-bottom: 0;
                border-bottom: none; /* Remove separator */
            }
            .sidebar-menu .user-info .d-flex {
                justify-content: flex-start; /* Align user info details to the left */
            }
            .sidebar-menu .user-info .welcome-text strong {
                color: #0056b3;
            }
            .sidebar-menu .user-info .user-role {
                color: #007bff;
            }
            .sidebar-menu .user-info .login-time {
                color: rgb(18, 4, 4);
            }
            .sidebar-menu .user-info .fa-user-circle,
            .sidebar-menu .user-info .fa-user-tag,
            .sidebar-menu .user-info .fa-clock {
                color: #343a40;
            }

            /* Navigation links as horizontal menu */
            .sidebar-menu .nav-links {
                flex-direction: row; /* Horizontal layout */
                flex-wrap: wrap; /* Allow wrapping if many links */
                gap: 25px; /* Space between menu items */
                margin-top: 0; /* No top margin needed for horizontal layout */
                width: auto; /* Allow links to take natural width */
                justify-content: flex-end; /* Align links to the right */
            }
            .sidebar-menu .nav-links a {
                width: auto; /* Reset width */
                color: var(--primary-color);
                background: none;
                box-shadow: none;
            }
            .sidebar-menu .nav-links a:hover {
                background: rgba(45, 86, 143, 0.1);
                color: var(--primary-color);
            }
            .sidebar-menu .nav-links a.active {
                background: var(--primary-color);
                color: #fff;
            }

            .overlay {
                display: none !important; /* Hide overlay on desktop */
            }
            .content {
                padding-top: var(--content-padding); /* Keep consistent padding */
                /* Adjust content height for desktop navbar */
                height: calc(100vh - var(--navbar-height-desktop) - (2 * var(--content-padding)));
            }
        }

        /* Mobile/Tablet: Navbar is minimal, sidebar slides out */
        @media (max-width: 992px) {
            .navbar {
                justify-content: space-between; /* Title left, hamburger right */
                min-height: var(--navbar-height-mobile);
            }
            .navbar h1 {
                font-size: 24px;
                width: auto; /* Allow width based on content */
                margin-bottom: 0;
            }
            .hamburger-icon {
                display: block;
            }
            /* The .sidebar-menu remains as a fixed, off-canvas element on mobile */
            .sidebar-menu {
                 z-index: 1050; /* Ensure it's above overlay */
            }
            .sidebar-menu .logo { /* Make sure logo is visible in mobile sidebar */
                display: flex; /* Show the logo in the sidebar */
            }
            .content {
                /* Height already defined for mobile, here for clarity.
                    Make sure this matches the base .content height. */
                height: calc(100vh - var(--navbar-height-mobile) - (2 * var(--content-padding)));
            }
             .overlay.active {
                display: block;
                /* Ensure overlay starts below navbar on mobile */
                top: var(--navbar-height-mobile);
                height: calc(100% - var(--navbar-height-mobile));
                z-index: 1040; /* Below sidebar, above content */
            }
        }

        @media (max-width: 576px) {
            .navbar h1 {
                font-size: 20px; /* Even smaller title for very small screens */
            }
            .sidebar-menu .logo img {
                width: 120px; /* Smaller logo for very small screens */
            }
            .sidebar-menu .user-info {
                font-size: 12px;
            }
            .sidebar-menu a {
                font-size: 15px;
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    


<nav class="navbar">
    <h1>Dugar Finance</h1>
    <i class="fas fa-bars hamburger-icon" id="hamburgerIcon"></i>

    <div class="sidebar-menu" id="sidebarMenu">
        <div class="logo">
            <img src="assets/images/logo.png" alt="Logo">
        </div>
        
        <div class="user-info">
            <div class="d-flex align-items-center">
                <i class="fa fa-user-circle me-2"></i>
                <span class="welcome-text">Welcome, <strong><?php echo $_SESSION['user_fullname']; ?></strong></span>
            </div>
            <div class="d-flex align-items-center mt-1">
                <i class="fa fa-user-tag me-2"></i>
                <span class="user-role"><?php echo ucfirst($_SESSION['user_role']); ?></span>
            </div>
            <div class="d-flex align-items-center mt-1">
                <i class="fa fa-clock me-2"></i>
                <span class="login-time">Last Login: <?php echo date("d M Y, h:i A", strtotime($_SESSION['last_login_time'])); ?></span>
            </div>
        </div>
        <div class="nav-links">
            <a href="#" onclick="loadPage('proposal.php')" data-page="proposal"><i class="fa fa-home"></i> <span>Proposals</span></a>
            <?php if ($_SESSION['user_role'] == 'sales' || $_SESSION['user_role'] == 'super agent'): ?>
                <a href="#" onclick="loadPage('rpt_sales_super_page.php')" data-page="report_sales_super_landing_page"><i class="fa fa-chart-line"></i> <span>Reports</span></a>
            <?php else: ?>
                <a href="#" onclick="loadPage('report_landing_page.php')" data-page="report_landing_page"><i class="fa fa-chart-line"></i> <span>Reports</span></a>
            <?php endif; ?>
            <?php if ($canManageUsers): // Only show for Admin or Approver ?>
                <a href="#" onclick="loadPage('user_management.php')" data-page="user_management">
                    <i class="fa fa-users"></i> <span>User Management</span>
                </a>
            <?php endif; ?>

            <a href="#" onclick="loadPage('change_password.php')" data-page="change_password">
                <i class="fa fa-key"></i> <span>Change Password</span>
            </a>
            <a href="logout.php"><i class="fa fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>
</nav>

<div class="content" id="content">
    <iframe id="contentFrame" class="frame-set" src="" frameborder="0"></iframe>
</div>
    
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebarMenu');
        const overlay = document.getElementById('overlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.classList.toggle('no-scroll');
    }

    function loadPage(page) {
        const contentFrame = document.getElementById("contentFrame");
        // Only set src if it's different to avoid unnecessary reloads of the iframe
        if (contentFrame.src.split('/').pop().split('?')[0] !== page.split('/').pop().split('?')[0]) {
             contentFrame.src = page;
        }

        // Remove 'active' class from all sidebar menu items
        let menuLinks = document.querySelectorAll(".sidebar-menu .nav-links a");
        menuLinks.forEach(link => link.classList.remove("active"));
        
        // Add 'active' class to the clicked menu item
        // Use a more robust way to get data-page by splitting the URL
        let dataPageAttribute = page.replace('.php', ''); // remove .php
        let activeLink = document.querySelector(`.sidebar-menu .nav-links a[data-page="${dataPageAttribute}"]`);
        if (activeLink) {
            activeLink.classList.add("active");
        }

        // Close sidebar if it's open (for mobile/sidebar view)
        const sidebar = document.getElementById('sidebarMenu');
        // Only close if it's acting as a sidebar (i.e., on mobile/tablet widths)
        if (sidebar.classList.contains('active') && window.innerWidth <= 992) {
            toggleSidebar();
        }
    }

    // Initial page load and active state
    document.addEventListener('DOMContentLoaded', function() {
        // Set the initial page to load based on user role
        // For Admin/Approver, it's typically 'proposal.php' or a dashboard summary
        // For others, also 'proposal.php' as it's the first common link
        loadPage("proposal.php"); 

        document.getElementById('hamburgerIcon').addEventListener('click', toggleSidebar);
        document.getElementById('overlay').addEventListener('click', toggleSidebar);

        // Ensure sidebar closes when a menu link is clicked (already handled by loadPage, but good to double check)
        document.querySelectorAll('.sidebar-menu .nav-links a').forEach(link => {
            link.addEventListener('click', function(event) {
                // The loadPage function already handles routing and closing.
                // No need for a separate toggleSidebar() call here, as loadPage already calls it.
            });
        });

        // Close sidebar if window is resized above mobile breakpoint while open
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                const sidebar = document.getElementById('sidebarMenu');
                const overlay = document.getElementById('overlay');
                // If sidebar is active and screen becomes desktop size, close sidebar
                if (window.innerWidth > 992 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.classList.remove('no-scroll');
                }
                // If resizing from desktop to mobile and overlay is somehow active (shouldn't be, but as a safeguard)
                if (window.innerWidth <= 992 && !sidebar.classList.contains('active') && overlay.classList.contains('active')) {
                     overlay.classList.remove('active');
                     document.body.classList.remove('no-scroll');
                }
            }, 250);
        });
    });
</script>
<style>
    .content {
        height: calc(100vh - 120px);
    }
    @media (max-width: 992px) {
    .content {
        height: calc(100vh - 62px);
    }
}
</style>
</body>
</html>