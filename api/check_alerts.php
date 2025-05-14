<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify authentication

if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Get unread notifications for the current user
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("SELECT * FROM Notifications 
                           WHERE User_ID = ? AND Status = 'Sent'
                           ORDER BY Sent_At DESC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Mark notifications as read
    if (!empty($notifications)) {
        $stmt = $conn->prepare("UPDATE Notifications SET Status = 'Read' 
                               WHERE User_ID = ? AND Status = 'Sent'");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'alerts' => $notifications
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?>