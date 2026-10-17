<?php
function checkActivityNotifications($user_id) {
    $conn = getDB();
    if (!$conn) return [];

    $now = new DateTime();
    $current_hour = (int)$now->format('H');
    
    // Only process notifications between 9 AM and 1 PM
    if ($current_hour < 9 || $current_hour >= 13) {
        return [];
    }

    // Find activities that are exactly 3 days away
    // We use a range to capture the whole day
    $target_date = (new DateTime())->modify('+3 days')->format('Y-m-d');
    
    $sql = "SELECT * FROM activities WHERE DATE(activity_date) = '$target_date' AND is_completed = 0";
    $res = $conn->query($sql);
    
    if (!$res) return [];

    while ($activity = $res->fetch_assoc()) {
        $activity_id = $activity['id'];
        $activity_name = $activity['name'];
        
        // Check how many notifications were sent for this activity today
        $today = $now->format('Y-m-d');
        $count_sql = "SELECT COUNT(*) as cnt FROM notifications 
                      WHERE activity_id = $activity_id 
                      AND DATE(sent_at) = '$today'";
        $count_res = $conn->query($count_sql)->fetch_assoc();
        $sent_count = (int)$count_res['cnt'];
        
        if ($sent_count < 4) {
            $message = "ئاگاداری: چالاکی '" . $activity_name . "' ٣ ڕۆژی ماوە بۆ ئەنجامدان.";
            $conn->query("INSERT INTO notifications (user_id, message, activity_id) VALUES ($user_id, '" . $conn->real_escape_string($message) . "', $activity_id)");
        }
    }
    
    return []; // This function just populates the table
}

function getUnreadNotifications($user_id) {
    $conn = getDB();
    if (!$conn) return [];
    
    $res = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY sent_at DESC");
    $notifs = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $notifs[] = $row;
        }
    }
    return $notifs;
}

function markNotificationsRead($user_id) {
    $conn = getDB();
    if (!$conn) return;
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
}
?>
