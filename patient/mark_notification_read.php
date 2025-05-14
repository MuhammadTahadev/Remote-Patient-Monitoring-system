<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only patients can access
requireRole('Patient');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    try {
        // Handle single notification mark as read
        if (isset($_POST['notification_id'])) {
            $notification_id = (int)$_POST['notification_id'];
            
            $stmt = $conn->prepare("UPDATE Notifications SET Status = 'Read' 
                                   WHERE Notification_ID = ? AND User_ID = ? AND Status = 'Sent'");
            $stmt->bind_param("ii", $notification_id, $user_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    setAlert('Notification marked as read', 'success');
                } else {
                    setAlert('Notification not found or already read', 'warning');
                }
            } else {
                throw new Exception("Database error: " . $stmt->error);
            }
            $stmt->close();
        } 
        // Handle mark all as read
        elseif (isset($_POST['mark_all'])) {
            $stmt = $conn->prepare("UPDATE Notifications SET Status = 'Read' 
                                   WHERE User_ID = ? AND Status = 'Sent'");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $count = $stmt->affected_rows;
                setAlert($count > 0 ? "Marked $count notifications as read" : "No unread notifications found", 
                        $count > 0 ? 'success' : 'info');
            } else {
                throw new Exception("Database error: " . $stmt->error);
            }
            $stmt->close();
        } 
        else {
            setAlert('Invalid request parameters', 'error');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        setAlert('An error occurred while processing your request', 'error');
    }
} else {
    setAlert('Invalid request method', 'error');
}

// Redirect back to the previous page or notifications page
$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'notifications.php';
header("Location: $redirect_url");
exit();
?>