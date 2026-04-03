<?php
function sendSMS($phone, $message) {
    $username = "YOUR_USERNAME";  // Africa's Talking dashboard
    $apiKey = "YOUR_API_KEY";
    
    $data = array(
        'username' => $username,
        'to' => $phone,
        'message' => $message
    );
    
    $url = "https://api.africastalking.com/version1/messaging";
    $options = array(
        'http' => array(
            'header' => "Accept: application/json\r\n" .
                       "Authorization: Basic " . base64_encode($username . ':' . $apiKey) . "\r\n" .
                       "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        )
    );
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return json_decode($result, true);
}

// Usage in admin approval:
function triggerDisasterAlerts($pdo, $disaster_id) {
    $disaster = $pdo->prepare("SELECT * FROM disasters WHERE id = ?")->fetch();
    $message = "🚨 ALERT: {$disaster['type']} at {$disaster['latitude']}, {$disaster['longitude']}. {$disaster['description']}";
    
    // Find nearby citizens (within 5km radius - simplified)
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.phone FROM users u 
        JOIN disasters d ON u.id = d.user_id 
        WHERE d.status = 'approved' 
        AND ABS(d.latitude - ?) < 0.05 AND ABS(d.longitude - ?) < 0.05
    ");
    $stmt->execute([$disaster['latitude'], $disaster['longitude']]);
    $phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($phones as $phone) {
        $result = sendSMS($phone, $message);
        if ($result['SMSMessageData']['Recipients'][0]['status'] === 'Sent') {
            $log_stmt = $pdo->prepare("INSERT INTO message_logs (disaster_id, phone_number) VALUES (?, ?)");
            $log_stmt->execute([$disaster_id, $phone]);
        }
    }
}
?>
