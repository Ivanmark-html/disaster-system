<?php 
require 'config.php'; 

$pdo = getDB();

// 1. Handle Filters
$type_filter = $_GET['type'] ?? '';
$time_filter = $_GET['time'] ?? 'all';

// 2. Build Query - Use 'd.' prefix to avoid "ambiguous column" error
$where = "WHERE d.status = 'approved'";

if ($type_filter) {
    // Basic sanitization using quote()
    $where .= " AND d.type = " . $pdo->quote($type_filter);
}

if ($time_filter === 'today') {
    $where .= " AND DATE(d.created_at) = CURDATE()";
} elseif ($time_filter === 'week') {
    $where .= " AND d.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
}

// 3. Fetch Data
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll();

$sql = "SELECT d.*, a.name as authority 
        FROM disasters d 
        LEFT JOIN authorities a ON d.authority_id = a.id 
        $where 
        ORDER BY d.created_at DESC";

$disasters = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Disaster Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { height: 500px; border-radius: 8px; }
        .filter-section { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .hero-carousel {
            margin-bottom: 2rem;
        }
        .hero-carousel .carousel-item img {
            height: 520px;
            object-fit: cover;
            filter: brightness(0.65);
        }
        .hero-carousel .carousel-caption {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.78), rgba(127, 29, 29, 0.72));
            border-radius: 20px;
            bottom: 3rem;
            left: 8%;
            max-width: 540px;
            padding: 1.5rem;
            right: auto;
            text-align: left;
        }
        .hero-carousel .carousel-control-prev,
        .hero-carousel .carousel-control-next {
            width: 8%;
        }
        .hero-carousel .carousel-control-prev-icon,
        .hero-carousel .carousel-control-next-icon {
            background-color: rgba(15, 23, 42, 0.65);
            background-size: 55%;
            border-radius: 50%;
            height: 3rem;
            width: 3rem;
        }
        .hero-carousel .carousel-indicators [data-bs-target] {
            background-color: #fff;
            border-radius: 999px;
            height: 0.45rem;
            width: 2.25rem;
        }
        @media (max-width: 768px) {
            .hero-carousel .carousel-item img {
                height: 360px;
            }
            .hero-carousel .carousel-caption {
                bottom: 1.25rem;
                left: 1rem;
                max-width: calc(100% - 2rem);
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php">Disaster System</a>
            <a class="btn btn-outline-light ms-auto" href="login.php">Login</a>
        </div>
    </nav>
    <div id="homepageCarousel" class="carousel slide carousel-fade hero-carousel" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#homepageCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#homepageCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#homepageCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
            <button type="button" data-bs-target="#homepageCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
        </div>
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img class="d-block w-100" src="images/4.webp" alt="Emergency response team">
                <div class="carousel-caption d-block">
                    <h2 class="fw-bold">Welcome to the Department of Disaster Management</h2>
                    <p class="mb-0">We cannot stop natural disasters, but with readiness, coordination, and fast reporting we can protect more lives.</p>
                </div>
            </div>
            <div class="carousel-item">
                <img class="d-block w-100" src="images/1.webp" alt="Disaster preparedness planning">
                <div class="carousel-caption d-block">
                    <h2 class="fw-bold">Preparedness Starts Before the Crisis</h2>
                    <p class="mb-0">Track incidents early, connect them to responders quickly, and keep communities informed in real time.</p>
                </div>
            </div>
            <div class="carousel-item">
                <img class="d-block w-100" src="images/2.jpg" alt="Flood response support">
                <div class="carousel-caption d-block">
                    <h2 class="fw-bold">Faster Response, Better Coordination</h2>
                    <p class="mb-0">Every accurate report helps the right authority reach the right place at the right time.</p>
                </div>
            </div>
            <div class="carousel-item">
                <img class="d-block w-100" src="images/3.jpg" alt="Community resilience">
                <div class="carousel-caption d-block">
                    <h2 class="fw-bold">Building Safer Communities Together</h2>
                    <p class="mb-0">Learn from the past, act in the present, and strengthen resilience for what comes next.</p>
                </div>
            </div>
        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#homepageCarousel" data-bs-slide="prev" aria-label="Previous slide">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#homepageCarousel" data-bs-slide="next" aria-label="Next slide">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>

    <div class="container">
        <div class="row filter-section">
            <div class="col-md-12">
                <label class="fw-bold me-2">Filters:</label>
                <select id="typeFilter" class="form-select d-inline w-auto">
                    <option value="">All Types</option>
                    <option value="fire" <?= $type_filter == 'fire' ? 'selected' : '' ?>>Fire</option>
                    <option value="flood" <?= $type_filter == 'flood' ? 'selected' : '' ?>>Flood</option>
                    <option value="landslide" <?= $type_filter == 'landslide' ? 'selected' : '' ?>>Landslide</option>
                </select>
                
                <select id="timeFilter" class="form-select d-inline w-auto ms-2">
                    <option value="all" <?= $time_filter == 'all' ? 'selected' : '' ?>>All Time</option>
                    <option value="today" <?= $time_filter == 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="week" <?= $time_filter == 'week' ? 'selected' : '' ?>>This Week</option>
                </select>
                <a href="index.php" class="btn btn-secondary btn-sm ms-2">Reset</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div id="map"></div>
            </div>
            <div class="col-md-4">
                <h4>Announcements</h4>
                <hr>
                <?php if (empty($announcements)): ?>
                    <p class="text-muted">No recent announcements.</p>
                <?php endif; ?>
                <?php foreach ($announcements as $ann): ?>
                    <div class="card mb-2 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-danger"><?= htmlspecialchars($ann['title']) ?></h6>
                            <p class="card-text small"><?= htmlspecialchars($ann['content']) ?></p>
                            <small class="text-muted"><?= date('M d, Y', strtotime($ann['created_at'])) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Filter logic
        document.getElementById('typeFilter').addEventListener('change', filterMap);
        document.getElementById('timeFilter').addEventListener('change', filterMap);

        function filterMap() {
            const type = document.getElementById('typeFilter').value;
            const time = document.getElementById('timeFilter').value;
            location.href = `?type=${type}&time=${time}`;
        }

        // Map Initialization
        var map = L.map('map').setView([1.2921, 36.8219], 10); 
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Render Markers
        <?php foreach ($disasters as $d): 
            $icon = $d['type'] == 'fire' ? '🔥' : ($d['type'] == 'flood' ? '🌊' : '⚠️');
        ?>
            L.marker([<?= $d['latitude'] ?>, <?= $d['longitude'] ?>])
                .addTo(map)
                .bindPopup(`
                    <strong><?= $icon ?> <?= strtoupper(htmlspecialchars($d['type'])) ?></strong><br>
                    <?= htmlspecialchars($d['description']) ?><br>
                    <small class="text-muted">Reported by: <?= htmlspecialchars($d['authority'] ?? 'Unknown') ?></small>
                `);
        <?php endforeach; ?>
    </script>
</body>
</html>
