<?php
require_once 'config.php';
requireRole('admin');

$pdo = getDB();
$message = '';
$generatedAuthority = null;

function adminStatusBadge(string $status): string
{
    return match ($status) {
        'pending' => 'warning text-dark',
        'approved' => 'success',
        'in_progress' => 'info text-dark',
        'resolved' => 'secondary',
        default => 'danger',
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['create_authority'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $coverage = trim($_POST['coverage']);

        if ($name !== '') {
            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare("INSERT INTO authorities (name, contact_phone, coverage_area) VALUES (?, ?, ?)");
                $stmt->execute([$name, $phone, $coverage]);
                $authId = $pdo->lastInsertId();

                $password = substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . "@agency.ke";
                $idNo = "AUTH-" . str_pad($authId, 3, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("INSERT INTO users (full_name, id_number, phone, email, password_hash, role, authority_id) VALUES (?, ?, ?, ?, ?, 'authority', ?)");
                $stmt->execute([$name . " Admin", $idNo, $phone, $email, $hash, $authId]);

                $pdo->commit();
                $generatedAuthority = [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'login_path' => 'login.php',
                ];
                $message = "<div class='alert alert-success border-0 shadow-sm'>Authority created. Email: <b>{$email}</b> | Temp Password: <b>{$password}</b></div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-danger border-0 shadow-sm'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    if (isset($_POST['assign_authority'])) {
        $reportId = (int) ($_POST['report_id'] ?? 0);
        $authorityId = ($_POST['authority_id'] ?? '') !== '' ? (int) $_POST['authority_id'] : null;

        $stmt = $pdo->prepare("UPDATE disasters SET authority_id = ? WHERE id = ?");
        $stmt->execute([$authorityId, $reportId]);
        $message = "<div class='alert alert-info border-0 shadow-sm'>Authority assignment updated for report #{$reportId}.</div>";
    }

    if (isset($_POST['update_report_status'])) {
        $reportId = (int) ($_POST['report_id'] ?? 0);
        $newStatus = $_POST['update_report_status'];

        $checkStmt = $pdo->prepare("SELECT authority_id FROM disasters WHERE id = ?");
        $checkStmt->execute([$reportId]);
        $reportMeta = $checkStmt->fetch();

        if (!$reportMeta) {
            $message = "<div class='alert alert-danger border-0 shadow-sm'>Report not found.</div>";
        } elseif ($newStatus === 'approved' && empty($reportMeta['authority_id'])) {
            $message = "<div class='alert alert-warning border-0 shadow-sm'>Assign an authority before approving report #{$reportId}.</div>";
        } else {
            $stmt = $pdo->prepare("UPDATE disasters SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $reportId]);
            $message = "<div class='alert alert-info border-0 shadow-sm'>Report #{$reportId} marked as {$newStatus}.</div>";
        }
    }

    if (isset($_POST['save_announcement'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$title, $content, $_SESSION['user_id']]);
        $message = "<div class='alert alert-success border-0 shadow-sm'>Announcement posted.</div>";
    }

    if (isset($_POST['delete_announcement'])) {
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$_POST['ann_id']]);
        $message = "<div class='alert alert-warning border-0 shadow-sm'>Announcement deleted.</div>";
    }
}

$authorities = $pdo->query("SELECT a.*, u.email FROM authorities a LEFT JOIN users u ON a.id = u.authority_id WHERE a.status = 'active' ORDER BY a.name")->fetchAll();
$reports = $pdo->query("
    SELECT d.*, u.full_name AS reporter, u.phone AS reporter_phone, a.name AS authority_name
    FROM disasters d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN authorities a ON d.authority_id = a.id
    ORDER BY
        CASE WHEN d.status = 'pending' THEN 0 ELSE 1 END,
        d.created_at DESC
")->fetchAll();
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
$users = $pdo->query("SELECT * FROM users WHERE role != 'admin' ORDER BY created_at DESC")->fetchAll();
$authorityLogins = $pdo->query("
    SELECT
        a.id,
        a.name,
        a.contact_phone,
        a.coverage_area,
        u.email,
        u.full_name
    FROM authorities a
    LEFT JOIN users u ON a.id = u.authority_id AND u.role = 'authority'
    WHERE a.status = 'active'
    ORDER BY a.created_at DESC
")->fetchAll();

$reportStats = [
    'total' => count($reports),
    'pending' => 0,
    'unassigned' => 0,
    'approved' => 0,
    'resolved' => 0,
];

foreach ($reports as $report) {
    if ($report['status'] === 'pending') {
        $reportStats['pending']++;
    }
    if (empty($report['authority_id'])) {
        $reportStats['unassigned']++;
    }
    if ($report['status'] === 'approved') {
        $reportStats['approved']++;
    }
    if ($report['status'] === 'resolved') {
        $reportStats['resolved']++;
    }
}
?>

<style>
    .admin-hero {
        background:
            radial-gradient(circle at top right, rgba(255, 179, 71, 0.35), transparent 30%),
            linear-gradient(135deg, #102a43 0%, #243b53 45%, #9f1239 100%);
        border-radius: 24px;
        color: #fff;
        overflow: hidden;
        position: relative;
    }
    .admin-hero::after {
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
        content: "";
        height: 100%;
        left: -30%;
        position: absolute;
        top: 0;
        transform: skewX(-20deg);
        width: 40%;
    }
    .metric-card {
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 20px;
    }
    .section-card {
        border: 0;
        border-radius: 22px;
        overflow: hidden;
    }
    .section-header {
        background: linear-gradient(135deg, #fff7ed 0%, #ffffff 100%);
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    }
    .report-row {
        vertical-align: top;
    }
    .report-row td {
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
    .mini-chip {
        background: #f1f5f9;
        border-radius: 999px;
        color: #334155;
        display: inline-block;
        font-size: 0.75rem;
        padding: 0.3rem 0.65rem;
    }
    .credential-panel {
        background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        border: 1px solid rgba(37, 99, 235, 0.12);
        border-radius: 18px;
    }
    .credential-key {
        color: #475569;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .login-generator-banner {
        background: linear-gradient(135deg, #fff7ed 0%, #ffffff 55%, #eff6ff 100%);
        border: 1px solid rgba(234, 88, 12, 0.12);
        border-radius: 22px;
    }
</style>

<?= $message ?>

<div class="row g-4">
    <div class="col-12">
        <div class="admin-hero shadow-lg p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="badge text-bg-light text-danger-emphasis mb-3">Admin Dispatch Console</span>
                    <h2 class="fw-bold mb-2">Coordinate, assign, and approve incoming disaster reports.</h2>
                    <p class="mb-0 text-white-50">Authorities must be assigned before any disaster moves into approved response handling.</p>
                </div>
                <div class="col-lg-4">
                    <div class="bg-white bg-opacity-10 rounded-4 p-3">
                        <div class="small text-uppercase text-white-50 mb-2">Current Focus</div>
                        <div class="fs-5 fw-semibold"><?= $reportStats['unassigned'] ?> unassigned reports</div>
                        <div class="small text-white-50 mt-2">Review pending incidents and route each one to the right authority first.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="login-generator-banner shadow-sm p-4">
            <div class="row align-items-center g-4">
                <div class="col-lg-5">
                    <span class="mini-chip mb-2">Quick Access</span>
                    <h4 class="mb-2">Generate Authority Accounts</h4>
                    <p class="text-muted mb-0">Use this space to create authority logins that will work immediately on the main login page.</p>
                </div>
                <div class="col-lg-7">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="col-md-4">
                            <input type="text" name="name" class="form-control" placeholder="Agency Name" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="phone" class="form-control" placeholder="Phone">
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="coverage" class="form-control" placeholder="Coverage Area">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button name="create_authority" class="btn btn-danger">Generate</button>
                        </div>
                    </form>

                    <?php if ($generatedAuthority): ?>
                        <div class="credential-panel p-3 mt-3">
                            <div class="fw-semibold mb-2">Latest Generated Credentials</div>
                            <div class="small">Email: <code><?= htmlspecialchars($generatedAuthority['email']) ?></code></div>
                            <div class="small">Temporary Password: <code><?= htmlspecialchars($generatedAuthority['password']) ?></code></div>
                            <div class="small mt-1">Sign in here: <a href="<?= htmlspecialchars($generatedAuthority['login_path']) ?>">login.php</a></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="metric-card shadow-sm h-100 p-4">
            <div class="text-muted small mb-1">Total Reports</div>
            <div class="display-6 fw-bold"><?= $reportStats['total'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="metric-card shadow-sm h-100 p-4">
            <div class="text-muted small mb-1">Pending Review</div>
            <div class="display-6 fw-bold text-warning"><?= $reportStats['pending'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="metric-card shadow-sm h-100 p-4">
            <div class="text-muted small mb-1">Unassigned</div>
            <div class="display-6 fw-bold text-danger"><?= $reportStats['unassigned'] ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="metric-card shadow-sm h-100 p-4">
            <div class="text-muted small mb-1">Resolved</div>
            <div class="display-6 fw-bold text-secondary"><?= $reportStats['resolved'] ?></div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="section-card card shadow-sm">
            <div class="section-header card-header py-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Incident Routing Board</h5>
                    <small class="text-muted">Assign first, approve second. The server also enforces this rule.</small>
                </div>
                <span class="mini-chip"><?= count($reports) ?> reports</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($reports)): ?>
                    <div class="p-4 text-center text-muted">No disaster reports available yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Incident</th>
                                    <th>Reporter</th>
                                    <th>Authority</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $r): ?>
                                    <?php $canApprove = !empty($r['authority_id']); ?>
                                    <tr class="report-row">
                                        <td style="min-width: 280px;">
                                            <div class="fw-semibold"><?= htmlspecialchars(ucfirst($r['type'])) ?> #<?= (int) $r['id'] ?></div>
                                            <div class="small text-muted mb-2"><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></div>
                                            <div class="small"><?= nl2br(htmlspecialchars($r['description'] ?: 'No description provided.')) ?></div>
                                            <div class="small text-muted mt-2">
                                                Lat: <?= htmlspecialchars((string) $r['latitude']) ?> |
                                                Lng: <?= htmlspecialchars((string) $r['longitude']) ?>
                                            </div>
                                        </td>
                                        <td style="min-width: 180px;">
                                            <div class="fw-semibold"><?= htmlspecialchars($r['reporter']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($r['reporter_phone']) ?></div>
                                        </td>
                                        <td style="min-width: 260px;">
                                            <form method="POST" class="d-flex gap-2">
                                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                                <select name="authority_id" class="form-select form-select-sm">
                                                    <option value="">Choose authority</option>
                                                    <?php foreach ($authorities as $authority): ?>
                                                        <option value="<?= $authority['id'] ?>" <?= (int) $r['authority_id'] === (int) $authority['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($authority['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button name="assign_authority" class="btn btn-sm btn-outline-primary">Save</button>
                                            </form>
                                            <div class="small mt-2 <?= $canApprove ? 'text-success' : 'text-danger' ?>">
                                                <?= $canApprove ? 'Ready for approval' : 'Assignment required before approval' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= adminStatusBadge($r['status']) ?>">
                                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $r['status']))) ?>
                                            </span>
                                        </td>
                                        <td style="min-width: 170px;">
                                            <?php if ($r['status'] === 'pending'): ?>
                                                <form method="POST" class="d-flex gap-2">
                                                    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                                    <button
                                                        name="update_report_status"
                                                        value="approved"
                                                        class="btn btn-sm btn-success"
                                                        <?= $canApprove ? '' : 'disabled' ?>
                                                        title="<?= $canApprove ? 'Approve report' : 'Assign an authority first' ?>"
                                                    >
                                                        Approve
                                                    </button>
                                                    <button name="update_report_status" value="rejected" class="btn btn-sm btn-outline-danger">Reject</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="small text-muted">Status already processed</span>
                                            <?php endif; ?>
                                        </td>
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
        <div class="section-card card shadow-sm mb-4">
            <div class="section-header card-header py-3 px-4">
                <h5 class="mb-1">Authority Login Generator</h5>
                <small class="text-muted">This secondary panel does the same thing if you prefer working from the sidebar.</small>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="text" name="name" class="form-control mb-3" placeholder="Agency Name" required>
                    <input type="text" name="phone" class="form-control mb-3" placeholder="Phone">
                    <textarea name="coverage" class="form-control mb-3" rows="3" placeholder="Coverage Area"></textarea>
                    <button name="create_authority" class="btn btn-danger w-100">Generate Authority Login</button>
                </form>

                <?php if ($generatedAuthority): ?>
                    <div class="credential-panel p-3 mt-4">
                        <div class="fw-semibold mb-3">Generated Credentials</div>
                        <div class="credential-key">Authority</div>
                        <div class="mb-2"><?= htmlspecialchars($generatedAuthority['name']) ?></div>
                        <div class="credential-key">Email</div>
                        <div class="mb-2"><code><?= htmlspecialchars($generatedAuthority['email']) ?></code></div>
                        <div class="credential-key">Temporary Password</div>
                        <div class="mb-2"><code><?= htmlspecialchars($generatedAuthority['password']) ?></code></div>
                        <div class="credential-key">Login Page</div>
                        <div><a href="<?= htmlspecialchars($generatedAuthority['login_path']) ?>">Open login page</a></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-card card shadow-sm mb-4">
            <div class="section-header card-header py-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Authority Login Accounts</h5>
                    <small class="text-muted">Existing authority accounts that can sign in through the main login page.</small>
                </div>
                <span class="mini-chip"><?= count($authorityLogins) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($authorityLogins)): ?>
                    <div class="p-4 text-muted">No authority login accounts have been created yet.</div>
                <?php else: ?>
                    <?php foreach ($authorityLogins as $authorityLogin): ?>
                        <div class="p-3 border-bottom">
                            <div class="fw-semibold"><?= htmlspecialchars($authorityLogin['name']) ?></div>
                            <div class="small text-muted mb-2"><?= htmlspecialchars($authorityLogin['coverage_area'] ?: 'Coverage not specified') ?></div>
                            <div class="small"><span class="credential-key">Login email</span></div>
                            <div class="small mb-2"><code><?= htmlspecialchars($authorityLogin['email'] ?: 'No login linked yet') ?></code></div>
                            <div class="small text-muted">Use these details on <a href="login.php">the login page</a>.</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-card card shadow-sm mb-4">
            <div class="section-header card-header py-3 px-4">
                <h5 class="mb-1">Announcements</h5>
                <small class="text-muted">Share updates with the wider system.</small>
            </div>
            <div class="card-body p-4">
                <form method="POST" class="mb-4">
                    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="text" name="title" class="form-control mb-2" placeholder="Title" required>
                    <textarea name="content" class="form-control mb-2" rows="3" placeholder="Write announcement here..." required></textarea>
                    <button name="save_announcement" class="btn btn-primary w-100">Post Announcement</button>
                </form>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Active Announcements</h6>
                    <span class="mini-chip"><?= count($announcements) ?></span>
                </div>

                <?php if (empty($announcements)): ?>
                    <p class="text-muted mb-0">No announcements yet.</p>
                <?php endif; ?>

                <?php foreach ($announcements as $ann): ?>
                    <div class="border rounded-4 p-3 mb-2">
                        <div class="fw-semibold"><?= htmlspecialchars($ann['title']) ?></div>
                        <div class="small text-muted mb-2"><?= date('M j, Y', strtotime($ann['created_at'])) ?></div>
                        <div class="small"><?= htmlspecialchars($ann['content']) ?></div>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
                            <button name="delete_announcement" class="btn btn-sm btn-link text-danger text-decoration-none p-0">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section-card card shadow-sm">
            <div class="section-header card-header py-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">System Users</h5>
                    <small class="text-muted">Recent non-admin accounts.</small>
                </div>
                <span class="mini-chip"><?= count($users) ?></span>
            </div>
            <div class="card-body p-0">
                <?php foreach ($users as $u): ?>
                    <div class="d-flex justify-content-between gap-3 p-3 border-bottom">
                        <div>
                            <div class="fw-semibold"><?= htmlspecialchars($u['full_name']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($u['role']) ?></div>
                        </div>
                        <div class="small text-muted text-end"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
