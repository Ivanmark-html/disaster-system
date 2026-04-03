<?php $pdo = getDB(); ?>

<div class="card mt-4">
    <div class="card-header"><h6>SMS Audit Trail</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Disaster</th><th>Phone</th><th>Sent</th></tr></thead>
                <tbody>
                    <?php
                    $logs = $pdo->query("
                        SELECT ml.*, d.type FROM message_logs ml 
                        JOIN disasters d ON ml.disaster_id = d.id 
                        ORDER BY ml.sent_at DESC LIMIT 50
                    ")->fetchAll();
                    foreach ($logs as $log): ?>
                        <tr>
                            <td><?= $log['type'] ?> #<?= $log['disaster_id'] ?></td>
                            <td><?= htmlspecialchars($log['phone_number']) ?></td>
                            <td><?= date('H:i', strtotime($log['sent_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
