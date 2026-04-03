<?php
require_once 'config.php';
requireRole('authority');

$pdo = getDB();
$userId = $_SESSION['user_id'];
$authorityId = $_SESSION['authority_id'] ?? null;

if (!$authorityId) {
    $userStmt = $pdo->prepare("
        SELECT u.authority_id, a.name AS authority_name, a.coverage_area, a.contact_phone
        FROM users u
        LEFT JOIN authorities a ON a.id = u.authority_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $userStmt->execute([$userId]);
    $authorityProfile = $userStmt->fetch();

    if ($authorityProfile) {
        $authorityId = $authorityProfile['authority_id'] ?? null;
        $_SESSION['authority_id'] = $authorityId;
    }
} else {
    $authorityStmt = $pdo->prepare("
        SELECT id, name AS authority_name, coverage_area, contact_phone
        FROM authorities
        WHERE id = ?
        LIMIT 1
    ");
    $authorityStmt->execute([$authorityId]);
    $authorityProfile = $authorityStmt->fetch();
}

if (empty($authorityProfile) || empty($authorityId)) {
    ?>
    <div class="alert alert-warning border-0 shadow-sm">
        No authority profile is linked to this account yet. Please contact the administrator.
    </div>
    <?php
    return;
}

$reportsStmt = $pdo->prepare("
    SELECT
        d.*,
        u.full_name AS reporter_name,
        u.phone AS reporter_phone,
        a.name AS authority_name
    FROM disasters d
    JOIN users u ON u.id = d.user_id
    LEFT JOIN authorities a ON a.id = d.authority_id
    WHERE d.authority_id = ?
    ORDER BY
        CASE
            WHEN d.status = 'pending' THEN 0
            WHEN d.status = 'approved' THEN 1
            WHEN d.status = 'in_progress' THEN 2
            ELSE 3
        END,
        d.created_at DESC
");
$reportsStmt->execute([$authorityId]);
$reports = $reportsStmt->fetchAll();

$statusCounts = [
    'total' => count($reports),
    'pending' => 0,
    'approved' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'rejected' => 0,
];

foreach ($reports as $report) {
    $status = $report['status'];
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}

$priorityReports = array_values(array_filter($reports, static fn(array $report): bool => in_array($report['status'], ['pending', 'approved', 'in_progress'], true)));
$recentResolved = array_values(array_filter($reports, static fn(array $report): bool => $report['status'] === 'resolved'));

function authorityStatusClass(string $status): string
{
    return match ($status) {
        'pending' => 'warning text-dark',
        'approved' => 'success',
        'in_progress' => 'info text-dark',
        'resolved' => 'secondary',
        default => 'danger',
    };
}
?>

<style>
    .authority-hero {
        background:
            radial-gradient(circle at top left, rgba(34, 197, 94, 0.22), transparent 28%),
            linear-gradient(135deg, #0f172a 0%, #134e4a 45%, #14532d 100%);
        border-radius: 24px;
        color: #fff;
        overflow: hidden;
        position: relative;
    }
    .authority-hero::after {
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.09), transparent);
        content: "";
        height: 100%;
        left: -28%;
        position: absolute;
        top: 0;
        transform: skewX(-20deg);
        width: 38%;
    }
    .authority-metric {
        background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%);
        border: 1px solid rgba(20, 83, 45, 0.08);
        border-radius: 20px;
    }
    .authority-section {
        border: 0;
        border-radius: 22px;
        overflow: hidden;
    }
    .authority-section-header {
        background: linear-gradient(135deg, #ecfdf5 0%, #ffffff 100%);
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    }
    .authority-chip {
        background: #ecfeff;
        border-radius: 999px;
        color: #0f766e;
        display: inline-block;
        font-size: 0.75rem;
        padding: 0.3rem 0.7rem;
    }
    .incident-card {
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 18px;
    }
    .incident-card + .incident-card {
        margin-top: 1rem;
    }
    .report-table td {
        vertical-align: top;
    }
</style>

<div class="row g-4">
    <div class="col-12">
        <div class="authority-hero shadow-lg p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="badge text-bg-light text-success-emphasis mb-3">Authority Response Hub</span>
                    <h2 class="fw-bold mb-2"><?= htmlspecialchars($authorityProfile['authority_name']) ?></h2>
                    <p class="mb-0 text-white-50">
                        Monitor assigned disasters, track field response, and keep high-priority incidents visible.
                    </p>
                </div>
                <div class="col-lg-4">
                    <div class="bg-white bg-opacity-10 rounded-4 p-3">
                        <div class="small text-uppercase text-white-50 mb-2">Coverage Area</div>
                        <div class="fs-5 fw-semibold"><?= htmlspecialchars($authorityProfile['coverage_area'] ?: 'Not specified') ?></div>
                        <div class="small text-white-50 mt-2">
                            Contact: <?= htmlspecialchars($authorityProfile['contact_phone'] ?: 'Not provided') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="authority-metric shadow-sm h-100 p-4">
            <div class="text-muted small mb-1">Assigned Reports</div>
            <div class="display-6 fw-bold"><?= $statusCounts['total'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="authority-metric shadow-sm h-100 p-4">
            <div class="text-muted small mb-1">Pending Intake</div>
            <div class="display-6 fw-bold text-warning"><?= $statusCounts['pending'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="authority-metric shadow-sm h-100 p-4">
            <div class="text-muted small mb-1">Active Response</div>
            <div class="display-6 fw-bold text-info"><?= $statusCounts['in_progress'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="authority-metric shadow-sm h-100 p-4">
            <div class="text-muted small mb-1">Resolved</div>
            <div class="display-6 fw-bold text-secondary"><?= $statusCounts['resolved'] ?></div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="authority-section card shadow-sm h-100">
            <div class="authority-section-header card-header py-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Priority Queue</h5>
                    <small class="text-muted">Incidents needing the most attention first.</small>
                </div>
                <span class="authority-chip"><?= count($priorityReports) ?></span>
            </div>
            <div class="card-body p-4">
                <?php if (empty($priorityReports)): ?>
                    <p class="text-muted mb-0">No active incidents in the queue right now.</p>
                <?php else: ?>
                    <?php foreach (array_slice($priorityReports, 0, 4) as $report): ?>
                        <div class="incident-card p-3">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars(ucfirst($report['type'])) ?> #<?= (int) $report['id'] ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($report['reporter_name']) ?></div>
                                </div>
                                <span class="badge bg-<?= authorityStatusClass($report['status']) ?>">
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $report['status']))) ?>
                                </span>
                            </div>
                            <div class="small mt-2"><?= nl2br(htmlspecialchars($report['description'] ?: 'No description provided.')) ?></div>
                            <div class="small text-muted mt-2">
                                <?= date('M j, Y g:i A', strtotime($report['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="authority-section card shadow-sm h-100">
            <div class="authority-section-header card-header py-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Assigned Disaster Reports</h5>
                    <small class="text-muted">Showing only disasters with `authority_id = <?= (int) $authorityId ?>`</small>
                </div>
                <span class="authority-chip"><?= count($reports) ?> visible</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($reports)): ?>
                    <div class="p-4 text-center text-muted">
                        No disaster reports have been assigned to this authority yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 report-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Incident</th>
                                    <th>Reporter</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Reported</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td style="min-width: 320px;">
                                            <div class="fw-semibold"><?= htmlspecialchars(ucfirst($report['type'])) ?> #<?= (int) $report['id'] ?></div>
                                            <div class="small mt-2"><?= nl2br(htmlspecialchars($report['description'] ?: 'No description provided.')) ?></div>
                                            <?php if (!empty($report['image_path'])): ?>
                                                <div class="mt-2">
                                                    <a href="<?= htmlspecialchars($report['image_path']) ?>" target="_blank" class="small text-decoration-none">View attachment</a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="min-width: 170px;">
                                            <div class="fw-semibold"><?= htmlspecialchars($report['reporter_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($report['reporter_phone']) ?></div>
                                        </td>
                                        <td class="small" style="min-width: 160px;">
                                            <div>Lat: <?= htmlspecialchars((string) $report['latitude']) ?></div>
                                            <div>Lng: <?= htmlspecialchars((string) $report['longitude']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= authorityStatusClass($report['status']) ?>">
                                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $report['status']))) ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted"><?= date('M j, Y g:i A', strtotime($report['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="authority-section card shadow-sm">
            <div class="authority-section-header card-header py-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Recently Resolved</h5>
                    <small class="text-muted">Closed incidents for quick review.</small>
                </div>
                <span class="authority-chip"><?= count($recentResolved) ?></span>
            </div>
            <div class="card-body p-4">
                <?php if (empty($recentResolved)): ?>
                    <p class="text-muted mb-0">No resolved incidents yet.</p>
                <?php else: ?>
                    <?php foreach (array_slice($recentResolved, 0, 5) as $report): ?>
                        <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars(ucfirst($report['type'])) ?> #<?= (int) $report['id'] ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($report['reporter_name']) ?></div>
                            </div>
                            <div class="small text-muted text-end"><?= date('M j', strtotime($report['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
