<?php 
require_once 'config.php'; 

// 1. Authentication Check: If no user ID, kick back to login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 2. Security Check: Ensure the user belongs to a valid role
// This prevents users with no role or invalid roles from seeing the page
requireRole(['citizen', 'admin', 'authority']); 

$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - <?= ucfirst($role) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        :root { --sidebar-width: 250px; }
        body { background-color: #f4f7f6; }
        .navbar-brand { letter-spacing: 1px; }
        .dashboard-container { min-height: 80vh; }
        /* Smooth transitions for dashboard elements */
        .card { border: none; border-radius: 12px; transition: transform 0.2s; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                🚨 EMERGENCY <span class="text-danger">DISPATCH</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3">
                        <span class="badge bg-secondary p-2">
                            Role: <?= strtoupper($role) ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link text-white">
                            Welcome, <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></strong>
                        </span>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4 dashboard-container">
        <?php 
        // Logic to determine which dashboard fragment to load
        $viewPath = 'includes/' . $role . '_dashboard.php';

        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "
            <div class='alert alert-warning border-0 shadow-sm'>
                <h4 class='alert-heading'>Dashboard Not Found</h4>
                <p>The system could not locate the view for the <strong>{$role}</strong> role. 
                Please contact the administrator or ensure <code>{$viewPath}</code> exists.</p>
            </div>";
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>