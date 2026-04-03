<?php
require 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Client-side validation already handled, but server-side too
    $errors = [];

    if (empty($full_name) || strlen($full_name) < 2) {
        $errors[] = 'Full name must be at least 2 characters';
    }
    if (empty($id_number) || strlen($id_number) < 5) {
        $errors[] = 'Valid ID number required (min 5 characters)';
    }
    if (empty($phone) || !preg_match('/^07\d{8}$/', $phone)) {
        $errors[] = 'Valid Kenyan phone number required (07XXXXXXXX)';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address required';
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }

    // Check if email or ID already exists
    if (empty($errors)) {
        $pdo = getDB();
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR id_number = ?");
        $check->execute([$email, $id_number]);
        if ($check->fetch()) {
            $errors[] = 'Email or ID number already registered';
        }
    }

    if (empty($errors)) {
        try {
            $pdo = getDB();
            $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, id_number, phone, email, password_hash, role) VALUES (?, ?, ?, ?, ?, 'citizen')");
            $stmt->execute([$full_name, $id_number, $phone, $email, $password_hash]);
            $success = 'Registration successful! Please login.';
        } catch (PDOException $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Disaster Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="d-flex flex-column align-items-center mb-4">
                            <i class="fas fa-exclamation-triangle text-danger fs-1 mb-3"></i>
                            <h3 class="mb-0">Citizen Registration</h3>
                            <p class="text-muted">Create account to report disasters</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= $success ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                           pattern=".{2,}" title="At least 2 characters" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ID Number <span class="text-danger">*</span></label>
                                    <input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>"
                                           pattern=".{5,}" title="At least 5 characters" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                           pattern="07[0-9]{8}" title="07XXXXXXXX format" placeholder="0712345678" required>
                                    <div class="form-text">Kenyan mobile number (07XXXXXXXX)</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" minlength="6" required>
                                <div class="form-text">Minimum 6 characters</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-danger btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Register
                                </button>
                            </div>
                        </form>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="login.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Have account? Login
                            </a>
                            <a href="index.php" class="text-decoration-none">
                                <i class="fas fa-globe me-1"></i>View Public Map
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time password match validation
        document.querySelector('form').addEventListener('input', function() {
            const pass = document.querySelector('input[name="password"]').value;
            const confirm = document.querySelector('input[name="confirm_password"]').value;
            const submitBtn = document.querySelector('button[type="submit"]');
           
            if (pass && confirm && pass !== confirm) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Passwords do not match';
            } else {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-user-plus me-2"></i>Register';
            }
        });
    </script>
</body>
</html>

