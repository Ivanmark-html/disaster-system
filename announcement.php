<?php require 'config.php'; requireRole('admin');
$pdo = getDB();

if ($_POST) {
    $stmt = $pdo->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['title'], $_POST['content'], $_SESSION['user_id']]);
}

$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
?>
<!-- Bootstrap form + editable table with delete buttons -->
