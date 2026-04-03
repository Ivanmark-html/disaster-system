<?php
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Handle Form Submission
if (isset($_POST['action']) && $_POST['action'] === 'submit_report') {
    csrf_verify();
    $target_dir = UPLOAD_DIR;
    $image_path = null;

    if (!empty($_FILES['image']['name'])) {
        $imageName = time() . '_' . basename($_FILES['image']['name']);
        $image_path = $target_dir . $imageName;
        move_uploaded_file($_FILES['image']['tmp_name'], $image_path);
    }

    $stmt = $pdo->prepare("INSERT INTO disasters (user_id, authority_id, type, latitude, longitude, description, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, null, $_POST['type'], $_POST['latitude'], $_POST['longitude'], $_POST['description'], $image_path]);
    $success = "Report submitted! ID: " . $pdo->lastInsertId();
}

// Fetch Data for Display
$my_reports = $pdo->prepare("SELECT d.*, a.name as authority_name FROM disasters d LEFT JOIN authorities a ON d.authority_id = a.id WHERE d.user_id = ? ORDER BY d.created_at DESC LIMIT 10");
$my_reports->execute([$user_id]);
$my_reports = $my_reports->fetchAll();
?>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= $success ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Report New Disaster</h5>
            </div>
            <div class="card-body">
                <form id="disasterForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="submit_report">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Disaster Type *</label>
                            <select id="disasterType" name="type" class="form-select" required>
                                <option value="fire">Fire</option>
                                <option value="flood">Flood</option>
                                <option value="landslide">Landslide</option>
                                <option value="earthquake">Earthquake</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label fw-bold">Description *</label>
                        <textarea name="description" class="form-control" rows="3" required placeholder="Describe the situation..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Photo (optional)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>

                    <div class="mb-3 border rounded p-3" style="background: #f8f9fa;">
                        <label class="fw-bold mb-2">Click map to set location</label>
                        <div id="reportMap" style="height: 300px; border: 2px dashed #dc3545; border-radius: 8px;"></div>
                        <div class="small text-muted mt-2 d-flex justify-content-between">
                            <span>Lat: <b id="latDisplay">-</b>, Lng: <b id="lngDisplay">-</b></span>
                            <span id="geoStatus" class="text-primary fw-bold"></span>
                        </div>
                    </div>

                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">

                    <button type="submit" id="submitBtn" class="btn btn-danger w-100 py-3 fw-bold shadow-sm">
                        Submit Emergency Report
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow border-0">
            <div class="card-header bg-white">
                <h6 class="mb-0">My Recent Reports</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($my_reports)): ?>
                    <p class="text-center p-4 text-muted">No reports found.</p>
                <?php endif; ?>
                <?php foreach ($my_reports as $report): ?>
                    <div class="p-3 border-bottom hover-bg-light">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-<?= match($report['status']) {
                                    'pending' => 'warning',
                                    'approved' => 'success',
                                    'in_progress' => 'info',
                                    'resolved' => 'secondary',
                                    default => 'danger'
                                } ?> mb-2"><?= ucfirst($report['status']) ?></span>
                                <h6 class="mb-1"><?= htmlspecialchars($report['type']) ?></h6>
                                <small class="text-muted d-block">
                                    Authority: <?= htmlspecialchars($report['authority_name'] ?: 'Awaiting admin assignment') ?>
                                </small>
                                <small class="text-muted d-block"><?= date('M j, H:i', strtotime($report['created_at'])) ?></small>
                            </div>
                            <?php if ($report['image_path']): ?>
                                <img src="<?= htmlspecialchars($report['image_path']) ?>" class="rounded" style="width:50px; height:50px; object-fit:cover;">
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let reportMap = L.map('reportMap').setView([-1.2921, 36.8219], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(reportMap);

let reportMarker;
reportMap.on('click', function(e) {
    if (reportMarker) reportMap.removeLayer(reportMarker);
    reportMarker = L.marker(e.latlng).addTo(reportMap);

    document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
    document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
    document.getElementById('latDisplay').textContent = e.latlng.lat.toFixed(6);
    document.getElementById('lngDisplay').textContent = e.latlng.lng.toFixed(6);
});

async function isNearWater(lat, lng) {
    const radius = 500;
    const query = `[out:json];(
        node["natural"="water"](around:${radius},${lat},${lng});
        way["natural"="water"](around:${radius},${lat},${lng});
        way["waterway"](around:${radius},${lat},${lng});
        relation["waterway"](around:${radius},${lat},${lng});
    );out count;`;

    try {
        const response = await fetch(`https://overpass-api.de/api/interpreter?data=${encodeURIComponent(query)}`);
        const data = await response.json();
        return parseInt(data.elements[0].tags.total) > 0;
    } catch (error) {
        console.error("Geo-validation failed", error);
        return true;
    }
}

document.getElementById('disasterForm').addEventListener('submit', async function(e) {
    const type = document.getElementById('disasterType').value;
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    const btn = document.getElementById('submitBtn');
    const statusText = document.getElementById('geoStatus');

    if (type === 'flood') {
        if (!lat || !lng) {
            alert("Please select a location on the map first.");
            e.preventDefault();
            return;
        }

        e.preventDefault();
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Verifying environment...`;
        statusText.innerHTML = "Checking for water sources...";

        const valid = await isNearWater(lat, lng);

        if (!valid) {
            alert("Report blocked: no nearby river, lake, or waterway was detected. Please verify the location.");
            btn.disabled = false;
            btn.innerHTML = "Submit Emergency Report";
            statusText.innerHTML = "Validation failed";
            statusText.className = "text-danger fw-bold";
        } else {
            statusText.innerHTML = "Environment verified";
            statusText.className = "text-success fw-bold";
            this.submit();
        }
    }
});
</script>
