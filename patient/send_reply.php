<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only patients can access
requireRole('Patient');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification_id = $_POST['notification_id'] ?? null;
    $receiver_id = $_POST['receiver_id'] ?? null;
    $message = trim($_POST['reply_message'] ?? '');
    $sender_id = $_SESSION['user_id'];

    if (!$notification_id || !$receiver_id || empty($message)) {
        setAlert('Missing required fields', 'error');
        header("Location: notifications.php");
        exit();
    }

    // Insert the reply as a new notification
    $stmt = $conn->prepare("INSERT INTO Notifications (User_ID, Sender_ID, Alert_Type, Message, Sent_At, Status) 
                           VALUES (?, ?, 'Reply', ?, NOW(), 'Sent')");
    $stmt->bind_param("iis", $receiver_id, $sender_id, $message);
    
    if ($stmt->execute()) {
        setAlert('Reply sent successfully', 'success');
    } else {
        setAlert('Failed to send reply', 'error');
    }

    $stmt->close();
}

header("Location: notifications.php");
exit();
?>