<?php require 'config.php'; 
requireRole('authority');
$authority_id = $_GET['authority_id'];
$pdo = getDB();

$incidents = $pdo->prepare("SELECT d.*, u.full_name, u.phone FROM disasters d JOIN users u ON d.user_id = u.id WHERE authority_id = ?");
$incidents->execute([$authority_id]);
$incidents = $incidents->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="incidents_' . $authority_id . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Type', 'Reporter', 'Phone', 'Lat', 'Lng', 'Status', 'Created']);

foreach ($incidents as $row) {
    fputcsv($output, [$row['id'], $row['type'], $row['full_name'], $row['phone'], $row['latitude'], $row['longitude'], $row['status'], $row['created_at']]);
}
?>
